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
use Drupal\tool\TypedData\InputDefinition;

/**
 * Tool plugin implementation.
 */
#[Tool(
  id: 'mcp_tools_find_orphaned_files',
  label: new TranslatableMarkup('Find Orphaned Files'),
  description: new TranslatableMarkup('Find managed files that are not referenced by any entity.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'limit' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Limit'),
      description: new TranslatableMarkup('Maximum files to check. Max 500.'),
      required: FALSE,
      default_value: 100,
    ),
  ],
  output_definitions: [
    'total_orphaned' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total Orphaned Files'),
      description: new TranslatableMarkup('Number of files found without entity references.'),
    ),
    'total_size_bytes' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total Size (bytes)'),
      description: new TranslatableMarkup('Combined size of orphaned files in bytes.'),
    ),
    'total_size_human' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Total Size (human readable)'),
      description: new TranslatableMarkup('Combined size formatted (e.g., "15.2 MB").'),
    ),
    'files' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Orphaned Files'),
      description: new TranslatableMarkup('Array of orphaned files with fid, filename, uri, filemime, filesize, and created. These may be safe to delete.'),
    ),
  ],
)]
class FindOrphanedFiles extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'content';


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
    $limit = min($input['limit'] ?? 100, 500);

    return [
      'success' => TRUE,
      'data' => $this->fileSystem->findOrphanedFiles($limit),
    ];
  }

  

  

}
