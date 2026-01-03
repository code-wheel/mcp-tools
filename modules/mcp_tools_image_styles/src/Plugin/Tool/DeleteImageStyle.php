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
 * Tool for deleting image styles.
 *
 * @Tool(
 *   id = "mcp_image_styles_delete",
 *   label = @Translation("Delete Image Style"),
 *   description = @Translation("Delete an image style. Use force=true to delete even if in use."),
 *   category = "image_styles",
 * )
 */
class DeleteImageStyle extends ToolPluginBase implements ContainerFactoryPluginInterface {

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
    $accessCheck = $this->accessManager->checkWriteAccess('delete', 'image_style');
    if (!$accessCheck['allowed']) {
      return [
        'success' => FALSE,
        'error' => $accessCheck['reason'],
        'code' => $accessCheck['code'] ?? 'ACCESS_DENIED',
      ];
    }

    $styleId = $input['style_id'] ?? '';
    $force = !empty($input['force']);

    if (empty($styleId)) {
      return [
        'success' => FALSE,
        'error' => 'style_id is required.',
      ];
    }

    $result = $this->imageStyleService->deleteImageStyle($styleId, $force);

    if ($result['success']) {
      $this->auditLogger->log('delete', 'image_style', $styleId, [
        'force' => $force,
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
        'description' => 'The machine name of the image style to delete.',
        'required' => TRUE,
      ],
      'force' => [
        'type' => 'boolean',
        'label' => 'Force',
        'description' => 'Delete even if the style is in use.',
        'required' => FALSE,
        'default' => FALSE,
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
