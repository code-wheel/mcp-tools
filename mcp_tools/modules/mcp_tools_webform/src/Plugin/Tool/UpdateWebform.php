<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_webform\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_webform\Service\WebformService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for updating a webform.
 *
 * @Tool(
 *   id = "mcp_update_webform",
 *   label = @Translation("Update Webform"),
 *   description = @Translation("Update webform settings and elements."),
 *   category = "webform",
 * )
 */
class UpdateWebform extends ToolPluginBase implements ContainerFactoryPluginInterface {

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

    $updates = [];

    if (isset($input['title'])) {
      $updates['title'] = $input['title'];
    }
    if (isset($input['description'])) {
      $updates['description'] = $input['description'];
    }
    if (isset($input['status'])) {
      $updates['status'] = $input['status'];
    }
    if (isset($input['elements'])) {
      $updates['elements'] = $input['elements'];
    }
    if (isset($input['settings'])) {
      $updates['settings'] = $input['settings'];
    }

    if (empty($updates)) {
      return ['success' => FALSE, 'error' => 'No updates provided.'];
    }

    return $this->webformService->updateWebform($id, $updates);
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'id' => ['type' => 'string', 'label' => 'Webform ID', 'required' => TRUE],
      'title' => ['type' => 'string', 'label' => 'New title', 'required' => FALSE],
      'description' => ['type' => 'string', 'label' => 'New description', 'required' => FALSE],
      'status' => ['type' => 'string', 'label' => 'Status (open/closed)', 'required' => FALSE],
      'elements' => ['type' => 'object', 'label' => 'Elements definition', 'required' => FALSE],
      'settings' => ['type' => 'object', 'label' => 'Settings', 'required' => FALSE],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'id' => ['type' => 'string', 'label' => 'Webform ID'],
      'title' => ['type' => 'string', 'label' => 'Title'],
      'status' => ['type' => 'string', 'label' => 'Status'],
      'message' => ['type' => 'string', 'label' => 'Result Message'],
    ];
  }

}
