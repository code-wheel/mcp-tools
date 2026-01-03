<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_redirect\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;

/**
 * Service for redirect operations.
 */
class RedirectService {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AccessManager $accessManager,
    protected AuditLogger $auditLogger,
  ) {}

  /**
   * List all redirects with pagination.
   *
   * @param int $limit
   *   Maximum number of redirects to return.
   * @param int $offset
   *   Offset for pagination.
   *
   * @return array
   *   Redirects data.
   */
  public function listRedirects(int $limit = 100, int $offset = 0): array {
    try {
      $storage = $this->entityTypeManager->getStorage('redirect');

      // Get total count.
      $totalCount = $storage->getQuery()
        ->accessCheck(TRUE)
        ->count()
        ->execute();

      // Get redirects.
      $ids = $storage->getQuery()
        ->accessCheck(TRUE)
        ->sort('rid', 'DESC')
        ->range($offset, $limit)
        ->execute();

      $redirects = $storage->loadMultiple($ids);

      $result = [];
      foreach ($redirects as $redirect) {
        $result[] = $this->formatRedirect($redirect);
      }

      return [
        'success' => TRUE,
        'data' => [
          'total' => (int) $totalCount,
          'limit' => $limit,
          'offset' => $offset,
          'redirects' => $result,
        ],
      ];
    }
    catch (\Exception $e) {
      return ['success' => FALSE, 'error' => 'Failed to list redirects: ' . $e->getMessage()];
    }
  }

  /**
   * Get redirect details by ID.
   *
   * @param int $id
   *   The redirect ID.
   *
   * @return array
   *   Redirect details.
   */
  public function getRedirect(int $id): array {
    try {
      $redirect = $this->entityTypeManager->getStorage('redirect')->load($id);

      if (!$redirect) {
        return ['success' => FALSE, 'error' => "Redirect with ID $id not found."];
      }

      return [
        'success' => TRUE,
        'data' => $this->formatRedirect($redirect),
      ];
    }
    catch (\Exception $e) {
      return ['success' => FALSE, 'error' => 'Failed to get redirect: ' . $e->getMessage()];
    }
  }

  /**
   * Create a new redirect.
   *
   * @param string $source
   *   The source path (without leading slash).
   * @param string $destination
   *   The destination path or URL.
   * @param int $statusCode
   *   The HTTP status code (301, 302, 303, 307).
   * @param string|null $language
   *   Optional language code.
   *
   * @return array
   *   Result of the operation.
   */
  public function createRedirect(string $source, string $destination, int $statusCode = 301, ?string $language = NULL): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    // Validate status code.
    $validStatusCodes = [301, 302, 303, 307];
    if (!in_array($statusCode, $validStatusCodes)) {
      return [
        'success' => FALSE,
        'error' => "Invalid status code '$statusCode'. Must be one of: " . implode(', ', $validStatusCodes),
      ];
    }

    // Normalize source path (remove leading slash if present).
    $source = ltrim($source, '/');

    if (empty($source)) {
      return ['success' => FALSE, 'error' => 'Source path cannot be empty.'];
    }

    if (empty($destination)) {
      return ['success' => FALSE, 'error' => 'Destination cannot be empty.'];
    }

    try {
      // Check if redirect already exists for this source.
      $existing = $this->findBySourceInternal($source, $language);
      if ($existing) {
        return [
          'success' => FALSE,
          'error' => "A redirect already exists for source path '$source'.",
          'existing_redirect' => $this->formatRedirect($existing),
        ];
      }

      $storage = $this->entityTypeManager->getStorage('redirect');

      $redirectData = [
        'redirect_source' => [
          'path' => $source,
          'query' => [],
        ],
        'redirect_redirect' => [
          'uri' => $this->normalizeDestination($destination),
        ],
        'status_code' => $statusCode,
        'language' => $language ?? 'und',
      ];

      $redirect = $storage->create($redirectData);
      $redirect->save();

      $this->auditLogger->logSuccess('create_redirect', 'redirect', (string) $redirect->id(), [
        'source' => $source,
        'destination' => $destination,
        'status_code' => $statusCode,
      ]);

      return [
        'success' => TRUE,
        'data' => [
          'redirect' => $this->formatRedirect($redirect),
          'message' => "Redirect from '/$source' to '$destination' created successfully.",
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('create_redirect', 'redirect', $source, ['error' => $e->getMessage()]);
      return ['success' => FALSE, 'error' => 'Failed to create redirect: ' . $e->getMessage()];
    }
  }

  /**
   * Update an existing redirect.
   *
   * @param int $id
   *   The redirect ID.
   * @param array $values
   *   Values to update (source, destination, status_code, language).
   *
   * @return array
   *   Result of the operation.
   */
  public function updateRedirect(int $id, array $values): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    try {
      $redirect = $this->entityTypeManager->getStorage('redirect')->load($id);

      if (!$redirect) {
        return ['success' => FALSE, 'error' => "Redirect with ID $id not found."];
      }

      $updatedFields = [];

      if (isset($values['source'])) {
        $source = ltrim($values['source'], '/');
        if (empty($source)) {
          return ['success' => FALSE, 'error' => 'Source path cannot be empty.'];
        }
        $redirect->setSource($source);
        $updatedFields[] = 'source';
      }

      if (isset($values['destination'])) {
        if (empty($values['destination'])) {
          return ['success' => FALSE, 'error' => 'Destination cannot be empty.'];
        }
        $redirect->setRedirect($this->normalizeDestination($values['destination']));
        $updatedFields[] = 'destination';
      }

      if (isset($values['status_code'])) {
        $validStatusCodes = [301, 302, 303, 307];
        if (!in_array($values['status_code'], $validStatusCodes)) {
          return [
            'success' => FALSE,
            'error' => "Invalid status code '{$values['status_code']}'. Must be one of: " . implode(', ', $validStatusCodes),
          ];
        }
        $redirect->setStatusCode($values['status_code']);
        $updatedFields[] = 'status_code';
      }

      if (isset($values['language'])) {
        $redirect->setLanguage($values['language']);
        $updatedFields[] = 'language';
      }

      if (empty($updatedFields)) {
        return ['success' => FALSE, 'error' => 'No valid fields provided for update.'];
      }

      $redirect->save();

      $this->auditLogger->logSuccess('update_redirect', 'redirect', (string) $id, [
        'updated_fields' => $updatedFields,
      ]);

      return [
        'success' => TRUE,
        'data' => [
          'redirect' => $this->formatRedirect($redirect),
          'updated_fields' => $updatedFields,
          'message' => "Redirect $id updated successfully.",
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('update_redirect', 'redirect', (string) $id, ['error' => $e->getMessage()]);
      return ['success' => FALSE, 'error' => 'Failed to update redirect: ' . $e->getMessage()];
    }
  }

  /**
   * Delete a redirect.
   *
   * @param int $id
   *   The redirect ID.
   *
   * @return array
   *   Result of the operation.
   */
  public function deleteRedirect(int $id): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    try {
      $redirect = $this->entityTypeManager->getStorage('redirect')->load($id);

      if (!$redirect) {
        return ['success' => FALSE, 'error' => "Redirect with ID $id not found."];
      }

      $redirectData = $this->formatRedirect($redirect);
      $redirect->delete();

      $this->auditLogger->logSuccess('delete_redirect', 'redirect', (string) $id, [
        'source' => $redirectData['source'],
        'destination' => $redirectData['destination'],
      ]);

      return [
        'success' => TRUE,
        'data' => [
          'id' => $id,
          'deleted_redirect' => $redirectData,
          'message' => "Redirect $id deleted successfully.",
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('delete_redirect', 'redirect', (string) $id, ['error' => $e->getMessage()]);
      return ['success' => FALSE, 'error' => 'Failed to delete redirect: ' . $e->getMessage()];
    }
  }

  /**
   * Find redirect by source path.
   *
   * @param string $source
   *   The source path to search for.
   *
   * @return array
   *   Search results.
   */
  public function findBySource(string $source): array {
    try {
      // Normalize source path.
      $source = ltrim($source, '/');

      $redirect = $this->findBySourceInternal($source);

      if (!$redirect) {
        return [
          'success' => TRUE,
          'data' => [
            'found' => FALSE,
            'source' => $source,
            'message' => "No redirect found for source path '/$source'.",
          ],
        ];
      }

      return [
        'success' => TRUE,
        'data' => [
          'found' => TRUE,
          'redirect' => $this->formatRedirect($redirect),
        ],
      ];
    }
    catch (\Exception $e) {
      return ['success' => FALSE, 'error' => 'Failed to find redirect: ' . $e->getMessage()];
    }
  }

  /**
   * Bulk import redirects.
   *
   * @param array $redirects
   *   Array of redirects to import. Each redirect should have:
   *   - source: The source path
   *   - destination: The destination path or URL
   *   - status_code: (optional) HTTP status code, defaults to 301
   *   - language: (optional) Language code
   *
   * @return array
   *   Import results.
   */
  public function importRedirects(array $redirects): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    if (empty($redirects)) {
      return ['success' => FALSE, 'error' => 'No redirects provided for import.'];
    }

    $created = [];
    $skipped = [];
    $errors = [];

    foreach ($redirects as $index => $redirectData) {
      $source = $redirectData['source'] ?? '';
      $destination = $redirectData['destination'] ?? '';
      $statusCode = $redirectData['status_code'] ?? 301;
      $language = $redirectData['language'] ?? NULL;

      // Validate required fields.
      if (empty($source)) {
        $errors[] = [
          'index' => $index,
          'error' => 'Missing source path.',
          'data' => $redirectData,
        ];
        continue;
      }

      if (empty($destination)) {
        $errors[] = [
          'index' => $index,
          'error' => 'Missing destination.',
          'data' => $redirectData,
        ];
        continue;
      }

      // Normalize source.
      $source = ltrim($source, '/');

      // Check for existing redirect.
      $existing = $this->findBySourceInternal($source, $language);
      if ($existing) {
        $skipped[] = [
          'index' => $index,
          'source' => $source,
          'reason' => 'Redirect already exists.',
          'existing_id' => $existing->id(),
        ];
        continue;
      }

      // Create the redirect.
      try {
        $storage = $this->entityTypeManager->getStorage('redirect');

        $newRedirect = $storage->create([
          'redirect_source' => [
            'path' => $source,
            'query' => [],
          ],
          'redirect_redirect' => [
            'uri' => $this->normalizeDestination($destination),
          ],
          'status_code' => $statusCode,
          'language' => $language ?? 'und',
        ]);
        $newRedirect->save();

        $created[] = [
          'index' => $index,
          'id' => $newRedirect->id(),
          'source' => $source,
          'destination' => $destination,
        ];
      }
      catch (\Exception $e) {
        $errors[] = [
          'index' => $index,
          'error' => $e->getMessage(),
          'data' => $redirectData,
        ];
      }
    }

    $this->auditLogger->logSuccess('import_redirects', 'redirect', 'bulk', [
      'total_requested' => count($redirects),
      'created' => count($created),
      'skipped' => count($skipped),
      'errors' => count($errors),
    ]);

    return [
      'success' => TRUE,
      'data' => [
        'total_requested' => count($redirects),
        'created_count' => count($created),
        'skipped_count' => count($skipped),
        'error_count' => count($errors),
        'created' => $created,
        'skipped' => $skipped,
        'errors' => $errors,
        'message' => sprintf(
          'Import complete: %d created, %d skipped, %d errors.',
          count($created),
          count($skipped),
          count($errors)
        ),
      ],
    ];
  }

  /**
   * Internal method to find a redirect by source path.
   *
   * @param string $source
   *   The source path (without leading slash).
   * @param string|null $language
   *   Optional language code.
   *
   * @return \Drupal\redirect\Entity\Redirect|null
   *   The redirect entity or NULL if not found.
   */
  protected function findBySourceInternal(string $source, ?string $language = NULL): ?object {
    $storage = $this->entityTypeManager->getStorage('redirect');

    $query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('redirect_source__path', $source);

    if ($language) {
      $query->condition('language', $language);
    }

    $ids = $query->execute();

    if (empty($ids)) {
      return NULL;
    }

    return $storage->load(reset($ids));
  }

  /**
   * Format a redirect entity for output.
   *
   * @param object $redirect
   *   The redirect entity.
   *
   * @return array
   *   Formatted redirect data.
   */
  protected function formatRedirect(object $redirect): array {
    $source = $redirect->getSourceUrl();
    $destination = $redirect->getRedirectUrl();

    return [
      'id' => (int) $redirect->id(),
      'source' => $redirect->getSourcePathWithQuery(),
      'source_url' => $source,
      'destination' => $destination ? $destination->toString() : '',
      'status_code' => $redirect->getStatusCode(),
      'language' => $redirect->language()->getId(),
      'count' => (int) $redirect->getCount(),
      'created' => $redirect->getCreated() ? date('Y-m-d H:i:s', $redirect->getCreated()) : NULL,
    ];
  }

  /**
   * Normalize destination to proper URI format.
   *
   * @param string $destination
   *   The destination path or URL.
   *
   * @return string
   *   Normalized URI.
   */
  protected function normalizeDestination(string $destination): string {
    // If it's already a full URL with scheme, return as-is.
    if (preg_match('#^https?://#', $destination)) {
      return $destination;
    }

    // If it starts with entity: or internal:, return as-is.
    if (preg_match('#^(entity|internal|route|base):#', $destination)) {
      return $destination;
    }

    // For internal paths, use internal: scheme.
    $destination = '/' . ltrim($destination, '/');
    return 'internal:' . $destination;
  }

}
