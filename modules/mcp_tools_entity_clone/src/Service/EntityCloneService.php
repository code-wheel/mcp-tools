<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_entity_clone\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;

/**
 * Service for entity clone operations.
 */
class EntityCloneService {

  /**
   * Entity types that support cloning.
   */
  protected const CLONEABLE_ENTITY_TYPES = [
    'node',
    'media',
    'paragraph',
    'taxonomy_term',
    'block_content',
    'menu_link_content',
  ];

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityFieldManagerInterface $entityFieldManager,
    protected ConfigFactoryInterface $configFactory,
    protected AccessManager $accessManager,
    protected AuditLogger $auditLogger,
  ) {}

  /**
   * Clone a single entity.
   *
   * @param string $entityType
   *   The entity type ID (e.g., 'node', 'media').
   * @param string|int $entityId
   *   The entity ID to clone.
   * @param array $options
   *   Clone options:
   *   - title_prefix: Prefix to add to the cloned entity title.
   *   - title_suffix: Suffix to add to the cloned entity title.
   *   - clone_children: Clone child paragraphs for nodes (default: TRUE).
   *
   * @return array
   *   Result with success status and cloned entity data.
   */
  public function cloneEntity(string $entityType, string|int $entityId, array $options = []): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    // Validate entity type.
    if (!in_array($entityType, self::CLONEABLE_ENTITY_TYPES, TRUE)) {
      return [
        'success' => FALSE,
        'error' => "Entity type '$entityType' is not supported for cloning.",
        'supported_types' => self::CLONEABLE_ENTITY_TYPES,
      ];
    }

    // Load the source entity.
    $storage = $this->entityTypeManager->getStorage($entityType);
    $entity = $storage->load($entityId);

    if (!$entity) {
      return [
        'success' => FALSE,
        'error' => "$entityType with ID '$entityId' not found.",
      ];
    }

    try {
      // Clone the entity.
      $clone = $entity->createDuplicate();

      // Apply title modifications.
      $labelKey = $entity->getEntityType()->getKey('label');
      if ($labelKey && $clone->hasField($labelKey)) {
        $originalLabel = $entity->label();
        $newLabel = ($options['title_prefix'] ?? '') . $originalLabel . ($options['title_suffix'] ?? '');

        // If no prefix/suffix provided, add default " (Clone)" suffix.
        if (empty($options['title_prefix']) && empty($options['title_suffix'])) {
          $newLabel = $originalLabel . ' (Clone)';
        }

        $clone->set($labelKey, $newLabel);
      }

      // Handle paragraph cloning for nodes.
      $cloneChildren = $options['clone_children'] ?? TRUE;
      if ($cloneChildren && $entity instanceof FieldableEntityInterface) {
        $this->cloneChildParagraphs($clone);
      }

      // Set as unpublished if applicable.
      if ($clone->hasField('status')) {
        $clone->set('status', 0);
      }

      $clone->save();

      $this->auditLogger->logSuccess('clone_entity', $entityType, (string) $clone->id(), [
        'source_id' => (string) $entityId,
        'options' => $options,
      ]);

      return [
        'success' => TRUE,
        'data' => [
          'entity_type' => $entityType,
          'source_id' => $entityId,
          'clone_id' => $clone->id(),
          'clone_uuid' => $clone->uuid(),
          'label' => $clone->label(),
          'status' => $clone->hasField('status') ? ($clone->get('status')->value ? 'published' : 'unpublished') : 'N/A',
          'message' => "Successfully cloned $entityType '$entityId' to new entity '" . $clone->id() . "'.",
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('clone_entity', $entityType, (string) $entityId, [
        'error' => $e->getMessage(),
      ]);

      return [
        'success' => FALSE,
        'error' => "Failed to clone entity: " . $e->getMessage(),
      ];
    }
  }

  /**
   * Clone entity with specified referenced entities.
   *
   * @param string $entityType
   *   The entity type ID.
   * @param string|int $entityId
   *   The entity ID to clone.
   * @param array $referenceFields
   *   List of reference field names to also clone (empty = clone main only).
   *
   * @return array
   *   Result with success status and cloned entities data.
   */
  public function cloneWithReferences(string $entityType, string|int $entityId, array $referenceFields = []): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    // Validate entity type.
    if (!in_array($entityType, self::CLONEABLE_ENTITY_TYPES, TRUE)) {
      return [
        'success' => FALSE,
        'error' => "Entity type '$entityType' is not supported for cloning.",
        'supported_types' => self::CLONEABLE_ENTITY_TYPES,
      ];
    }

    // Load the source entity.
    $storage = $this->entityTypeManager->getStorage($entityType);
    $entity = $storage->load($entityId);

    if (!$entity) {
      return [
        'success' => FALSE,
        'error' => "$entityType with ID '$entityId' not found.",
      ];
    }

    if (!$entity instanceof FieldableEntityInterface) {
      return [
        'success' => FALSE,
        'error' => "Entity type '$entityType' does not support field references.",
      ];
    }

    try {
      $clonedReferences = [];
      $referenceMapping = [];

      // First, clone all referenced entities.
      foreach ($referenceFields as $fieldName) {
        if (!$entity->hasField($fieldName)) {
          continue;
        }

        $field = $entity->get($fieldName);
        $fieldDefinition = $field->getFieldDefinition();
        $fieldType = $fieldDefinition->getType();

        // Only process entity reference fields.
        if (!in_array($fieldType, ['entity_reference', 'entity_reference_revisions'], TRUE)) {
          continue;
        }

        $targetType = $fieldDefinition->getSetting('target_type');

        foreach ($field->referencedEntities() as $referencedEntity) {
          $refId = $referencedEntity->id();
          $refKey = "$targetType:$refId";

          // Skip if already cloned.
          if (isset($referenceMapping[$refKey])) {
            continue;
          }

          // Clone the referenced entity.
          $refClone = $referencedEntity->createDuplicate();

          // Handle nested paragraphs.
          if ($refClone instanceof FieldableEntityInterface) {
            $this->cloneChildParagraphs($refClone);
          }

          $refClone->save();

          $referenceMapping[$refKey] = $refClone->id();
          $clonedReferences[] = [
            'entity_type' => $targetType,
            'source_id' => $refId,
            'clone_id' => $refClone->id(),
            'label' => $refClone->label() ?? "Entity $refId",
          ];
        }
      }

      // Now clone the main entity.
      $clone = $entity->createDuplicate();

      // Update the label.
      $labelKey = $entity->getEntityType()->getKey('label');
      if ($labelKey && $clone->hasField($labelKey)) {
        $clone->set($labelKey, $entity->label() . ' (Clone)');
      }

      // Update reference fields to point to cloned entities.
      foreach ($referenceFields as $fieldName) {
        if (!$clone->hasField($fieldName)) {
          continue;
        }

        $field = $clone->get($fieldName);
        $fieldDefinition = $field->getFieldDefinition();
        $fieldType = $fieldDefinition->getType();

        if (!in_array($fieldType, ['entity_reference', 'entity_reference_revisions'], TRUE)) {
          continue;
        }

        $targetType = $fieldDefinition->getSetting('target_type');
        $newValues = [];

        foreach ($entity->get($fieldName) as $item) {
          $targetId = $item->target_id;
          $refKey = "$targetType:$targetId";

          if (isset($referenceMapping[$refKey])) {
            $newValues[] = ['target_id' => $referenceMapping[$refKey]];
          }
          else {
            // Keep original reference if not cloned.
            $newValues[] = ['target_id' => $targetId];
          }
        }

        $clone->set($fieldName, $newValues);
      }

      // Handle child paragraphs not in reference fields.
      $this->cloneChildParagraphs($clone, $referenceFields);

      // Set as unpublished if applicable.
      if ($clone->hasField('status')) {
        $clone->set('status', 0);
      }

      $clone->save();

      $this->auditLogger->logSuccess('clone_with_references', $entityType, (string) $clone->id(), [
        'source_id' => (string) $entityId,
        'reference_fields' => $referenceFields,
        'cloned_references_count' => count($clonedReferences),
      ]);

      return [
        'success' => TRUE,
        'data' => [
          'entity_type' => $entityType,
          'source_id' => $entityId,
          'clone_id' => $clone->id(),
          'clone_uuid' => $clone->uuid(),
          'label' => $clone->label(),
          'cloned_references' => $clonedReferences,
          'message' => "Successfully cloned $entityType with " . count($clonedReferences) . " referenced entities.",
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('clone_with_references', $entityType, (string) $entityId, [
        'error' => $e->getMessage(),
      ]);

      return [
        'success' => FALSE,
        'error' => "Failed to clone entity with references: " . $e->getMessage(),
      ];
    }
  }

  /**
   * Get list of entity types that can be cloned.
   *
   * @return array
   *   Result with list of cloneable entity types.
   */
  public function getCloneableTypes(): array {
    $types = [];

    foreach (self::CLONEABLE_ENTITY_TYPES as $entityTypeId) {
      try {
        $entityType = $this->entityTypeManager->getDefinition($entityTypeId, FALSE);
        if (!$entityType) {
          continue;
        }

        $bundles = $this->entityTypeManager->getStorage($entityType->getBundleEntityType() ?? $entityTypeId)
          ->loadMultiple();

        $bundleInfo = [];
        if ($entityType->getBundleEntityType()) {
          foreach ($bundles as $bundle) {
            $bundleInfo[] = [
              'id' => $bundle->id(),
              'label' => $bundle->label(),
            ];
          }
        }
        else {
          $bundleInfo[] = [
            'id' => $entityTypeId,
            'label' => $entityType->getLabel(),
          ];
        }

        $types[] = [
          'entity_type' => $entityTypeId,
          'label' => (string) $entityType->getLabel(),
          'has_bundles' => !empty($entityType->getBundleEntityType()),
          'bundles' => $bundleInfo,
        ];
      }
      catch (\Exception $e) {
        // Skip entity types that don't exist on this site.
        continue;
      }
    }

    return [
      'success' => TRUE,
      'data' => [
        'types' => $types,
        'total' => count($types),
      ],
    ];
  }

  /**
   * Get clone settings for a specific entity type and bundle.
   *
   * @param string $entityType
   *   The entity type ID.
   * @param string $bundle
   *   The bundle machine name.
   *
   * @return array
   *   Clone settings for the bundle.
   */
  public function getCloneSettings(string $entityType, string $bundle): array {
    // Load Entity Clone configuration if available.
    $config = $this->configFactory->get("entity_clone.settings.$entityType.$bundle");
    $defaultConfig = $this->configFactory->get('entity_clone.settings');

    // Get field definitions to identify reference fields.
    $fieldDefinitions = $this->entityFieldManager->getFieldDefinitions($entityType, $bundle);
    $referenceFields = [];
    $paragraphFields = [];

    foreach ($fieldDefinitions as $fieldName => $definition) {
      if (!str_starts_with($fieldName, 'field_')) {
        continue;
      }

      $fieldType = $definition->getType();

      if (in_array($fieldType, ['entity_reference', 'entity_reference_revisions'], TRUE)) {
        $targetType = $definition->getSetting('target_type');
        $fieldInfo = [
          'name' => $fieldName,
          'label' => (string) $definition->getLabel(),
          'target_type' => $targetType,
          'cardinality' => $definition->getFieldStorageDefinition()->getCardinality(),
        ];

        if ($targetType === 'paragraph') {
          $paragraphFields[] = $fieldInfo;
        }
        else {
          $referenceFields[] = $fieldInfo;
        }
      }
    }

    // Determine clone behavior from config or defaults.
    $cloneSettings = [
      'take_ownership' => $config->get('take_ownership') ?? $defaultConfig->get('take_ownership') ?? TRUE,
      'no_suffix' => $config->get('no_suffix') ?? $defaultConfig->get('no_suffix') ?? FALSE,
      'default_suffix' => ' (Clone)',
    ];

    return [
      'success' => TRUE,
      'data' => [
        'entity_type' => $entityType,
        'bundle' => $bundle,
        'settings' => $cloneSettings,
        'reference_fields' => $referenceFields,
        'paragraph_fields' => $paragraphFields,
        'has_paragraphs' => !empty($paragraphFields),
        'message' => "Clone settings for $entityType.$bundle retrieved.",
      ],
    ];
  }

  /**
   * Clone child paragraphs within an entity.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity being cloned.
   * @param array $excludeFields
   *   Fields to exclude from paragraph cloning.
   */
  protected function cloneChildParagraphs(FieldableEntityInterface $entity, array $excludeFields = []): void {
    $fieldDefinitions = $entity->getFieldDefinitions();

    foreach ($fieldDefinitions as $fieldName => $definition) {
      // Skip excluded fields.
      if (in_array($fieldName, $excludeFields, TRUE)) {
        continue;
      }

      $fieldType = $definition->getType();

      // Handle entity reference revisions (paragraphs).
      if ($fieldType === 'entity_reference_revisions') {
        $targetType = $definition->getSetting('target_type');

        if ($targetType === 'paragraph') {
          $newParagraphs = [];

          foreach ($entity->get($fieldName) as $item) {
            if ($item->entity) {
              $paragraphClone = $item->entity->createDuplicate();

              // Recursively clone nested paragraphs.
              if ($paragraphClone instanceof FieldableEntityInterface) {
                $this->cloneChildParagraphs($paragraphClone);
              }

              $newParagraphs[] = $paragraphClone;
            }
          }

          $entity->set($fieldName, $newParagraphs);
        }
      }
    }
  }

}
