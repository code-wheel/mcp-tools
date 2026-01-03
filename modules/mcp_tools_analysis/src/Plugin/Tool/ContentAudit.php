<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_analysis\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_analysis\Service\AnalysisService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for auditing content health.
 *
 * @Tool(
 *   id = "mcp_analysis_content_audit",
 *   label = @Translation("Content Audit"),
 *   description = @Translation("Find stale content (not updated in X days), orphaned content (unpublished), and drafts."),
 *   category = "analysis",
 * )
 */
class ContentAudit extends ToolPluginBase implements ContainerFactoryPluginInterface {

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
    $options = [];

    if (isset($input['stale_days'])) {
      $staleDays = (int) $input['stale_days'];
      if ($staleDays < 1) {
        return ['success' => FALSE, 'error' => 'stale_days must be a positive integer.'];
      }
      $options['stale_days'] = $staleDays;
    }

    if (isset($input['include_drafts'])) {
      $options['include_drafts'] = (bool) $input['include_drafts'];
    }

    if (isset($input['content_types']) && !empty($input['content_types'])) {
      $options['content_types'] = is_array($input['content_types'])
        ? $input['content_types']
        : [$input['content_types']];
    }

    return $this->analysisService->contentAudit($options);
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'stale_days' => [
        'type' => 'integer',
        'label' => 'Stale Days',
        'description' => 'Days since last update to consider content stale (default: 365)',
        'required' => FALSE,
      ],
      'include_drafts' => [
        'type' => 'boolean',
        'label' => 'Include Drafts',
        'description' => 'Include draft content in audit (default: true)',
        'required' => FALSE,
      ],
      'content_types' => [
        'type' => 'array',
        'label' => 'Content Types',
        'description' => 'Array of content type machine names to audit (default: all)',
        'required' => FALSE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'stale_content' => [
        'type' => 'array',
        'label' => 'Stale Content',
        'description' => 'Content not updated within the specified period',
      ],
      'stale_count' => [
        'type' => 'integer',
        'label' => 'Stale Count',
      ],
      'orphaned_content' => [
        'type' => 'array',
        'label' => 'Orphaned Content',
        'description' => 'Unpublished content with no recent activity',
      ],
      'orphaned_count' => [
        'type' => 'integer',
        'label' => 'Orphaned Count',
      ],
      'drafts' => [
        'type' => 'array',
        'label' => 'Drafts',
        'description' => 'Content in draft state',
      ],
      'draft_count' => [
        'type' => 'integer',
        'label' => 'Draft Count',
      ],
      'suggestions' => [
        'type' => 'array',
        'label' => 'Suggestions',
      ],
    ];
  }

}
