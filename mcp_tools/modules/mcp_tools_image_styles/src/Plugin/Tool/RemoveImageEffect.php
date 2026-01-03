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
 * Tool for removing effects from image styles.
 *
 * @Tool(
 *   id = "mcp_image_styles_remove_effect",
 *   label = @Translation("Remove Image Effect"),
 *   description = @Translation("Remove an image effect from a style by its UUID."),
 *   category = "image_styles",
 * )
 */
class RemoveImageEffect extends ToolPluginBase implements ContainerFactoryPluginInterface {

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
    $effectUuid = $input['effect_uuid'] ?? '';

    if (empty($styleId) || empty($effectUuid)) {
      return [
        'success' => FALSE,
        'error' => 'Both style_id and effect_uuid are required.',
      ];
    }

    $result = $this->imageStyleService->removeImageEffect($styleId, $effectUuid);

    if ($result['success']) {
      $this->auditLogger->log('update', 'image_style', $styleId, [
        'action' => 'remove_effect',
        'effect_uuid' => $effectUuid,
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
      'effect_uuid' => [
        'type' => 'string',
        'label' => 'Effect UUID',
        'description' => 'The UUID of the effect to remove (from list/get image style).',
        'required' => TRUE,
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
      'message' => [
        'type' => 'string',
        'label' => 'Result message',
      ],
    ];
  }

}
