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
  id: 'mcp_update_webform',
  label: new TranslatableMarkup('Update Webform'),
  description: new TranslatableMarkup('Update webform settings and elements.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Webform ID'),
      description: new TranslatableMarkup('Machine name of the webform to update. Use ListWebforms to find available webforms.'),
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
      description: new TranslatableMarkup('Updated YAML-style element definitions. Replaces existing elements. Use GetWebform to see current elements.'),
      required: FALSE,
    ),
    'settings' => new InputDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Settings'),
      description: new TranslatableMarkup('Webform settings: confirmation_type, confirmation_message, submission_limit, etc.'),
      required: FALSE,
    ),
  ],
  output_definitions: [
    'id' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Webform ID'),
      description: new TranslatableMarkup('Machine name of the updated webform.'),
    ),
    'title' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Title'),
      description: new TranslatableMarkup('Current title of the webform after update.'),
    ),
    'status' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Status'),
      description: new TranslatableMarkup('Current status: "open" or "closed".'),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup('Human-readable confirmation of what was updated.'),
    ),
  ],
)]
class UpdateWebform extends McpToolsToolBase {

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

    if (empty($updates)) {
      return ['success' => FALSE, 'error' => 'No updates provided.'];
    }

    return $this->webformService->updateWebform($id, $updates);
  }

  

  

}
