<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_image_styles\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\image\ImageEffectManager;
use Drupal\image\ImageStyleInterface;

/**
 * Service for image style operations.
 */
class ImageStyleService {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ImageEffectManager $imageEffectManager,
  ) {}

  /**
   * List all image styles.
   *
   * @return array
   *   List of image styles with their configurations.
   */
  public function listImageStyles(): array {
    $storage = $this->entityTypeManager->getStorage('image_style');
    $styles = $storage->loadMultiple();

    $result = [];
    foreach ($styles as $style) {
      assert($style instanceof ImageStyleInterface);
      $effects = [];
      foreach ($style->getEffects() as $effect) {
        $effects[] = [
          'uuid' => $effect->getUuid(),
          'id' => $effect->getPluginId(),
          'label' => $effect->label(),
          'weight' => $effect->getWeight(),
          'configuration' => $effect->getConfiguration(),
        ];
      }

      $result[] = [
        'id' => $style->id(),
        'label' => $style->label(),
        'effects_count' => count($effects),
        'effects' => $effects,
      ];
    }

    return [
      'total' => count($result),
      'styles' => $result,
    ];
  }

  /**
   * Get a specific image style.
   *
   * @param string $styleId
   *   The image style ID.
   *
   * @return array|null
   *   The image style details or null if not found.
   */
  public function getImageStyle(string $styleId): ?array {
    $storage = $this->entityTypeManager->getStorage('image_style');
    $style = $storage->load($styleId);

    if (!$style instanceof ImageStyleInterface) {
      return NULL;
    }

    $effects = [];
    foreach ($style->getEffects() as $effect) {
      $effects[] = [
        'uuid' => $effect->getUuid(),
        'id' => $effect->getPluginId(),
        'label' => $effect->label(),
        'weight' => $effect->getWeight(),
        'configuration' => $effect->getConfiguration(),
      ];
    }

    return [
      'id' => $style->id(),
      'label' => $style->label(),
      'effects' => $effects,
    ];
  }

  /**
   * Create a new image style.
   *
   * @param string $id
   *   Machine name for the style.
   * @param string $label
   *   Human-readable label.
   *
   * @return array
   *   Result with created style info.
   */
  public function createImageStyle(string $id, string $label): array {
    $storage = $this->entityTypeManager->getStorage('image_style');

    // Check if already exists.
    if ($storage->load($id)) {
      return [
        'success' => FALSE,
        'error' => "Image style '$id' already exists.",
        'code' => 'ALREADY_EXISTS',
      ];
    }

    // Validate machine name.
    if (!preg_match('/^[a-z0-9_]+$/', $id)) {
      return [
        'success' => FALSE,
        'error' => "Invalid machine name '$id'. Use only lowercase letters, numbers, and underscores.",
        'code' => 'VALIDATION_ERROR',
      ];
    }

    $style = $storage->create([
      'name' => $id,
      'label' => $label,
    ]);
    $style->save();

    return [
      'success' => TRUE,
      'message' => "Image style '$label' created successfully.",
      'id' => $style->id(),
      'label' => $style->label(),
    ];
  }

  /**
   * Delete an image style.
   *
   * @param string $styleId
   *   The image style ID.
   * @param bool $force
   *   Whether to force deletion.
   *
   * @return array
   *   Result of the deletion.
   */
  public function deleteImageStyle(string $styleId, bool $force = FALSE): array {
    $storage = $this->entityTypeManager->getStorage('image_style');
    $style = $storage->load($styleId);

    if (!$style instanceof ImageStyleInterface) {
      return [
        'success' => FALSE,
        'error' => "Image style '$styleId' not found.",
        'code' => 'NOT_FOUND',
      ];
    }

    // Check for usage in fields.
    $usage = $this->checkImageStyleUsage($styleId);
    if (!empty($usage) && !$force) {
      return [
        'success' => FALSE,
        'error' => "Image style '$styleId' is used in " . count($usage) . " field(s). Use force=true to delete anyway.",
        'code' => 'ENTITY_IN_USE',
        'usage' => $usage,
      ];
    }

    $label = $style->label();
    $style->delete();

    return [
      'success' => TRUE,
      'message' => "Image style '$label' deleted successfully.",
      'deleted_id' => $styleId,
    ];
  }

  /**
   * Add an effect to an image style.
   *
   * @param string $styleId
   *   The image style ID.
   * @param string $effectId
   *   The effect plugin ID (e.g., 'image_scale', 'image_crop').
   * @param array $configuration
   *   Effect configuration.
   *
   * @return array
   *   Result of adding the effect.
   */
  public function addImageEffect(string $styleId, string $effectId, array $configuration = []): array {
    $storage = $this->entityTypeManager->getStorage('image_style');
    $style = $storage->load($styleId);

    if (!$style instanceof ImageStyleInterface) {
      return [
        'success' => FALSE,
        'error' => "Image style '$styleId' not found.",
        'code' => 'NOT_FOUND',
      ];
    }

    // Validate effect exists.
    $definitions = $this->imageEffectManager->getDefinitions();
    if (!isset($definitions[$effectId])) {
      return [
        'success' => FALSE,
        'error' => "Unknown image effect '$effectId'. Use list_image_effects to see available effects.",
        'code' => 'VALIDATION_ERROR',
        'available_effects' => array_keys($definitions),
      ];
    }

    // Add effect configuration.
    $configuration['id'] = $effectId;
    $effectConfig = [
      'id' => $effectId,
      'data' => $configuration,
    ];

    $uuid = $style->addImageEffect($effectConfig);
    $style->save();

    return [
      'success' => TRUE,
      'message' => "Effect '$effectId' added to image style '$styleId'.",
      'effect_uuid' => $uuid,
      'style_id' => $styleId,
    ];
  }

  /**
   * Remove an effect from an image style.
   *
   * @param string $styleId
   *   The image style ID.
   * @param string $effectUuid
   *   The UUID of the effect to remove.
   *
   * @return array
   *   Result of removing the effect.
   */
  public function removeImageEffect(string $styleId, string $effectUuid): array {
    $storage = $this->entityTypeManager->getStorage('image_style');
    $style = $storage->load($styleId);

    if (!$style instanceof ImageStyleInterface) {
      return [
        'success' => FALSE,
        'error' => "Image style '$styleId' not found.",
        'code' => 'NOT_FOUND',
      ];
    }

    // Check effect exists.
    $effect = $style->getEffect($effectUuid);
    if (!$effect) {
      return [
        'success' => FALSE,
        'error' => "Effect '$effectUuid' not found in image style '$styleId'.",
        'code' => 'NOT_FOUND',
      ];
    }

    $effectLabel = $effect->label();
    $style->deleteImageEffect($effect);
    $style->save();

    return [
      'success' => TRUE,
      'message' => "Effect '$effectLabel' removed from image style '$styleId'.",
      'removed_uuid' => $effectUuid,
    ];
  }

  /**
   * List available image effects.
   *
   * @return array
   *   List of available image effect plugins.
   */
  public function listImageEffects(): array {
    $definitions = $this->imageEffectManager->getDefinitions();
    $effects = [];

    foreach ($definitions as $id => $definition) {
      $effects[] = [
        'id' => $id,
        'label' => (string) $definition['label'],
        'description' => (string) ($definition['description'] ?? ''),
      ];
    }

    return [
      'total' => count($effects),
      'effects' => $effects,
    ];
  }

  /**
   * Check if an image style is used in any field configurations.
   *
   * @param string $styleId
   *   The image style ID.
   *
   * @return array
   *   List of fields using this style.
   */
  protected function checkImageStyleUsage(string $styleId): array {
    $usage = [];

    // Check field formatters and widgets.
    $fieldConfigStorage = $this->entityTypeManager->getStorage('field_config');
    $fieldConfigs = $fieldConfigStorage->loadMultiple();

    foreach ($fieldConfigs as $fieldConfig) {
      $settings = $fieldConfig->getSettings();
      // Check for image_style in settings.
      if (!empty($settings['preview_image_style']) && $settings['preview_image_style'] === $styleId) {
        $usage[] = $fieldConfig->id();
      }
    }

    // Check entity view displays.
    $viewDisplayStorage = $this->entityTypeManager->getStorage('entity_view_display');
    $viewDisplays = $viewDisplayStorage->loadMultiple();

    foreach ($viewDisplays as $display) {
      $components = $display->getComponents();
      foreach ($components as $fieldName => $component) {
        if (isset($component['settings']['image_style']) && $component['settings']['image_style'] === $styleId) {
          $usage[] = $display->id() . ':' . $fieldName;
        }
      }
    }

    return array_unique($usage);
  }

}
