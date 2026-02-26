<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_image_styles\Plugin\tool\Tool;

use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\mcp_tools_image_styles\Service\ImageStyleService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp_tools\Tool\McpToolsToolBase;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinition;

/**
 * Tool plugin implementation.
 */
#[Tool(
  id: 'mcp_image_styles_delete',
  label: new TranslatableMarkup('Delete Image Style'),
  description: new TranslatableMarkup('Delete an image style. Use force=true to delete even if in use.'),
  operation: ToolOperation::Write,
  destructive: TRUE,
  input_definitions: [
    'style_id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Style ID'),
      description: new TranslatableMarkup('The machine name of the image style to delete.'),
      required: TRUE,
    ),
    'force' => new InputDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Force'),
      description: new TranslatableMarkup('Delete even if the style is in use.'),
      required: FALSE,
      default_value: FALSE,
    ),
  ],
  output_definitions: [
    'success' => new ContextDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Success status'),
      description: new TranslatableMarkup('True if style was deleted. Derivative images are also removed.'),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result message'),
      description: new TranslatableMarkup('Confirmation or error if style is in use (use force=true).'),
    ),
  ],
)]
class DeleteImageStyle extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'image_styles';


  /**
   * The image style service.
   *
   * @var \Drupal\mcp_tools_image_styles\Service\ImageStyleService
   */
  protected ImageStyleService $imageStyleService;
  /**
   * The access manager.
   *
   * @var \Drupal\mcp_tools\Service\AccessManager
   */
  protected AccessManager $accessManager;
  /**
   * The audit logger.
   *
   * @var \Drupal\mcp_tools\Service\AuditLogger
   */
  protected AuditLogger $auditLogger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->imageStyleService = $container->get('mcp_tools_image_styles.image_style_service');
    $instance->accessManager = $container->get('mcp_tools.access_manager');
    $instance->auditLogger = $container->get('mcp_tools.audit_logger');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeLegacy(array $input): array {
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

}
