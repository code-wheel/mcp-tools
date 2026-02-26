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
  id: 'mcp_image_styles_add_effect',
  label: new TranslatableMarkup('Add Image Effect'),
  description: new TranslatableMarkup('Add an image effect to an existing style (e.g., scale, crop, rotate).'),
  operation: ToolOperation::Write,
  input_definitions: [
    'style_id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Style ID'),
      description: new TranslatableMarkup('The machine name of the image style.'),
      required: TRUE,
    ),
    'effect_id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Effect ID'),
      description: new TranslatableMarkup('The effect plugin ID (e.g., image_scale, image_crop, image_resize, image_rotate).'),
      required: TRUE,
    ),
    'configuration' => new InputDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Configuration'),
      description: new TranslatableMarkup('Effect-specific configuration (e.g., {width: 200, height: 200} for scale).'),
      required: FALSE,
    ),
  ],
  output_definitions: [
    'success' => new ContextDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Success status'),
      description: new TranslatableMarkup('True if effect was added to the style.'),
    ),
    'effect_uuid' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Effect UUID'),
      description: new TranslatableMarkup('UUID of the added effect. Use with RemoveImageEffect to remove.'),
    ),
  ],
)]
class AddImageEffect extends McpToolsToolBase {

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

}
