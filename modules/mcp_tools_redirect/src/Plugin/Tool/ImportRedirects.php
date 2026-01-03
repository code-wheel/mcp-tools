<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_redirect\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_redirect\Service\RedirectService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for bulk importing redirects.
 *
 * @Tool(
 *   id = "mcp_redirect_import",
 *   label = @Translation("Import Redirects"),
 *   description = @Translation("Bulk import multiple URL redirects. This is a write operation."),
 *   category = "redirect",
 * )
 */
class ImportRedirects extends ToolPluginBase implements ContainerFactoryPluginInterface {

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
    $redirects = $input['redirects'] ?? [];

    if (empty($redirects)) {
      return ['success' => FALSE, 'error' => 'Redirects array is required.'];
    }

    if (!is_array($redirects)) {
      return ['success' => FALSE, 'error' => 'Redirects must be an array of redirect objects.'];
    }

    return $this->redirectService->importRedirects($redirects);
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'redirects' => [
        'type' => 'array',
        'label' => 'Redirects',
        'description' => 'Array of redirect objects. Each object should have: source (required), destination (required), status_code (optional, default 301), language (optional).',
        'required' => TRUE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'total_requested' => ['type' => 'integer', 'label' => 'Total Requested'],
      'created_count' => ['type' => 'integer', 'label' => 'Created Count'],
      'skipped_count' => ['type' => 'integer', 'label' => 'Skipped Count'],
      'error_count' => ['type' => 'integer', 'label' => 'Error Count'],
      'created' => ['type' => 'array', 'label' => 'Created Redirects'],
      'skipped' => ['type' => 'array', 'label' => 'Skipped Redirects'],
      'errors' => ['type' => 'array', 'label' => 'Errors'],
      'message' => ['type' => 'string', 'label' => 'Result Message'],
    ];
  }

}
