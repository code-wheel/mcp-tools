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
  id: 'mcp_tools_get_files',
  label: new TranslatableMarkup('Get Files'),
  description: new TranslatableMarkup('Get managed files with summary and MIME type breakdown.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'limit' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Limit'),
      description: new TranslatableMarkup('Maximum files to return. Max 100.'),
      required: FALSE,
      default_value: 50,
    ),
  ],
  output_definitions: [
    'total_files' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total Managed Files'),
      description: new TranslatableMarkup(''),
    ),
    'returned' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Files Returned'),
      description: new TranslatableMarkup(''),
    ),
    'by_mime_type' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Files by MIME Type'),
      description: new TranslatableMarkup(''),
    ),
    'files' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('File List'),
      description: new TranslatableMarkup(''),
    ),
  ],
)]
class GetFiles extends McpToolsToolBase {

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
    $limit = min($input['limit'] ?? 50, 100);

    return [
      'success' => TRUE,
      'data' => $this->fileSystem->getFilesSummary($limit),
    ];
  }

  

  

}
