<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools\Service\FileSystemService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for finding orphaned files.
 *
 * @Tool(
 *   id = "mcp_tools_find_orphaned_files",
 *   label = @Translation("Find Orphaned Files"),
 *   description = @Translation("Find managed files that are not referenced by any entity."),
 *   category = "content",
 * )
 */
class FindOrphanedFiles extends ToolPluginBase implements ContainerFactoryPluginInterface {

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
    $limit = min($input['limit'] ?? 100, 500);

    return [
      'success' => TRUE,
      'data' => $this->fileSystem->findOrphanedFiles($limit),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'limit' => [
        'type' => 'integer',
        'label' => 'Limit',
        'description' => 'Maximum files to check. Max 500.',
        'required' => FALSE,
        'default' => 100,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'total_orphaned' => [
        'type' => 'integer',
        'label' => 'Total Orphaned Files',
      ],
      'total_size_bytes' => [
        'type' => 'integer',
        'label' => 'Total Size (bytes)',
      ],
      'total_size_human' => [
        'type' => 'string',
        'label' => 'Total Size (human readable)',
      ],
      'files' => [
        'type' => 'list',
        'label' => 'Orphaned Files',
      ],
    ];
  }

}
