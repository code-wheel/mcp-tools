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
 * Tool for creating image styles.
 *
 * @Tool(
 *   id = "mcp_image_styles_create",
 *   label = @Translation("Create Image Style"),
 *   description = @Translation("Create a new image style."),
 *   category = "image_styles",
 * )
 */
class CreateImageStyle extends ToolPluginBase implements ContainerFactoryPluginInterface {

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
    $accessCheck = $this->accessManager->checkWriteAccess('create', 'image_style');
    if (!$accessCheck['allowed']) {
      return [
        'success' => FALSE,
        'error' => $accessCheck['reason'],
        'code' => $accessCheck['code'] ?? 'ACCESS_DENIED',
      ];
    }

    $id = $input['id'] ?? '';
    $label = $input['label'] ?? '';

    if (empty($id) || empty($label)) {
      return [
        'success' => FALSE,
        'error' => 'Both id and label are required.',
      ];
    }

    $result = $this->imageStyleService->createImageStyle($id, $label);

    if ($result['success']) {
      $this->auditLogger->log('create', 'image_style', $id, [
        'label' => $label,
      ]);
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'id' => [
        'type' => 'string',
        'label' => 'Style ID',
        'description' => 'Machine name (lowercase letters, numbers, underscores only).',
        'required' => TRUE,
      ],
      'label' => [
        'type' => 'string',
        'label' => 'Label',
        'description' => 'Human-readable label for the style.',
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
