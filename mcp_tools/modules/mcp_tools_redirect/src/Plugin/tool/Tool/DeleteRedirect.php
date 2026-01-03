<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_redirect\Plugin\tool\Tool;

use Drupal\mcp_tools_redirect\Service\RedirectService;
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
  id: 'mcp_redirect_delete',
  label: new TranslatableMarkup('Delete Redirect'),
  description: new TranslatableMarkup('Delete a URL redirect. This is a write operation.'),
  operation: ToolOperation::Write,
  destructive: TRUE,
  input_definitions: [
    'id' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Redirect ID'),
      description: new TranslatableMarkup('The redirect ID to delete.'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'id' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Deleted Redirect ID'),
      description: new TranslatableMarkup(''),
    ),
    'deleted_redirect' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Deleted Redirect Details'),
      description: new TranslatableMarkup(''),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup(''),
    ),
  ],
)]
class DeleteRedirect extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'redirect';


  protected RedirectService $redirectService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->redirectService = $container->get('mcp_tools_redirect.redirect');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeLegacy(array $input): array {
    $id = $input['id'] ?? 0;

    if (empty($id)) {
      return ['success' => FALSE, 'error' => 'Redirect ID is required.'];
    }

    return $this->redirectService->deleteRedirect((int) $id);
  }

  

  

}
