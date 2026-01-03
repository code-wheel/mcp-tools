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
  id: 'mcp_analysis_content_audit',
  label: new TranslatableMarkup('Content Audit'),
  description: new TranslatableMarkup('Find stale content (not updated in X days), orphaned content (unpublished), and drafts.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'stale_days' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Stale Days'),
      description: new TranslatableMarkup('Days since last update to consider content stale (default: 365)'),
      required: FALSE,
    ),
    'include_drafts' => new InputDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Include Drafts'),
      description: new TranslatableMarkup('Include draft content in audit (default: true)'),
      required: FALSE,
    ),
    'content_types' => new InputDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Content Types'),
      description: new TranslatableMarkup('Array of content type machine names to audit (default: all)'),
      required: FALSE,
    ),
  ],
  output_definitions: [
    'stale_content' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Stale Content'),
      description: new TranslatableMarkup('Content not updated within the specified period'),
    ),
    'stale_count' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Stale Count'),
      description: new TranslatableMarkup(''),
    ),
    'orphaned_content' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Orphaned Content'),
      description: new TranslatableMarkup('Unpublished content with no recent activity'),
    ),
    'orphaned_count' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Orphaned Count'),
      description: new TranslatableMarkup(''),
    ),
    'drafts' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Drafts'),
      description: new TranslatableMarkup('Content in draft state'),
    ),
    'draft_count' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Draft Count'),
      description: new TranslatableMarkup(''),
    ),
    'suggestions' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Suggestions'),
      description: new TranslatableMarkup(''),
    ),
  ],
)]
class ContentAudit extends McpToolsToolBase {

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

  

  

}
