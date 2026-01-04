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
  id: 'mcp_get_webform',
  label: new TranslatableMarkup('Get Webform'),
  description: new TranslatableMarkup('Get webform details including elements configuration.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Webform ID'),
      description: new TranslatableMarkup('Machine name of the webform to retrieve. Use ListWebforms to find available webforms.'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'id' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Webform ID'),
      description: new TranslatableMarkup('Machine name of the webform.'),
    ),
    'title' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Title'),
      description: new TranslatableMarkup('Human-readable title of the webform.'),
    ),
    'description' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Description'),
      description: new TranslatableMarkup('Administrative description of the webform.'),
    ),
    'status' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Status'),
      description: new TranslatableMarkup('Webform status: "open" (accepting submissions) or "closed".'),
    ),
    'elements' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Elements'),
      description: new TranslatableMarkup('Form element definitions with #type, #title, #required, etc. Use with UpdateWebform to modify.'),
    ),
    'settings' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Settings'),
      description: new TranslatableMarkup('Webform configuration: confirmation settings, submission handling, access controls, etc.'),
    ),
  ],
)]
class GetWebform extends McpToolsToolBase {

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

    return $this->webformService->getWebform($id);
  }

  

  

}
