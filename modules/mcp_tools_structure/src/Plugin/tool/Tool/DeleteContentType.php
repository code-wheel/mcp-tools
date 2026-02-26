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
  id: 'mcp_structure_delete_content_type',
  label: new TranslatableMarkup('Delete Content Type'),
  description: new TranslatableMarkup('Delete a content type. Will fail if content exists unless force=true.'),
  operation: ToolOperation::Write,
  destructive: TRUE,
  input_definitions: [
    'id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Content Type ID'),
      description: new TranslatableMarkup('Machine name of the content type to delete. Use ListContentTypes to see types.'),
      required: TRUE,
    ),
    'force' => new InputDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Force Delete'),
      description: new TranslatableMarkup('WARNING: If true, deletes type even if content exists. All content of this type will be deleted. Default false.'),
      required: FALSE,
      default_value: FALSE,
    ),
  ],
  output_definitions: [
    'id' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Deleted Content Type ID'),
      description: new TranslatableMarkup('Machine name of the deleted content type.'),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup('Confirmation or error message.'),
    ),
  ],
)]
class DeleteContentType extends McpToolsToolBase {

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
      return ['success' => FALSE, 'error' => 'Content type id is required.'];
    }

    return $this->contentTypeService->deleteContentType($id, $input['force'] ?? FALSE);
  }

}
