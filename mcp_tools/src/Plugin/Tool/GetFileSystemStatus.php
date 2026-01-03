<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools\Service\FileSystemService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for getting file system status.
 *
 * @Tool(
 *   id = "mcp_tools_get_file_system_status",
 *   label = @Translation("Get File System Status"),
 *   description = @Translation("Get file system status including directory permissions and stream wrappers."),
 *   category = "site_health",
 * )
 */
class GetFileSystemStatus extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected FileSystemService $fileSystem;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->fileSystem = $container->get('mcp_tools.file_system');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $input = []): array {
    return [
      'success' => TRUE,
      'data' => $this->fileSystem->getFileSystemStatus(),
    ];
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
      'directories' => [
        'type' => 'map',
        'label' => 'Directory Status',
      ],
      'stream_wrappers' => [
        'type' => 'list',
        'label' => 'Stream Wrappers',
      ],
    ];
  }

}
