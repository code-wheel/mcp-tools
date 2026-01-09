<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_moderation\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\content_moderation\StateTransitionValidationInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;

/**
 * Service for content moderation operations.
 */
class ModerationService {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ModerationInformationInterface $moderationInformation,
    protected StateTransitionValidationInterface $stateTransitionValidation,
    protected AccountProxyInterface $currentUser,
    protected TimeInterface $time,
    protected AccessManager $accessManager,
    protected AuditLogger $auditLogger,
  ) {}

  /**
   * List all workflows with their states and transitions.
   *
   * @return array
   *   Workflows data.
   */
  public function listWorkflows(): array {
    $workflowStorage = $this->entityTypeManager->getStorage('workflow');
    $workflows = $workflowStorage->loadMultiple();

    $result = [];
    foreach ($workflows as $workflow) {
      // Only include content moderation workflows.
      if ($workflow->getTypePlugin()->getPluginId() !== 'content_moderation') {
        continue;
      }

      $typePlugin = $workflow->getTypePlugin();
      $states = [];
      foreach ($typePlugin->getStates() as $stateId => $state) {
        $states[$stateId] = [
          'id' => $stateId,
          'label' => $state->label(),
          'published' => $state->isPublishedState(),
          'default_revision' => $state->isDefaultRevisionState(),
        ];
      }

      $transitions = [];
      foreach ($typePlugin->getTransitions() as $transitionId => $transition) {
        $fromStates = [];
        foreach ($transition->from() as $fromState) {
          $fromStates[] = $fromState->id();
        }
        $transitions[$transitionId] = [
          'id' => $transitionId,
          'label' => $transition->label(),
          'from' => $fromStates,
          'to' => $transition->to()->id(),
        ];
      }

      // Get entity types using this workflow.
      $entityTypes = $typePlugin->getEntityTypes();

      $result[] = [
        'id' => $workflow->id(),
        'label' => $workflow->label(),
        'states' => $states,
        'transitions' => $transitions,
        'entity_types' => $entityTypes,
      ];
    }

    return [
      'success' => TRUE,
      'data' => [
        'total' => count($result),
        'workflows' => $result,
      ],
    ];
  }

  /**
   * Get details of a specific workflow.
   *
   * @param string $id
   *   The workflow ID.
   *
   * @return array
   *   Workflow details.
   */
  public function getWorkflow(string $id): array {
    $workflow = $this->entityTypeManager->getStorage('workflow')->load($id);

    if (!$workflow) {
      return ['success' => FALSE, 'error' => "Workflow '$id' not found."];
    }

    $typePlugin = $workflow->getTypePlugin();

    if ($typePlugin->getPluginId() !== 'content_moderation') {
      return ['success' => FALSE, 'error' => "Workflow '$id' is not a content moderation workflow."];
    }

    $states = [];
    foreach ($typePlugin->getStates() as $stateId => $state) {
      $states[$stateId] = [
        'id' => $stateId,
        'label' => $state->label(),
        'published' => $state->isPublishedState(),
        'default_revision' => $state->isDefaultRevisionState(),
        'weight' => $state->weight(),
      ];
    }

    $transitions = [];
    foreach ($typePlugin->getTransitions() as $transitionId => $transition) {
      $fromStates = [];
      foreach ($transition->from() as $fromState) {
        $fromStates[] = $fromState->id();
      }
      $transitions[$transitionId] = [
        'id' => $transitionId,
        'label' => $transition->label(),
        'from' => $fromStates,
        'to' => $transition->to()->id(),
        'weight' => $transition->weight(),
      ];
    }

    // Get entity type bundles configuration.
    $entityTypeBundles = [];
    foreach ($typePlugin->getEntityTypes() as $entityTypeId) {
      $bundles = $typePlugin->getBundlesForEntityType($entityTypeId);
      $entityTypeBundles[$entityTypeId] = $bundles;
    }

    return [
      'success' => TRUE,
      'data' => [
        'id' => $workflow->id(),
        'label' => $workflow->label(),
        'states' => $states,
        'transitions' => $transitions,
        'entity_types' => $entityTypeBundles,
      ],
    ];
  }

  /**
   * Get current moderation state of an entity.
   *
   * @param string $entityType
   *   The entity type ID (e.g., 'node').
   * @param int $entityId
   *   The entity ID.
   *
   * @return array
   *   Moderation state data.
   */
  public function getModerationState(string $entityType, int $entityId): array {
    $storage = $this->entityTypeManager->getStorage($entityType);
    $entity = $storage->load($entityId);

    if (!$entity) {
      return ['success' => FALSE, 'error' => "Entity of type '$entityType' with ID $entityId not found."];
    }

    // Check if entity is moderated.
    if (!$this->moderationInformation->isModeratedEntity($entity)) {
      return ['success' => FALSE, 'error' => "Entity is not under content moderation."];
    }

    // Get the workflow for this entity.
    $workflow = $this->moderationInformation->getWorkflowForEntity($entity);

    if (!$workflow) {
      return ['success' => FALSE, 'error' => "No workflow found for this entity."];
    }

    $currentState = $entity->get('moderation_state')->value;
    $typePlugin = $workflow->getTypePlugin();
    $state = $typePlugin->getState($currentState);

    // Get available transitions.
    $availableTransitions = [];
    $validTransitions = $this->stateTransitionValidation->getValidTransitions($entity, $this->currentUser);
    foreach ($validTransitions as $transition) {
      $availableTransitions[] = [
        'id' => $transition->id(),
        'label' => $transition->label(),
        'to_state' => $transition->to()->id(),
        'to_state_label' => $transition->to()->label(),
      ];
    }

    // Get entity label/title.
    $label = $entity->label();

    return [
      'success' => TRUE,
      'data' => [
        'entity_type' => $entityType,
        'entity_id' => $entityId,
        'label' => $label,
        'bundle' => $entity->bundle(),
        'workflow_id' => $workflow->id(),
        'workflow_label' => $workflow->label(),
        'current_state' => [
          'id' => $currentState,
          'label' => $state->label(),
          'published' => $state->isPublishedState(),
          'default_revision' => $state->isDefaultRevisionState(),
        ],
        'available_transitions' => $availableTransitions,
      ],
    ];
  }

  /**
   * Set moderation state of an entity (creates a new revision).
   *
   * @param string $entityType
   *   The entity type ID.
   * @param int $entityId
   *   The entity ID.
   * @param string $state
   *   The new moderation state.
   * @param string $revisionMessage
   *   Optional revision log message.
   *
   * @return array
   *   Result of the operation.
   */
  public function setModerationState(string $entityType, int $entityId, string $state, string $revisionMessage = ''): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    $storage = $this->entityTypeManager->getStorage($entityType);
    $entity = $storage->load($entityId);

    if (!$entity) {
      return ['success' => FALSE, 'error' => "Entity of type '$entityType' with ID $entityId not found."];
    }

    // Check if entity is moderated.
    if (!$this->moderationInformation->isModeratedEntity($entity)) {
      return ['success' => FALSE, 'error' => "Entity is not under content moderation."];
    }

    // Get the workflow for this entity.
    $workflow = $this->moderationInformation->getWorkflowForEntity($entity);

    if (!$workflow) {
      return ['success' => FALSE, 'error' => "No workflow found for this entity."];
    }

    $typePlugin = $workflow->getTypePlugin();

    // Validate the target state exists.
    if (!$typePlugin->hasState($state)) {
      $availableStates = array_keys($typePlugin->getStates());
      return [
        'success' => FALSE,
        'error' => "State '$state' does not exist in workflow. Available states: " . implode(', ', $availableStates),
      ];
    }

    $currentState = $entity->get('moderation_state')->value;

    // Check if the transition is valid.
    $validTransitions = $this->stateTransitionValidation->getValidTransitions($entity, $this->currentUser);
    $isValidTransition = FALSE;
    foreach ($validTransitions as $transition) {
      if ($transition->to()->id() === $state) {
        $isValidTransition = TRUE;
        break;
      }
    }

    if (!$isValidTransition && $currentState !== $state) {
      $availableTargets = [];
      foreach ($validTransitions as $transition) {
        $availableTargets[] = $transition->to()->id();
      }
      return [
        'success' => FALSE,
        'error' => "Transition from '$currentState' to '$state' is not allowed. Available transitions: " . implode(', ', $availableTargets),
      ];
    }

    // Already in the requested state.
    if ($currentState === $state) {
      return [
        'success' => TRUE,
        'data' => [
          'entity_type' => $entityType,
          'entity_id' => $entityId,
          'label' => $entity->label(),
          'previous_state' => $currentState,
          'new_state' => $state,
          'changed' => FALSE,
          'message' => "Entity is already in state '$state'.",
        ],
      ];
    }

    try {
      $previousState = $currentState;

      // Create a new revision.
      $entity->set('moderation_state', $state);

      if ($entity instanceof RevisionLogInterface) {
        $entity->setNewRevision(TRUE);
        $message = $revisionMessage ?: "Moderation state changed from '$previousState' to '$state' via MCP Tools";
        $entity->setRevisionLogMessage($message);
        $entity->setRevisionCreationTime($this->time->getRequestTime());
        $entity->setRevisionUserId($this->currentUser->id());
      }

      $entity->save();

      $newState = $typePlugin->getState($state);

      $this->auditLogger->logSuccess('set_moderation_state', $entityType, (string) $entityId, [
        'previous_state' => $previousState,
        'new_state' => $state,
        'workflow' => $workflow->id(),
      ]);

      return [
        'success' => TRUE,
        'data' => [
          'entity_type' => $entityType,
          'entity_id' => $entityId,
          'label' => $entity->label(),
          'previous_state' => $previousState,
          'new_state' => $state,
          'new_state_label' => $newState->label(),
          'is_published' => $newState->isPublishedState(),
          'changed' => TRUE,
          'message' => "Moderation state changed from '$previousState' to '$state'.",
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('set_moderation_state', $entityType, (string) $entityId, ['error' => $e->getMessage()]);
      return ['success' => FALSE, 'error' => 'Failed to set moderation state: ' . $e->getMessage()];
    }
  }

  /**
   * Get revision history with moderation states.
   *
   * @param string $entityType
   *   The entity type ID.
   * @param int $entityId
   *   The entity ID.
   * @param int $limit
   *   Maximum number of revisions to return.
   *
   * @return array
   *   Revision history data.
   */
  public function getModerationHistory(string $entityType, int $entityId, int $limit = 50): array {
    $storage = $this->entityTypeManager->getStorage($entityType);
    $entity = $storage->load($entityId);

    if (!$entity) {
      return ['success' => FALSE, 'error' => "Entity of type '$entityType' with ID $entityId not found."];
    }

    // Check if entity is moderated.
    if (!$this->moderationInformation->isModeratedEntity($entity)) {
      return ['success' => FALSE, 'error' => "Entity is not under content moderation."];
    }

    // Get entity type definition.
    $entityTypeDefinition = $this->entityTypeManager->getDefinition($entityType);
    if (!$entityTypeDefinition->isRevisionable()) {
      return ['success' => FALSE, 'error' => "Entity type '$entityType' does not support revisions."];
    }

    // Get the workflow for this entity.
    $workflow = $this->moderationInformation->getWorkflowForEntity($entity);
    $typePlugin = $workflow ? $workflow->getTypePlugin() : NULL;

    // Query revisions.
    $revisionIds = $storage->getQuery()
      ->accessCheck(TRUE)
      ->allRevisions()
      ->condition($entityTypeDefinition->getKey('id'), $entityId)
      ->sort($entityTypeDefinition->getKey('revision'), 'DESC')
      ->range(0, $limit)
      ->execute();

    $revisions = [];
    foreach ($revisionIds as $revisionId => $entityIdResult) {
      $revision = $storage->loadRevision($revisionId);

      if (!$revision) {
        continue;
      }

      $moderationState = NULL;
      $stateLabel = NULL;
      $isPublished = NULL;

      if ($revision->hasField('moderation_state') && !$revision->get('moderation_state')->isEmpty()) {
        $moderationState = $revision->get('moderation_state')->value;
        if ($typePlugin && $moderationState && $typePlugin->hasState($moderationState)) {
          $state = $typePlugin->getState($moderationState);
          $stateLabel = $state->label();
          $isPublished = $state->isPublishedState();
        }
      }

      $revisionData = [
        'revision_id' => $revisionId,
        'moderation_state' => $moderationState,
        'moderation_state_label' => $stateLabel,
        'is_published' => $isPublished,
        'is_current' => $revision->isDefaultRevision(),
      ];

      if ($revision instanceof RevisionLogInterface) {
        $revisionData['revision_log'] = $revision->getRevisionLogMessage();
        $revisionData['revision_created'] = date('Y-m-d H:i:s', $revision->getRevisionCreationTime());
        $revisionData['revision_user'] = $revision->getRevisionUserId();
      }

      $revisions[] = $revisionData;
    }

    return [
      'success' => TRUE,
      'data' => [
        'entity_type' => $entityType,
        'entity_id' => $entityId,
        'label' => $entity->label(),
        'bundle' => $entity->bundle(),
        'workflow_id' => $workflow ? $workflow->id() : NULL,
        'total_revisions' => count($revisions),
        'revisions' => $revisions,
      ],
    ];
  }

  /**
   * Get content in a specific moderation state.
   *
   * @param string $workflowId
   *   The workflow ID.
   * @param string $state
   *   The moderation state.
   * @param int $limit
   *   Maximum number of entities to return.
   *
   * @return array
   *   Content data.
   */
  public function getContentByState(string $workflowId, string $state, int $limit = 50): array {
    $workflow = $this->entityTypeManager->getStorage('workflow')->load($workflowId);

    if (!$workflow) {
      return ['success' => FALSE, 'error' => "Workflow '$workflowId' not found."];
    }

    $typePlugin = $workflow->getTypePlugin();

    if ($typePlugin->getPluginId() !== 'content_moderation') {
      return ['success' => FALSE, 'error' => "Workflow '$workflowId' is not a content moderation workflow."];
    }

    // Validate state exists.
    if (!$typePlugin->hasState($state)) {
      $availableStates = array_keys($typePlugin->getStates());
      return [
        'success' => FALSE,
        'error' => "State '$state' does not exist in workflow. Available states: " . implode(', ', $availableStates),
      ];
    }

    $stateInfo = $typePlugin->getState($state);

    $results = [];
    $totalCount = 0;

    // Get entity types and bundles using this workflow.
    foreach ($typePlugin->getEntityTypes() as $entityTypeId) {
      $bundles = $typePlugin->getBundlesForEntityType($entityTypeId);

      if (empty($bundles)) {
        continue;
      }

      $storage = $this->entityTypeManager->getStorage($entityTypeId);
      $entityTypeDefinition = $this->entityTypeManager->getDefinition($entityTypeId);

      // Query entities in this state.
      $query = $storage->getQuery()
        ->accessCheck(TRUE)
        ->condition('moderation_state', $state);

      if ($bundleKey = $entityTypeDefinition->getKey('bundle')) {
        $query->condition($bundleKey, $bundles, 'IN');
      }

      // Get total count first.
      $countQuery = clone $query;
      $entityCount = $countQuery->count()->execute();
      $totalCount += (int) $entityCount;

      // Get entities with limit.
      $entityIds = $query->range(0, $limit)->execute();
      $entities = $storage->loadMultiple($entityIds);

      foreach ($entities as $entity) {
        $entityData = [
          'entity_type' => $entityTypeId,
          'entity_id' => $entity->id(),
          'bundle' => $entity->bundle(),
          'label' => $entity->label(),
        ];

        // Add URL if available.
        if ($entity->hasLinkTemplate('canonical')) {
          try {
            $entityData['url'] = $entity->toUrl()->toString();
          }
          catch (\Exception $e) {
            // URL generation failed, skip.
          }
        }

        // Add changed time if available.
        if ($entity->hasField('changed')) {
          $changed = $entity->get('changed')->value;
          $entityData['changed'] = $changed ? date('Y-m-d H:i:s', $changed) : NULL;
        }

        $results[] = $entityData;
      }
    }

    return [
      'success' => TRUE,
      'data' => [
        'workflow_id' => $workflowId,
        'workflow_label' => $workflow->label(),
        'state' => $state,
        'state_label' => $stateInfo->label(),
        'state_published' => $stateInfo->isPublishedState(),
        'total' => $totalCount,
        'returned' => count($results),
        'limit' => $limit,
        'content' => $results,
      ],
    ];
  }

}
