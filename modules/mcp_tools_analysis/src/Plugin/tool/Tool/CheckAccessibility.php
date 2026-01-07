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
  id: 'mcp_analysis_accessibility',
  label: new TranslatableMarkup('Check Accessibility'),
  description: new TranslatableMarkup('Perform basic accessibility checks: images without alt, heading order, link text quality.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'entity_type' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Entity Type'),
      description: new TranslatableMarkup('Entity type to check (e.g., "node")'),
      required: TRUE,
    ),
    'entity_id' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Entity ID'),
      description: new TranslatableMarkup('ID of the entity to check'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'entity_type' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Entity Type'),
      description: new TranslatableMarkup('The entity type that was checked.'),
    ),
    'entity_id' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Entity ID'),
      description: new TranslatableMarkup('The entity ID that was checked.'),
    ),
    'title' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Title'),
      description: new TranslatableMarkup('Entity title for reference.'),
    ),
    'issues' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Accessibility Issues'),
      description: new TranslatableMarkup('Issues found with type, severity, WCAG reference, and message'),
    ),
    'error_count' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Error Count'),
      description: new TranslatableMarkup('WCAG errors that must be fixed for compliance.'),
    ),
    'warning_count' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Warning Count'),
      description: new TranslatableMarkup('Potential issues that should be reviewed.'),
    ),
    'info_count' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Info Count'),
      description: new TranslatableMarkup('Informational notices for best practices.'),
    ),
    'suggestions' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Suggestions'),
      description: new TranslatableMarkup('Specific fixes to resolve accessibility issues.'),
    ),
  ],
)]
class CheckAccessibility extends McpToolsToolBase {

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

    return $this->analysisService->checkAccessibility($entityType, $entityId);
  }

  

  

}
