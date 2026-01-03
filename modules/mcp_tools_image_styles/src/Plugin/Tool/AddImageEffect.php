<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_image_styles\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\mcp_tools_image_styles\Service\ImageStyleService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for adding effects to image styles.
 *
 * @Tool(
 *   id = "mcp_image_styles_add_effect",
 *   label = @Translation("Add Image Effect"),
 *   description = @Translation("Add an image effect to an existing style (e.g., scale, crop, rotate)."),
 *   category = "image_styles",
 * )
 */
class AddImageEffect extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected ImageStyleService $imageStyleService;
  protected AccessManager $accessManager;
  protected AuditLogger $auditLogger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->imageStyleService = $container->get('mcp_tools_image_styles.image_style_service');
    $instance->accessManager = $container->get('mcp_tools.access_manager');
    $instance->auditLogger = $container->get('mcp_tools.audit_logger');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $input = []): array {
    // Check write access.
    $accessCheck = $this->accessManager->checkWriteAccess('update', 'image_style');
    if (!$accessCheck['allowed']) {
      return [
        'success' => FALSE,
        'error' => $accessCheck['reason'],
        'code' => $accessCheck['code'] ?? 'ACCESS_DENIED',
      ];
    }

    $styleId = $input['style_id'] ?? '';
    $effectId = $input['effect_id'] ?? '';
    $configuration = $input['configuration'] ?? [];

    if (empty($styleId) || empty($effectId)) {
      return [
        'success' => FALSE,
        'error' => 'Both style_id and effect_id are required.',
      ];
    }

    $result = $this->imageStyleService->addImageEffect($styleId, $effectId, $configuration);

    if ($result['success']) {
      $this->auditLogger->log('update', 'image_style', $styleId, [
        'action' => 'add_effect',
        'effect_id' => $effectId,
      ]);
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'style_id' => [
        'type' => 'string',
        'label' => 'Style ID',
        'description' => 'The machine name of the image style.',
        'required' => TRUE,
      ],
      'effect_id' => [
        'type' => 'string',
        'label' => 'Effect ID',
        'description' => 'The effect plugin ID (e.g., image_scale, image_crop, image_resize, image_rotate).',
        'required' => TRUE,
      ],
      'configuration' => [
        'type' => 'object',
        'label' => 'Configuration',
        'description' => 'Effect-specific configuration (e.g., {width: 200, height: 200} for scale).',
        'required' => FALSE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'success' => [
        'type' => 'boolean',
        'label' => 'Success status',
      ],
      'effect_uuid' => [
        'type' => 'string',
        'label' => 'Effect UUID',
      ],
    ];
  }

}
