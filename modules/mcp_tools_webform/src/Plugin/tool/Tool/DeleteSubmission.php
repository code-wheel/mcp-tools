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
  id: 'mcp_delete_webform_submission',
  label: new TranslatableMarkup('Delete Webform Submission'),
  description: new TranslatableMarkup('Permanently delete a webform submission.'),
  operation: ToolOperation::Write,
  destructive: TRUE,
  input_definitions: [
    'sid' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Submission ID'),
      description: new TranslatableMarkup('Numeric submission ID to delete. Use GetSubmissions to find submission IDs. WARNING: This is permanent.'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'sid' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Deleted Submission ID'),
      description: new TranslatableMarkup('The submission ID that was deleted. This ID is no longer valid.'),
    ),
    'webform_id' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Webform ID'),
      description: new TranslatableMarkup('Machine name of the webform the submission belonged to.'),
    ),
    'webform_title' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Webform Title'),
      description: new TranslatableMarkup('Title of the webform for confirmation.'),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup('Human-readable confirmation of the deletion. WARNING: This cannot be undone.'),
    ),
  ],
)]
class DeleteSubmission extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'webform';
  protected const MCP_WRITE_KIND = 'content';


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
    $sid = $input['sid'] ?? 0;

    if (empty($sid)) {
      return ['success' => FALSE, 'error' => 'Submission ID (sid) is required.'];
    }

    return $this->webformService->deleteSubmission((int) $sid);
  }

  

  

}
