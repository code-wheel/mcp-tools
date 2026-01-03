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
      return ['success' => FALSE, 'error' => "Block plugin '$pluginId' not found."];
    }

    // Determine theme.
    $theme = $options['theme'] ?? $this->themeHandler->getDefault();
    if (!$this->themeHandler->themeExists($theme)) {
      return ['success' => FALSE, 'error' => "Theme '$theme' not found."];
    }

    // Validate region exists in the theme.
    $regions = system_region_list($theme, REGIONS_VISIBLE);
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

      // Build block configuration.
      $blockConfig = [
        'id' => $blockId,
        'plugin' => $pluginId,
        'region' => $region,
        'theme' => $theme,
        'weight' => $options['weight'] ?? 0,
        'status' => TRUE,
        'settings' => array_merge(
          $this->blockManager->createInstance($pluginId)->defaultConfiguration(),
          $options['settings'] ?? []
        ),
      ];

      // Set label if provided.
      if (isset($options['label'])) {
        $blockConfig['settings']['label'] = $options['label'];
        $blockConfig['settings']['label_display'] = $options['label_display'] ?? 'visible';
      }

      // Add visibility conditions if provided.
      if (isset($options['visibility'])) {
        $blockConfig['visibility'] = $options['visibility'];
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
          'weight' => $blockConfig['weight'],
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
      return ['success' => FALSE, 'error' => "Block '$blockId' not found."];
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
      return ['success' => FALSE, 'error' => "Block '$blockId' not found."];
    }

    try {
      $updated = [];

      // Update region.
      if (isset($config['region'])) {
        $theme = $block->getTheme();
        $regions = system_region_list($theme, REGIONS_VISIBLE);
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
          $settings['label_display'] = $config['label_display'];
          $updated[] = 'label_display';
        }

        if (isset($config['settings'])) {
          $settings = array_merge($settings, $config['settings']);
          $updated[] = 'settings';
        }

        $block->set('settings', $settings);
      }

      // Update visibility.
      if (isset($config['visibility'])) {
        $block->setVisibilityConfig($config['visibility']['condition_id'] ?? 'request_path', $config['visibility']);
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

      $regions = system_region_list($theme, REGIONS_VISIBLE);
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

}
