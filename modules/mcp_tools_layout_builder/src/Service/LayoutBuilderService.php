<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_layout_builder\Service;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Layout\LayoutPluginManagerInterface;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;

/**
 * Service for Layout Builder management.
 */
class LayoutBuilderService {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityDisplayRepositoryInterface $entityDisplayRepository,
    protected LayoutPluginManagerInterface $layoutPluginManager,
    protected BlockManagerInterface $blockManager,
    protected UuidInterface $uuid,
    protected AccessManager $accessManager,
    protected AuditLogger $auditLogger,
  ) {}

  /**
   * Enable Layout Builder for a content type.
   *
   * @param string $entityType
   *   The entity type (e.g., 'node').
   * @param string $bundle
   *   The bundle/content type machine name.
   *
   * @return array
   *   Result with success status.
   */
  public function enableLayoutBuilder(string $entityType, string $bundle): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    $display = $this->getOrCreateDisplay($entityType, $bundle);
    if (!$display) {
      return [
        'success' => FALSE,
        'error' => "Could not load or create display for $entityType.$bundle.",
      ];
    }

    try {
      // Enable Layout Builder on the display.
      $display->enableLayoutBuilder();
      $display->save();

      $this->auditLogger->logSuccess('enable_layout_builder', 'entity_view_display', "$entityType.$bundle", [
        'entity_type' => $entityType,
        'bundle' => $bundle,
      ]);

      return [
        'success' => TRUE,
        'data' => [
          'entity_type' => $entityType,
          'bundle' => $bundle,
          'layout_builder_enabled' => TRUE,
          'message' => "Layout Builder enabled for $entityType.$bundle.",
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('enable_layout_builder', 'entity_view_display', "$entityType.$bundle", [
        'error' => $e->getMessage(),
      ]);

      return [
        'success' => FALSE,
        'error' => 'Failed to enable Layout Builder: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Disable Layout Builder for a content type.
   *
   * @param string $entityType
   *   The entity type (e.g., 'node').
   * @param string $bundle
   *   The bundle/content type machine name.
   *
   * @return array
   *   Result with success status.
   */
  public function disableLayoutBuilder(string $entityType, string $bundle): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    $display = $this->loadDisplay($entityType, $bundle);
    if (!$display) {
      return [
        'success' => FALSE,
        'error' => "Display for $entityType.$bundle not found.",
      ];
    }

    try {
      $display->disableLayoutBuilder();
      $display->save();

      $this->auditLogger->logSuccess('disable_layout_builder', 'entity_view_display', "$entityType.$bundle", [
        'entity_type' => $entityType,
        'bundle' => $bundle,
      ]);

      return [
        'success' => TRUE,
        'data' => [
          'entity_type' => $entityType,
          'bundle' => $bundle,
          'layout_builder_enabled' => FALSE,
          'message' => "Layout Builder disabled for $entityType.$bundle.",
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('disable_layout_builder', 'entity_view_display', "$entityType.$bundle", [
        'error' => $e->getMessage(),
      ]);

      return [
        'success' => FALSE,
        'error' => 'Failed to disable Layout Builder: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Toggle per-entity overrides (custom layouts).
   *
   * @param string $entityType
   *   The entity type (e.g., 'node').
   * @param string $bundle
   *   The bundle/content type machine name.
   * @param bool $allow
   *   Whether to allow custom layouts.
   *
   * @return array
   *   Result with success status.
   */
  public function allowCustomLayouts(string $entityType, string $bundle, bool $allow): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    $display = $this->loadDisplay($entityType, $bundle);
    if (!$display) {
      return [
        'success' => FALSE,
        'error' => "Display for $entityType.$bundle not found.",
      ];
    }

    if (!$display->isLayoutBuilderEnabled()) {
      return [
        'success' => FALSE,
        'error' => "Layout Builder is not enabled for $entityType.$bundle. Enable it first.",
      ];
    }

    try {
      $display->setOverridable($allow);
      $display->save();

      $this->auditLogger->logSuccess('allow_custom_layouts', 'entity_view_display', "$entityType.$bundle", [
        'entity_type' => $entityType,
        'bundle' => $bundle,
        'allow_custom' => $allow,
      ]);

      return [
        'success' => TRUE,
        'data' => [
          'entity_type' => $entityType,
          'bundle' => $bundle,
          'allow_custom_layouts' => $allow,
          'message' => "Custom layouts " . ($allow ? 'enabled' : 'disabled') . " for $entityType.$bundle.",
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('allow_custom_layouts', 'entity_view_display', "$entityType.$bundle", [
        'error' => $e->getMessage(),
      ]);

      return [
        'success' => FALSE,
        'error' => 'Failed to update custom layouts setting: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Get default layout sections for a content type.
   *
   * @param string $entityType
   *   The entity type (e.g., 'node').
   * @param string $bundle
   *   The bundle/content type machine name.
   *
   * @return array
   *   Result with success status and sections data.
   */
  public function getLayout(string $entityType, string $bundle): array {
    $display = $this->loadDisplay($entityType, $bundle);
    if (!$display) {
      return [
        'success' => FALSE,
        'error' => "Display for $entityType.$bundle not found.",
      ];
    }

    if (!$display->isLayoutBuilderEnabled()) {
      return [
        'success' => FALSE,
        'error' => "Layout Builder is not enabled for $entityType.$bundle.",
      ];
    }

    try {
      $sections = $display->getSections();
      $sectionsData = [];

      foreach ($sections as $delta => $section) {
        $sectionData = [
          'delta' => $delta,
          'layout_id' => $section->getLayoutId(),
          'layout_settings' => $section->getLayoutSettings(),
          'components' => [],
        ];

        foreach ($section->getComponents() as $uuid => $component) {
          $sectionData['components'][] = [
            'uuid' => $uuid,
            'region' => $component->getRegion(),
            'plugin_id' => $component->getPluginId(),
            'weight' => $component->getWeight(),
            'configuration' => $component->get('configuration'),
          ];
        }

        $sectionsData[] = $sectionData;
      }

      return [
        'success' => TRUE,
        'data' => [
          'entity_type' => $entityType,
          'bundle' => $bundle,
          'layout_builder_enabled' => TRUE,
          'allow_custom_layouts' => $display->isOverridable(),
          'sections' => $sectionsData,
          'section_count' => count($sectionsData),
        ],
      ];
    }
    catch (\Exception $e) {
      return [
        'success' => FALSE,
        'error' => 'Failed to get layout: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Add a section to the layout.
   *
   * @param string $entityType
   *   The entity type (e.g., 'node').
   * @param string $bundle
   *   The bundle/content type machine name.
   * @param string $layoutId
   *   The layout plugin ID (e.g., 'layout_onecol', 'layout_twocol_section').
   * @param int $delta
   *   The position to insert the section at.
   *
   * @return array
   *   Result with success status.
   */
  public function addSection(string $entityType, string $bundle, string $layoutId, int $delta): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    $display = $this->loadDisplay($entityType, $bundle);
    if (!$display) {
      return [
        'success' => FALSE,
        'error' => "Display for $entityType.$bundle not found.",
      ];
    }

    if (!$display->isLayoutBuilderEnabled()) {
      return [
        'success' => FALSE,
        'error' => "Layout Builder is not enabled for $entityType.$bundle.",
      ];
    }

    // Validate layout plugin.
    if (!$this->layoutPluginManager->hasDefinition($layoutId)) {
      return [
        'success' => FALSE,
        'error' => "Layout plugin '$layoutId' not found. Use mcp_layout_list_plugins to see available layouts.",
      ];
    }

    try {
      $section = new Section($layoutId);
      $display->insertSection($delta, $section);
      $display->save();

      $this->auditLogger->logSuccess('add_section', 'entity_view_display', "$entityType.$bundle", [
        'layout_id' => $layoutId,
        'delta' => $delta,
      ]);

      return [
        'success' => TRUE,
        'data' => [
          'entity_type' => $entityType,
          'bundle' => $bundle,
          'layout_id' => $layoutId,
          'delta' => $delta,
          'section_count' => count($display->getSections()),
          'message' => "Section with layout '$layoutId' added at position $delta.",
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('add_section', 'entity_view_display', "$entityType.$bundle", [
        'error' => $e->getMessage(),
      ]);

      return [
        'success' => FALSE,
        'error' => 'Failed to add section: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Remove a section from the layout.
   *
   * @param string $entityType
   *   The entity type (e.g., 'node').
   * @param string $bundle
   *   The bundle/content type machine name.
   * @param int $delta
   *   The section delta to remove.
   *
   * @return array
   *   Result with success status.
   */
  public function removeSection(string $entityType, string $bundle, int $delta): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    $display = $this->loadDisplay($entityType, $bundle);
    if (!$display) {
      return [
        'success' => FALSE,
        'error' => "Display for $entityType.$bundle not found.",
      ];
    }

    if (!$display->isLayoutBuilderEnabled()) {
      return [
        'success' => FALSE,
        'error' => "Layout Builder is not enabled for $entityType.$bundle.",
      ];
    }

    $sections = $display->getSections();
    if (!isset($sections[$delta])) {
      return [
        'success' => FALSE,
        'error' => "Section at delta $delta not found. Available deltas: 0-" . (count($sections) - 1),
      ];
    }

    try {
      $display->removeSection($delta);
      $display->save();

      $this->auditLogger->logSuccess('remove_section', 'entity_view_display', "$entityType.$bundle", [
        'delta' => $delta,
      ]);

      return [
        'success' => TRUE,
        'data' => [
          'entity_type' => $entityType,
          'bundle' => $bundle,
          'removed_delta' => $delta,
          'section_count' => count($display->getSections()),
          'message' => "Section at position $delta removed.",
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('remove_section', 'entity_view_display', "$entityType.$bundle", [
        'error' => $e->getMessage(),
      ]);

      return [
        'success' => FALSE,
        'error' => 'Failed to remove section: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Add a block to a section.
   *
   * @param string $entityType
   *   The entity type (e.g., 'node').
   * @param string $bundle
   *   The bundle/content type machine name.
   * @param int $sectionDelta
   *   The section delta to add the block to.
   * @param string $region
   *   The region within the section.
   * @param string $blockId
   *   The block plugin ID.
   *
   * @return array
   *   Result with success status.
   */
  public function addBlock(string $entityType, string $bundle, int $sectionDelta, string $region, string $blockId): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    $display = $this->loadDisplay($entityType, $bundle);
    if (!$display) {
      return [
        'success' => FALSE,
        'error' => "Display for $entityType.$bundle not found.",
      ];
    }

    if (!$display->isLayoutBuilderEnabled()) {
      return [
        'success' => FALSE,
        'error' => "Layout Builder is not enabled for $entityType.$bundle.",
      ];
    }

    $sections = $display->getSections();
    if (!isset($sections[$sectionDelta])) {
      return [
        'success' => FALSE,
        'error' => "Section at delta $sectionDelta not found.",
      ];
    }

    // Validate block plugin.
    if (!$this->blockManager->hasDefinition($blockId)) {
      return [
        'success' => FALSE,
        'error' => "Block plugin '$blockId' not found.",
      ];
    }

    // Validate region exists in layout.
    $section = $sections[$sectionDelta];
    $layoutDefinition = $this->layoutPluginManager->getDefinition($section->getLayoutId());
    $regions = $layoutDefinition->getRegions();
    if (!isset($regions[$region])) {
      return [
        'success' => FALSE,
        'error' => "Region '$region' not found in layout. Available regions: " . implode(', ', array_keys($regions)),
      ];
    }

    try {
      $componentUuid = $this->uuid->generate();
      $component = new SectionComponent($componentUuid, $region, ['id' => $blockId]);
      $section->appendComponent($component);

      $display->setSection($sectionDelta, $section);
      $display->save();

      $this->auditLogger->logSuccess('add_block', 'entity_view_display', "$entityType.$bundle", [
        'section_delta' => $sectionDelta,
        'region' => $region,
        'block_id' => $blockId,
        'component_uuid' => $componentUuid,
      ]);

      return [
        'success' => TRUE,
        'data' => [
          'entity_type' => $entityType,
          'bundle' => $bundle,
          'section_delta' => $sectionDelta,
          'region' => $region,
          'block_id' => $blockId,
          'component_uuid' => $componentUuid,
          'message' => "Block '$blockId' added to section $sectionDelta, region '$region'.",
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('add_block', 'entity_view_display', "$entityType.$bundle", [
        'error' => $e->getMessage(),
      ]);

      return [
        'success' => FALSE,
        'error' => 'Failed to add block: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Remove a block from the layout.
   *
   * @param string $entityType
   *   The entity type (e.g., 'node').
   * @param string $bundle
   *   The bundle/content type machine name.
   * @param string $blockUuid
   *   The UUID of the block component to remove.
   *
   * @return array
   *   Result with success status.
   */
  public function removeBlock(string $entityType, string $bundle, string $blockUuid): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    $display = $this->loadDisplay($entityType, $bundle);
    if (!$display) {
      return [
        'success' => FALSE,
        'error' => "Display for $entityType.$bundle not found.",
      ];
    }

    if (!$display->isLayoutBuilderEnabled()) {
      return [
        'success' => FALSE,
        'error' => "Layout Builder is not enabled for $entityType.$bundle.",
      ];
    }

    // Find the section containing the block.
    $sections = $display->getSections();
    $found = FALSE;
    $foundSectionDelta = NULL;

    foreach ($sections as $delta => $section) {
      $component = $section->getComponent($blockUuid);
      if ($component) {
        $found = TRUE;
        $foundSectionDelta = $delta;
        break;
      }
    }

    if (!$found) {
      return [
        'success' => FALSE,
        'error' => "Block with UUID '$blockUuid' not found in any section.",
      ];
    }

    try {
      $section = $sections[$foundSectionDelta];
      $section->removeComponent($blockUuid);

      $display->setSection($foundSectionDelta, $section);
      $display->save();

      $this->auditLogger->logSuccess('remove_block', 'entity_view_display', "$entityType.$bundle", [
        'block_uuid' => $blockUuid,
        'section_delta' => $foundSectionDelta,
      ]);

      return [
        'success' => TRUE,
        'data' => [
          'entity_type' => $entityType,
          'bundle' => $bundle,
          'removed_block_uuid' => $blockUuid,
          'section_delta' => $foundSectionDelta,
          'message' => "Block with UUID '$blockUuid' removed from section $foundSectionDelta.",
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('remove_block', 'entity_view_display', "$entityType.$bundle", [
        'error' => $e->getMessage(),
      ]);

      return [
        'success' => FALSE,
        'error' => 'Failed to remove block: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * List available layout plugins.
   *
   * @return array
   *   Result with available layout plugins.
   */
  public function listLayoutPlugins(): array {
    try {
      $definitions = $this->layoutPluginManager->getDefinitions();
      $layouts = [];

      foreach ($definitions as $id => $definition) {
        $regions = [];
        foreach ($definition->getRegions() as $regionId => $regionInfo) {
          $regions[$regionId] = $regionInfo['label'] ?? $regionId;
        }

        $layouts[] = [
          'id' => $id,
          'label' => (string) $definition->getLabel(),
          'category' => (string) $definition->getCategory(),
          'regions' => $regions,
          'default_region' => $definition->getDefaultRegion(),
        ];
      }

      // Sort by category and label.
      usort($layouts, function ($a, $b) {
        $categoryCompare = strcmp($a['category'], $b['category']);
        if ($categoryCompare !== 0) {
          return $categoryCompare;
        }
        return strcmp($a['label'], $b['label']);
      });

      return [
        'success' => TRUE,
        'data' => [
          'layouts' => $layouts,
          'count' => count($layouts),
        ],
      ];
    }
    catch (\Exception $e) {
      return [
        'success' => FALSE,
        'error' => 'Failed to list layout plugins: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Load an entity view display.
   *
   * @param string $entityType
   *   The entity type.
   * @param string $bundle
   *   The bundle.
   * @param string $viewMode
   *   The view mode (default: 'default').
   *
   * @return \Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay|null
   *   The display entity or NULL.
   */
  protected function loadDisplay(string $entityType, string $bundle, string $viewMode = 'default') {
    $displayId = "$entityType.$bundle.$viewMode";
    return $this->entityTypeManager->getStorage('entity_view_display')->load($displayId);
  }

  /**
   * Get or create an entity view display.
   *
   * @param string $entityType
   *   The entity type.
   * @param string $bundle
   *   The bundle.
   * @param string $viewMode
   *   The view mode (default: 'default').
   *
   * @return \Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay|null
   *   The display entity or NULL.
   */
  protected function getOrCreateDisplay(string $entityType, string $bundle, string $viewMode = 'default') {
    $display = $this->loadDisplay($entityType, $bundle, $viewMode);
    if (!$display) {
      // Create the display if it doesn't exist.
      $display = $this->entityDisplayRepository->getViewDisplay($entityType, $bundle, $viewMode);
      $display->save();
    }
    return $display;
  }

}
