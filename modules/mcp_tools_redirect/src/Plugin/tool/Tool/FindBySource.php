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
  id: 'mcp_redirect_find',
  label: new TranslatableMarkup('Find Redirect by Source'),
  description: new TranslatableMarkup('Find a redirect by its source path.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'source' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Source Path'),
      description: new TranslatableMarkup('The source path to search for (e.g., "old-page" or "/old-page").'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'found' => new ContextDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Redirect Found'),
      description: new TranslatableMarkup('TRUE if a redirect exists for the source path, FALSE otherwise. Check this before accessing redirect details.'),
    ),
    'redirect' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Redirect Details'),
      description: new TranslatableMarkup('The matching redirect object with id, source, destination, status_code, and language. Use id with UpdateRedirect or DeleteRedirect tools. NULL if not found.'),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup('Human-readable message describing the search result.'),
    ),
  ],
)]
class FindBySource extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'redirect';


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
    $source = $input['source'] ?? '';

    if (empty($source)) {
      return ['success' => FALSE, 'error' => 'Source path is required.'];
    }

    return $this->redirectService->findBySource($source);
  }

  

  

}
