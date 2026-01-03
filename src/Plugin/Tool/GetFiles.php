<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools\Service\FileSystemService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for getting managed files.
 *
 * @Tool(
 *   id = "mcp_tools_get_files",
 *   label = @Translation("Get Files"),
 *   description = @Translation("Get managed files with summary and MIME type breakdown."),
 *   category = "content",
 * )
 */
class GetFiles extends ToolPluginBase implements ContainerFactoryPluginInterface {

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
    $limit = min($input['limit'] ?? 50, 100);

    return [
      'success' => TRUE,
      'data' => $this->fileSystem->getFilesSummary($limit),
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
        'description' => 'Maximum files to return. Max 100.',
        'required' => FALSE,
        'default' => 50,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'total_files' => [
        'type' => 'integer',
        'label' => 'Total Managed Files',
      ],
      'returned' => [
        'type' => 'integer',
        'label' => 'Files Returned',
      ],
      'by_mime_type' => [
        'type' => 'map',
        'label' => 'Files by MIME Type',
      ],
      'files' => [
        'type' => 'list',
        'label' => 'File List',
      ],
    ];
  }

}
