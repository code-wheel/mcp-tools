<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_webform\Plugin\tool\Tool;

use Drupal\mcp_tools_webform\Service\WebformService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\tool\TypedData\InputDefinition;
use Drupal\tool\TypedData\OutputDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp_tools\Tool\McpToolsToolBase;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\Tool\ToolOperation;

/**
 * Tool plugin implementation.
 */
#[Tool(
  id: 'mcp_list_webforms',
  label: new TranslatableMarkup('List Webforms'),
  description: new TranslatableMarkup('List all webforms with submission counts.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'search' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Search'),
      description: new TranslatableMarkup('Only webforms with this in their id or title, any case.'),
      required: FALSE,
    ),
    'limit' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Limit'),
      description: new TranslatableMarkup('At most this many webforms.'),
      required: FALSE,
    ),
  ],
  output_definitions: [
    'total' => new OutputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total Webforms'),
      description: new TranslatableMarkup('Total number of webforms in the system.'),
    ),
    'matching' => new OutputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Matching'),
      description: new TranslatableMarkup('With search: how many webforms matched.'),
    ),
    'webforms' => new OutputDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Webforms'),
      description: new TranslatableMarkup('Array of webform objects with id, title, status, and submission_count. Use id with mcp_get_webform for full details.'),
    ),
  ],
)]
class ListWebforms extends McpToolsToolBase {

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
    return $this->webformService->listWebforms(
      isset($input['search']) ? (string) $input['search'] : NULL,
      isset($input['limit']) ? (int) $input['limit'] : NULL,
    );
  }

}
