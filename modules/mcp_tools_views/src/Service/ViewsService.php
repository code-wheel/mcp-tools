<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_views\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\views\ViewsData;

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
   * Sort options.
   *
   * The first four are the values the tools document. The rest are older
   * names of the same sorts. "title" stands for the label of the base table:
   * the name of a term or a user.
   */
  protected const SORT_OPTIONS = [
    'newest' => ['field' => 'created', 'order' => 'DESC'],
    'oldest' => ['field' => 'created', 'order' => 'ASC'],
    'changed' => ['field' => 'changed', 'order' => 'DESC'],
    'title' => ['field' => 'title', 'order' => 'ASC'],
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
    protected ViewsData $viewsData,
  ) {}

  /**
   * What Views knows about a table: its fields, sorts and entity type.
   */
  protected function tableData(string $table): array {
    return $this->viewsData->get($table) ?: [];
  }

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

    // The table is on the list, but Views only has data for it when the
    // module that owns it is enabled.
    if (!$this->tableData($baseTable)) {
      return [
        'success' => FALSE,
        'error' => "Views has no data for the base table '$baseTable' on this site. The module it belongs to may not be enabled. Nothing was created.",
      ];
    }

    // A sort nobody knows, or one this table cannot do, is refused. When no
    // sort is asked for, the newest come first where the table has a date.
    $sort = [];
    if (isset($options['sort'])) {
      if (!isset(self::SORT_OPTIONS[$options['sort']])) {
        return [
          'success' => FALSE,
          'error' => "Unknown sort '{$options['sort']}'. Use newest, oldest, title or changed. Nothing was created.",
        ];
      }
      $sort = $this->buildSorts($baseTable, $options['sort']);
      if (!$sort) {
        $column = $this->sortColumn($baseTable, $options['sort']);
        return [
          'success' => FALSE,
          'error' => "The base table '$baseTable' cannot be sorted by '{$options['sort']}': it has no '$column' to sort on. Nothing was created.",
        ];
      }
    }
    else {
      $sort = $this->buildSorts($baseTable, 'newest');
    }

    // Fields that are neither a column of the table nor a field of its
    // entity type would give a view that cannot be shown.
    $fields = $this->buildFields($baseTable, $options['fields'] ?? []);
    $unknown = array_keys(array_filter($fields, static fn($field): bool => $field === NULL));
    if ($unknown) {
      return [
        'success' => FALSE,
        'error' => "Unknown field(s) for a view of '$baseTable': " . implode(', ', $unknown) . '. Nothing was created.',
      ];
    }
    if (!empty($options['link_title']) && isset($fields['title'])) {
      // The formatter has to be named for its setting to mean anything.
      $fields['title']['type'] = 'string';
      $fields['title']['settings'] = ['link_to_entity' => TRUE];
    }
    $options['fields'] = $fields;
    $options['sorts'] = $sort;

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
      // Views' own check, the one its UI runs before a save: a page with no
      // path, a feed with a style it cannot take, an attachment attached to
      // nothing were saved as success.
      if (($problem = $this->viewProblem($view)) !== NULL) {
        return ['success' => FALSE, 'error' => $problem];
      }
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

    // The title leads the list and links to the content, unless the caller
    // switches it off. A field list of the caller's does not drop it.
    $showTitle = $options['show_title'] ?? TRUE;
    $fields = array_values(array_diff($options['fields'] ?? ['created'], ['title']));
    if ($showTitle) {
      array_unshift($fields, 'title');
    }

    $viewOptions = [
      'description' => $options['description'] ?? "Listing of $label",
      'page_path' => $options['page_path'] ?? '/' . str_replace('_', '-', $id),
      'items_per_page' => $options['items_per_page'] ?? 10,
      'content_type' => $contentType,
      'link_title' => $showTitle,
      'fields' => $fields,
    ];

    if (isset($options['sort'])) {
      $viewOptions['sort'] = $options['sort'];
    }

    if (!empty($options['block'])) {
      $viewOptions['block'] = TRUE;
      $viewOptions['block_items'] = $options['block_items'] ?? 5;
    }

    // Note: createView also checks access, but we check here for early return.
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
    if (!$this->accessManager->canWrite('delete')) {
      return $this->accessManager->getWriteAccessDenied();
    }

    $view = $this->entityTypeManager->getStorage('view')->load($id);

    if (!$view) {
      return [
        'success' => FALSE,
        'error' => "View '$id' not found. Use mcp_tools_list_config with the prefix views.view to see the views.",
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
        'error' => "View '$viewId' not found. Use mcp_tools_list_config with the prefix views.view to see the views.",
      ];
    }

    if (!isset(self::DISPLAY_TYPES[$displayType])) {
      return [
        'success' => FALSE,
        'error' => 'Invalid display type. Valid options: ' . implode(', ', array_keys(self::DISPLAY_TYPES)),
      ];
    }

    try {
      $displayId = $view->addDisplay($displayType);

      // Configure the display.
      $display = &$view->getDisplay($displayId);

      if ($displayType === 'page' && !empty($options['path'])) {
        $display['display_options']['path'] = ltrim($options['path'], '/');
      }

      // A display takes its title and its pager from the default display
      // unless it says it has its own. Without the two "defaults" flags
      // Views stores these values and never uses them.
      if (!empty($options['title'])) {
        $display['display_options']['defaults']['title'] = FALSE;
        $display['display_options']['title'] = $options['title'];
      }

      if (!empty($options['items_per_page'])) {
        $pager = $view->getDisplay('default')['display_options']['pager'] ?? [];
        $pager['type'] = $pager['type'] ?? 'some';
        $pager['options']['items_per_page'] = (int) $options['items_per_page'];
        $display['display_options']['defaults']['pager'] = FALSE;
        $display['display_options']['pager'] = $pager;
      }

      // Views' own check, the one its UI runs before a save: a page with no
      // path, a feed with a style it cannot take, an attachment attached to
      // nothing were saved as success.
      if (($problem = $this->viewProblem($view)) !== NULL) {
        // The view in memory has the refused display: forget it.
        $this->entityTypeManager->getStorage('view')->resetCache([$viewId]);
        return ['success' => FALSE, 'error' => $problem];
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
        'error' => "View '$id' not found. Use mcp_tools_list_config with the prefix views.view to see the views.",
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
   * The permission to see a view of a base table.
   */
  protected function viewPermission(string $baseTable): string {
    return match ($baseTable) {
      'users_field_data' => 'access user profiles',
      'file_managed' => 'access files overview',
      'media_field_data' => 'view media',
      'comment_field_data' => 'access comments',
      'node_field_data', 'taxonomy_term_field_data' => 'access content',
      // Another table: only who may build views sees it until a person
      // sets its access.
      default => 'administer views',
    };
  }

  /**
   * What Views says is wrong with a view, or NULL.
   */
  protected function viewProblem(object $view): ?string {
    if (!method_exists($view, 'getExecutable')) {
      return NULL;
    }
    $errors = [];
    foreach ($view->getExecutable()->validate() as $displayErrors) {
      foreach ((array) $displayErrors as $error) {
        $errors[] = strip_tags((string) $error);
      }
    }
    return $errors === [] ? NULL : 'Nothing was saved: Views refuses this view. ' . implode(' ', array_unique($errors));
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
        'fields' => $options['fields'],
        'pager' => [
          'type' => 'full',
          'options' => [
            'items_per_page' => $options['items_per_page'] ?? 10,
          ],
        ],
        'sorts' => $options['sorts'],
        'style' => [
          'type' => $options['style'] ?? 'default',
        ],
        'row' => [
          'type' => 'fields',
        ],
        // A view had no access rule, so a page of users or files was open
        // to anonymous visitors. The permission Drupal's views wizard uses
        // for what the view lists.
        'access' => [
          'type' => 'perm',
          'options' => ['perm' => $this->viewPermission($baseTable)],
        ],
      ],
    ];

    // Only what is published, for the tables that have a status.
    $statusTables = ['users_field_data', 'media_field_data', 'comment_field_data'];
    if (in_array($baseTable, $statusTables, TRUE)) {
      $display['display_options']['filters']['status'] = [
        'id' => 'status',
        'table' => $baseTable,
        'field' => 'status',
        'value' => '1',
        'plugin_id' => 'boolean',
      ];
    }

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
   *
   * @return array
   *   Field settings by name. A name Views cannot place has NULL.
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
    $base = $this->tableData($baseTable);
    $entityType = $base['table']['entity type'] ?? NULL;

    foreach ($fieldList as $field) {
      // A column of the base table, such as title, or a field added to the
      // entity type, such as body, which lives in a table of its own.
      $table = $baseTable;
      $data = $base[$field]['field'] ?? NULL;
      if (!$data && $entityType) {
        $table = $entityType . '__' . $field;
        $data = $this->tableData($table)[$field]['field'] ?? NULL;
      }
      if (!$data) {
        $fields[$field] = NULL;
        continue;
      }
      $fields[$field] = [
        'id' => $field,
        'table' => $table,
        'field' => $field,
        'plugin_id' => $data['id'] ?? 'field',
      ];
    }

    return $fields;
  }

  /**
   * The column a sort option means on a base table.
   */
  protected function sortColumn(string $baseTable, string $sortOption): string {
    $field = self::SORT_OPTIONS[$sortOption]['field'];
    if ($field !== 'title') {
      return $field;
    }
    return match ($baseTable) {
      'node_field_data' => 'title',
      'file_managed' => 'filename',
      'comment_field_data' => 'subject',
      default => 'name',
    };
  }

  /**
   * Build sorts configuration.
   *
   * @return array
   *   The sort, or nothing when the table has no such column to sort on.
   */
  protected function buildSorts(string $baseTable, string $sortOption): array {
    $field = $this->sortColumn($baseTable, $sortOption);
    $sort = $this->tableData($baseTable)[$field]['sort'] ?? NULL;
    if (!$sort) {
      return [];
    }

    return [
      $field => [
        'id' => $field,
        'table' => $baseTable,
        'field' => $field,
        'order' => self::SORT_OPTIONS[$sortOption]['order'],
        'plugin_id' => $sort['id'] ?? 'standard',
      ],
    ];
  }

}
