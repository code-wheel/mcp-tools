<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Plugin\tool\Tool;

use Drupal\mcp_tools\Service\FileSystemService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp_tools\Tool\McpToolsToolBase;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\Tool\ToolOperation;

/**
 * Tool plugin implementation.
 */
#[Tool(
  id: 'mcp_tools_get_file_system_status',
  label: new TranslatableMarkup('Get File System Status'),
  description: new TranslatableMarkup('Get file system status including directory permissions and stream wrappers.'),
  operation: ToolOperation::Read,
  input_definitions: [],
  output_definitions: [
    'directories' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Directory Status'),
      description: new TranslatableMarkup('Status of key directories (public, private, temp) with path, exists, writable, and free_space.'),
    ),
    'stream_wrappers' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Stream Wrappers'),
      description: new TranslatableMarkup('Available stream wrappers (e.g., public://, private://) with scheme, name, and description.'),
    ),
  ],
)]
class GetFileSystemStatus extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'site_health';


  /**
   * The file system.
   *
   * @var \Drupal\mcp_tools\Service\FileSystemService
   */
  protected FileSystemService $fileSystem;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->fileSystem = $container->get('mcp_tools.file_system');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeLegacy(array $input): array {
    return [
      'success' => TRUE,
      'data' => $this->fileSystem->getFileSystemStatus(),
    ];
  }

}
