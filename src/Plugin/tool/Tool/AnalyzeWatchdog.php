<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Plugin\tool\Tool;

use Drupal\mcp_tools\Service\WatchdogAnalyzer;
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
  id: 'mcp_tools_analyze_watchdog',
  label: new TranslatableMarkup('Analyze Watchdog'),
  description: new TranslatableMarkup('Analyze recent log entries from the database log (watchdog) including errors, warnings, and summaries.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'mode' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Analysis Mode'),
      description: new TranslatableMarkup('Mode of analysis: "summary" (grouped by type), "errors" (recent errors only), or "recent" (all recent entries).'),
      required: FALSE,
      default_value: 'summary',
    ),
    'limit' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Limit'),
      description: new TranslatableMarkup('Maximum number of entries to return (for errors/recent modes). Max 100.'),
      required: FALSE,
      default_value: 20,
    ),
    'hours' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Hours'),
      description: new TranslatableMarkup('Number of hours to look back (for summary mode). Max 168 (1 week).'),
      required: FALSE,
      default_value: 24,
    ),
  ],
  output_definitions: [
    'mode' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Analysis Mode Used'),
      description: new TranslatableMarkup('The analysis mode that was used: summary, errors, or recent.'),
    ),
    'data' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Analysis Results'),
      description: new TranslatableMarkup('For summary: counts by type/severity. For errors/recent: array of log entries with wid, type, severity, message, timestamp, and user.'),
    ),
  ],
)]
class AnalyzeWatchdog extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'site_health';


  /**
   * The watchdog analyzer service.
   */
  protected WatchdogAnalyzer $watchdogAnalyzer;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->watchdogAnalyzer = $container->get('mcp_tools.watchdog_analyzer');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeLegacy(array $input): array {
    $mode = $input['mode'] ?? 'summary';
    $limit = min($input['limit'] ?? 20, 100);
    $hours = min($input['hours'] ?? 24, 168); // Max 1 week.

    $data = match ($mode) {
      'errors' => $this->watchdogAnalyzer->getRecentErrors($limit),
      'recent' => $this->watchdogAnalyzer->getRecentEntries($limit),
      'summary' => $this->watchdogAnalyzer->getErrorSummary($hours),
      default => $this->watchdogAnalyzer->getErrorSummary($hours),
    };

    return [
      'success' => TRUE,
      'mode' => $mode,
      'data' => $data,
    ];
  }

  

  

}
