<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_migration\Service;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\State\StateInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;

/**
 * Service for content import/export and migration operations.
 */
class MigrationService {

  /**
   * Maximum number of items allowed per import.
   */
  protected const MAX_IMPORT_ITEMS = 100;

  /**
   * Maximum number of items for export.
   */
  protected const MAX_EXPORT_ITEMS = 100;

  /**
   * State key for tracking import status.
   */
  protected const IMPORT_STATUS_KEY = 'mcp_tools_migration.last_import';

  /**
   * Fields that can NEVER be set via import.
   *
   * SECURITY: These fields could be used for privilege escalation or
   * data integrity attacks if allowed to be set via import.
   */
  protected const PROTECTED_FIELDS = [
    // User/ownership fields.
    'uid',
    'revision_uid',
    // System fields.
    'nid',
    'vid',
    'uuid',
    'type',
    'langcode',
    // Timestamp fields (should be auto-managed).
    'created',
    'changed',
    'revision_timestamp',
    // Access control fields.
    'status',  // Publish status should be explicit, not imported blindly.
    // Moderation fields.
    'moderation_state',
    'content_translation_source',
    'content_translation_outdated',
    // Path/routing (prevent redirect attacks).
    'path',
  ];

  /**
   * Field patterns that are blocked from import (regex).
   */
  protected const PROTECTED_FIELD_PATTERNS = [
    '/^revision_/',  // All revision fields
    '/^content_translation_/',  // All translation fields
  ];

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityFieldManagerInterface $entityFieldManager,
    protected AccountProxyInterface $currentUser,
    protected StateInterface $state,
    protected AccessManager $accessManager,
    protected AuditLogger $auditLogger,
  ) {}

  /**
   * Import content from CSV string.
   *
   * @param string $contentType
   *   The content type machine name.
   * @param string $csvData
   *   CSV data as a string (first row should be headers).
   * @param array $fieldMapping
   *   Mapping of CSV columns to Drupal fields (e.g., ['csv_column' => 'field_name']).
   *
   * @return array
   *   Result array with success status and import details.
   */
  public function importFromCsv(string $contentType, string $csvData, array $fieldMapping): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    // Parse CSV data.
    $lines = str_getcsv($csvData, "\n", '"', '\\');
    if (count($lines) < 2) {
      return ['success' => FALSE, 'error' => 'CSV must contain a header row and at least one data row.'];
    }

    $headers = str_getcsv(array_shift($lines), ',', '"', '\\');
    $items = [];

    foreach ($lines as $line) {
      if (empty(trim($line))) {
        continue;
      }
      $values = str_getcsv($line, ',', '"', '\\');
      if (count($values) !== count($headers)) {
        continue;
      }
      $item = [];
      foreach ($headers as $index => $header) {
        $fieldName = $fieldMapping[$header] ?? $header;
        $item[$fieldName] = $values[$index];
      }
      $items[] = $item;
    }

    return $this->importFromJson($contentType, $items);
  }

  /**
   * Import content from JSON array.
   *
   * @param string $contentType
   *   The content type machine name.
   * @param array $items
   *   Array of items to import.
   *
   * @return array
   *   Result array with success status and import details.
   */
  public function importFromJson(string $contentType, array $items): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    // Validate content type exists.
    $nodeType = $this->entityTypeManager->getStorage('node_type')->load($contentType);
    if (!$nodeType) {
      return ['success' => FALSE, 'error' => "Content type '$contentType' not found."];
    }

    // Check item limit.
    if (count($items) > self::MAX_IMPORT_ITEMS) {
      return [
        'success' => FALSE,
        'error' => sprintf('Import limited to %d items per call. Received %d items.', self::MAX_IMPORT_ITEMS, count($items)),
      ];
    }

    // Validate before import.
    $validation = $this->validateImport($contentType, $items);
    if (!$validation['success'] || !empty($validation['data']['errors'])) {
      return [
        'success' => FALSE,
        'error' => 'Validation failed. Please fix errors before importing.',
        'validation_errors' => $validation['data']['errors'] ?? [],
      ];
    }

    $fieldDefinitions = $this->entityFieldManager->getFieldDefinitions('node', $contentType);
    $results = [
      'created' => [],
      'failed' => [],
    ];

    // Store import status.
    $importId = uniqid('import_', TRUE);
    $this->setImportStatus($importId, 'in_progress', count($items), 0);

    try {
      foreach ($items as $index => $item) {
        try {
          $title = $item['title'] ?? $item['name'] ?? 'Imported item ' . ($index + 1);
          unset($item['title'], $item['name']);

          $nodeData = [
            'type' => $contentType,
            'title' => $title,
            'uid' => $this->currentUser->id(),
            'status' => $item['status'] ?? 0,
          ];
          unset($item['status']);

          foreach ($item as $fieldName => $value) {
            if (empty($value) && $value !== '0' && $value !== 0) {
              continue;
            }

            // SECURITY: Skip protected fields.
            if ($this->isProtectedField($fieldName)) {
              continue;
            }

            if (!str_starts_with($fieldName, 'field_') && !in_array($fieldName, ['body'])) {
              $checkName = 'field_' . $fieldName;
              if (isset($fieldDefinitions[$checkName])) {
                $fieldName = $checkName;
              }
            }

            // SECURITY: Double-check normalized field name.
            if ($this->isProtectedField($fieldName)) {
              continue;
            }

            if (isset($fieldDefinitions[$fieldName])) {
              $nodeData[$fieldName] = $this->normalizeFieldValue($fieldName, $value, $fieldDefinitions);
            }
          }

          $node = $this->entityTypeManager->getStorage('node')->create($nodeData);
          $node->save();

          $results['created'][] = [
            'nid' => $node->id(),
            'title' => $title,
            'row' => $index + 1,
          ];

          $this->setImportStatus($importId, 'in_progress', count($items), count($results['created']));
        }
        catch (\Exception $e) {
          $results['failed'][] = [
            'row' => $index + 1,
            'error' => $e->getMessage(),
            'title' => $item['title'] ?? 'Unknown',
          ];
        }
      }

      $this->setImportStatus($importId, 'completed', count($items), count($results['created']), count($results['failed']));

      $this->auditLogger->logSuccess('import_content', 'migration', $importId, [
        'content_type' => $contentType,
        'created_count' => count($results['created']),
        'failed_count' => count($results['failed']),
      ]);

      return [
        'success' => TRUE,
        'data' => [
          'import_id' => $importId,
          'content_type' => $contentType,
          'total_items' => count($items),
          'created_count' => count($results['created']),
          'failed_count' => count($results['failed']),
          'created' => $results['created'],
          'failed' => $results['failed'],
          'message' => sprintf('Successfully imported %d of %d items.', count($results['created']), count($items)),
        ],
      ];
    }
    catch (\Exception $e) {
      $this->setImportStatus($importId, 'failed', count($items), count($results['created']), count($results['failed']));
      $this->auditLogger->logFailure('import_content', 'migration', $importId, ['error' => $e->getMessage()]);
      return ['success' => FALSE, 'error' => 'Import failed: ' . $e->getMessage()];
    }
  }

  /**
   * Validate data before import.
   *
   * @param string $contentType
   *   The content type machine name.
   * @param array $items
   *   Array of items to validate.
   *
   * @return array
   *   Validation result with any errors found.
   */
  public function validateImport(string $contentType, array $items): array {
    // Check content type exists.
    $nodeType = $this->entityTypeManager->getStorage('node_type')->load($contentType);
    if (!$nodeType) {
      return ['success' => FALSE, 'error' => "Content type '$contentType' not found."];
    }

    // Check item limit.
    if (count($items) > self::MAX_IMPORT_ITEMS) {
      return [
        'success' => FALSE,
        'error' => sprintf('Import limited to %d items per call. Received %d items.', self::MAX_IMPORT_ITEMS, count($items)),
      ];
    }

    $fieldMapping = $this->getFieldMapping($contentType);
    $requiredFields = $fieldMapping['data']['required'] ?? [];
    $optionalFields = $fieldMapping['data']['optional'] ?? [];
    $allFields = array_merge($requiredFields, $optionalFields);

    $errors = [];
    $warnings = [];

    foreach ($items as $index => $item) {
      $rowNum = $index + 1;

      // Check title is present.
      if (empty($item['title']) && empty($item['name'])) {
        $errors[] = [
          'row' => $rowNum,
          'field' => 'title',
          'message' => 'Title (or name) is required.',
        ];
      }

      // Check required fields.
      foreach ($requiredFields as $field => $info) {
        if ($field === 'title') {
          continue;
        }
        $fieldKey = $field;
        if (!isset($item[$fieldKey])) {
          $fieldKey = str_replace('field_', '', $field);
        }
        if (!isset($item[$fieldKey]) && !isset($item[$field])) {
          $warnings[] = [
            'row' => $rowNum,
            'field' => $field,
            'message' => "Required field '$field' is missing.",
          ];
        }
      }

      // Check for unknown fields.
      foreach (array_keys($item) as $fieldName) {
        if (in_array($fieldName, ['title', 'name', 'status'])) {
          continue;
        }
        $normalizedName = str_starts_with($fieldName, 'field_') ? $fieldName : 'field_' . $fieldName;
        if (!isset($allFields[$normalizedName]) && !isset($allFields[$fieldName]) && $fieldName !== 'body') {
          $warnings[] = [
            'row' => $rowNum,
            'field' => $fieldName,
            'message' => "Unknown field '$fieldName' will be ignored.",
          ];
        }
      }
    }

    return [
      'success' => TRUE,
      'data' => [
        'valid' => empty($errors),
        'total_items' => count($items),
        'error_count' => count($errors),
        'warning_count' => count($warnings),
        'errors' => $errors,
        'warnings' => $warnings,
      ],
    ];
  }

  /**
   * Get field mapping for a content type.
   *
   * @param string $contentType
   *   The content type machine name.
   *
   * @return array
   *   Array of required and optional fields.
   */
  public function getFieldMapping(string $contentType): array {
    $nodeType = $this->entityTypeManager->getStorage('node_type')->load($contentType);
    if (!$nodeType) {
      return ['success' => FALSE, 'error' => "Content type '$contentType' not found."];
    }

    $fieldDefinitions = $this->entityFieldManager->getFieldDefinitions('node', $contentType);
    $required = [];
    $optional = [];

    // Title is always required.
    $required['title'] = [
      'label' => 'Title',
      'type' => 'string',
      'description' => 'The content title (required).',
    ];

    foreach ($fieldDefinitions as $fieldName => $definition) {
      // Skip base fields that are auto-managed.
      if (in_array($fieldName, [
        'nid', 'uuid', 'vid', 'langcode', 'type', 'revision_timestamp',
        'revision_uid', 'revision_log', 'status', 'uid', 'title',
        'created', 'changed', 'promote', 'sticky', 'default_langcode',
        'revision_default', 'revision_translation_affected', 'path',
        'menu_link', 'content_translation_source', 'content_translation_outdated',
      ])) {
        continue;
      }

      $fieldInfo = [
        'label' => $definition->getLabel(),
        'type' => $definition->getType(),
        'description' => $definition->getDescription() ?: 'No description available.',
      ];

      // Add allowed values for list fields.
      $settings = $definition->getSettings();
      if (isset($settings['allowed_values'])) {
        $fieldInfo['allowed_values'] = $settings['allowed_values'];
      }

      // Check if field is required.
      if ($definition->isRequired()) {
        $required[$fieldName] = $fieldInfo;
      }
      else {
        $optional[$fieldName] = $fieldInfo;
      }
    }

    return [
      'success' => TRUE,
      'data' => [
        'content_type' => $contentType,
        'label' => $nodeType->label(),
        'required' => $required,
        'optional' => $optional,
      ],
    ];
  }

  /**
   * Export content to CSV format.
   *
   * @param string $contentType
   *   The content type machine name.
   * @param int $limit
   *   Maximum number of items to export.
   *
   * @return array
   *   Result with CSV data string.
   */
  public function exportToCsv(string $contentType, int $limit = 100): array {
    $nodeType = $this->entityTypeManager->getStorage('node_type')->load($contentType);
    if (!$nodeType) {
      return ['success' => FALSE, 'error' => "Content type '$contentType' not found."];
    }

    $limit = min($limit, self::MAX_EXPORT_ITEMS);

    $fieldDefinitions = $this->entityFieldManager->getFieldDefinitions('node', $contentType);
    $exportFields = ['nid', 'title', 'status', 'created'];

    // Add custom fields.
    foreach (array_keys($fieldDefinitions) as $fieldName) {
      if (str_starts_with($fieldName, 'field_') || $fieldName === 'body') {
        $exportFields[] = $fieldName;
      }
    }

    $nodes = $this->entityTypeManager->getStorage('node')
      ->loadByProperties(['type' => $contentType]);

    $nodes = array_slice($nodes, 0, $limit);

    $rows = [];
    $rows[] = implode(',', array_map([$this, 'csvEscape'], $exportFields));

    foreach ($nodes as $node) {
      $row = [];
      foreach ($exportFields as $fieldName) {
        if ($fieldName === 'nid') {
          $row[] = $node->id();
        }
        elseif ($fieldName === 'title') {
          $row[] = $this->csvEscape($node->getTitle());
        }
        elseif ($fieldName === 'status') {
          $row[] = $node->isPublished() ? '1' : '0';
        }
        elseif ($fieldName === 'created') {
          $row[] = date('Y-m-d H:i:s', $node->getCreatedTime());
        }
        elseif ($node->hasField($fieldName)) {
          $value = $this->extractFieldValue($node->get($fieldName));
          $row[] = $this->csvEscape((string) $value);
        }
        else {
          $row[] = '';
        }
      }
      $rows[] = implode(',', $row);
    }

    return [
      'success' => TRUE,
      'data' => [
        'content_type' => $contentType,
        'exported_count' => count($nodes),
        'fields' => $exportFields,
        'csv_data' => implode("\n", $rows),
        'message' => sprintf('Exported %d items of type %s.', count($nodes), $contentType),
      ],
    ];
  }

  /**
   * Export content to JSON format.
   *
   * @param string $contentType
   *   The content type machine name.
   * @param int $limit
   *   Maximum number of items to export.
   *
   * @return array
   *   Result with JSON data array.
   */
  public function exportToJson(string $contentType, int $limit = 100): array {
    $nodeType = $this->entityTypeManager->getStorage('node_type')->load($contentType);
    if (!$nodeType) {
      return ['success' => FALSE, 'error' => "Content type '$contentType' not found."];
    }

    $limit = min($limit, self::MAX_EXPORT_ITEMS);

    $fieldDefinitions = $this->entityFieldManager->getFieldDefinitions('node', $contentType);

    $nodes = $this->entityTypeManager->getStorage('node')
      ->loadByProperties(['type' => $contentType]);

    $nodes = array_slice($nodes, 0, $limit);

    $items = [];
    foreach ($nodes as $node) {
      $item = [
        'nid' => $node->id(),
        'uuid' => $node->uuid(),
        'title' => $node->getTitle(),
        'status' => $node->isPublished() ? 1 : 0,
        'created' => date('c', $node->getCreatedTime()),
        'changed' => date('c', $node->getChangedTime()),
      ];

      foreach (array_keys($fieldDefinitions) as $fieldName) {
        if ((str_starts_with($fieldName, 'field_') || $fieldName === 'body') && $node->hasField($fieldName)) {
          $item[$fieldName] = $this->extractFieldValue($node->get($fieldName), TRUE);
        }
      }

      $items[] = $item;
    }

    return [
      'success' => TRUE,
      'data' => [
        'content_type' => $contentType,
        'exported_count' => count($items),
        'items' => $items,
        'message' => sprintf('Exported %d items of type %s.', count($items), $contentType),
      ],
    ];
  }

  /**
   * Get status of last import operation.
   *
   * @return array
   *   Import status information.
   */
  public function getImportStatus(): array {
    $status = $this->state->get(self::IMPORT_STATUS_KEY);

    if (!$status) {
      return [
        'success' => TRUE,
        'data' => [
          'has_import' => FALSE,
          'message' => 'No recent import found.',
        ],
      ];
    }

    return [
      'success' => TRUE,
      'data' => [
        'has_import' => TRUE,
        'import_id' => $status['import_id'],
        'status' => $status['status'],
        'total_items' => $status['total_items'],
        'processed' => $status['processed'],
        'failed' => $status['failed'] ?? 0,
        'started_at' => $status['started_at'],
        'updated_at' => $status['updated_at'],
      ],
    ];
  }

  /**
   * Store import status in state.
   */
  protected function setImportStatus(string $importId, string $status, int $total, int $processed, int $failed = 0): void {
    $this->state->set(self::IMPORT_STATUS_KEY, [
      'import_id' => $importId,
      'status' => $status,
      'total_items' => $total,
      'processed' => $processed,
      'failed' => $failed,
      'started_at' => date('c'),
      'updated_at' => date('c'),
    ]);
  }

  /**
   * Normalize field value for import.
   */
  protected function normalizeFieldValue(string $fieldName, mixed $value, array $fieldDefinitions): mixed {
    if (!isset($fieldDefinitions[$fieldName])) {
      return $value;
    }

    $fieldType = $fieldDefinitions[$fieldName]->getType();

    return match ($fieldType) {
      'text_long', 'text_with_summary' => is_array($value) ? $value : ['value' => $value, 'format' => 'basic_html'],
      'entity_reference' => is_array($value) ? $value : ['target_id' => (int) $value],
      'image', 'file' => is_array($value) ? $value : ['target_id' => (int) $value],
      'link' => is_array($value) ? $value : ['uri' => $value],
      'datetime' => is_array($value) ? $value : ['value' => $value],
      'boolean' => (bool) $value,
      'integer' => (int) $value,
      'decimal', 'float' => (float) $value,
      default => $value,
    };
  }

  /**
   * Extract field value for export.
   */
  protected function extractFieldValue($fieldItemList, bool $fullFormat = FALSE): mixed {
    if ($fieldItemList->isEmpty()) {
      return $fullFormat ? NULL : '';
    }

    $fieldType = $fieldItemList->getFieldDefinition()->getType();

    if ($fullFormat) {
      $values = [];
      foreach ($fieldItemList as $item) {
        $values[] = $item->getValue();
      }
      return count($values) === 1 ? $values[0] : $values;
    }

    // Simple string extraction for CSV.
    $item = $fieldItemList->first();
    if (!$item) {
      return '';
    }

    return match ($fieldType) {
      'text_long', 'text_with_summary' => $item->value ?? '',
      'entity_reference' => $item->target_id ?? '',
      'image', 'file' => $item->target_id ?? '',
      'link' => $item->uri ?? '',
      'datetime' => $item->value ?? '',
      'boolean' => $item->value ? '1' : '0',
      default => $item->value ?? '',
    };
  }

  /**
   * Escape a value for CSV output.
   */
  protected function csvEscape(string $value): string {
    if (str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n")) {
      return '"' . str_replace('"', '""', $value) . '"';
    }
    return $value;
  }

  /**
   * Check if a field is protected from import.
   *
   * SECURITY: Protected fields could be used for privilege escalation,
   * data integrity attacks, or to bypass access controls.
   *
   * @param string $fieldName
   *   The field name to check.
   *
   * @return bool
   *   TRUE if the field is protected and should not be imported.
   */
  protected function isProtectedField(string $fieldName): bool {
    // Normalize field name.
    $fieldLower = strtolower(trim($fieldName));

    // Check explicit blocklist.
    foreach (self::PROTECTED_FIELDS as $protected) {
      if ($fieldLower === strtolower($protected)) {
        return TRUE;
      }
    }

    // Check patterns.
    foreach (self::PROTECTED_FIELD_PATTERNS as $pattern) {
      if (preg_match($pattern, $fieldName)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Get list of protected fields (for documentation/transparency).
   *
   * @return array
   *   List of protected field names and patterns.
   */
  public function getProtectedFields(): array {
    return [
      'fields' => self::PROTECTED_FIELDS,
      'patterns' => self::PROTECTED_FIELD_PATTERNS,
      'note' => 'These fields cannot be set via import for security reasons.',
    ];
  }

}
