<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_webform\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_webform\Service\WebformService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for deleting a webform.
 *
 * @Tool(
 *   id = "mcp_delete_webform",
 *   label = @Translation("Delete Webform"),
 *   description = @Translation("Permanently delete a webform and all its submissions."),
 *   category = "webform",
 * )
 */
class DeleteWebform extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected WebformService $webformService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->webformService = $container->get('mcp_tools_webform.webform');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $input = []): array {
    $id = $input['id'] ?? '';

    if (empty($id)) {
      return ['success' => FALSE, 'error' => 'Webform ID is required.'];
    }

    return $this->webformService->deleteWebform($id);
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'id' => ['type' => 'string', 'label' => 'Webform ID', 'required' => TRUE],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'id' => ['type' => 'string', 'label' => 'Deleted Webform ID'],
      'title' => ['type' => 'string', 'label' => 'Title'],
      'submissions_deleted' => ['type' => 'integer', 'label' => 'Submissions Deleted'],
      'message' => ['type' => 'string', 'label' => 'Result Message'],
    ];
  }

}
