<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_analysis\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_analysis\Service\AnalysisService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for finding unused fields across entities.
 *
 * @Tool(
 *   id = "mcp_analysis_unused_fields",
 *   label = @Translation("Find Unused Fields"),
 *   description = @Translation("Find fields with no data across all entities. Helps identify fields to clean up."),
 *   category = "analysis",
 * )
 */
class FindUnusedFields extends ToolPluginBase implements ContainerFactoryPluginInterface {

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
    return $this->analysisService->findUnusedFields();
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    // No inputs required.
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'unused_fields' => [
        'type' => 'array',
        'label' => 'Unused Fields',
        'description' => 'List of fields with no data, including entity type, bundle, and field type',
      ],
      'unused_count' => [
        'type' => 'integer',
        'label' => 'Unused Count',
        'description' => 'Number of unused fields found',
      ],
      'suggestions' => [
        'type' => 'array',
        'label' => 'Suggestions',
      ],
    ];
  }

}
