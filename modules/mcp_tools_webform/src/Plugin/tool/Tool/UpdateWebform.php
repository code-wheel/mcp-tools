<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_webform\Plugin\tool\Tool;

use Drupal\mcp_tools_webform\Service\WebformService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\tool\TypedData\OutputDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp_tools\Tool\McpToolsToolBase;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinition;

/**
 * Tool plugin implementation.
 */
#[Tool(
  id: 'mcp_update_webform',
  label: new TranslatableMarkup('Update Webform'),
  description: new TranslatableMarkup('Update a webform: its title, status and settings, or its form fields. To add or change single fields use set_elements: only the fields you send change and the others are kept, so there is no need to fetch or resend them.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Webform ID'),
      description: new TranslatableMarkup('Machine name of the webform to update. Use mcp_list_webforms to find available webforms.'),
      required: TRUE,
    ),
    'title' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('New title'),
      description: new TranslatableMarkup('New human-readable title for the webform.'),
      required: FALSE,
    ),
    'description' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('New description'),
      description: new TranslatableMarkup('Administrative description explaining the purpose of this webform.'),
      required: FALSE,
    ),
    'status' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Status (open/closed)'),
      description: new TranslatableMarkup('Set to "open" to accept submissions or "closed" to disable the form.'),
      required: FALSE,
    ),
    'elements' => new InputDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Elements definition'),
      description: new TranslatableMarkup('All of the form\'s elements, keyed by element name, e.g. {"email": {"#type": "email", "#title": "Email"}}. They replace the elements there were: mcp_get_webform shows them. Each needs a #type. To add or change one, use set_elements.'),
      required: FALSE,
    ),
    'set_elements' => new InputDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Elements to add or change'),
      description: new TranslatableMarkup('Add or change some elements and keep the others, keyed by element name, e.g. {"phone": {"#type": "tel", "#title": "Phone"}}. An element of that name is replaced, a new one is added at the end.'),
      required: FALSE,
    ),
    'remove_elements' => new InputDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Elements to remove'),
      description: new TranslatableMarkup('Names of elements to remove, e.g. ["phone"]. The others are kept.'),
      required: FALSE,
    ),
    'settings' => new InputDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Settings'),
      description: new TranslatableMarkup('Webform settings by their names, e.g. {"confirmation_message": "Thanks", "limit_total": 100}.'),
      required: FALSE,
    ),
  ],
  output_definitions: [
    'id' => new OutputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Webform ID'),
      description: new TranslatableMarkup('Machine name of the updated webform.'),
    ),
    'title' => new OutputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Title'),
      description: new TranslatableMarkup('Current title of the webform after update.'),
    ),
    'status' => new OutputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Status'),
      description: new TranslatableMarkup('Current status: "open" or "closed".'),
    ),
    'message' => new OutputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup('Human-readable confirmation of what was updated.'),
    ),
  ],
)]
class UpdateWebform extends McpToolsToolBase {

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

    if (empty($id)) {
      return ['success' => FALSE, 'error' => 'Webform ID is required.'];
    }

    $updates = [];

    if (isset($input['title'])) {
      $updates['title'] = $input['title'];
    }
    if (isset($input['description'])) {
      $updates['description'] = $input['description'];
    }
    if (isset($input['status'])) {
      $updates['status'] = $input['status'];
    }
    if (isset($input['elements'])) {
      $updates['elements'] = $input['elements'];
    }
    if (isset($input['settings'])) {
      $updates['settings'] = $input['settings'];
    }
    foreach (['set_elements', 'remove_elements'] as $key) {
      if (!empty($input[$key])) {
        $updates[$key] = $input[$key];
      }
    }

    if (empty($updates)) {
      return ['success' => FALSE, 'error' => 'No updates provided.'];
    }

    return $this->webformService->updateWebform($id, $updates);
  }

}
