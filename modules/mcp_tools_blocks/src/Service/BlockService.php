<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_blocks\Service;

use Drupal\block\Entity\Block;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;

/**
 * Service for block placement operations.
 */
class BlockService {

  /**
   * The refusal of a label_display that is not one.
   */
  private const LABEL_DISPLAY_ERROR = 'label_display is "visible" or "hidden".';

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected BlockManagerInterface $blockManager,
    protected ThemeHandlerInterface $themeHandler,
    protected AccessManager $accessManager,
    protected AuditLogger $auditLogger,
  ) {}

  /**
   * Place a block in a theme region.
   *
   * @param string $pluginId
   *   The block plugin ID.
   * @param string $region
   *   The region to place the block in.
   * @param array $options
   *   Optional configuration options including:
   *   - theme: The theme machine name (defaults to default theme).
   *   - id: Custom block instance ID.
   *   - label: The block label.
   *   - label_display: Whether to display the label (visible/hidden).
   *   - weight: The block weight for ordering.
   *   - visibility: Visibility conditions.
   *   - settings: Block-specific settings.
   *
   * @return array
   *   Result array with success status and block data.
   */
  public function placeBlock(string $pluginId, string $region, array $options = []): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    // Validate the block plugin exists.
    $definitions = $this->blockManager->getDefinitions();
    if (!isset($definitions[$pluginId])) {
      return [
        'success' => FALSE,
        'error' => "Block plugin '$pluginId' not found. Use mcp_list_available_blocks to see available block types.",
      ];
    }

    // Determine theme.
    $theme = $options['theme'] ?? $this->themeHandler->getDefault();
    if (!$this->themeHandler->themeExists($theme)) {
      return ['success' => FALSE, 'error' => "Theme '$theme' not found."];
    }

    // Validate region exists in the theme.
    $regions = $this->visibleRegions($theme);
    if (!isset($regions[$region])) {
      return ['success' => FALSE, 'error' => "Region '$region' not found in theme '$theme'."];
    }

    try {
      // Generate a unique block ID if not provided.
      $blockId = $options['id'] ?? $this->generateBlockId($pluginId, $theme);

      // Check if block already exists.
      $existingBlock = $this->entityTypeManager->getStorage('block')->load($blockId);
      if ($existingBlock) {
        return ['success' => FALSE, 'error' => "Block with ID '$blockId' already exists."];
      }

      $plugin = $this->blockManager->createInstance($pluginId);
      $unknown = $this->unknownSettings($plugin->getConfiguration(), $options['settings'] ?? []);
      if ($unknown !== NULL) {
        return ['success' => FALSE, 'error' => $unknown];
      }
      $visibility = NULL;
      if (isset($options['visibility'])) {
        $visibility = $this->visibilityConditions($options['visibility']);
        if (is_string($visibility)) {
          return ['success' => FALSE, 'error' => $visibility];
        }
      }

      // Build block configuration.
      $blockConfig = [
        'id' => $blockId,
        'plugin' => $pluginId,
        'region' => $region,
        'theme' => $theme,
        'weight' => $options['weight'] ?? 0,
        'status' => TRUE,
        'settings' => array_merge(
          $plugin->defaultConfiguration(),
          $options['settings'] ?? []
        ),
      ];

      // Set label if provided.
      if (isset($options['label'])) {
        $blockConfig['settings']['label'] = $options['label'];
        $display = $this->labelDisplay($options['label_display'] ?? 'visible');
        if ($display === NULL) {
          return ['success' => FALSE, 'error' => self::LABEL_DISPLAY_ERROR];
        }
        $blockConfig['settings']['label_display'] = $display;
      }

      // Add visibility conditions if provided.
      if ($visibility !== NULL) {
        $blockConfig['visibility'] = $visibility;
      }

      $block = Block::create($blockConfig);
      $block->save();

      $this->auditLogger->logSuccess('place_block', 'block', $blockId, [
        'plugin_id' => $pluginId,
        'region' => $region,
        'theme' => $theme,
      ]);

      return [
        'success' => TRUE,
        'data' => [
          'block_id' => $blockId,
          'plugin_id' => $pluginId,
          'region' => $region,
          'theme' => $theme,
          'message' => "Block '$blockId' placed in region '$region' of theme '$theme'.",
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('place_block', 'block', $options['id'] ?? 'new', ['error' => $e->getMessage()]);
      return ['success' => FALSE, 'error' => 'Failed to place block: ' . $e->getMessage()];
    }
  }

  /**
   * Remove a placed block.
   *
   * @param string $blockId
   *   The block instance ID.
   *
   * @return array
   *   Result array with success status.
   */
  public function removeBlock(string $blockId): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    $block = $this->entityTypeManager->getStorage('block')->load($blockId);
    if (!$block) {
      return [
        'success' => FALSE,
        'error' => "Block '$blockId' not found. Use mcp_list_regions to see the placed blocks.",
      ];
    }

    try {
      $pluginId = $block->getPluginId();
      $region = $block->getRegion();
      $theme = $block->getTheme();

      $block->delete();

      $this->auditLogger->logSuccess('remove_block', 'block', $blockId, [
        'plugin_id' => $pluginId,
        'region' => $region,
        'theme' => $theme,
      ]);

      return [
        'success' => TRUE,
        'data' => [
          'block_id' => $blockId,
          'plugin_id' => $pluginId,
          'region' => $region,
          'theme' => $theme,
          'message' => "Block '$blockId' removed successfully.",
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('remove_block', 'block', $blockId, ['error' => $e->getMessage()]);
      return ['success' => FALSE, 'error' => 'Failed to remove block: ' . $e->getMessage()];
    }
  }

  /**
   * Update block configuration.
   *
   * @param string $blockId
   *   The block instance ID.
   * @param array $config
   *   Configuration to update, including:
   *   - region: Move to a new region.
   *   - weight: Update weight.
   *   - label: Update label.
   *   - label_display: Update label display (visible/hidden).
   *   - status: Enable/disable the block.
   *   - visibility: Update visibility conditions.
   *   - settings: Block-specific settings.
   *
   * @return array
   *   Result array with success status.
   */
  public function configureBlock(string $blockId, array $config): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    $block = $this->entityTypeManager->getStorage('block')->load($blockId);
    if (!$block) {
      return [
        'success' => FALSE,
        'error' => "Block '$blockId' not found. Use mcp_list_regions to see the placed blocks.",
      ];
    }

    try {
      $updated = [];

      // Update region.
      if (isset($config['region'])) {
        $theme = $block->getTheme();
        $regions = $this->visibleRegions($theme);
        if (!isset($regions[$config['region']])) {
          return ['success' => FALSE, 'error' => "Region '{$config['region']}' not found in theme '$theme'."];
        }
        $block->setRegion($config['region']);
        $updated[] = 'region';
      }

      // Update weight.
      if (isset($config['weight'])) {
        $block->setWeight((int) $config['weight']);
        $updated[] = 'weight';
      }

      // Update status.
      if (isset($config['status'])) {
        $block->setStatus((bool) $config['status']);
        $updated[] = 'status';
      }

      // Update block settings.
      if (isset($config['settings']) || isset($config['label']) || isset($config['label_display'])) {
        $settings = $block->get('settings');

        if (isset($config['label'])) {
          $settings['label'] = $config['label'];
          $updated[] = 'label';
        }

        if (isset($config['label_display'])) {
          $settings['label_display'] = $this->labelDisplay($config['label_display']);
          if ($settings['label_display'] === NULL) {
            return ['success' => FALSE, 'error' => self::LABEL_DISPLAY_ERROR];
          }
          $updated[] = 'label_display';
        }

        if (isset($config['settings'])) {
          $unknown = $this->unknownSettings($block->getPlugin()->getConfiguration(), $config['settings']);
          if ($unknown !== NULL) {
            return ['success' => FALSE, 'error' => $unknown];
          }
          $settings = array_merge($settings, $config['settings']);
          $updated[] = 'settings';
        }

        $block->set('settings', $settings);
      }

      // Update visibility: the conditions given replace the ones there were.
      if (isset($config['visibility'])) {
        $visibility = $this->visibilityConditions($config['visibility']);
        if (is_string($visibility)) {
          return ['success' => FALSE, 'error' => $visibility];
        }
        foreach (array_keys($block->getVisibility()) as $old) {
          if (!isset($visibility[$old])) {
            $block->getVisibilityConditions()->removeInstanceId($old);
          }
        }
        foreach ($visibility as $conditionId => $conditionConfig) {
          $block->setVisibilityConfig($conditionId, $conditionConfig);
        }
        $updated[] = 'visibility';
      }

      if (empty($updated)) {
        return [
          'success' => TRUE,
          'data' => [
            'block_id' => $blockId,
            'message' => 'No changes were made.',
            'changed' => FALSE,
          ],
        ];
      }

      $block->save();

      $this->auditLogger->logSuccess('configure_block', 'block', $blockId, [
        'updated_fields' => $updated,
      ]);

      return [
        'success' => TRUE,
        'data' => [
          'block_id' => $blockId,
          'updated' => $updated,
          'message' => "Block '$blockId' configured successfully.",
          'changed' => TRUE,
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('configure_block', 'block', $blockId, ['error' => $e->getMessage()]);
      return ['success' => FALSE, 'error' => 'Failed to configure block: ' . $e->getMessage()];
    }
  }

  /**
   * List available block plugins.
   *
   * @return array
   *   Result array with available block plugins.
   */
  public function listAvailableBlocks(): array {
    try {
      $definitions = $this->blockManager->getDefinitions();
      $blocks = [];

      foreach ($definitions as $pluginId => $definition) {
        $blocks[] = [
          'plugin_id' => $pluginId,
          'label' => (string) ($definition['admin_label'] ?? $pluginId),
          'category' => (string) ($definition['category'] ?? 'Other'),
          'provider' => $definition['provider'] ?? 'unknown',
        ];
      }

      // Sort by category, then by label.
      usort($blocks, function ($a, $b) {
        $categoryCompare = strcmp($a['category'], $b['category']);
        return $categoryCompare !== 0 ? $categoryCompare : strcmp($a['label'], $b['label']);
      });

      $this->auditLogger->logSuccess('list_available_blocks', 'block', 'all', [
        'count' => count($blocks),
      ]);

      return [
        'success' => TRUE,
        'data' => [
          'blocks' => $blocks,
          'count' => count($blocks),
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('list_available_blocks', 'block', 'all', ['error' => $e->getMessage()]);
      return ['success' => FALSE, 'error' => 'Failed to list blocks: ' . $e->getMessage()];
    }
  }

  /**
   * List available regions for a theme.
   *
   * @param string|null $theme
   *   The theme machine name. Defaults to the default theme.
   *
   * @return array
   *   Result array with available regions.
   */
  public function listRegions(?string $theme = NULL): array {
    try {
      $theme = $theme ?? $this->themeHandler->getDefault();

      if (!$this->themeHandler->themeExists($theme)) {
        return ['success' => FALSE, 'error' => "Theme '$theme' not found."];
      }

      $regions = $this->visibleRegions($theme);
      $regionList = [];

      foreach ($regions as $regionId => $regionLabel) {
        $regionList[] = [
          'id' => $regionId,
          'label' => (string) $regionLabel,
        ];
      }

      // Get blocks currently placed in each region.
      $placedBlocks = $this->entityTypeManager->getStorage('block')
        ->loadByProperties(['theme' => $theme]);

      $regionBlocks = [];
      foreach ($placedBlocks as $block) {
        $blockRegion = $block->getRegion();
        if (!isset($regionBlocks[$blockRegion])) {
          $regionBlocks[$blockRegion] = [];
        }
        $regionBlocks[$blockRegion][] = [
          'block_id' => $block->id(),
          'plugin_id' => $block->getPluginId(),
          'weight' => $block->getWeight(),
          'status' => $block->status(),
        ];
      }

      // Add block counts to regions.
      foreach ($regionList as &$region) {
        $region['blocks'] = $regionBlocks[$region['id']] ?? [];
        $region['block_count'] = count($region['blocks']);
      }

      $this->auditLogger->logSuccess('list_regions', 'theme', $theme, [
        'region_count' => count($regionList),
      ]);

      return [
        'success' => TRUE,
        'data' => [
          'theme' => $theme,
          'regions' => $regionList,
          'count' => count($regionList),
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('list_regions', 'theme', $theme ?? 'default', ['error' => $e->getMessage()]);
      return ['success' => FALSE, 'error' => 'Failed to list regions: ' . $e->getMessage()];
    }
  }

  /**
   * Why some settings are not the block's, or NULL when all of them are.
   *
   * Settings a block does not have were merged in and stored, and the call
   * said success: the block went on as before.
   *
   * @param array $defaults
   *   The block plugin's configuration: the keys it has, its own and the
   *   ones every block has (label, label_display, provider).
   * @param mixed $given
   *   The settings that were given.
   */
  protected function unknownSettings(array $defaults, mixed $given): ?string {
    if (!is_array($given)) {
      return 'settings must be an object of the block\'s own settings.';
    }
    // The plugin id and its provider are the block's, not settings to set.
    $settable = array_diff(array_keys($defaults), ['id', 'provider']);
    $unknown = array_values(array_diff(array_keys($given), $settable));
    if ($unknown === []) {
      return NULL;
    }
    return 'This block has no setting ' . implode(', ', $unknown) . ' to set. Its settings are: ' . implode(', ', $settable) . '.';
  }

  /**
   * Visibility conditions in the shape Drupal stores, from what was given.
   *
   * One shape for both tools: {"request_path": {"pages": "/about"}},
   * {"user_role": {"roles": ["authenticated"]}} or
   * {"entity_bundle:node": {"bundles": ["article"]}}, keyed by condition.
   * "node_type", the name Drupal 8 had for the last, is taken too. The
   * configure tool used to want one condition with a "condition_id" key and
   * stored the place tool's shape wrong; that shape is still taken. Pages can
   * be a list, roles a list or a map.
   *
   * @return array|string
   *   The conditions, keyed by condition id, or why they cannot be read.
   */
  protected function visibilityConditions(mixed $given): array|string {
    if (!is_array($given)) {
      return 'visibility must be an object, like {"request_path": {"pages": "/about"}}.';
    }
    if (isset($given['condition_id']) && is_string($given['condition_id'])) {
      $id = $given['condition_id'];
      unset($given['condition_id']);
      $given = [$id => $given];
    }
    // The flat shape the release took for a page, {"pages": "/about"}, and
    // the same for roles and content types: one condition each.
    $flat = ['pages' => 'request_path', 'roles' => 'user_role', 'bundles' => 'entity_bundle:node'];
    if (array_intersect_key($given, $flat) !== []) {
      $negate = $given['negate'] ?? NULL;
      $unflat = [];
      foreach ($flat as $key => $condition) {
        if (array_key_exists($key, $given)) {
          $unflat[$condition] = [$key => $given[$key]] + ($negate !== NULL ? ['negate' => $negate] : []);
        }
      }
      $given = $unflat;
    }
    $conditions = [];
    foreach ($given as $id => $conditionConfig) {
      if (!is_string($id) || !is_array($conditionConfig)) {
        return 'visibility is keyed by condition, like {"request_path": {"pages": "/about"}} or {"user_role": {"roles": ["authenticated"]}}.';
      }
      if ($id === 'node_type') {
        $id = 'entity_bundle:node';
      }
      $conditionConfig['id'] = $id;
      if (isset($conditionConfig['pages'])) {
        $pages = is_array($conditionConfig['pages']) ? $conditionConfig['pages'] : preg_split('/\R/', (string) $conditionConfig['pages']);
        // "\/user" is "/user" escaped once too often. A page that is not a
        // path was stored and matched nothing.
        $pages = array_values(array_filter(array_map(static fn($page): string => str_replace('\\/', '/', trim((string) $page)), $pages), 'strlen'));
        foreach ($pages as $page) {
          if (!str_starts_with($page, '/') && $page !== '<front>') {
            return "visibility pages are paths that start with /, or <front>: '$page' is not one.";
          }
        }
        $conditionConfig['pages'] = implode("\n", $pages);
      }
      if (str_starts_with($id, 'entity_bundle:') && isset($conditionConfig['bundles'])) {
        $bundles = (array) $conditionConfig['bundles'];
        $conditionConfig['bundles'] = array_is_list($bundles) ? array_combine($bundles, $bundles) : $bundles;
      }
      if ($id === 'entity_bundle:node') {
        $conditionConfig['context_mapping'] ??= ['node' => '@node.node_route_context:node'];
      }
      if ($id === 'user_role') {
        $roles = (array) ($conditionConfig['roles'] ?? []);
        $conditionConfig['roles'] = array_is_list($roles) ? array_combine($roles, $roles) : $roles;
        $conditionConfig['context_mapping'] ??= ['user' => '@user.current_user_context:current_user'];
      }
      $conditions[$id] = $conditionConfig;
    }
    return $conditions;
  }

  /**
   * Drupal's value for showing the label, or NULL when it cannot be read.
   *
   * Drupal shows a label when label_display is not empty: "hidden" showed
   * it. Hidden is '0'.
   */
  protected function labelDisplay(mixed $given): ?string {
    if (is_bool($given) || is_int($given)) {
      return $given ? 'visible' : '0';
    }
    return match (strtolower(trim((string) $given))) {
      'visible', 'show', 'true', 'yes', '1' => 'visible',
      'hidden', 'hide', 'false', 'no', '0', '' => '0',
      default => NULL,
    };
  }

  /**
   * Generate a unique block ID.
   *
   * @param string $pluginId
   *   The block plugin ID.
   * @param string $theme
   *   The theme machine name.
   *
   * @return string
   *   A unique block ID.
   */
  protected function generateBlockId(string $pluginId, string $theme): string {
    // Clean up plugin ID to make it suitable for a machine name.
    $baseId = preg_replace('/[^a-z0-9_]/', '_', strtolower($pluginId));
    $baseId = preg_replace('/_+/', '_', $baseId);
    $baseId = trim($baseId, '_');

    // Prefix with theme.
    $baseId = $theme . '_' . $baseId;

    // Ensure uniqueness by checking existing blocks.
    $storage = $this->entityTypeManager->getStorage('block');
    $id = $baseId;
    $counter = 1;

    while ($storage->load($id)) {
      $id = $baseId . '_' . $counter;
      $counter++;
    }

    return $id;
  }

  /**
   * The theme's visible regions: machine name => label.
   *
   * The function system_region_list() is deprecated in Drupal 11.4 and
   * removed in 13. Its replacement is on the theme object from 11.4, which
   * Drupal 10 does not have.
   */
  protected function visibleRegions(string $theme): array {
    if (!$this->themeHandler->themeExists($theme)) {
      return [];
    }
    $extension = $this->themeHandler->getTheme($theme);
    if (method_exists($extension, 'listVisibleRegions')) {
      return $extension->listVisibleRegions();
    }
    // @phpstan-ignore-next-line Drupal 10 and 11 before 11.4.
    return system_region_list($theme, REGIONS_VISIBLE);
  }

}
