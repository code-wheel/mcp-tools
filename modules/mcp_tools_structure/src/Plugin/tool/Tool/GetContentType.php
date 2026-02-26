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
 * Tool plugin to get content type details with fields.
 */
#[Tool(
  id: 'mcp_structure_get_content_type',
  label: new TranslatableMarkup('Get Content Type'),
  description: new TranslatableMarkup('Get detailed content type information including all field definitions. Use this to understand the schema before creating or updating content.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Content Type ID'),
      description: new TranslatableMarkup('Machine name of the content type (e.g., "article", "page"). Use ListContentTypes to see available types.'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'id' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Content Type ID'),
      description: new TranslatableMarkup('Machine name of the content type.'),
    ),
    'label' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Label'),
      description: new TranslatableMarkup('Human-readable name of the content type.'),
    ),
    'description' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Description'),
      description: new TranslatableMarkup('Description of the content type purpose.'),
    ),
    'fields' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Fields'),
      description: new TranslatableMarkup('Array of field definitions with name, label, type, required, cardinality, and type-specific settings (target_type, allowed_values, max_length). Use field names when creating content.'),
    ),
    'field_count' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Field Count'),
      description: new TranslatableMarkup('Number of configurable fields on this content type.'),
    ),
    'new_revision' => new ContextDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('New Revision'),
      description: new TranslatableMarkup('Whether new revisions are created by default.'),
    ),
    'admin_path' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Admin Path'),
      description: new TranslatableMarkup('Path to manage this content type in admin UI.'),
    ),
    'add_content_path' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Add Content Path'),
      description: new TranslatableMarkup('Path to create new content of this type.'),
    ),
  ],
)]
class GetContentType extends McpToolsToolBase {

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

    if (empty($id)) {
      return ['success' => FALSE, 'error' => 'Content type ID is required.'];
    }

    return $this->contentTypeService->getContentType($id);
  }

}
