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
  id: 'mcp_create_webform',
  label: new TranslatableMarkup('Create Webform'),
  description: new TranslatableMarkup('Create a new webform with elements.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Webform machine name'),
      description: new TranslatableMarkup('Unique machine name for the webform (lowercase, underscores only, e.g., "contact_form").'),
      required: TRUE,
    ),
    'title' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Title'),
      description: new TranslatableMarkup('Human-readable title displayed to users (e.g., "Contact Form").'),
      required: TRUE,
    ),
    'elements' => new InputDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Elements definition'),
      description: new TranslatableMarkup('YAML-style element definitions. Keys are element names, values are element config with #type, #title, etc.'),
      required: FALSE,
    ),
  ],
  output_definitions: [
    'id' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Webform ID'),
      description: new TranslatableMarkup('Machine name of the created webform. Use with GetWebform, UpdateWebform, or DeleteWebform.'),
    ),
    'title' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Title'),
      description: new TranslatableMarkup('Title of the created webform.'),
    ),
    'status' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Status'),
      description: new TranslatableMarkup('Webform status: "open" (accepting submissions) or "closed". Use UpdateWebform to change.'),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup('Human-readable confirmation of the webform creation.'),
    ),
  ],
)]
class CreateWebform extends McpToolsToolBase {

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
    $id = $input['id'] ?? '';
    $title = $input['title'] ?? '';

    if (empty($id) || empty($title)) {
      return ['success' => FALSE, 'error' => 'Both id and title are required.'];
    }

    $elements = $input['elements'] ?? [];

    return $this->webformService->createWebform($id, $title, $elements);
  }

}
