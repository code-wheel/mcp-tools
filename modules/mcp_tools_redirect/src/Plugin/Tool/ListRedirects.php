<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_redirect\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_redirect\Service\RedirectService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for listing all redirects.
 *
 * @Tool(
 *   id = "mcp_redirect_list",
 *   label = @Translation("List Redirects"),
 *   description = @Translation("List all URL redirects with pagination."),
 *   category = "redirect",
 * )
 */
class ListRedirects extends ToolPluginBase implements ContainerFactoryPluginInterface {

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
    $limit = $input['limit'] ?? 100;
    $offset = $input['offset'] ?? 0;

    return $this->redirectService->listRedirects((int) $limit, (int) $offset);
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'limit' => [
        'type' => 'integer',
        'label' => 'Limit',
        'description' => 'Maximum number of redirects to return (default: 100).',
        'required' => FALSE,
      ],
      'offset' => [
        'type' => 'integer',
        'label' => 'Offset',
        'description' => 'Number of redirects to skip for pagination (default: 0).',
        'required' => FALSE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'total' => ['type' => 'integer', 'label' => 'Total Redirects'],
      'limit' => ['type' => 'integer', 'label' => 'Limit'],
      'offset' => ['type' => 'integer', 'label' => 'Offset'],
      'redirects' => ['type' => 'array', 'label' => 'Redirects'],
    ];
  }

}
