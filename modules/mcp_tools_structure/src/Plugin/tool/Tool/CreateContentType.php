<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_structure\Plugin\tool\Tool;

use Drupal\mcp_tools_structure\Service\ContentTypeService;
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
  id: 'mcp_structure_create_content_type',
  label: new TranslatableMarkup('Create Content Type'),
  description: new TranslatableMarkup('Create a new content type with optional body field.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Machine Name'),
      description: new TranslatableMarkup('Lowercase, underscores (e.g., "blog_post")'),
      required: TRUE,
    ),
    'label' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Label'),
      description: new TranslatableMarkup('Human-readable name (e.g., "Blog Post")'),
      required: TRUE,
    ),
    'description' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Description'),
      description: new TranslatableMarkup('Description shown to content editors explaining this type.'),
      required: FALSE,
    ),
    'create_body' => new InputDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Create Body Field'),
      description: new TranslatableMarkup('True to add a body field with text format. Defaults to true.'),
      required: FALSE,
      default_value: TRUE,
    ),
  ],
  output_definitions: [
    'id' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Content Type ID'),
      description: new TranslatableMarkup('Machine name of the created content type. Use with AddField and CreateContent.'),
    ),
    'label' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Content Type Label'),
      description: new TranslatableMarkup('Human-readable label of the content type.'),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup('Success or error message.'),
    ),
  ],
)]
class CreateContentType extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'structure';


  /**
   * The content type service.
   *
   * @var \Drupal\mcp_tools_structure\Service\ContentTypeService
   */
  protected ContentTypeService $contentTypeService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->contentTypeService = $container->get('mcp_tools_structure.content_type');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeLegacy(array $input): array {
    $id = $input['id'] ?? '';
    $label = $input['label'] ?? '';

    if (empty($id) || empty($label)) {
      return ['success' => FALSE, 'error' => 'Both id and label are required.'];
    }

    return $this->contentTypeService->createContentType($id, $label, [
      'description' => $input['description'] ?? '',
      'create_body' => $input['create_body'] ?? TRUE,
    ]);
  }

}
