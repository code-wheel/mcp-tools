<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools\Service\WatchdogAnalyzer;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for analyzing watchdog/database log entries.
 *
 * @Tool(
 *   id = "mcp_tools_analyze_watchdog",
 *   label = @Translation("Analyze Watchdog"),
 *   description = @Translation("Analyze recent log entries from the database log (watchdog) including errors, warnings, and summaries."),
 *   category = "site_health",
 * )
 */
class AnalyzeWatchdog extends ToolPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The watchdog analyzer service.
   */
  protected WatchdogAnalyzer $watchdogAnalyzer;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->watchdogAnalyzer = $container->get('mcp_tools.watchdog_analyzer');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $input = []): array {
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

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'mode' => [
        'type' => 'string',
        'label' => 'Analysis Mode',
        'description' => 'Mode of analysis: "summary" (grouped by type), "errors" (recent errors only), or "recent" (all recent entries).',
        'required' => FALSE,
        'default' => 'summary',
      ],
      'limit' => [
        'type' => 'integer',
        'label' => 'Limit',
        'description' => 'Maximum number of entries to return (for errors/recent modes). Max 100.',
        'required' => FALSE,
        'default' => 20,
      ],
      'hours' => [
        'type' => 'integer',
        'label' => 'Hours',
        'description' => 'Number of hours to look back (for summary mode). Max 168 (1 week).',
        'required' => FALSE,
        'default' => 24,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'mode' => [
        'type' => 'string',
        'label' => 'Analysis Mode Used',
      ],
      'data' => [
        'type' => 'map',
        'label' => 'Analysis Results',
      ],
    ];
  }

}
