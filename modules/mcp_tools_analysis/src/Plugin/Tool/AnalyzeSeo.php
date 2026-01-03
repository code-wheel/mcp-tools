<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_analysis\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_analysis\Service\AnalysisService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for analyzing SEO of a specific entity.
 *
 * @Tool(
 *   id = "mcp_analysis_seo",
 *   label = @Translation("Analyze SEO"),
 *   description = @Translation("Check meta tags, headings, alt text, and content quality for a specific entity."),
 *   category = "analysis",
 * )
 */
class AnalyzeSeo extends ToolPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The analysis service.
   */
  protected AnalysisService $analysisService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->analysisService = $container->get('mcp_tools_analysis.analysis');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $input = []): array {
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

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'entity_type' => [
        'type' => 'string',
        'label' => 'Entity Type',
        'description' => 'Entity type to analyze (e.g., "node", "taxonomy_term")',
        'required' => TRUE,
      ],
      'entity_id' => [
        'type' => 'integer',
        'label' => 'Entity ID',
        'description' => 'ID of the entity to analyze',
        'required' => TRUE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'entity_type' => [
        'type' => 'string',
        'label' => 'Entity Type',
      ],
      'entity_id' => [
        'type' => 'integer',
        'label' => 'Entity ID',
      ],
      'title' => [
        'type' => 'string',
        'label' => 'Title',
      ],
      'seo_score' => [
        'type' => 'integer',
        'label' => 'SEO Score',
        'description' => 'Score from 0-100',
      ],
      'score_rating' => [
        'type' => 'string',
        'label' => 'Score Rating',
        'description' => 'good, needs_improvement, or poor',
      ],
      'issues' => [
        'type' => 'array',
        'label' => 'Issues',
        'description' => 'SEO issues found with type, severity, and message',
      ],
      'issue_count' => [
        'type' => 'integer',
        'label' => 'Issue Count',
      ],
      'suggestions' => [
        'type' => 'array',
        'label' => 'Suggestions',
      ],
    ];
  }

}
