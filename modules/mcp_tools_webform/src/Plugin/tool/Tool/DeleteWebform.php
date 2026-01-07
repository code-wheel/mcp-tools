<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_webform\Plugin\tool\Tool;

use Drupal\mcp_tools_webform\Service\WebformService;
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
  id: 'mcp_delete_webform',
  label: new TranslatableMarkup('Delete Webform'),
  description: new TranslatableMarkup('Permanently delete a webform and all its submissions.'),
  operation: ToolOperation::Write,
  destructive: TRUE,
  input_definitions: [
    'id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Webform ID'),
      description: new TranslatableMarkup('Machine name of the webform to delete. WARNING: This permanently deletes all submissions.'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'id' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Deleted Webform ID'),
      description: new TranslatableMarkup('Machine name of the deleted webform. This ID is no longer valid.'),
    ),
    'title' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Title'),
      description: new TranslatableMarkup('Title of the deleted webform for confirmation.'),
    ),
    'submissions_deleted' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Submissions Deleted'),
      description: new TranslatableMarkup('Number of submissions that were permanently deleted with the webform.'),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup('Human-readable confirmation of the deletion. WARNING: This cannot be undone.'),
    ),
  ],
)]
class DeleteWebform extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'webform';


  protected WebformService $webformService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->webformService = $container->get('mcp_tools_webform.webform');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeLegacy(array $input): array {
    $id = $input['id'] ?? '';

    if (empty($id)) {
      return ['success' => FALSE, 'error' => 'Webform ID is required.'];
    }

    return $this->webformService->deleteWebform($id);
  }

  

  

}
