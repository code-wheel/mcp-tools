<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Plugin\tool\Tool;

use Drupal\mcp_tools\Service\FileSystemService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\tool\TypedData\OutputDefinition;
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
    'total_orphaned' => new OutputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total Orphaned Files'),
      description: new TranslatableMarkup('Number of files on the site that no entity uses. files holds up to limit of them.'),
    ),
    'total_size_bytes' => new OutputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total Size (bytes)'),
      description: new TranslatableMarkup('Combined size in bytes of the orphaned files shown.'),
    ),
    'total_size_human' => new OutputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Total Size (human readable)'),
      description: new TranslatableMarkup('Combined size formatted (e.g., "15.2 MB").'),
    ),
    'files' => new OutputDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Orphaned Files'),
      description: new TranslatableMarkup('Array of orphaned files with fid, filename, uri, size (bytes), and created. These may be safe to delete.'),
    ),
  ],
)]
class FindOrphanedFiles extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'content';


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
    if (!$this->fileSystem->hasManagedFiles()) {
      return ['success' => FALSE, 'error' => 'This site has no managed files: the File module is not enabled.'];
    }
    $limit = min($input['limit'] ?? 100, 500);

    $data = $this->fileSystem->findOrphanedFiles($limit);
    // "No orphaned files found." was in data.note, which the tool does not
    // declare: the answer said "Success." and an empty list.
    $message = $data['note'] ?? 'Success.';
    unset($data['note']);
    return ['success' => TRUE, 'message' => $message, 'data' => $data];
  }

}
