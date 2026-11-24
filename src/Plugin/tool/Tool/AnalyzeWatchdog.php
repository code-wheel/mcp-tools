<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Plugin\tool\Tool;

use Drupal\mcp_tools\Service\WatchdogAnalyzer;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\tool\TypedData\OutputDefinition;
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
    'mode' => new OutputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Analysis Mode Used'),
      description: new TranslatableMarkup('The analysis mode that was used: summary, errors, or recent.'),
    ),
    'data' => new OutputDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Analysis Results'),
      description: new TranslatableMarkup('For summary: counts by type/severity. For errors/recent: total (all that match), total_returned, and entries (up to limit, the newest first), each with id, type, severity, message and timestamp.'),
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
    // Max 1 week.
    $hours = min($input['hours'] ?? 24, 168);

    // Another mode gave the summary, and the answer named the mode asked
    // for: "error" read as errors.
    if (!in_array($mode, ['summary', 'errors', 'recent'], TRUE)) {
      return ['success' => FALSE, 'error' => "mode is summary, errors or recent, not '$mode'."];
    }
    $data = match ($mode) {
      'errors' => $this->watchdogAnalyzer->getRecentErrors($limit),
      'recent' => $this->watchdogAnalyzer->getRecentEntries($limit),
      'summary' => $this->watchdogAnalyzer->getErrorSummary($hours),
    };

    // Both outputs under data: with "data" at the top, the summary's own
    // keys were read as the tool's too, besides its data output.
    return [
      'success' => TRUE,
      'data' => ['mode' => $mode, 'data' => $data],
    ];
  }

}
