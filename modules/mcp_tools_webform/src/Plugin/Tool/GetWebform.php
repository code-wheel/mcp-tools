<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_webform\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_webform\Service\WebformService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for getting webform details.
 *
 * @Tool(
 *   id = "mcp_get_webform",
 *   label = @Translation("Get Webform"),
 *   description = @Translation("Get webform details including elements configuration."),
 *   category = "webform",
 * )
 */
class GetWebform extends ToolPluginBase implements ContainerFactoryPluginInterface {

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

    return $this->webformService->getWebform($id);
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
      'id' => ['type' => 'string', 'label' => 'Webform ID'],
      'title' => ['type' => 'string', 'label' => 'Title'],
      'description' => ['type' => 'string', 'label' => 'Description'],
      'status' => ['type' => 'string', 'label' => 'Status'],
      'elements' => ['type' => 'object', 'label' => 'Elements'],
      'settings' => ['type' => 'object', 'label' => 'Settings'],
    ];
  }

}
