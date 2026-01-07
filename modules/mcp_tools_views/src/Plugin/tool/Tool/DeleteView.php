<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_views\Plugin\tool\Tool;

use Drupal\mcp_tools_views\Service\ViewsService;
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
  id: 'mcp_delete_view',
  label: new TranslatableMarkup('Delete View'),
  description: new TranslatableMarkup('Delete a view. Core views are protected.'),
  operation: ToolOperation::Write,
  destructive: TRUE,
  input_definitions: [
    'id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('View Machine Name'),
      description: new TranslatableMarkup('ID of view to delete. DESTRUCTIVE: All displays and config permanently removed.'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'id' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('View ID'),
      description: new TranslatableMarkup('Machine name of the deleted view.'),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup('Success confirmation. View is permanently deleted.'),
    ),
  ],
)]
class DeleteView extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'views';


  protected ViewsService $viewsService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->viewsService = $container->get('mcp_tools_views.views');
    return $instance;
  }

  protected function executeLegacy(array $input): array {
    $id = $input['id'] ?? '';

    if (empty($id)) {
      return ['success' => FALSE, 'error' => 'View id is required.'];
    }

    return $this->viewsService->deleteView($id);
  }


}
