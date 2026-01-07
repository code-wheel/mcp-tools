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
  id: 'mcp_redirect_get',
  label: new TranslatableMarkup('Get Redirect'),
  description: new TranslatableMarkup('Get details of a specific redirect by ID.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'id' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Redirect ID'),
      description: new TranslatableMarkup('The redirect ID to retrieve.'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'id' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Redirect ID'),
      description: new TranslatableMarkup('The unique numeric identifier for this redirect. Use with UpdateRedirect or DeleteRedirect tools.'),
    ),
    'source' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Source Path'),
      description: new TranslatableMarkup('The source path that triggers this redirect (e.g., "old-page" or "/legacy/url"). Does not include the domain.'),
    ),
    'destination' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Destination'),
      description: new TranslatableMarkup('The target path or URL where visitors are redirected (e.g., "/new-page" or "https://example.com/page").'),
    ),
    'status_code' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Status Code'),
      description: new TranslatableMarkup('HTTP redirect status code: 301 (permanent - cached by browsers), 302 (temporary), 303 (see other), or 307 (temporary preserve method).'),
    ),
    'language' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Language'),
      description: new TranslatableMarkup('Language code if this is a language-specific redirect (e.g., "en", "de"), or empty for language-neutral redirects.'),
    ),
    'count' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Hit Count'),
      description: new TranslatableMarkup('Number of times this redirect has been triggered. Useful for identifying high-traffic redirects or unused ones.'),
    ),
  ],
)]
class GetRedirect extends McpToolsToolBase {

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
    $id = $input['id'] ?? 0;

    if (empty($id)) {
      return ['success' => FALSE, 'error' => 'Redirect ID is required.'];
    }

    return $this->redirectService->getRedirect((int) $id);
  }

  

  

}
