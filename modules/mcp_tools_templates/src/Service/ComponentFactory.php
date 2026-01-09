<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_templates\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Factory service for creating Drupal configuration entities from templates.
 *
 * Handles the creation of vocabularies, roles, content types, fields,
 * media types, webforms, and views from template definitions.
 */
class ComponentFactory {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected LoggerInterface $logger,
  ) {}

  /**
   * Create vocabularies from template definition.
   *
   * @param array $vocabularies
   *   Vocabulary definitions keyed by machine name.
   * @param bool $skipExisting
   *   Whether to skip existing vocabularies.
   *
   * @return array
   *   Result with 'created', 'skipped', and 'errors' arrays.
   */
  public function createVocabularies(array $vocabularies, bool $skipExisting): array {
    $created = [];
    $skipped = [];
    $errors = [];

    $storage = $this->entityTypeManager->getStorage('taxonomy_vocabulary');

    foreach ($vocabularies as $vocabId => $config) {
      try {
        $existing = $storage->load($vocabId);
        if ($existing) {
          if ($skipExisting) {
            $skipped[] = ['type' => 'vocabulary', 'id' => $vocabId, 'label' => $config['label']];
            continue;
          }
        }

        $vocab = $storage->create([
          'vid' => $vocabId,
          'name' => $config['label'],
          'description' => $config['description'] ?? '',
        ]);
        $vocab->save();

        $created[] = ['type' => 'vocabulary', 'id' => $vocabId, 'label' => $config['label']];
      }
      catch (\Exception $e) {
        $errors[] = ['type' => 'vocabulary', 'id' => $vocabId, 'error' => $e->getMessage()];
      }
    }

    return ['created' => $created, 'skipped' => $skipped, 'errors' => $errors];
  }

  /**
   * Create roles from template definition.
   *
   * @param array $roles
   *   Role definitions keyed by machine name.
   * @param bool $skipExisting
   *   Whether to skip existing roles.
   *
   * @return array
   *   Result with 'created', 'skipped', and 'errors' arrays.
   */
  public function createRoles(array $roles, bool $skipExisting): array {
    $created = [];
    $skipped = [];
    $errors = [];

    $storage = $this->entityTypeManager->getStorage('user_role');

    foreach ($roles as $roleId => $config) {
      try {
        $existing = $storage->load($roleId);
        if ($existing) {
          if ($skipExisting) {
            $skipped[] = ['type' => 'role', 'id' => $roleId, 'label' => $config['label']];
            continue;
          }
        }

        $role = $storage->create([
          'id' => $roleId,
          'label' => $config['label'],
        ]);
        $role->save();

        // Grant permissions.
        if (!empty($config['permissions'])) {
          foreach ($config['permissions'] as $permission) {
            $role->grantPermission($permission);
          }
          $role->save();
        }

        $created[] = ['type' => 'role', 'id' => $roleId, 'label' => $config['label']];
      }
      catch (\Exception $e) {
        $errors[] = ['type' => 'role', 'id' => $roleId, 'error' => $e->getMessage()];
      }
    }

    return ['created' => $created, 'skipped' => $skipped, 'errors' => $errors];
  }

  /**
   * Create content types from template definition.
   *
   * @param array $contentTypes
   *   Content type definitions keyed by machine name.
   * @param bool $skipExisting
   *   Whether to skip existing content types.
   *
   * @return array
   *   Result with 'created', 'skipped', and 'errors' arrays.
   */
  public function createContentTypes(array $contentTypes, bool $skipExisting): array {
    $created = [];
    $skipped = [];
    $errors = [];

    $nodeTypeStorage = $this->entityTypeManager->getStorage('node_type');

    foreach ($contentTypes as $typeId => $config) {
      try {
        $existing = $nodeTypeStorage->load($typeId);
        if ($existing) {
          if ($skipExisting) {
            $skipped[] = ['type' => 'content_type', 'id' => $typeId, 'label' => $config['label']];
            continue;
          }
        }

        // Create content type.
        $nodeType = $nodeTypeStorage->create([
          'type' => $typeId,
          'name' => $config['label'],
          'description' => $config['description'] ?? '',
        ]);
        $nodeType->save();

        // Create fields.
        if (!empty($config['fields'])) {
          $this->createFields('node', $typeId, $config['fields']);
        }

        $created[] = ['type' => 'content_type', 'id' => $typeId, 'label' => $config['label']];
      }
      catch (\Exception $e) {
        $errors[] = ['type' => 'content_type', 'id' => $typeId, 'error' => $e->getMessage()];
      }
    }

    return ['created' => $created, 'skipped' => $skipped, 'errors' => $errors];
  }

  /**
   * Create fields for an entity bundle.
   *
   * @param string $entityType
   *   The entity type.
   * @param string $bundle
   *   The bundle.
   * @param array $fields
   *   Field definitions.
   */
  public function createFields(string $entityType, string $bundle, array $fields): void {
    $fieldStorageStorage = $this->entityTypeManager->getStorage('field_storage_config');
    $fieldConfigStorage = $this->entityTypeManager->getStorage('field_config');

    foreach ($fields as $fieldName => $fieldConfig) {
      // Check if storage exists.
      $storageId = $entityType . '.' . $fieldName;
      $storage = $fieldStorageStorage->load($storageId);

      if (!$storage) {
        // Create field storage.
        $storageConfig = [
          'field_name' => $fieldName,
          'entity_type' => $entityType,
          'type' => $fieldConfig['type'],
          'cardinality' => $fieldConfig['cardinality'] ?? 1,
        ];

        // Handle entity reference target type.
        if ($fieldConfig['type'] === 'entity_reference' && !empty($fieldConfig['target'])) {
          $targetParts = explode(':', $fieldConfig['target']);
          $storageConfig['settings'] = [
            'target_type' => $targetParts[0] === 'taxonomy_term' ? 'taxonomy_term' : ($targetParts[0] === 'node' ? 'node' : $targetParts[0]),
          ];
        }

        // Handle list fields.
        if ($fieldConfig['type'] === 'list_string' && !empty($fieldConfig['allowed_values'])) {
          $allowedValues = [];
          foreach ($fieldConfig['allowed_values'] as $value) {
            $allowedValues[$value] = $value;
          }
          $storageConfig['settings'] = ['allowed_values' => $allowedValues];
        }

        $storage = $fieldStorageStorage->create($storageConfig);
        $storage->save();
      }

      // Create field config for this bundle.
      $fieldId = $entityType . '.' . $bundle . '.' . $fieldName;
      $existingField = $fieldConfigStorage->load($fieldId);

      if (!$existingField) {
        $configData = [
          'field_name' => $fieldName,
          'entity_type' => $entityType,
          'bundle' => $bundle,
          'label' => $fieldConfig['label'],
        ];

        // Handle entity reference handler settings.
        if ($fieldConfig['type'] === 'entity_reference' && !empty($fieldConfig['target'])) {
          $targetParts = explode(':', $fieldConfig['target']);
          $targetType = $targetParts[0];
          $targetBundle = $targetParts[1] ?? NULL;

          if ($targetType === 'taxonomy_term' && $targetBundle) {
            $configData['settings'] = [
              'handler' => 'default:taxonomy_term',
              'handler_settings' => [
                'target_bundles' => [$targetBundle => $targetBundle],
              ],
            ];
          }
          elseif ($targetType === 'node' && $targetBundle) {
            $configData['settings'] = [
              'handler' => 'default:node',
              'handler_settings' => [
                'target_bundles' => [$targetBundle => $targetBundle],
              ],
            ];
          }
        }

        $field = $fieldConfigStorage->create($configData);
        $field->save();

        // Configure form and view display.
        $this->configureFieldDisplay($entityType, $bundle, $fieldName, $fieldConfig);
      }
    }
  }

  /**
   * Configure field display for form and view modes.
   *
   * @param string $entityType
   *   The entity type.
   * @param string $bundle
   *   The bundle.
   * @param string $fieldName
   *   The field name.
   * @param array $fieldConfig
   *   The field configuration.
   */
  public function configureFieldDisplay(string $entityType, string $bundle, string $fieldName, array $fieldConfig): void {
    try {
      // Configure form display.
      $formDisplayStorage = $this->entityTypeManager->getStorage('entity_form_display');
      $formDisplayId = $entityType . '.' . $bundle . '.default';
      $formDisplay = $formDisplayStorage->load($formDisplayId);

      if (!$formDisplay) {
        $formDisplay = $formDisplayStorage->create([
          'targetEntityType' => $entityType,
          'bundle' => $bundle,
          'mode' => 'default',
          'status' => TRUE,
        ]);
      }

      $formDisplay->setComponent($fieldName, [
        'weight' => 10,
      ]);
      $formDisplay->save();

      // Configure view display.
      $viewDisplayStorage = $this->entityTypeManager->getStorage('entity_view_display');
      $viewDisplayId = $entityType . '.' . $bundle . '.default';
      $viewDisplay = $viewDisplayStorage->load($viewDisplayId);

      if (!$viewDisplay) {
        $viewDisplay = $viewDisplayStorage->create([
          'targetEntityType' => $entityType,
          'bundle' => $bundle,
          'mode' => 'default',
          'status' => TRUE,
        ]);
      }

      $viewDisplay->setComponent($fieldName, [
        'weight' => 10,
        'label' => 'above',
      ]);
      $viewDisplay->save();
    }
    catch (\Exception $e) {
      // Display configuration is non-critical.
      $this->logger->warning(
        'Failed to configure display for field @field: @error',
        ['@field' => $fieldName, '@error' => $e->getMessage()]
      );
    }
  }

  /**
   * Create media types from template definition.
   *
   * @param array $mediaTypes
   *   Media type definitions keyed by machine name.
   * @param bool $skipExisting
   *   Whether to skip existing media types.
   *
   * @return array
   *   Result with 'created', 'skipped', and 'errors' arrays.
   */
  public function createMediaTypes(array $mediaTypes, bool $skipExisting): array {
    $created = [];
    $skipped = [];
    $errors = [];

    try {
      $storage = $this->entityTypeManager->getStorage('media_type');
    }
    catch (\Exception $e) {
      $errors[] = ['type' => 'media_type', 'id' => '*', 'error' => 'Media module not installed.'];
      return ['created' => $created, 'skipped' => $skipped, 'errors' => $errors];
    }

    foreach ($mediaTypes as $typeId => $config) {
      try {
        $existing = $storage->load($typeId);
        if ($existing) {
          if ($skipExisting) {
            $skipped[] = ['type' => 'media_type', 'id' => $typeId, 'label' => $config['label']];
            continue;
          }
        }

        $mediaType = $storage->create([
          'id' => $typeId,
          'label' => $config['label'],
          'description' => $config['description'] ?? '',
          'source' => $config['source'] ?? 'image',
        ]);
        $mediaType->save();

        $created[] = ['type' => 'media_type', 'id' => $typeId, 'label' => $config['label']];
      }
      catch (\Exception $e) {
        $errors[] = ['type' => 'media_type', 'id' => $typeId, 'error' => $e->getMessage()];
      }
    }

    return ['created' => $created, 'skipped' => $skipped, 'errors' => $errors];
  }

  /**
   * Create webforms from template definition.
   *
   * @param array $webforms
   *   Webform definitions keyed by machine name.
   * @param bool $skipExisting
   *   Whether to skip existing webforms.
   *
   * @return array
   *   Result with 'created', 'skipped', and 'errors' arrays.
   */
  public function createWebforms(array $webforms, bool $skipExisting): array {
    $created = [];
    $skipped = [];
    $errors = [];

    try {
      $storage = $this->entityTypeManager->getStorage('webform');
    }
    catch (\Exception $e) {
      $errors[] = ['type' => 'webform', 'id' => '*', 'error' => 'Webform module not installed.'];
      return ['created' => $created, 'skipped' => $skipped, 'errors' => $errors];
    }

    foreach ($webforms as $webformId => $config) {
      try {
        $existing = $storage->load($webformId);
        if ($existing) {
          if ($skipExisting) {
            $skipped[] = ['type' => 'webform', 'id' => $webformId, 'label' => $config['label']];
            continue;
          }
        }

        // Build elements YAML.
        $elements = [];
        foreach ($config['elements'] ?? [] as $elementId => $elementConfig) {
          $elements[$elementId] = [
            '#type' => $elementConfig['type'],
            '#title' => $elementConfig['title'],
          ];
          if (!empty($elementConfig['required'])) {
            $elements[$elementId]['#required'] = TRUE;
          }
        }

        $webform = $storage->create([
          'id' => $webformId,
          'title' => $config['label'],
          'description' => $config['description'] ?? '',
          'elements' => \Symfony\Component\Yaml\Yaml::dump($elements),
          'status' => 'open',
        ]);
        $webform->save();

        $created[] = ['type' => 'webform', 'id' => $webformId, 'label' => $config['label']];
      }
      catch (\Exception $e) {
        $errors[] = ['type' => 'webform', 'id' => $webformId, 'error' => $e->getMessage()];
      }
    }

    return ['created' => $created, 'skipped' => $skipped, 'errors' => $errors];
  }

  /**
   * Create views from template definition.
   *
   * @param array $views
   *   View definitions keyed by machine name.
   * @param bool $skipExisting
   *   Whether to skip existing views.
   *
   * @return array
   *   Result with 'created', 'skipped', and 'errors' arrays.
   */
  public function createViews(array $views, bool $skipExisting): array {
    $created = [];
    $skipped = [];
    $errors = [];

    $storage = $this->entityTypeManager->getStorage('view');

    foreach ($views as $viewId => $config) {
      try {
        $existing = $storage->load($viewId);
        if ($existing) {
          if ($skipExisting) {
            $skipped[] = ['type' => 'view', 'id' => $viewId, 'label' => $config['label']];
            continue;
          }
        }

        // Build view configuration.
        $viewConfig = [
          'id' => $viewId,
          'label' => $config['label'],
          'description' => $config['description'] ?? '',
          'base_table' => $config['base_table'] ?? 'node_field_data',
          'display' => [
            'default' => [
              'display_plugin' => 'default',
              'id' => 'default',
              'display_title' => 'Default',
              'position' => 0,
              'display_options' => [
                'title' => $config['label'],
                'pager' => [
                  'type' => 'some',
                  'options' => [
                    'items_per_page' => $config['pager'] ?? 10,
                  ],
                ],
                'style' => [
                  'type' => $config['style'] ?? 'default',
                ],
                'row' => [
                  'type' => 'fields',
                ],
              ],
            ],
          ],
        ];

        // Add page display if configured.
        if (!empty($config['display']['page'])) {
          $viewConfig['display']['page_1'] = [
            'display_plugin' => 'page',
            'id' => 'page_1',
            'display_title' => 'Page',
            'position' => 1,
            'display_options' => [
              'path' => ltrim($config['display']['page'], '/'),
            ],
          ];
        }

        // Add block display if configured.
        if (!empty($config['display']['block'])) {
          $viewConfig['display']['block_1'] = [
            'display_plugin' => 'block',
            'id' => 'block_1',
            'display_title' => 'Block',
            'position' => 2,
          ];
        }

        $view = $storage->create($viewConfig);
        $view->save();

        $created[] = ['type' => 'view', 'id' => $viewId, 'label' => $config['label']];
      }
      catch (\Exception $e) {
        $errors[] = ['type' => 'view', 'id' => $viewId, 'error' => $e->getMessage()];
      }
    }

    return ['created' => $created, 'skipped' => $skipped, 'errors' => $errors];
  }

}
