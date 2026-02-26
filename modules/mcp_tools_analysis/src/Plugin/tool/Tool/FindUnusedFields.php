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

/**
 * Tool plugin implementation.
 */
#[Tool(
  id: 'mcp_analysis_unused_fields',
  label: new TranslatableMarkup('Find Unused Fields'),
  description: new TranslatableMarkup('Find fields with no data across all entities. Helps identify fields to clean up.'),
  operation: ToolOperation::Read,
  input_definitions: [],
  output_definitions: [
    'unused_fields' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Unused Fields'),
      description: new TranslatableMarkup('List of fields with no data, including entity type, bundle, and field type'),
    ),
    'unused_count' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Unused Count'),
      description: new TranslatableMarkup('Number of unused fields found'),
    ),
    'suggestions' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Suggestions'),
      description: new TranslatableMarkup('Recommendations for handling unused fields.'),
    ),
  ],
)]
class FindUnusedFields extends McpToolsToolBase {

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
    return $this->analysisService->findUnusedFields();
  }

}
