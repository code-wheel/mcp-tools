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
  id: 'mcp_redirect_import',
  label: new TranslatableMarkup('Import Redirects'),
  description: new TranslatableMarkup('Bulk import multiple URL redirects. This is a write operation.'),
  operation: ToolOperation::Write,
  destructive: TRUE,
  input_definitions: [
    'redirects' => new InputDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Redirects'),
      description: new TranslatableMarkup('Array of redirect objects. Each object should have: source (required), destination (required), status_code (optional, default 301), language (optional).'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'total_requested' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total Requested'),
      description: new TranslatableMarkup('Total number of redirects submitted in the import request.'),
    ),
    'created_count' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Created Count'),
      description: new TranslatableMarkup('Number of redirects successfully created. These are now active and can be managed with redirect tools.'),
    ),
    'skipped_count' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Skipped Count'),
      description: new TranslatableMarkup('Number of redirects skipped because a redirect for that source path already exists.'),
    ),
    'error_count' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Error Count'),
      description: new TranslatableMarkup('Number of redirects that failed to import due to validation errors or other issues.'),
    ),
    'created' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Created Redirects'),
      description: new TranslatableMarkup('Array of successfully created redirect objects with their assigned IDs. Use these IDs with GetRedirect or UpdateRedirect.'),
    ),
    'skipped' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Skipped Redirects'),
      description: new TranslatableMarkup('Array of skipped redirects with reason. Typically skipped because a redirect already exists for the source. Use FindBySource to check existing.'),
    ),
    'errors' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Errors'),
      description: new TranslatableMarkup('Array of failed imports with error details. Check for missing required fields or invalid values.'),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup('Human-readable summary of the import operation results.'),
    ),
  ],
)]
class ImportRedirects extends McpToolsToolBase {

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
    $redirects = $input['redirects'] ?? [];

    if (empty($redirects)) {
      return ['success' => FALSE, 'error' => 'Redirects array is required.'];
    }

    if (!is_array($redirects)) {
      return ['success' => FALSE, 'error' => 'Redirects must be an array of redirect objects.'];
    }

    return $this->redirectService->importRedirects($redirects);
  }

}
