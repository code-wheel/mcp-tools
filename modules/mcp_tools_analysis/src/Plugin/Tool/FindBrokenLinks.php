<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_analysis\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_analysis\Service\AnalysisService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for finding broken internal links in content.
 *
 * @Tool(
 *   id = "mcp_analysis_broken_links",
 *   label = @Translation("Find Broken Links"),
 *   description = @Translation("Scan content for broken internal links (404s). Checks href attributes in text fields."),
 *   category = "analysis",
 * )
 */
class FindBrokenLinks extends ToolPluginBase implements ContainerFactoryPluginInterface {

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
    $limit = isset($input['limit']) ? (int) $input['limit'] : 100;

    if ($limit < 1 || $limit > 500) {
      return ['success' => FALSE, 'error' => 'Limit must be between 1 and 500.'];
    }

    return $this->analysisService->findBrokenLinks($limit);
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'limit' => [
        'type' => 'integer',
        'label' => 'Limit',
        'description' => 'Maximum number of links to check (1-500, default: 100)',
        'required' => FALSE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'broken_links' => [
        'type' => 'array',
        'label' => 'Broken Links',
        'description' => 'List of broken links found with source information',
      ],
      'total_checked' => [
        'type' => 'integer',
        'label' => 'Total Checked',
        'description' => 'Number of links checked',
      ],
      'broken_count' => [
        'type' => 'integer',
        'label' => 'Broken Count',
        'description' => 'Number of broken links found',
      ],
      'suggestions' => [
        'type' => 'array',
        'label' => 'Suggestions',
        'description' => 'Recommendations for fixing issues',
      ],
    ];
  }

}
