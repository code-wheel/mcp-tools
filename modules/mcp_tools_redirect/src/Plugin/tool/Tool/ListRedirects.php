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
  id: 'mcp_redirect_list',
  label: new TranslatableMarkup('List Redirects'),
  description: new TranslatableMarkup('List all URL redirects with pagination.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'limit' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Limit'),
      description: new TranslatableMarkup('Maximum number of redirects to return (default: 100).'),
      required: FALSE,
    ),
    'offset' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Offset'),
      description: new TranslatableMarkup('Number of redirects to skip for pagination (default: 0).'),
      required: FALSE,
    ),
  ],
  output_definitions: [
    'total' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total Redirects'),
      description: new TranslatableMarkup('Total number of redirects in the system across all pages. Use with limit and offset for pagination calculations.'),
    ),
    'limit' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Limit'),
      description: new TranslatableMarkup('Maximum number of redirects returned in this response. Default is 100.'),
    ),
    'offset' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Offset'),
      description: new TranslatableMarkup('Number of redirects skipped before this page. Use offset + limit for the next page.'),
    ),
    'redirects' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Redirects'),
      description: new TranslatableMarkup('Array of redirect objects, each containing id, source, destination, status_code, language, and count. Use id with GetRedirect for full details.'),
    ),
  ],
)]
class ListRedirects extends McpToolsToolBase {

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
    $limit = $input['limit'] ?? 100;
    $offset = $input['offset'] ?? 0;

    return $this->redirectService->listRedirects((int) $limit, (int) $offset);
  }

}
