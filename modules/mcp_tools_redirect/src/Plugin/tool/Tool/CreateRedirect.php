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
  id: 'mcp_redirect_create',
  label: new TranslatableMarkup('Create Redirect'),
  description: new TranslatableMarkup('Create a new URL redirect. This is a write operation.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'source' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Source Path'),
      description: new TranslatableMarkup('The source path to redirect from (e.g., "old-page" or "/old-page").'),
      required: TRUE,
    ),
    'destination' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Destination'),
      description: new TranslatableMarkup('The destination path or URL to redirect to (e.g., "/new-page" or "https://example.com").'),
      required: TRUE,
    ),
    'status_code' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Status Code'),
      description: new TranslatableMarkup('HTTP redirect status code: 301 (permanent), 302 (temporary), 303, or 307. Default: 301.'),
      required: FALSE,
    ),
    'language' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Language'),
      description: new TranslatableMarkup('Language code for language-specific redirect (e.g., "en", "de"). Leave empty for language-neutral.'),
      required: FALSE,
    ),
  ],
  output_definitions: [
    'redirect' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Created Redirect'),
      description: new TranslatableMarkup('The created redirect object containing id, source, destination, status_code, and language. Use id with GetRedirect, UpdateRedirect, or DeleteRedirect tools.'),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup('Human-readable confirmation message describing the redirect creation result.'),
    ),
  ],
)]
class CreateRedirect extends McpToolsToolBase {

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
    $destination = $input['destination'] ?? '';
    $statusCode = $input['status_code'] ?? 301;
    $language = $input['language'] ?? NULL;

    if (empty($source)) {
      return ['success' => FALSE, 'error' => 'Source path is required.'];
    }

    if (empty($destination)) {
      return ['success' => FALSE, 'error' => 'Destination is required.'];
    }

    return $this->redirectService->createRedirect($source, $destination, (int) $statusCode, $language);
  }

  

  

}
