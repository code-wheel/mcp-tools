<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_migration\Service;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\State\StateInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\mcp_tools\Service\LinkTarget;
use Drupal\mcp_tools\Service\TextFormat;

/**
 * Service for content import/export and migration operations.
 */
class MigrationService {

  /**
   * Maximum number of items allowed per import.
   */
  protected const MAX_IMPORT_ITEMS = 1000;

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
  // Publish status should be explicit, not imported blindly.
    'status',
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
  // All revision fields.
    '/^revision_/',
  // All translation fields.
    '/^content_translation_/',
  ];

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityFieldManagerInterface $entityFieldManager,
    protected AccountProxyInterface $currentUser,
    protected StateInterface $state,
    protected AccessManager $accessManager,
    protected AuditLogger $auditLogger,
    protected ?MigrateImport $migrateImport = NULL,
    protected ?LinkTarget $linkTarget = NULL,
  ) {}

  /**
   * Import content from CSV data.
   *
   * @param string $contentType
   *   The content type machine name.
   * @param string $csvData
   *   CSV data as a string (first row should be headers).
   * @param array $fieldMapping
   *   Mapping of CSV columns to Drupal fields
   *   (e.g., ['csv_column' => 'field_name']).
   * @param string $key
   *   The column whose value names a row for good: an id from the source,
   *   or uuid. Required.
   * @param array $options
   *   'dry_run' => TRUE plans and writes nothing.
   *
   * @return array
   *   Result array with success status and import details.
   */
  public function importFromCsv(string $contentType, string $csvData, array $fieldMapping, string $key = '', array $options = []): array {
    // A stream, not lines: a quoted comma or newline is part of its value.
    $stream = fopen('php://temp', 'r+');
    fwrite($stream, $csvData);
    rewind($stream);
    $headers = fgetcsv($stream, NULL, ',', '"', '');
    if (!$headers || $headers === [NULL]) {
      fclose($stream);
      return ['success' => FALSE, 'error' => 'CSV must contain a header row and at least one data row.'];
    }
    $headers = array_map(fn($h) => $fieldMapping[trim((string) $h)] ?? trim((string) $h), $headers);
    $items = [];
    $rowErrors = [];
    $number = 0;
    while (($values = fgetcsv($stream, NULL, ',', '"', '')) !== FALSE) {
      if ($values === [NULL]) {
        continue;
      }
      $number++;
      if (count($values) !== count($headers)) {
        $rowErrors[$number] = sprintf('Row %d has %d columns, the header has %d.', $number, count($values), count($headers));
        continue;
      }
      $items[$number] = array_combine($headers, $values);
    }
    fclose($stream);
    if ($number === 0) {
      return ['success' => FALSE, 'error' => 'CSV must contain a header row and at least one data row.'];
    }
    return $this->import($contentType, $items, $key, $options, $rowErrors);
  }

  /**
   * Import content from JSON array.
   *
   * @param string $contentType
   *   The content type machine name.
   * @param array $items
   *   Array of items to import.
   * @param string $key
   *   The field whose value names a row for good: an id from the source,
   *   or uuid. Required.
   * @param array $options
   *   'dry_run' => TRUE plans and writes nothing.
   *
   * @return array
   *   Result array with success status and import details.
   */
  public function importFromJson(string $contentType, array $items, string $key = '', array $options = []): array {
    $numbered = [];
    foreach (array_values($items) as $index => $item) {
      $numbered[$index + 1] = (array) $item;
    }
    return $this->import($contentType, $numbered, $key, $options);
  }

  /**
   * The entity types the import takes.
   *
   * The bundle entity type (NULL: one bundle, named as the type), the key a
   * row has when none is named (NULL: the caller names one), columns that
   * go to a field of another name, and values every row gets when it has
   * none.
   */
  protected const IMPORT_TYPES = [
    'node' => ['bundles' => 'node_type', 'key' => NULL, 'aliases' => ['name' => 'title'], 'fill' => []],
    'taxonomy_term' => ['bundles' => 'taxonomy_vocabulary', 'key' => 'name', 'aliases' => [], 'fill' => []],
    'redirect' => [
      'bundles' => NULL,
      'key' => 'source',
      'aliases' => ['source' => 'redirect_source', 'destination' => 'redirect_redirect'],
      'fill' => ['status_code' => 301, 'language' => 'und'],
    ],
  ];

  /**
   * Checks the rows and runs them as a migration.
   *
   * @param string $bundle
   *   The content type or vocabulary; for redirects, empty.
   * @param array<int, array<string, mixed>> $items
   *   Rows by their row number.
   * @param string $key
   *   The key column.
   * @param array $options
   *   The options: 'dry_run', 'entity_type' (node by default).
   * @param array<int, string> $rowErrors
   *   Rows already refused (CSV), by row number.
   *
   * @return array
   *   The result.
   */
  protected function import(string $bundle, array $items, string $key, array $options = [], array $rowErrors = []): array {
    $entityType = (string) ($options['entity_type'] ?? 'node');
    $type = self::IMPORT_TYPES[$entityType] ?? NULL;
    if ($type === NULL) {
      return [
        'success' => FALSE,
        'error' => sprintf("The import takes %s, not '%s'.", implode(', ', array_keys(self::IMPORT_TYPES)), $entityType),
      ];
    }
    $key = trim($key) !== '' ? trim($key) : (string) $type['key'];
    if ($key === '') {
      return [
        'success' => FALSE,
        'error' => 'An import needs a key: the name of a column whose value names its row for good, such as an id from the source, or uuid. A later import with the same key updates what the first one made, and does not make copies.',
        'code' => 'KEY_REQUIRED',
      ];
    }
    if (($missing = $this->migrateMissing()) !== NULL) {
      return $missing;
    }
    if ($type['bundles'] === NULL) {
      if ($bundle !== '' && $bundle !== $entityType) {
        return ['success' => FALSE, 'error' => "A $entityType has no types: leave content_type out."];
      }
      $bundle = $entityType;
    }
    elseif (!$this->entityTypeManager->getStorage($type['bundles'])->load($bundle)) {
      $what = $entityType === 'node' ? 'Content type' : 'Vocabulary';
      return ['success' => FALSE, 'error' => "$what '$bundle' not found."];
    }
    if (count($items) + count($rowErrors) > self::MAX_IMPORT_ITEMS) {
      return [
        'success' => FALSE,
        'error' => sprintf('Import limited to %d rows. Received %d.', self::MAX_IMPORT_ITEMS, count($items) + count($rowErrors)),
      ];
    }

    $definitions = $this->entityFieldManager->getFieldDefinitions($entityType, $bundle);
    $info = $this->entityTypeManager->getDefinition($entityType);
    $idKey = (string) $info->getKey('id');
    $labelField = $info->getKey('label') ?: NULL;
    $never = array_values(array_filter([$idKey, $info->getKey('revision'), $info->getKey('bundle')]));

    // A column that is no field of the type is refused, with the fields
    // there are: it was dropped without a word.
    $columns = [];
    $unknown = [];
    $ignored = [];
    foreach ($items as $item) {
      foreach (array_keys($item) as $column) {
        $column = (string) $column;
        if (isset($columns[$column]) || isset($ignored[$column])) {
          continue;
        }
        $field = $this->fieldFor($column, $definitions, $type['aliases'], $never);
        // The key is also a field when it is one (a term's name, a
        // redirect's source); an id from the source is the key only.
        if ($column === $key && ($field === NULL || $field === '' || $column === 'uuid')) {
          continue;
        }
        if ($field === NULL) {
          $unknown[$column] = $column;
        }
        elseif ($field === '') {
          $ignored[$column] = $column;
        }
        else {
          $columns[$column] = $field;
        }
      }
    }
    if ($unknown) {
      $fields = array_values(array_filter(array_keys($definitions), fn($f) => $this->fieldFor($f, $definitions, [], $never) === $f));
      return [
        'success' => FALSE,
        'error' => sprintf("%s %s has no field %s. Its fields are: %s.", $entityType, $bundle, implode(', ', $unknown), implode(', ', $fields)),
      ];
    }
    if (!$this->accessManager->canWrite('write', min(MigrateImport::RUN_SIZE, max(1, count($items))))) {
      return $this->accessManager->getWriteAccessDenied();
    }

    $importId = MigrateImport::id($entityType, $bundle, $key);
    $failed = [];
    foreach ($rowErrors as $number => $error) {
      $failed[$number] = ['row' => $number, 'error' => $error];
    }

    // The rows by their key.
    $byKey = [];
    $numberOf = [];
    foreach ($items as $number => $item) {
      $value = $item[$key] ?? NULL;
      if ($value === NULL || $value === '' || is_array($value)) {
        $failed[$number] = ['row' => $number, 'error' => "Row $number has no value for the key '$key'."];
        continue;
      }
      $value = (string) $value;
      if (isset($numberOf[$value])) {
        $failed[$number] = [
          'row' => $number,
          'error' => "Row $number has the same key '$value' as row {$numberOf[$value]}.",
        ];
        continue;
      }
      $numberOf[$value] = $number;
      $byKey[$value] = $item;
    }

    // What is there already. Made by an earlier run of this import: it is
    // updated. Matched by uuid: updated, as an export imported back. Matched
    // by name or source and not made by this import: left as it is.
    $made = $this->migrateImport->made($importId, array_keys($byKey));
    $existing = [];
    $ids = [];
    foreach (array_keys($byKey) as $value) {
      if (isset($made[$value])) {
        continue;
      }
      $found = $this->existingFor($entityType, $bundle, $key, (string) $value);
      if ($found === NULL) {
        continue;
      }
      if ($found['update']) {
        $ids[$value] = $found['id'];
        continue;
      }
      $existing[$value] = ['row' => $numberOf[$value], 'key' => (string) $value, 'id' => $found['id']];
      unset($byKey[$value]);
    }

    // Reference fields to this same type may name other rows by their key.
    $selfFields = [];
    foreach ($columns as $field) {
      if ($definitions[$field]->getType() === 'entity_reference' && $definitions[$field]->getSetting('target_type') === $entityType) {
        $selfFields[$field] = $field;
      }
    }

    $rows = [];
    $needs = [];
    foreach ($byKey as $value => $item) {
      $number = $numberOf[$value];
      $row = [$key => (string) $value] + $type['fill'];
      $needs[$value] = [];
      $problems = [];
      foreach ($columns as $column => $field) {
        if (!array_key_exists($column, $item)) {
          continue;
        }
        $given = $item[$column];
        if (isset($selfFields[$field])) {
          $row["_self_$field"] = [];
          $row["_ids_$field"] = [];
          if ($given === NULL || $given === '') {
            continue;
          }
          $single = $definitions[$field]->getFieldStorageDefinition()->getCardinality() === 1;
          foreach (array_map('trim', $single || is_array($given) ? (array) $given : explode('|', (string) $given)) as $part) {
            $part = (string) (is_array($part) ? ($part['target_id'] ?? '') : $part);
            if ($part === '') {
              continue;
            }
            if (isset($byKey[$part]) && $part !== (string) $value) {
              $row["_self_$field"][] = $part;
              $needs[$value][] = $part;
            }
            elseif (isset($existing[$part])) {
              $row["_ids_$field"][] = (int) $existing[$part]['id'];
            }
            else {
              [$refs, $missing] = $this->references($definitions[$field], $part, $type['bundles'] ? [$bundle] : NULL);
              if ($missing) {
                $problems[] = sprintf("%s: no row or %s named '%s'.", $field, $entityType, $part);
              }
              foreach ($refs as $ref) {
                $row["_ids_$field"][] = (int) $ref['target_id'];
              }
            }
          }
          continue;
        }
        if ($given === NULL || $given === '') {
          $row[$field] = NULL;
          continue;
        }
        if ($definitions[$field]->getType() === 'entity_reference' && !is_array($given)) {
          [$refs, $missing] = $this->references($definitions[$field], (string) $given);
          if ($missing) {
            $problems[] = sprintf('%s: no %s named %s.', $field, $definitions[$field]->getSetting('target_type'), implode(', ', array_map(fn($m) => "'$m'", $missing)));
            continue;
          }
          $row[$field] = $refs;
          continue;
        }
        $row[$field] = $field === 'status' ? (int) (bool) $given : $this->normalizeFieldValue($field, $given, $definitions);
      }
      if ($labelField !== NULL && empty($row[$labelField])) {
        $problems[] = ucfirst($labelField) . ' is required.';
      }
      if ($entityType === 'redirect' && (empty($row['redirect_source']['path']) || empty($row['redirect_redirect']['uri']))) {
        $problems[] = 'A redirect needs a source and a destination.';
      }
      if ($problems) {
        $failed[$number] = [
          'row' => $number,
          'key' => (string) $value,
          'error' => "Row $number: " . implode(' ', $problems),
        ];
        continue;
      }
      if (isset($ids[$value])) {
        $row[$idKey] = $ids[$value];
        $row['uuid'] = (string) $value;
      }
      $rows[$value] = $row;
    }

    // A row that another row refers to runs first.
    [$rows, $circular] = $this->inOrder($rows, $needs);
    foreach ($circular as $value) {
      $failed[$numberOf[$value]] = [
        'row' => $numberOf[$value],
        'key' => (string) $value,
        'error' => "Row {$numberOf[$value]}: its references lead back to itself, or to a row that is refused.",
      ];
    }

    $actions = [];
    foreach ($rows as $value => $row) {
      $actions[$value] = isset($made[$value]) || isset($row[$idKey]) ? 'update' : 'create';
    }

    if (!empty($options['dry_run'])) {
      $plan = [];
      foreach ($actions as $value => $action) {
        $plan[] = ['row' => $numberOf[$value], 'key' => (string) $value, 'action' => $action];
      }
      ksort($failed);
      return [
        'success' => TRUE,
        'data' => [
          'import_id' => $importId,
          'dry_run' => TRUE,
          'plan' => $plan,
          'existing' => array_values($existing),
          'failed_count' => count($failed),
          'failed' => array_values($failed),
          'ignored_columns' => array_values($ignored),
          'message' => sprintf('Plan only, nothing written: %d to create, %d to update, %d there already, %d refused.', count(array_keys($actions, 'create', TRUE)), count(array_keys($actions, 'update', TRUE)), count($existing), count($failed)),
        ],
      ];
    }

    $fields = array_merge(array_values($columns), array_keys($type['fill']), $ids ? [$idKey, 'uuid'] : []);
    $fields = array_values(array_unique($fields));
    // A new node is unpublished unless the import says otherwise.
    $defaults = $entityType === 'node' && !in_array('status', $fields, TRUE) ? ['status' => 0] : [];
    try {
      $this->migrateImport->save($importId, $entityType, $bundle, $key, $rows, $fields, $defaults, array_values($selfFields));
      $run = $this->migrateImport->run($importId, count($rows));
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('import_content', 'migration', $importId, ['error' => $e->getMessage()]);
      return ['success' => FALSE, 'error' => 'Import failed: ' . $e->getMessage()];
    }
    foreach ($run['failed'] as $value => $error) {
      $number = $numberOf[$value] ?? 0;
      $failed[$number] = ['row' => $number, 'key' => (string) $value, 'error' => "Row $number: $error"];
    }
    ksort($failed);
    $created = $updated = 0;
    $warnings = [];
    foreach ($run['saved'] as $value) {
      ($actions[$value] ?? 'create') === 'update' ? $updated++ : $created++;
      // A redirect to nowhere is made, as one redirect is, and said.
      $uri = $rows[$value]['redirect_redirect']['uri'] ?? NULL;
      if ($uri !== NULL && ($warning = $this->linkTarget?->warning((string) $uri, 'mcp_redirect_update')) !== NULL) {
        $warnings[] = ['row' => $numberOf[$value] ?? 0, 'key' => (string) $value, 'warning' => $warning];
      }
    }
    $this->auditLogger->logSuccess('import_content', 'migration', $importId, [
      'entity_type' => $entityType,
      'bundle' => $bundle,
      'created_count' => $created,
      'updated_count' => $updated,
      'failed_count' => count($failed),
    ]);
    $message = sprintf('%d created, %d updated, %d there already, %d failed.', $created, $updated, count($existing), count($failed));
    if (!$run['done']) {
      $message .= sprintf(' %d rows are left: call again with the same rows and key to go on.', $run['remaining']);
    }
    if ($run['reset']) {
      $message .= ' An earlier run of this import had stopped halfway; it was reset.';
    }
    return [
      'success' => TRUE,
      'data' => [
        'import_id' => $importId,
        'entity_type' => $entityType,
        'content_type' => $bundle,
        'total_items' => count($items) + count($rowErrors),
        'created_count' => $created,
        'updated_count' => $updated,
        'failed_count' => count($failed),
        'failed' => array_values($failed),
        'existing' => array_values($existing),
        'warnings' => $warnings,
        'ignored_columns' => array_values($ignored),
        'remaining' => $run['remaining'],
        'done' => $run['done'],
        'message' => $message,
      ],
    ];
  }

  /**
   * The answer when Migrate or migrate_plus is not on, else NULL.
   *
   * Composer does not bring migrate_plus (only this submodule needs it), and
   * a site that updates from an earlier release has this module on without
   * them: the exports and the field tools work, the import says what to do.
   */
  protected function migrateMissing(): ?array {
    if ($this->migrateImport !== NULL && $this->migrateImport->ready()) {
      return NULL;
    }
    return [
      'success' => FALSE,
      'error' => 'The import runs on Drupal Migrate and needs the migrate_plus module, which is not on: composer require drupal/migrate_plus, then drush en migrate migrate_plus. The exports and the field tools work without it.',
      'code' => 'MODULE_NOT_ENABLED',
    ];
  }

  /**
   * The field a column goes to.
   *
   * @return string|null
   *   The field; '' for a field that is never imported (an id, the owner,
   *   the dates); NULL for no field of the type.
   */
  protected function fieldFor(string $column, array $definitions, array $aliases = [], array $never = []): ?string {
    if (isset($aliases[$column])) {
      return $aliases[$column];
    }
    if (in_array($column, $never, TRUE)) {
      return '';
    }
    if ($column === 'status' && isset($definitions['status'])) {
      return 'status';
    }
    if ($this->isProtectedField($column)) {
      return isset($definitions[$column]) ? '' : NULL;
    }
    if (isset($definitions[$column])) {
      return $column;
    }
    if (isset($definitions['field_' . $column])) {
      return 'field_' . $column;
    }
    return NULL;
  }

  /**
   * Orders rows so that a row another row refers to comes first.
   *
   * @param array<string, array> $rows
   *   The rows by key.
   * @param array<string, string[]> $needs
   *   For each key, the keys of the rows it refers to.
   *
   * @return array{0: array<string, array>, 1: string[]}
   *   The rows in order, and the keys that cannot be placed (a cycle, or a
   *   row that refers to a refused row).
   */
  protected function inOrder(array $rows, array $needs): array {
    $ordered = [];
    $left = $rows;
    do {
      $placed = 0;
      foreach ($left as $value => $row) {
        $waiting = array_filter($needs[$value] ?? [], fn($n) => !isset($ordered[$n]));
        if ($waiting === []) {
          $ordered[$value] = $row;
          unset($left[$value]);
          $placed++;
        }
      }
    } while ($placed > 0 && $left !== []);
    return [$ordered, array_map('strval', array_keys($left))];
  }

  /**
   * Reference values by id or by name; "|" separates several.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $definition
   *   The reference field.
   * @param string $given
   *   The value.
   * @param string[]|null $bundles
   *   Look only in these bundles; NULL for the field's own.
   *
   * @return array{0: array<int, array{target_id: int}>, 1: string[]}
   *   The references, and the names or ids that lead nowhere.
   */
  protected function references($definition, string $given, ?array $bundles = NULL): array {
    $targetType = (string) $definition->getSetting('target_type');
    $bundles ??= array_values((array) ($definition->getSetting('handler_settings')['target_bundles'] ?? []));
    $parts = $definition->getFieldStorageDefinition()->getCardinality() === 1 ? [$given] : explode('|', $given);
    $storage = $this->entityTypeManager->getStorage($targetType);
    $entityType = $this->entityTypeManager->getDefinition($targetType);
    $refs = [];
    $missing = [];
    foreach (array_map('trim', $parts) as $part) {
      if ($part === '') {
        continue;
      }
      if (ctype_digit($part)) {
        $storage->load($part) ? $refs[] = ['target_id' => (int) $part] : $missing[] = $part;
        continue;
      }
      $query = $storage->getQuery()->accessCheck(FALSE)->condition((string) $entityType->getKey('label'), $part)->range(0, 1);
      if ($bundles && $entityType->getKey('bundle')) {
        $query->condition((string) $entityType->getKey('bundle'), $bundles, 'IN');
      }
      $ids = $query->execute();
      $ids ? $refs[] = ['target_id' => (int) reset($ids)] : $missing[] = $part;
    }
    return [$refs, $missing];
  }

  /**
   * What is there already for a key value, made by hand or another import.
   *
   * @return array{id: int, update: bool}|null
   *   The id, and whether the import updates it (a uuid: an export imported
   *   back) or leaves it (a term by its name, a redirect by its source).
   */
  protected function existingFor(string $entityType, string $bundle, string $key, string $value): ?array {
    $natural = $key === 'uuid' || ($entityType === 'taxonomy_term' && $key === 'name') || ($entityType === 'redirect' && $key === 'source');
    if (!$natural) {
      return NULL;
    }
    $storage = $this->entityTypeManager->getStorage($entityType);
    $info = $this->entityTypeManager->getDefinition($entityType);
    $query = $storage->getQuery()->accessCheck(FALSE)->range(0, 1);
    if ($info->getKey('bundle') && $info->getBundleEntityType()) {
      $query->condition((string) $info->getKey('bundle'), $bundle);
    }
    if ($key === 'uuid') {
      $ids = $query->condition('uuid', $value)->execute();
      return $ids ? ['id' => (int) reset($ids), 'update' => TRUE] : NULL;
    }
    if ($entityType === 'taxonomy_term' && $key === 'name') {
      $ids = $query->condition('name', $value)->execute();
      return $ids ? ['id' => (int) reset($ids), 'update' => FALSE] : NULL;
    }
    if ($entityType === 'redirect' && $key === 'source') {
      $ids = $query->condition('redirect_source__path', ltrim(trim($value), '/'))->execute();
      return $ids ? ['id' => (int) reset($ids), 'update' => FALSE] : NULL;
    }
    return NULL;
  }

  /**
   * Undoes one import: deletes what it made, keeps what it only updated.
   *
   * @param string $importId
   *   The import_id an import answered with.
   *
   * @return array
   *   The result.
   */
  public function rollbackImport(string $importId): array {
    if (($missing = $this->migrateMissing()) !== NULL) {
      return $missing;
    }
    if (!$this->migrateImport->exists($importId)) {
      return ['success' => FALSE, 'error' => "No import '$importId'. mcp_migration_import_status lists the imports."];
    }
    $count = $this->migrateImport->madeCount($importId);
    if (!$this->accessManager->canWrite('delete', max(1, $count))) {
      return $this->accessManager->getWriteAccessDenied();
    }
    if (!$this->migrateImport->rollback($importId)) {
      return [
        'success' => FALSE,
        'error' => "The undo of '$importId' did not finish. mcp_migration_import_status shows what is left.",
      ];
    }
    $this->auditLogger->logSuccess('rollback_import', 'migration', $importId, ['deleted' => $count]);
    return [
      'success' => TRUE,
      'data' => [
        'import_id' => $importId,
        'deleted_count' => $count,
        'message' => sprintf('Undone: %d nodes the import made are deleted. Nodes it only updated are kept as they are now.', $count),
      ],
    ];
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
   * @param int $offset
   *   How many nodes to skip, in id order.
   *
   * @return array
   *   Result with CSV data string.
   */
  public function exportToCsv(string $contentType, int $limit = 100, int $offset = 0): array {
    $nodeType = $this->entityTypeManager->getStorage('node_type')->load($contentType);
    if (!$nodeType) {
      return ['success' => FALSE, 'error' => "Content type '$contentType' not found."];
    }

    $limit = min($limit, self::MAX_EXPORT_ITEMS);

    $fieldDefinitions = $this->entityFieldManager->getFieldDefinitions('node', $contentType);
    $exportFields = ['nid', 'uuid', 'title', 'status', 'created'];

    // Add custom fields.
    foreach (array_keys($fieldDefinitions) as $fieldName) {
      if (str_starts_with($fieldName, 'field_') || $fieldName === 'body') {
        $exportFields[] = $fieldName;
      }
    }

    [$nodes, $total] = $this->exportPage($contentType, $limit, $offset);

    $rows = [];
    $rows[] = implode(',', array_map([$this, 'csvEscape'], $exportFields));

    foreach ($nodes as $node) {
      $row = [];
      foreach ($exportFields as $fieldName) {
        if ($fieldName === 'nid') {
          $row[] = $node->id();
        }
        elseif ($fieldName === 'uuid') {
          $row[] = $node->uuid();
        }
        elseif ($fieldName === 'title') {
          $row[] = $this->csvEscape($node->getTitle());
        }
        elseif ($fieldName === 'status') {
          $row[] = $node->isPublished() ? '1' : '0';
        }
        elseif ($fieldName === 'created') {
          $row[] = date('Y-m-d H:i:s', (int) $node->getCreatedTime());
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
        'total' => $total,
        'next_offset' => $offset + count($nodes) < $total ? $offset + count($nodes) : NULL,
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
   * @param int $offset
   *   How many nodes to skip, in id order.
   *
   * @return array
   *   Result with JSON data array.
   */
  public function exportToJson(string $contentType, int $limit = 100, int $offset = 0): array {
    $nodeType = $this->entityTypeManager->getStorage('node_type')->load($contentType);
    if (!$nodeType) {
      return ['success' => FALSE, 'error' => "Content type '$contentType' not found."];
    }

    $limit = min($limit, self::MAX_EXPORT_ITEMS);

    $fieldDefinitions = $this->entityFieldManager->getFieldDefinitions('node', $contentType);

    [$nodes, $total] = $this->exportPage($contentType, $limit, $offset);

    $items = [];
    foreach ($nodes as $node) {
      $item = [
        'nid' => $node->id(),
        'uuid' => $node->uuid(),
        'title' => $node->getTitle(),
        'status' => $node->isPublished() ? 1 : 0,
        'created' => date('c', (int) $node->getCreatedTime()),
        'changed' => date('c', (int) $node->getChangedTime()),
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
        'total' => $total,
        'next_offset' => $offset + count($items) < $total ? $offset + count($items) : NULL,
        'items' => $items,
        'message' => sprintf('Exported %d items of type %s.', count($items), $contentType),
      ],
    ];
  }

  /**
   * The nodes of a type in id order, one page.
   *
   * @return array{0: \Drupal\node\NodeInterface[], 1: int}
   *   The page, and how many nodes of the type there are.
   */
  protected function exportPage(string $contentType, int $limit, int $offset): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $query = fn() => $storage->getQuery()->accessCheck(TRUE)->condition('type', $contentType);
    $total = (int) $query()->count()->execute();
    $ids = $query()->sort('nid')->range(max(0, $offset), max(1, min($limit, self::MAX_EXPORT_ITEMS)))->execute();
    return [array_values($storage->loadMultiple($ids)), $total];
  }

  /**
   * What an import made and what failed, from its record.
   *
   * @param string|null $importId
   *   The import_id an import answered with. NULL lists the imports.
   *
   * @return array
   *   Import status information.
   */
  public function getImportStatus(?string $importId = NULL): array {
    if (($missing = $this->migrateMissing()) !== NULL) {
      return $missing;
    }
    if ($importId !== NULL && $importId !== '') {
      if (!$this->migrateImport->exists($importId)) {
        return [
          'success' => FALSE,
          'error' => "No import '$importId'. Call this without an import_id to list the imports.",
        ];
      }
      return ['success' => TRUE, 'data' => ['has_import' => TRUE] + $this->migrateImport->status($importId)];
    }
    $ids = $this->entityTypeManager->getStorage('migration')->getQuery()->accessCheck(FALSE)
      ->condition('id', MigrateImport::PREFIX, 'STARTS_WITH')->sort('id')->execute();
    $imports = array_map(fn($id) => $this->migrateImport->status((string) $id), array_values($ids));
    return [
      'success' => TRUE,
      'data' => [
        'has_import' => (bool) $imports,
        'imports' => $imports,
        'message' => $imports ? sprintf('%d imports.', count($imports)) : 'No import found.',
      ],
    ];
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
      'text_long', 'text_with_summary' => TextFormat::fieldValue($this->entityTypeManager, $value),
      'entity_reference' => is_array($value) ? $value : ['target_id' => (int) $value],
      'image', 'file' => is_array($value) ? $value : ['target_id' => (int) $value],
      'link' => is_array($value) ? $value : ['uri' => $this->linkUri((string) $value)],
      'redirect_source' => is_array($value) ? $value : ['path' => ltrim(trim((string) $value), '/'), 'query' => []],
      'datetime' => is_array($value) ? $value : ['value' => $value],
      'boolean' => (bool) $value,
      'integer' => (int) $value,
      'decimal', 'float' => (float) $value,
      default => $value,
    };
  }

  /**
   * A link as Drupal stores it: a path becomes internal:/path.
   */
  protected function linkUri(string $value): string {
    $value = trim($value);
    if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $value)) {
      return $value;
    }
    return 'internal:/' . ltrim($value, '/');
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
