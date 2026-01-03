<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_redirect\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_redirect\Service\RedirectService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for creating a new redirect.
 *
 * @Tool(
 *   id = "mcp_redirect_create",
 *   label = @Translation("Create Redirect"),
 *   description = @Translation("Create a new URL redirect. This is a write operation."),
 *   category = "redirect",
 * )
 */
class CreateRedirect extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected RedirectService $redirectService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->redirectService = $container->get('mcp_tools_redirect.redirect');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $input = []): array {
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

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'source' => [
        'type' => 'string',
        'label' => 'Source Path',
        'description' => 'The source path to redirect from (e.g., "old-page" or "/old-page").',
        'required' => TRUE,
      ],
      'destination' => [
        'type' => 'string',
        'label' => 'Destination',
        'description' => 'The destination path or URL to redirect to (e.g., "/new-page" or "https://example.com").',
        'required' => TRUE,
      ],
      'status_code' => [
        'type' => 'integer',
        'label' => 'Status Code',
        'description' => 'HTTP redirect status code: 301 (permanent), 302 (temporary), 303, or 307. Default: 301.',
        'required' => FALSE,
      ],
      'language' => [
        'type' => 'string',
        'label' => 'Language',
        'description' => 'Language code for language-specific redirect (e.g., "en", "de"). Leave empty for language-neutral.',
        'required' => FALSE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'redirect' => ['type' => 'object', 'label' => 'Created Redirect'],
      'message' => ['type' => 'string', 'label' => 'Result Message'],
    ];
  }

}
