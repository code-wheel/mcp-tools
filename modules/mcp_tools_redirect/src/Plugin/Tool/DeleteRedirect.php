<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_redirect\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_redirect\Service\RedirectService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for deleting a redirect.
 *
 * @Tool(
 *   id = "mcp_redirect_delete",
 *   label = @Translation("Delete Redirect"),
 *   description = @Translation("Delete a URL redirect. This is a write operation."),
 *   category = "redirect",
 * )
 */
class DeleteRedirect extends ToolPluginBase implements ContainerFactoryPluginInterface {

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

    return $this->redirectService->deleteRedirect((int) $id);
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'id' => [
        'type' => 'integer',
        'label' => 'Redirect ID',
        'description' => 'The redirect ID to delete.',
        'required' => TRUE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'id' => ['type' => 'integer', 'label' => 'Deleted Redirect ID'],
      'deleted_redirect' => ['type' => 'object', 'label' => 'Deleted Redirect Details'],
      'message' => ['type' => 'string', 'label' => 'Result Message'],
    ];
  }

}
