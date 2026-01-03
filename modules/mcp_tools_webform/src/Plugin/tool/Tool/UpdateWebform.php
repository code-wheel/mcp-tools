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
      description: new TranslatableMarkup(''),
      required: TRUE,
    ),
    'title' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('New title'),
      description: new TranslatableMarkup(''),
      required: FALSE,
    ),
    'description' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('New description'),
      description: new TranslatableMarkup(''),
      required: FALSE,
    ),
    'status' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Status (open/closed)'),
      description: new TranslatableMarkup(''),
      required: FALSE,
    ),
    'elements' => new InputDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Elements definition'),
      description: new TranslatableMarkup(''),
      required: FALSE,
    ),
    'settings' => new InputDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Settings'),
      description: new TranslatableMarkup(''),
      required: FALSE,
    ),
  ],
  output_definitions: [
    'id' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Webform ID'),
      description: new TranslatableMarkup(''),
    ),
    'title' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Title'),
      description: new TranslatableMarkup(''),
    ),
    'status' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Status'),
      description: new TranslatableMarkup(''),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup(''),
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
