<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_views\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;

/**
 * Service for Views management.
 */
class ViewsService {

  /**
   * Common view display types.
   */
  protected const DISPLAY_TYPES = [
    'page' => 'Page',
    'block' => 'Block',
    'feed' => 'Feed',
    'attachment' => 'Attachment',
    'embed' => 'Embed',
  ];

  /**
   * Common sort options.
   */
  protected const SORT_OPTIONS = [
    'created_desc' => ['field' => 'created', 'order' => 'DESC'],
    'created_asc' => ['field' => 'created', 'order' => 'ASC'],
    'changed_desc' => ['field' => 'changed', 'order' => 'DESC'],
    'title_asc' => ['field' => 'title', 'order' => 'ASC'],
    'title_desc' => ['field' => 'title', 'order' => 'DESC'],
  ];

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AccessManager $accessManager,
    protected AuditLogger $auditLogger,
  ) {}

  /**
   * Create a new view.
   *
   * @param string $id
   *   Machine name.
   * @param string $label
   *   Human-readable name.
   * @param string $baseTable
   *   Base table (node_field_data, users_field_data, taxonomy_term_field_data).
   * @param array $options
   *   Optional: description, displays, filters, sorts.
   *
   * @return array
   *   Result with success status.
   */
  public function createView(string $id, string $label, string $baseTable = 'node_field_data', array $options = []): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    // Validate machine name.
    if (!preg_match('/^[a-z][a-z0-9_]*$/', $id)) {
      return [
        'success' => FALSE,
        'error' => 'Invalid machine name. Use lowercase letters, numbers, and underscores.',
      ];
    }

    // Check if view exists.
    $existing = $this->entityTypeManager->getStorage('view')->load($id);
    if ($existing) {
      return [
        'success' => FALSE,
        'error' => "View '$id' already exists.",
      ];
    }

    // Validate base table.
    $validBaseTables = [
      'node_field_data' => 'Content',
      'users_field_data' => 'Users',
      'taxonomy_term_field_data' => 'Taxonomy terms',
      'file_managed' => 'Files',
      'media_field_data' => 'Media',
      'comment_field_data' => 'Comments',
    ];

    if (!isset($validBaseTables[$baseTable])) {
      return [
        'success' => FALSE,
        'error' => 'Invalid base table. Valid options: ' . implode(', ', array_keys($validBaseTables)),
      ];
    }

    try {
      // Build view configuration.
      $viewConfig = [
        'id' => $id,
        'label' => $label,
        'description' => $options['description'] ?? '',
        'base_table' => $baseTable,
        'base_field' => $this->getBaseField($baseTable),
        'display' => [],
      ];

      // Add default display.
      $viewConfig['display']['default'] = $this->buildDefaultDisplay($baseTable, $options);

      // Add page display if requested.
      if (!empty($options['page_path'])) {
        $viewConfig['display']['page_1'] = $this->buildPageDisplay($options);
      }

      // Add block display if requested.
      if (!empty($options['block'])) {
        $viewConfig['display']['block_1'] = $this->buildBlockDisplay($options);
      }

      $view = $this->entityTypeManager->getStorage('view')->create($viewConfig);
      $view->save();

      $this->auditLogger->logSuccess('create_view', 'view', $id, [
        'label' => $label,
        'base_table' => $baseTable,
        'displays' => array_keys($viewConfig['display']),
      ]);

      $result = [
        'success' => TRUE,
        'data' => [
          'id' => $id,
          'label' => $label,
          'base_table' => $baseTable,
          'displays' => array_keys($viewConfig['display']),
          'message' => "View '$label' ($id) created successfully.",
          'admin_path' => "/admin/structure/views/view/$id",
        ],
      ];

      if (!empty($options['page_path'])) {
        $result['data']['page_path'] = $options['page_path'];
      }

      return $result;
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('create_view', 'view', $id, [
        'error' => $e->getMessage(),
      ]);

      return [
        'success' => FALSE,
        'error' => 'Failed to create view: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Create a simple content listing view.
   *
   * @param string $id
   *   Machine name.
   * @param string $label
   *   Human-readable name.
   * @param string $contentType
   *   Content type to filter by (optional).
   * @param array $options
   *   Optional settings.
   *
   * @return array
   *   Result with success status.
   */
  public function createContentListView(string $id, string $label, string $contentType = '', array $options = []): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    $viewOptions = [
      'description' => $options['description'] ?? "Listing of $label",
      'page_path' => $options['page_path'] ?? '/' . str_replace('_', '-', $id),
      'items_per_page' => $options['items_per_page'] ?? 10,
      'sort' => $options['sort'] ?? 'created_desc',
      'content_type' => $contentType,
      'show_title' => $options['show_title'] ?? TRUE,
      'fields' => $options['fields'] ?? ['title', 'created'],
    ];

    if (!empty($options['block'])) {
      $viewOptions['block'] = TRUE;
      $viewOptions['block_items'] = $options['block_items'] ?? 5;
    }

    // Note: createView also checks access, but we check here for early return
    return $this->createView($id, $label, 'node_field_data', $viewOptions);
  }

  /**
   * Delete a view.
   *
   * @param string $id
   *   View machine name.
   *
   * @return array
   *   Result with success status.
   */
  public function deleteView(string $id): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    $view = $this->entityTypeManager->getStorage('view')->load($id);

    if (!$view) {
      return [
        'success' => FALSE,
        'error' => "View '$id' not found. Use mcp_list_views to see available views.",
      ];
    }

    // Protect core views.
    $coreViews = ['frontpage', 'taxonomy_term', 'content', 'files', 'user_admin_people', 'watchdog'];
    if (in_array($id, $coreViews)) {
      return [
        'success' => FALSE,
        'error' => "Cannot delete core view '$id'.",
      ];
    }

    try {
      $label = $view->label();
      $view->delete();

      $this->auditLogger->logSuccess('delete_view', 'view', $id, [
        'label' => $label,
      ]);

      return [
        'success' => TRUE,
        'data' => [
          'id' => $id,
          'message' => "View '$label' ($id) deleted successfully.",
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('delete_view', 'view', $id, [
        'error' => $e->getMessage(),
      ]);

      return [
        'success' => FALSE,
        'error' => 'Failed to delete view: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Add a display to an existing view.
   *
   * @param string $viewId
   *   View machine name.
   * @param string $displayType
   *   Display type: page, block, feed.
   * @param array $options
   *   Display options.
   *
   * @return array
   *   Result with success status.
   */
  public function addDisplay(string $viewId, string $displayType, array $options = []): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    $view = $this->entityTypeManager->getStorage('view')->load($viewId);

    if (!$view) {
      return [
        'success' => FALSE,
        'error' => "View '$viewId' not found. Use mcp_list_views to see available views.",
      ];
    }

    if (!isset(self::DISPLAY_TYPES[$displayType])) {
      return [
        'success' => FALSE,
        'error' => 'Invalid display type. Valid options: ' . implode(', ', array_keys(self::DISPLAY_TYPES)),
      ];
    }

    try {
      $executable = $view->getExecutable();
      $displayId = $executable->addDisplay($displayType);

      // Configure the display.
      $display = &$view->getDisplay($displayId);

      if ($displayType === 'page' && !empty($options['path'])) {
        $display['display_options']['path'] = ltrim($options['path'], '/');
      }

      if (!empty($options['title'])) {
        $display['display_options']['title'] = $options['title'];
      }

      if (!empty($options['items_per_page'])) {
        $display['display_options']['pager']['options']['items_per_page'] = $options['items_per_page'];
      }

      $view->save();

      $this->auditLogger->logSuccess('add_view_display', 'view', $viewId, [
        'display_id' => $displayId,
        'display_type' => $displayType,
      ]);

      return [
        'success' => TRUE,
        'data' => [
          'view_id' => $viewId,
          'display_id' => $displayId,
          'display_type' => $displayType,
          'message' => "Added $displayType display to view '$viewId'.",
        ],
      ];
    }
    catch (\Exception $e) {
      return [
        'success' => FALSE,
        'error' => 'Failed to add display: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Enable or disable a view.
   *
   * @param string $id
   *   View machine name.
   * @param bool $enable
   *   TRUE to enable, FALSE to disable.
   *
   * @return array
   *   Result with success status.
   */
  public function setViewStatus(string $id, bool $enable): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    $view = $this->entityTypeManager->getStorage('view')->load($id);

    if (!$view) {
      return [
        'success' => FALSE,
        'error' => "View '$id' not found. Use mcp_list_views to see available views.",
      ];
    }

    try {
      $enable ? $view->enable() : $view->disable();
      $view->save();

      $this->auditLogger->logSuccess($enable ? 'enable_view' : 'disable_view', 'view', $id, []);

      return [
        'success' => TRUE,
        'data' => [
          'id' => $id,
          'status' => $enable ? 'enabled' : 'disabled',
          'message' => "View '$id' " . ($enable ? 'enabled' : 'disabled') . '.',
        ],
      ];
    }
    catch (\Exception $e) {
      return [
        'success' => FALSE,
        'error' => 'Failed to change view status: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Get base field for a table.
   */
  protected function getBaseField(string $baseTable): string {
    return match ($baseTable) {
      'node_field_data' => 'nid',
      'users_field_data' => 'uid',
      'taxonomy_term_field_data' => 'tid',
      'file_managed' => 'fid',
      'media_field_data' => 'mid',
      'comment_field_data' => 'cid',
      default => 'id',
    };
  }

  /**
   * Build default display configuration.
   */
  protected function buildDefaultDisplay(string $baseTable, array $options): array {
    $display = [
      'display_plugin' => 'default',
      'id' => 'default',
      'display_title' => 'Default',
      'position' => 0,
      'display_options' => [
        'fields' => $this->buildFields($baseTable, $options['fields'] ?? []),
        'pager' => [
          'type' => 'full',
          'options' => [
            'items_per_page' => $options['items_per_page'] ?? 10,
          ],
        ],
        'sorts' => $this->buildSorts($options['sort'] ?? 'created_desc'),
        'style' => [
          'type' => $options['style'] ?? 'default',
        ],
        'row' => [
          'type' => 'fields',
        ],
      ],
    ];

    // Add content type filter if specified.
    if (!empty($options['content_type']) && $baseTable === 'node_field_data') {
      $display['display_options']['filters']['type'] = [
        'id' => 'type',
        'table' => 'node_field_data',
        'field' => 'type',
        'value' => [$options['content_type'] => $options['content_type']],
        'plugin_id' => 'bundle',
      ];
    }

    // Add published filter for content.
    if ($baseTable === 'node_field_data') {
      $display['display_options']['filters']['status'] = [
        'id' => 'status',
        'table' => 'node_field_data',
        'field' => 'status',
        'value' => '1',
        'plugin_id' => 'boolean',
      ];
    }

    return $display;
  }

  /**
   * Build page display configuration.
   */
  protected function buildPageDisplay(array $options): array {
    return [
      'display_plugin' => 'page',
      'id' => 'page_1',
      'display_title' => 'Page',
      'position' => 1,
      'display_options' => [
        'path' => ltrim($options['page_path'] ?? '/view', '/'),
        'menu' => $options['menu'] ?? [],
      ],
    ];
  }

  /**
   * Build block display configuration.
   */
  protected function buildBlockDisplay(array $options): array {
    return [
      'display_plugin' => 'block',
      'id' => 'block_1',
      'display_title' => 'Block',
      'position' => 2,
      'display_options' => [
        'pager' => [
          'type' => 'some',
          'options' => [
            'items_per_page' => $options['block_items'] ?? 5,
          ],
        ],
      ],
    ];
  }

  /**
   * Build fields configuration.
   */
  protected function buildFields(string $baseTable, array $requestedFields): array {
    $fields = [];

    // Default fields based on base table.
    $defaultFields = match ($baseTable) {
      'node_field_data' => ['title', 'created'],
      'users_field_data' => ['name', 'mail', 'created'],
      'taxonomy_term_field_data' => ['name', 'description__value'],
      default => [],
    };

    $fieldList = !empty($requestedFields) ? $requestedFields : $defaultFields;

    foreach ($fieldList as $index => $field) {
      $fields[$field] = [
        'id' => $field,
        'table' => $baseTable,
        'field' => $field,
        'plugin_id' => 'field',
      ];
    }

    return $fields;
  }

  /**
   * Build sorts configuration.
   */
  protected function buildSorts(string $sortOption): array {
    $sortConfig = self::SORT_OPTIONS[$sortOption] ?? self::SORT_OPTIONS['created_desc'];

    return [
      $sortConfig['field'] => [
        'id' => $sortConfig['field'],
        'table' => 'node_field_data',
        'field' => $sortConfig['field'],
        'order' => $sortConfig['order'],
        'plugin_id' => 'date',
      ],
    ];
  }

}
