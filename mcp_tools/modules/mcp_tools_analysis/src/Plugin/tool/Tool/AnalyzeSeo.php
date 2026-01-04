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
  id: 'mcp_analysis_seo',
  label: new TranslatableMarkup('Analyze SEO'),
  description: new TranslatableMarkup('Check meta tags, headings, alt text, and content quality for a specific entity.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'entity_type' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Entity Type'),
      description: new TranslatableMarkup('Entity type to analyze (e.g., "node", "taxonomy_term")'),
      required: TRUE,
    ),
    'entity_id' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Entity ID'),
      description: new TranslatableMarkup('ID of the entity to analyze'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'entity_type' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Entity Type'),
      description: new TranslatableMarkup('The entity type that was analyzed.'),
    ),
    'entity_id' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Entity ID'),
      description: new TranslatableMarkup('The entity ID that was analyzed.'),
    ),
    'title' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Title'),
      description: new TranslatableMarkup('Entity title for reference.'),
    ),
    'seo_score' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('SEO Score'),
      description: new TranslatableMarkup('Score from 0-100'),
    ),
    'score_rating' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Score Rating'),
      description: new TranslatableMarkup('good, needs_improvement, or poor'),
    ),
    'issues' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Issues'),
      description: new TranslatableMarkup('SEO issues found with type, severity, and message'),
    ),
    'issue_count' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Issue Count'),
      description: new TranslatableMarkup('Total number of SEO issues found.'),
    ),
    'suggestions' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Suggestions'),
      description: new TranslatableMarkup('Specific fixes to improve SEO score.'),
    ),
  ],
)]
class AnalyzeSeo extends McpToolsToolBase {

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
    $entityType = $input['entity_type'] ?? '';
    $entityId = isset($input['entity_id']) ? (int) $input['entity_id'] : 0;

    if (empty($entityType)) {
      return ['success' => FALSE, 'error' => 'entity_type is required.'];
    }

    if ($entityId < 1) {
      return ['success' => FALSE, 'error' => 'entity_id must be a positive integer.'];
    }

    return $this->analysisService->analyzeSeo($entityType, $entityId);
  }

  

  

}
