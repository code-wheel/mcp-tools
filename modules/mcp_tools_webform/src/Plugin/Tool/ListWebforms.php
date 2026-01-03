<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_webform\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_webform\Service\WebformService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for listing all webforms.
 *
 * @Tool(
 *   id = "mcp_list_webforms",
 *   label = @Translation("List Webforms"),
 *   description = @Translation("List all webforms with submission counts."),
 *   category = "webform",
 * )
 */
class ListWebforms extends ToolPluginBase implements ContainerFactoryPluginInterface {

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
    return $this->webformService->listWebforms();
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'total' => ['type' => 'integer', 'label' => 'Total Webforms'],
      'webforms' => ['type' => 'list', 'label' => 'Webforms'],
    ];
  }

}
