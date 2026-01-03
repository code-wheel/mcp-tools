<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_redirect\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_redirect\Service\RedirectService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for finding a redirect by source path.
 *
 * @Tool(
 *   id = "mcp_redirect_find",
 *   label = @Translation("Find Redirect by Source"),
 *   description = @Translation("Find a redirect by its source path."),
 *   category = "redirect",
 * )
 */
class FindBySource extends ToolPluginBase implements ContainerFactoryPluginInterface {

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

    if (empty($source)) {
      return ['success' => FALSE, 'error' => 'Source path is required.'];
    }

    return $this->redirectService->findBySource($source);
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'source' => [
        'type' => 'string',
        'label' => 'Source Path',
        'description' => 'The source path to search for (e.g., "old-page" or "/old-page").',
        'required' => TRUE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'found' => ['type' => 'boolean', 'label' => 'Redirect Found'],
      'redirect' => ['type' => 'object', 'label' => 'Redirect Details'],
      'message' => ['type' => 'string', 'label' => 'Result Message'],
    ];
  }

}
