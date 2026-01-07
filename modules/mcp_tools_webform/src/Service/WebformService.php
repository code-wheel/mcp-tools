<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_webform\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;

/**
 * Service for webform operations.
 */
class WebformService {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AccessManager $accessManager,
    protected AuditLogger $auditLogger,
  ) {}

  /**
   * List all webforms with submission counts.
   *
   * @return array
   *   Webforms data.
   */
  public function listWebforms(): array {
    $webformStorage = $this->entityTypeManager->getStorage('webform');
    $submissionStorage = $this->entityTypeManager->getStorage('webform_submission');

    $webforms = $webformStorage->loadMultiple();

    $result = [];
    foreach ($webforms as $webform) {
      // Count submissions for this webform.
      $submissionCount = $submissionStorage->getQuery()
        ->accessCheck(TRUE)
        ->condition('webform_id', $webform->id())
        ->count()
        ->execute();

      $result[] = [
        'id' => $webform->id(),
        'title' => $webform->label(),
        'description' => $webform->getDescription(),
        'status' => $webform->isOpen() ? 'open' : 'closed',
        'submission_count' => (int) $submissionCount,
        'created' => $webform->get('created') ? date('Y-m-d H:i:s', $webform->get('created')) : NULL,
        'updated' => $webform->get('updated') ? date('Y-m-d H:i:s', $webform->get('updated')) : NULL,
      ];
    }

    return [
      'success' => TRUE,
      'data' => [
        'total' => count($result),
        'webforms' => $result,
      ],
    ];
  }

  /**
   * Get webform details including elements.
   *
   * @param string $id
   *   The webform ID.
   *
   * @return array
   *   Webform details.
   */
  public function getWebform(string $id): array {
    $webform = $this->entityTypeManager->getStorage('webform')->load($id);

    if (!$webform) {
      return ['success' => FALSE, 'error' => "Webform '$id' not found."];
    }

    $submissionStorage = $this->entityTypeManager->getStorage('webform_submission');
    $submissionCount = $submissionStorage->getQuery()
      ->accessCheck(TRUE)
      ->condition('webform_id', $id)
      ->count()
      ->execute();

    $elements = $webform->getElementsDecoded();
    $processedElements = $this->processElements($elements);

    return [
      'success' => TRUE,
      'data' => [
        'id' => $webform->id(),
        'title' => $webform->label(),
        'description' => $webform->getDescription(),
        'status' => $webform->isOpen() ? 'open' : 'closed',
        'submission_count' => (int) $submissionCount,
        'elements' => $processedElements,
        'settings' => [
          'confirmation_type' => $webform->getSetting('confirmation_type'),
          'confirmation_message' => $webform->getSetting('confirmation_message'),
          'submission_limit' => $webform->getSetting('limit_total'),
          'submission_limit_user' => $webform->getSetting('limit_user'),
        ],
        'created' => $webform->get('created') ? date('Y-m-d H:i:s', $webform->get('created')) : NULL,
        'updated' => $webform->get('updated') ? date('Y-m-d H:i:s', $webform->get('updated')) : NULL,
      ],
    ];
  }

  /**
   * Get webform submissions.
   *
   * @param string $webformId
   *   The webform ID.
   * @param int $limit
   *   Maximum number of submissions to return.
   * @param int $offset
   *   Offset for pagination.
   *
   * @return array
   *   Submissions data.
   */
  public function getSubmissions(string $webformId, int $limit = 50, int $offset = 0): array {
    $webform = $this->entityTypeManager->getStorage('webform')->load($webformId);

    if (!$webform) {
      return ['success' => FALSE, 'error' => "Webform '$webformId' not found."];
    }

    $submissionStorage = $this->entityTypeManager->getStorage('webform_submission');

    // Get total count.
    $totalCount = $submissionStorage->getQuery()
      ->accessCheck(TRUE)
      ->condition('webform_id', $webformId)
      ->count()
      ->execute();

    // Get submissions.
    $sids = $submissionStorage->getQuery()
      ->accessCheck(TRUE)
      ->condition('webform_id', $webformId)
      ->sort('created', 'DESC')
      ->range($offset, $limit)
      ->execute();

    $submissions = $submissionStorage->loadMultiple($sids);

    $result = [];
    foreach ($submissions as $submission) {
      $data = $submission->getData();

      $result[] = [
        'sid' => $submission->id(),
        'uuid' => $submission->uuid(),
        'created' => date('Y-m-d H:i:s', $submission->getCreatedTime()),
        'completed' => $submission->getCompletedTime() ? date('Y-m-d H:i:s', $submission->getCompletedTime()) : NULL,
        'changed' => date('Y-m-d H:i:s', $submission->getChangedTime()),
        'uid' => $submission->getOwnerId(),
        'remote_addr' => $submission->getRemoteAddr(),
        'data' => $data,
      ];
    }

    return [
      'success' => TRUE,
      'data' => [
        'webform_id' => $webformId,
        'webform_title' => $webform->label(),
        'total' => (int) $totalCount,
        'limit' => $limit,
        'offset' => $offset,
        'submissions' => $result,
      ],
    ];
  }

  /**
   * Create a new webform.
   *
   * @param string $id
   *   The webform machine name.
   * @param string $title
   *   The webform title.
   * @param array $elements
   *   The webform elements definition.
   *
   * @return array
   *   Result of the operation.
   */
  public function createWebform(string $id, string $title, array $elements = []): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    // Validate ID format.
    if (!preg_match('/^[a-z][a-z0-9_]*$/', $id)) {
      return [
        'success' => FALSE,
        'error' => "Invalid webform ID '$id'. Must start with a lowercase letter and contain only lowercase letters, numbers, and underscores.",
      ];
    }

    // Check if webform already exists.
    $existing = $this->entityTypeManager->getStorage('webform')->load($id);
    if ($existing) {
      return ['success' => FALSE, 'error' => "Webform with ID '$id' already exists."];
    }

    try {
      $webformData = [
        'id' => $id,
        'title' => $title,
        'status' => 'open',
      ];

      if (!empty($elements)) {
        $webformData['elements'] = $this->encodeElements($elements);
      }

      $webform = $this->entityTypeManager->getStorage('webform')->create($webformData);
      $webform->save();

      $this->auditLogger->logSuccess('create_webform', 'webform', $id, [
        'title' => $title,
        'element_count' => count($elements),
      ]);

      return [
        'success' => TRUE,
        'data' => [
          'id' => $webform->id(),
          'title' => $webform->label(),
          'status' => 'open',
          'message' => "Webform '$title' created successfully.",
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('create_webform', 'webform', $id, ['error' => $e->getMessage()]);
      return ['success' => FALSE, 'error' => 'Failed to create webform: ' . $e->getMessage()];
    }
  }

  /**
   * Update a webform.
   *
   * @param string $id
   *   The webform ID.
   * @param array $updates
   *   The updates to apply.
   *
   * @return array
   *   Result of the operation.
   */
  public function updateWebform(string $id, array $updates): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    $webform = $this->entityTypeManager->getStorage('webform')->load($id);

    if (!$webform) {
      return ['success' => FALSE, 'error' => "Webform '$id' not found."];
    }

    try {
      if (isset($updates['title'])) {
        $webform->set('title', $updates['title']);
      }

      if (isset($updates['description'])) {
        $webform->set('description', $updates['description']);
      }

      if (isset($updates['status'])) {
        $webform->setStatus($updates['status'] === 'open' || $updates['status'] === TRUE);
      }

      if (isset($updates['elements'])) {
        $webform->set('elements', $this->encodeElements($updates['elements']));
      }

      if (isset($updates['settings'])) {
        foreach ($updates['settings'] as $key => $value) {
          $webform->setSetting($key, $value);
        }
      }

      $webform->save();

      $this->auditLogger->logSuccess('update_webform', 'webform', $id, [
        'updates' => array_keys($updates),
      ]);

      return [
        'success' => TRUE,
        'data' => [
          'id' => $webform->id(),
          'title' => $webform->label(),
          'status' => $webform->isOpen() ? 'open' : 'closed',
          'message' => "Webform '$id' updated successfully.",
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('update_webform', 'webform', $id, ['error' => $e->getMessage()]);
      return ['success' => FALSE, 'error' => 'Failed to update webform: ' . $e->getMessage()];
    }
  }

  /**
   * Delete a webform.
   *
   * @param string $id
   *   The webform ID.
   *
   * @return array
   *   Result of the operation.
   */
  public function deleteWebform(string $id): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    $webform = $this->entityTypeManager->getStorage('webform')->load($id);

    if (!$webform) {
      return ['success' => FALSE, 'error' => "Webform '$id' not found."];
    }

    try {
      $title = $webform->label();

      // Count submissions that will be deleted.
      $submissionStorage = $this->entityTypeManager->getStorage('webform_submission');
      $submissionCount = $submissionStorage->getQuery()
        ->accessCheck(TRUE)
        ->condition('webform_id', $id)
        ->count()
        ->execute();

      $webform->delete();

      $this->auditLogger->logSuccess('delete_webform', 'webform', $id, [
        'title' => $title,
        'submissions_deleted' => (int) $submissionCount,
      ]);

      return [
        'success' => TRUE,
        'data' => [
          'id' => $id,
          'title' => $title,
          'submissions_deleted' => (int) $submissionCount,
          'message' => "Webform '$title' and $submissionCount submission(s) deleted successfully.",
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('delete_webform', 'webform', $id, ['error' => $e->getMessage()]);
      return ['success' => FALSE, 'error' => 'Failed to delete webform: ' . $e->getMessage()];
    }
  }

  /**
   * Delete a submission.
   *
   * @param int $sid
   *   The submission ID.
   *
   * @return array
   *   Result of the operation.
   */
  public function deleteSubmission(int $sid): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    $submission = $this->entityTypeManager->getStorage('webform_submission')->load($sid);

    if (!$submission) {
      return ['success' => FALSE, 'error' => "Submission with ID $sid not found."];
    }

    try {
      $webformId = $submission->getWebform()->id();
      $webformTitle = $submission->getWebform()->label();

      $submission->delete();

      $this->auditLogger->logSuccess('delete_submission', 'webform_submission', (string) $sid, [
        'webform_id' => $webformId,
      ]);

      return [
        'success' => TRUE,
        'data' => [
          'sid' => $sid,
          'webform_id' => $webformId,
          'webform_title' => $webformTitle,
          'message' => "Submission $sid deleted successfully.",
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('delete_submission', 'webform_submission', (string) $sid, ['error' => $e->getMessage()]);
      return ['success' => FALSE, 'error' => 'Failed to delete submission: ' . $e->getMessage()];
    }
  }

  /**
   * Process elements for output.
   *
   * @param array $elements
   *   Raw elements array.
   *
   * @return array
   *   Processed elements.
   */
  protected function processElements(array $elements): array {
    $processed = [];

    foreach ($elements as $key => $element) {
      if (str_starts_with($key, '#')) {
        continue;
      }

      $processed[$key] = [
        'type' => $element['#type'] ?? 'unknown',
        'title' => $element['#title'] ?? $key,
        'required' => $element['#required'] ?? FALSE,
        'description' => $element['#description'] ?? NULL,
      ];

      // Add type-specific properties.
      if (isset($element['#options'])) {
        $processed[$key]['options'] = $element['#options'];
      }
      if (isset($element['#default_value'])) {
        $processed[$key]['default_value'] = $element['#default_value'];
      }
      if (isset($element['#placeholder'])) {
        $processed[$key]['placeholder'] = $element['#placeholder'];
      }
    }

    return $processed;
  }

  /**
   * Encode elements array to YAML string.
   *
   * @param array $elements
   *   Elements definition.
   *
   * @return string
   *   YAML encoded elements.
   */
  protected function encodeElements(array $elements): string {
    $yamlElements = [];

    foreach ($elements as $key => $element) {
      $yamlElement = [];

      if (isset($element['type'])) {
        $yamlElement['#type'] = $element['type'];
      }
      if (isset($element['title'])) {
        $yamlElement['#title'] = $element['title'];
      }
      if (isset($element['required'])) {
        $yamlElement['#required'] = (bool) $element['required'];
      }
      if (isset($element['description'])) {
        $yamlElement['#description'] = $element['description'];
      }
      if (isset($element['options'])) {
        $yamlElement['#options'] = $element['options'];
      }
      if (isset($element['default_value'])) {
        $yamlElement['#default_value'] = $element['default_value'];
      }
      if (isset($element['placeholder'])) {
        $yamlElement['#placeholder'] = $element['placeholder'];
      }

      $yamlElements[$key] = $yamlElement;
    }

    return \Drupal\Component\Serialization\Yaml::encode($yamlElements);
  }

}
