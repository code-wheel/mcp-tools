<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_jsonapi\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;

/**
 * Service for JSON:API entity operations via MCP.
 */
class JsonApiService {

  /**
   * Entity types that are always blocked (security-critical).
   */
  protected const ALWAYS_BLOCKED = [
    'user',
    'shortcut',
    'shortcut_set',
  ];

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ResourceTypeRepositoryInterface $resourceTypeRepository,
    protected EntityRepositoryInterface $entityRepository,
    protected ConfigFactoryInterface $configFactory,
    protected AccessManager $accessManager,
    protected AuditLogger $auditLogger,
    protected AccountProxyInterface $currentUser,
  ) {}

  /**
   * Discover available entity types exposed via JSON:API.
   *
   * @return array
   *   List of available entity types with metadata.
   */
  public function discoverTypes(): array {
    $resourceTypes = $this->resourceTypeRepository->all();
    $config = $this->configFactory->get('mcp_tools_jsonapi.settings');
    $allowedTypes = $config->get('allowed_entity_types') ?? [];

    $types = [];
    foreach ($resourceTypes as $resourceType) {
      $entityTypeId = $resourceType->getEntityTypeId();
      $bundle = $resourceType->getBundle();

      // Skip if blocked.
      if ($this->isEntityTypeBlocked($entityTypeId)) {
        continue;
      }

      // Skip if not in allowlist (when allowlist is not empty).
      if (!empty($allowedTypes) && !in_array($entityTypeId, $allowedTypes, TRUE)) {
        continue;
      }

      // Skip internal resource types.
      if ($resourceType->isInternal()) {
        continue;
      }

      $entityType = $this->entityTypeManager->getDefinition($entityTypeId);

      $types[] = [
        'entity_type' => $entityTypeId,
        'bundle' => $bundle,
        'resource_type' => $resourceType->getTypeName(),
        'label' => $entityType->getLabel(),
        'bundle_label' => $bundle !== $entityTypeId ? $this->getBundleLabel($entityTypeId, $bundle) : NULL,
        'supports_revisions' => $entityType->isRevisionable(),
        'supports_translations' => $entityType->isTranslatable(),
      ];
    }

    // Sort by entity type and bundle.
    usort($types, fn($a, $b) =>
      strcmp($a['entity_type'], $b['entity_type']) ?: strcmp($a['bundle'], $b['bundle'])
    );

    return [
      'success' => TRUE,
      'data' => [
        'types' => $types,
        'total' => count($types),
        'note' => 'Use mcp_jsonapi_list_entities or mcp_jsonapi_get_entity for specific entity operations.',
      ],
    ];
  }

  /**
   * Get a single entity by type and UUID.
   *
   * @param string $entityType
   *   Entity type ID.
   * @param string $uuid
   *   Entity UUID.
   * @param string|null $bundle
   *   Optional bundle filter.
   *
   * @return array
   *   Entity data or error.
   */
  public function getEntity(string $entityType, string $uuid, ?string $bundle = NULL): array {
    if ($this->isEntityTypeBlocked($entityType)) {
      return [
        'success' => FALSE,
        'error' => "Entity type '$entityType' is not accessible via JSON:API tools.",
      ];
    }

    try {
      $entity = $this->entityRepository->loadEntityByUuid($entityType, $uuid);

      if (!$entity) {
        return [
          'success' => FALSE,
          'error' => "Entity not found: $entityType with UUID $uuid",
        ];
      }

      if ($bundle && $entity->bundle() !== $bundle) {
        return [
          'success' => FALSE,
          'error' => "Entity exists but is not of bundle '$bundle'.",
        ];
      }

      // Check access.
      if (!$entity->access('view', $this->currentUser)) {
        return [
          'success' => FALSE,
          'error' => 'Access denied to view this entity.',
        ];
      }

      return [
        'success' => TRUE,
        'data' => $this->serializeEntity($entity),
      ];
    }
    catch (\Exception $e) {
      return [
        'success' => FALSE,
        'error' => 'Failed to load entity: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * List entities with optional filters.
   *
   * @param string $entityType
   *   Entity type ID.
   * @param string|null $bundle
   *   Optional bundle filter.
   * @param array $filters
   *   Optional field filters.
   * @param int $limit
   *   Maximum items to return.
   * @param int $offset
   *   Pagination offset.
   *
   * @return array
   *   List of entities or error.
   */
  public function listEntities(string $entityType, ?string $bundle = NULL, array $filters = [], int $limit = 25, int $offset = 0): array {
    if ($this->isEntityTypeBlocked($entityType)) {
      return [
        'success' => FALSE,
        'error' => "Entity type '$entityType' is not accessible via JSON:API tools.",
      ];
    }

    $config = $this->configFactory->get('mcp_tools_jsonapi.settings');
    $maxItems = $config->get('max_items_per_page') ?? 50;
    $limit = min($limit, $maxItems);

    try {
      $storage = $this->entityTypeManager->getStorage($entityType);
      $entityTypeDef = $this->entityTypeManager->getDefinition($entityType);

      $query = $storage->getQuery()
        ->accessCheck(TRUE)
        ->range($offset, $limit);

      // Filter by bundle if specified.
      if ($bundle && $bundleKey = $entityTypeDef->getKey('bundle')) {
        $query->condition($bundleKey, $bundle);
      }

      // Apply field filters.
      foreach ($filters as $field => $value) {
        // Basic sanitization - only allow alphanumeric field names.
        if (!preg_match('/^[a-z_][a-z0-9_]*$/i', $field)) {
          continue;
        }
        $query->condition($field, $value);
      }

      // Sort by ID descending (newest first).
      if ($idKey = $entityTypeDef->getKey('id')) {
        $query->sort($idKey, 'DESC');
      }

      $ids = $query->execute();
      $entities = $storage->loadMultiple($ids);

      $items = [];
      foreach ($entities as $entity) {
        if ($entity instanceof ContentEntityInterface) {
          $items[] = $this->serializeEntity($entity);
        }
      }

      // Get total count.
      $countQuery = $storage->getQuery()->accessCheck(TRUE);
      if ($bundle && $bundleKey = $entityTypeDef->getKey('bundle')) {
        $countQuery->condition($bundleKey, $bundle);
      }
      foreach ($filters as $field => $value) {
        if (preg_match('/^[a-z_][a-z0-9_]*$/i', $field)) {
          $countQuery->condition($field, $value);
        }
      }
      $total = $countQuery->count()->execute();

      return [
        'success' => TRUE,
        'data' => [
          'items' => $items,
          'total' => (int) $total,
          'limit' => $limit,
          'offset' => $offset,
          'has_more' => ($offset + $limit) < $total,
        ],
      ];
    }
    catch (\Exception $e) {
      return [
        'success' => FALSE,
        'error' => 'Failed to list entities: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Create a new entity.
   *
   * @param string $entityType
   *   Entity type ID.
   * @param string $bundle
   *   Bundle/type.
   * @param array $fields
   *   Field values.
   *
   * @return array
   *   Created entity data or error.
   */
  public function createEntity(string $entityType, string $bundle, array $fields): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    $config = $this->configFactory->get('mcp_tools_jsonapi.settings');
    if (!$config->get('allow_write_operations')) {
      return [
        'success' => FALSE,
        'error' => 'Write operations are disabled for JSON:API tools.',
      ];
    }

    if ($this->isEntityTypeBlocked($entityType)) {
      return [
        'success' => FALSE,
        'error' => "Entity type '$entityType' is not accessible via JSON:API tools.",
      ];
    }

    try {
      $storage = $this->entityTypeManager->getStorage($entityType);
      $entityTypeDef = $this->entityTypeManager->getDefinition($entityType);

      // Build entity values with bundle.
      $values = $fields;
      if ($bundleKey = $entityTypeDef->getKey('bundle')) {
        $values[$bundleKey] = $bundle;
      }

      $entity = $storage->create($values);

      // Check create access.
      if (!$entity->access('create', $this->currentUser)) {
        return [
          'success' => FALSE,
          'error' => 'Access denied to create this entity type.',
        ];
      }

      $entity->save();

      $this->auditLogger->logSuccess('jsonapi_create', $entityType, $entity->uuid(), [
        'bundle' => $bundle,
        'id' => $entity->id(),
      ]);

      return [
        'success' => TRUE,
        'data' => $this->serializeEntity($entity),
        'message' => "Created $entityType entity with ID " . $entity->id(),
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('jsonapi_create', $entityType, 'new', [
        'error' => $e->getMessage(),
      ]);
      return [
        'success' => FALSE,
        'error' => 'Failed to create entity: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Update an existing entity.
   *
   * @param string $entityType
   *   Entity type ID.
   * @param string $uuid
   *   Entity UUID.
   * @param array $fields
   *   Field values to update.
   *
   * @return array
   *   Updated entity data or error.
   */
  public function updateEntity(string $entityType, string $uuid, array $fields): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    $config = $this->configFactory->get('mcp_tools_jsonapi.settings');
    if (!$config->get('allow_write_operations')) {
      return [
        'success' => FALSE,
        'error' => 'Write operations are disabled for JSON:API tools.',
      ];
    }

    if ($this->isEntityTypeBlocked($entityType)) {
      return [
        'success' => FALSE,
        'error' => "Entity type '$entityType' is not accessible via JSON:API tools.",
      ];
    }

    try {
      $entity = $this->entityRepository->loadEntityByUuid($entityType, $uuid);

      if (!$entity) {
        return [
          'success' => FALSE,
          'error' => "Entity not found: $entityType with UUID $uuid",
        ];
      }

      // Check update access.
      if (!$entity->access('update', $this->currentUser)) {
        return [
          'success' => FALSE,
          'error' => 'Access denied to update this entity.',
        ];
      }

      // Update fields.
      foreach ($fields as $fieldName => $value) {
        if ($entity->hasField($fieldName)) {
          $entity->set($fieldName, $value);
        }
      }

      $entity->save();

      $this->auditLogger->logSuccess('jsonapi_update', $entityType, $uuid, [
        'fields_updated' => array_keys($fields),
      ]);

      return [
        'success' => TRUE,
        'data' => $this->serializeEntity($entity),
        'message' => "Updated $entityType entity.",
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('jsonapi_update', $entityType, $uuid, [
        'error' => $e->getMessage(),
      ]);
      return [
        'success' => FALSE,
        'error' => 'Failed to update entity: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Delete an entity.
   *
   * @param string $entityType
   *   Entity type ID.
   * @param string $uuid
   *   Entity UUID.
   *
   * @return array
   *   Success status or error.
   */
  public function deleteEntity(string $entityType, string $uuid): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    $config = $this->configFactory->get('mcp_tools_jsonapi.settings');
    if (!$config->get('allow_write_operations')) {
      return [
        'success' => FALSE,
        'error' => 'Write operations are disabled for JSON:API tools.',
      ];
    }

    if ($this->isEntityTypeBlocked($entityType)) {
      return [
        'success' => FALSE,
        'error' => "Entity type '$entityType' is not accessible via JSON:API tools.",
      ];
    }

    try {
      $entity = $this->entityRepository->loadEntityByUuid($entityType, $uuid);

      if (!$entity) {
        return [
          'success' => FALSE,
          'error' => "Entity not found: $entityType with UUID $uuid",
        ];
      }

      // Check delete access.
      if (!$entity->access('delete', $this->currentUser)) {
        return [
          'success' => FALSE,
          'error' => 'Access denied to delete this entity.',
        ];
      }

      $id = $entity->id();
      $label = $entity->label();
      $entity->delete();

      $this->auditLogger->logSuccess('jsonapi_delete', $entityType, $uuid, [
        'id' => $id,
        'label' => $label,
      ]);

      return [
        'success' => TRUE,
        'data' => [
          'deleted' => TRUE,
          'entity_type' => $entityType,
          'uuid' => $uuid,
          'id' => $id,
          'label' => $label,
        ],
        'message' => "Deleted $entityType entity: $label",
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('jsonapi_delete', $entityType, $uuid, [
        'error' => $e->getMessage(),
      ]);
      return [
        'success' => FALSE,
        'error' => 'Failed to delete entity: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Check if an entity type is blocked.
   *
   * @param string $entityType
   *   Entity type ID.
   *
   * @return bool
   *   TRUE if blocked.
   */
  protected function isEntityTypeBlocked(string $entityType): bool {
    // Always block security-critical types.
    if (in_array($entityType, self::ALWAYS_BLOCKED, TRUE)) {
      return TRUE;
    }

    $config = $this->configFactory->get('mcp_tools_jsonapi.settings');
    $blockedTypes = $config->get('blocked_entity_types') ?? [];

    return in_array($entityType, $blockedTypes, TRUE);
  }

  /**
   * Serialize an entity to a simple array.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   *
   * @return array
   *   Serialized entity data.
   */
  protected function serializeEntity(ContentEntityInterface $entity): array {
    $data = [
      'entity_type' => $entity->getEntityTypeId(),
      'bundle' => $entity->bundle(),
      'id' => $entity->id(),
      'uuid' => $entity->uuid(),
      'label' => $entity->label(),
    ];

    // Add common fields.
    if ($entity->hasField('status')) {
      $data['status'] = (bool) $entity->get('status')->value;
    }
    if ($entity->hasField('created')) {
      $data['created'] = $entity->get('created')->value;
    }
    if ($entity->hasField('changed')) {
      $data['changed'] = $entity->get('changed')->value;
    }

    // Add all field values (simplified).
    $config = $this->configFactory->get('mcp_tools_jsonapi.settings');
    $includeRelationships = $config->get('include_relationships') ?? FALSE;

    foreach ($entity->getFields() as $fieldName => $field) {
      // Skip base fields we already included.
      if (in_array($fieldName, [
        'uuid', 'id', 'type', 'bundle', 'status',
        'created', 'changed', 'langcode', 'default_langcode',
      ])) {
        continue;
      }

      $fieldDef = $field->getFieldDefinition();
      $fieldType = $fieldDef->getType();

      // Skip entity reference fields unless configured to include.
      if (!$includeRelationships && str_starts_with($fieldType, 'entity_reference')) {
        continue;
      }

      // Get field value.
      $value = $field->getValue();
      if (!empty($value)) {
        // Simplify single-value fields.
        if (count($value) === 1 && isset($value[0]['value'])) {
          $data['fields'][$fieldName] = $value[0]['value'];
        }
        elseif (count($value) === 1 && isset($value[0]['target_id'])) {
          $data['fields'][$fieldName] = $value[0]['target_id'];
        }
        else {
          $data['fields'][$fieldName] = $value;
        }
      }
    }

    return $data;
  }

  /**
   * Get bundle label.
   *
   * @param string $entityType
   *   Entity type ID.
   * @param string $bundle
   *   Bundle ID.
   *
   * @return string|null
   *   Bundle label or NULL.
   */
  protected function getBundleLabel(string $entityType, string $bundle): ?string {
    try {
      $entityTypeDef = $this->entityTypeManager->getDefinition($entityType);
      $bundleEntityType = $entityTypeDef->getBundleEntityType();
      if ($bundleEntityType) {
        $bundleEntity = $this->entityTypeManager->getStorage($bundleEntityType)->load($bundle);
        if ($bundleEntity) {
          return (string) $bundleEntity->label();
        }
      }
    }
    catch (\Exception $e) {
      // Ignore.
    }
    return NULL;
  }

}
