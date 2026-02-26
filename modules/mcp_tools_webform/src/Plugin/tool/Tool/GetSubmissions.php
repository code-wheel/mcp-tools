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
  id: 'mcp_get_webform_submissions',
  label: new TranslatableMarkup('Get Webform Submissions'),
  description: new TranslatableMarkup('Get submissions for a webform with pagination.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'webform_id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Webform ID'),
      description: new TranslatableMarkup('Machine name of the webform to get submissions for.'),
      required: TRUE,
    ),
    'limit' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Limit (max 100)'),
      description: new TranslatableMarkup('Maximum number of submissions to return (default: 50, max: 100).'),
      required: FALSE,
    ),
    'offset' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Offset'),
      description: new TranslatableMarkup('Number of submissions to skip for pagination (default: 0).'),
      required: FALSE,
    ),
  ],
  output_definitions: [
    'webform_id' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Webform ID'),
      description: new TranslatableMarkup('Machine name of the webform these submissions belong to.'),
    ),
    'webform_title' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Webform Title'),
      description: new TranslatableMarkup('Human-readable title of the webform.'),
    ),
    'total' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total Submissions'),
      description: new TranslatableMarkup('Total number of submissions for this webform across all pages.'),
    ),
    'limit' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Limit'),
      description: new TranslatableMarkup('Maximum submissions returned in this response.'),
    ),
    'offset' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Offset'),
      description: new TranslatableMarkup('Number of submissions skipped. Use offset + limit for next page.'),
    ),
    'submissions' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Submissions'),
      description: new TranslatableMarkup('Array of submission objects with sid, created, remote_addr, and data. Use sid with DeleteSubmission to remove.'),
    ),
  ],
)]
class GetSubmissions extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'webform';


  /**
   * The webform service.
   *
   * @var \Drupal\mcp_tools_webform\Service\WebformService
   */
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
    $webformId = $input['webform_id'] ?? '';

    if (empty($webformId)) {
      return ['success' => FALSE, 'error' => 'Webform ID is required.'];
    }

    $limit = (int) ($input['limit'] ?? 50);
    $offset = (int) ($input['offset'] ?? 0);

    // Enforce reasonable limits.
    $limit = min(max($limit, 1), 100);
    $offset = max($offset, 0);

    return $this->webformService->getSubmissions($webformId, $limit, $offset);
  }

}
