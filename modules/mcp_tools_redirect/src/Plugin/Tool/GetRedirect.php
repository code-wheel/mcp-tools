<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_redirect\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_redirect\Service\RedirectService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for getting redirect details.
 *
 * @Tool(
 *   id = "mcp_redirect_get",
 *   label = @Translation("Get Redirect"),
 *   description = @Translation("Get details of a specific redirect by ID."),
 *   category = "redirect",
 * )
 */
class GetRedirect extends ToolPluginBase implements ContainerFactoryPluginInterface {

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
    $id = $input['id'] ?? 0;

    if (empty($id)) {
      return ['success' => FALSE, 'error' => 'Redirect ID is required.'];
    }

    return $this->redirectService->getRedirect((int) $id);
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'id' => [
        'type' => 'integer',
        'label' => 'Redirect ID',
        'description' => 'The redirect ID to retrieve.',
        'required' => TRUE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'id' => ['type' => 'integer', 'label' => 'Redirect ID'],
      'source' => ['type' => 'string', 'label' => 'Source Path'],
      'destination' => ['type' => 'string', 'label' => 'Destination'],
      'status_code' => ['type' => 'integer', 'label' => 'Status Code'],
      'language' => ['type' => 'string', 'label' => 'Language'],
      'count' => ['type' => 'integer', 'label' => 'Hit Count'],
    ];
  }

}
