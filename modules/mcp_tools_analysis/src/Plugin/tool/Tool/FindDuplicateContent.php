<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_analysis\Plugin\tool\Tool;

use Drupal\mcp_tools_analysis\Service\AnalysisService;
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
  id: 'mcp_analysis_duplicates',
  label: new TranslatableMarkup('Find Duplicate Content'),
  description: new TranslatableMarkup('Find similar content based on field values. Useful for identifying redundant pages.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'content_type' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Content Type'),
      description: new TranslatableMarkup('Machine name of the content type to search'),
      required: TRUE,
    ),
    'field' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Field'),
      description: new TranslatableMarkup('Field to compare for similarity (default: "title")'),
      required: FALSE,
    ),
    'threshold' => new InputDefinition(
      data_type: 'float',
      label: new TranslatableMarkup('Similarity Threshold'),
      description: new TranslatableMarkup('Similarity threshold 0.1-1.0 (default: 0.8 = 80% similar)'),
      required: FALSE,
    ),
  ],
  output_definitions: [
    'content_type' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Content Type'),
      description: new TranslatableMarkup('The content type that was analyzed.'),
    ),
    'field_compared' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Field Compared'),
      description: new TranslatableMarkup('The field used for similarity comparison.'),
    ),
    'threshold' => new ContextDefinition(
      data_type: 'float',
      label: new TranslatableMarkup('Threshold Used'),
      description: new TranslatableMarkup('Similarity threshold that was applied (0.1-1.0).'),
    ),
    'items_analyzed' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Items Analyzed'),
      description: new TranslatableMarkup('Total number of items compared.'),
    ),
    'duplicates' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Potential Duplicates'),
      description: new TranslatableMarkup('Pairs of similar content with similarity percentage'),
    ),
    'duplicate_count' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Duplicate Count'),
      description: new TranslatableMarkup('Number of potential duplicate pairs found.'),
    ),
    'suggestions' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Suggestions'),
      description: new TranslatableMarkup('Recommendations for handling duplicates.'),
    ),
  ],
)]
class FindDuplicateContent extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'analysis';


  /**
   * The analysis service.
   */
  protected AnalysisService $analysisService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->analysisService = $container->get('mcp_tools_analysis.analysis');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeLegacy(array $input): array {
    $contentType = $input['content_type'] ?? '';
    $field = $input['field'] ?? 'title';
    $threshold = isset($input['threshold']) ? (float) $input['threshold'] : 0.8;

    if (empty($contentType)) {
      return ['success' => FALSE, 'error' => 'content_type is required.'];
    }

    if ($threshold < 0.1 || $threshold > 1.0) {
      return ['success' => FALSE, 'error' => 'threshold must be between 0.1 and 1.0.'];
    }

    return $this->analysisService->findDuplicateContent($contentType, $field, $threshold);
  }

}
