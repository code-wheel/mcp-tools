<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_webform\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_webform\Service\WebformService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for creating a webform.
 *
 * @Tool(
 *   id = "mcp_create_webform",
 *   label = @Translation("Create Webform"),
 *   description = @Translation("Create a new webform with elements."),
 *   category = "webform",
 * )
 */
class CreateWebform extends ToolPluginBase implements ContainerFactoryPluginInterface {

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
    $title = $input['title'] ?? '';

    if (empty($id) || empty($title)) {
      return ['success' => FALSE, 'error' => 'Both id and title are required.'];
    }

    $elements = $input['elements'] ?? [];

    return $this->webformService->createWebform($id, $title, $elements);
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'id' => ['type' => 'string', 'label' => 'Webform machine name', 'required' => TRUE],
      'title' => ['type' => 'string', 'label' => 'Title', 'required' => TRUE],
      'elements' => ['type' => 'object', 'label' => 'Elements definition', 'required' => FALSE],
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
