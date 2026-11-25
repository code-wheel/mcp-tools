<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_webform\Service;

use Drupal\Component\Serialization\Yaml;
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
   * List the webforms with submission counts, all or some.
   *
   * @param string|null $search
   *   Only webforms with this in their id or title (any case).
   * @param int|null $limit
   *   At most this many.
   *
   * @return array
   *   Webforms data.
   */
  public function listWebforms(?string $search = NULL, ?int $limit = NULL): array {
    if ($limit !== NULL && $limit < 1) {
      return ['success' => FALSE, 'error' => "limit is at least 1, not $limit. Leave it out for every webform."];
    }
    $webformStorage = $this->entityTypeManager->getStorage('webform');
    $submissionStorage = $this->entityTypeManager->getStorage('webform_submission');

    $webforms = $webformStorage->loadMultiple();
    $all = count($webforms);
    $search = $search === NULL || trim($search) === '' ? NULL : mb_strtolower(trim($search));
    if ($search !== NULL) {
      $webforms = array_filter($webforms, static fn($webform): bool => str_contains(mb_strtolower($webform->id() . ' ' . $webform->label()), $search));
    }
    $matching = count($webforms);
    if ($limit !== NULL) {
      $webforms = array_slice($webforms, 0, $limit, TRUE);
    }

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
        'created' => $webform->get('created') ? date('Y-m-d H:i:s', (int) $webform->get('created')) : NULL,
        'updated' => $webform->get('updated') ? date('Y-m-d H:i:s', (int) $webform->get('updated')) : NULL,
      ];
    }

    $data = ['total' => $all, 'webforms' => $result];
    if ($search !== NULL) {
      $data['matching'] = $matching;
    }
    return ['success' => TRUE, 'data' => $data];
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
        'created' => date('Y-m-d H:i:s', (int) $submission->getCreatedTime()),
        'completed' => $submission->getCompletedTime() ? date('Y-m-d H:i:s', (int) $submission->getCompletedTime()) : NULL,
        'changed' => date('Y-m-d H:i:s', (int) $submission->getChangedTime()),
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

    $encoded = $this->elementProperties($elements, '');
    if (is_string($encoded)) {
      return ['success' => FALSE, 'error' => $encoded];
    }

    try {
      $webformData = [
        'id' => $id,
        'title' => $title,
        'status' => 'open',
      ];

      if ($encoded !== []) {
        $webformData['elements'] = Yaml::encode($encoded);
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

      // Webform's own statuses. Any value but exactly "open" closed the form,
      // "Open" and "true" too.
      if (isset($updates['status'])) {
        $status = is_bool($updates['status']) ? ($updates['status'] ? 'open' : 'closed') : strtolower(trim((string) $updates['status']));
        if (!in_array($status, ['open', 'closed', 'scheduled'], TRUE)) {
          return ['success' => FALSE, 'error' => "status is open, closed or scheduled, not '{$updates['status']}'."];
        }
        $webform->setStatus($status);
      }

      if (isset($updates['elements'])) {
        $encoded = $this->elementProperties((array) $updates['elements'], '');
        if (is_string($encoded)) {
          return ['success' => FALSE, 'error' => $encoded];
        }
        $webform->set('elements', Yaml::encode($encoded));
      }

      // One element at a time: a model that had to send all of them back
      // to add one often could not write so large a call.
      if (isset($updates['set_elements']) || isset($updates['remove_elements'])) {
        $elements = $webform->getElementsDecoded();
        foreach ((array) ($updates['remove_elements'] ?? []) as $name) {
          if (!array_key_exists((string) $name, $elements)) {
            return [
              'success' => FALSE,
              'error' => "Webform '$id' has no element '$name'. Its elements: " . implode(', ', array_keys($elements)) . '.',
            ];
          }
          unset($elements[(string) $name]);
        }
        if (isset($updates['set_elements'])) {
          $encoded = $this->elementProperties((array) $updates['set_elements'], '');
          if (is_string($encoded)) {
            return ['success' => FALSE, 'error' => $encoded];
          }
          foreach ($encoded as $name => $element) {
            $elements[$name] = $element;
          }
        }
        $webform->set('elements', Yaml::encode($elements));
      }

      if (isset($updates['settings'])) {
        // A key webform does not have was stored and did nothing: the
        // tool's own example, submission_limit, is not one (limit_total is).
        $known = method_exists($webform, 'getDefaultSettings') ? array_keys($webform::getDefaultSettings()) : NULL;
        if ($known !== NULL) {
          $unknown = array_values(array_diff(array_keys((array) $updates['settings']), $known));
          if ($unknown !== []) {
            // The settings that share the rarest words with what was asked:
            // "limit" says more than "submission", which dozens have.
            $frequency = array_count_values(array_merge(...array_map(static fn($c) => array_unique(explode('_', $c)), $known)));
            $near = [];
            foreach ($unknown as $key) {
              $words = array_filter(explode('_', strtolower((string) $key)));
              $scores = [];
              foreach ($known as $candidate) {
                $shared = array_intersect($words, explode('_', $candidate));
                if ($shared !== []) {
                  $scores[$candidate] = array_sum(array_map(static fn($w) => 1000 / $frequency[$w], $shared)) - levenshtein((string) $key, $candidate);
                }
              }
              arsort($scores);
              $near += array_combine(array_keys(array_slice($scores, 0, 5, TRUE)), array_keys(array_slice($scores, 0, 5, TRUE)));
            }
            return [
              'success' => FALSE,
              'error' => 'Webform has no setting ' . implode(', ', $unknown) . '.' . ($near ? ' Did you mean ' . implode(', ', array_slice($near, 0, 6)) . '?' : '') . ' Nothing was saved.',
            ];
          }
        }
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
    if (!$this->accessManager->canWrite('delete')) {
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
    if (!$this->accessManager->canWrite('delete')) {
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
   * The elements with "#" property keys, as Drupal stores them, or an error.
   *
   * Both ways of writing a property are taken: "#type" as webform's YAML has
   * it, and "type". Before, only plain keys of seven names were read, and an
   * element written with "#" keys, as the tools' descriptions said, was
   * stored empty with "success".
   */
  private function elementProperties(array $elements, string $parent): array|string {
    $out = [];
    foreach ($elements as $key => $element) {
      $name = $parent . $key;
      if (!is_array($element)) {
        return "Element '$name' must be an object, like {\"#type\": \"textfield\", \"#title\": \"Name\"}.";
      }
      $properties = [];
      foreach ($element as $property => $value) {
        $property = (string) $property;
        // A child of a container (a fieldset) is an element of its own.
        if (is_array($value) && (isset($value['#type']) || isset($value['type']))) {
          $child = $this->elementProperties([$property => $value], $name . '.');
          if (is_string($child)) {
            return $child;
          }
          $properties += $child;
          continue;
        }
        $property = str_starts_with($property, '#') ? $property : '#' . $property;
        $properties[$property] = $property === '#required' ? (bool) $value : $value;
      }
      if (!isset($properties['#type'])) {
        return "Element '$name' has no type. Give each element one, like {\"#type\": \"email\", \"#title\": \"Email\"}.";
      }
      $out[$key] = $properties;
    }
    return $out;
  }

}
