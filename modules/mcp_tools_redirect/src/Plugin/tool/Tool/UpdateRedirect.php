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
  id: 'mcp_redirect_update',
  label: new TranslatableMarkup('Update Redirect'),
  description: new TranslatableMarkup('Update an existing URL redirect. This is a write operation.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'id' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Redirect ID'),
      description: new TranslatableMarkup('The redirect ID to update.'),
      required: TRUE,
    ),
    'source' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Source Path'),
      description: new TranslatableMarkup('New source path to redirect from.'),
      required: FALSE,
    ),
    'destination' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Destination'),
      description: new TranslatableMarkup('New destination path or URL to redirect to.'),
      required: FALSE,
    ),
    'status_code' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Status Code'),
      description: new TranslatableMarkup('New HTTP redirect status code (301, 302, 303, or 307).'),
      required: FALSE,
    ),
    'language' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Language'),
      description: new TranslatableMarkup('New language code for the redirect.'),
      required: FALSE,
    ),
  ],
  output_definitions: [
    'redirect' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Updated Redirect'),
      description: new TranslatableMarkup('The updated redirect object with current values for id, source, destination, status_code, and language. Use with GetRedirect to verify changes.'),
    ),
    'updated_fields' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Updated Fields'),
      description: new TranslatableMarkup('List of field names that were modified (e.g., ["source", "destination"]). Empty if no changes were made.'),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup('Human-readable confirmation message describing what was updated.'),
    ),
  ],
)]
class UpdateRedirect extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'redirect';


  /**
   * The redirect service.
   *
   * @var \Drupal\mcp_tools_redirect\Service\RedirectService
   */
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

    $values = [];

    if (isset($input['source'])) {
      $values['source'] = $input['source'];
    }
    if (isset($input['destination'])) {
      $values['destination'] = $input['destination'];
    }
    if (isset($input['status_code'])) {
      $values['status_code'] = (int) $input['status_code'];
    }
    if (isset($input['language'])) {
      $values['language'] = $input['language'];
    }

    if (empty($values)) {
      return [
        'success' => FALSE,
        'error' => 'At least one field to update is required'
        . ' (source, destination, status_code, or language).',
      ];
    }

    return $this->redirectService->updateRedirect((int) $id, $values);
  }

}
