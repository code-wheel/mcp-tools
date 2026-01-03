<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_cron\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\CronInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Drupal\Core\State\StateInterface;

/**
 * Service for cron management operations.
 */
class CronService {

  public function __construct(
    protected CronInterface $cron,
    protected StateInterface $state,
    protected ConfigFactoryInterface $configFactory,
    protected ModuleHandlerInterface $moduleHandler,
    protected QueueWorkerManagerInterface $queueWorkerManager,
    protected QueueFactory $queueFactory,
  ) {}

  /**
   * Get cron status.
   *
   * @return array
   *   Cron status information.
   */
  public function getCronStatus(): array {
    $lastRun = $this->state->get('system.cron_last', 0);
    $config = $this->configFactory->get('system.cron');
    $threshold = $config->get('threshold.autorun') ?? 10800;

    $cronJobs = $this->getCronJobs();

    return [
      'last_run' => $lastRun ? date('Y-m-d H:i:s', $lastRun) : 'Never',
      'last_run_timestamp' => $lastRun,
      'seconds_since_last_run' => $lastRun ? time() - $lastRun : NULL,
      'autorun_threshold' => $threshold,
      'threshold_human' => $this->formatDuration($threshold),
      'is_overdue' => $lastRun && (time() - $lastRun) > $threshold,
      'cron_key' => $this->state->get('system.cron_key', ''),
      'jobs_count' => count($cronJobs),
      'jobs' => $cronJobs,
    ];
  }

  /**
   * Run cron.
   *
   * @return array
   *   Result of the cron run.
   */
  public function runCron(): array {
    $startTime = microtime(TRUE);
    $lastRunBefore = $this->state->get('system.cron_last', 0);

    try {
      $result = $this->cron->run();
      $endTime = microtime(TRUE);
      $duration = round($endTime - $startTime, 2);
      $lastRunAfter = $this->state->get('system.cron_last', 0);

      if ($result) {
        return [
          'success' => TRUE,
          'message' => 'Cron completed successfully.',
          'duration_seconds' => $duration,
          'previous_run' => $lastRunBefore ? date('Y-m-d H:i:s', $lastRunBefore) : 'Never',
          'current_run' => date('Y-m-d H:i:s', $lastRunAfter),
        ];
      }
      else {
        return [
          'success' => FALSE,
          'error' => 'Cron returned false. It may have been running already or encountered an error.',
          'code' => 'CRON_FAILED',
          'duration_seconds' => $duration,
        ];
      }
    }
    catch (\Exception $e) {
      return [
        'success' => FALSE,
        'error' => 'Cron failed: ' . $e->getMessage(),
        'code' => 'INTERNAL_ERROR',
      ];
    }
  }

  /**
   * Run a specific queue.
   *
   * @param string $queueName
   *   The queue name to process.
   * @param int $limit
   *   Maximum items to process.
   *
   * @return array
   *   Result of processing the queue.
   */
  public function runQueue(string $queueName, int $limit = 100): array {
    $definitions = $this->queueWorkerManager->getDefinitions();

    if (!isset($definitions[$queueName])) {
      return [
        'success' => FALSE,
        'error' => "Unknown queue '$queueName'.",
        'code' => 'NOT_FOUND',
        'available_queues' => array_keys($definitions),
      ];
    }

    $queue = $this->queueFactory->get($queueName);
    $worker = $this->queueWorkerManager->createInstance($queueName);

    $processed = 0;
    $failed = 0;
    $startTime = microtime(TRUE);

    while ($processed < $limit && ($item = $queue->claimItem())) {
      try {
        $worker->processItem($item->data);
        $queue->deleteItem($item);
        $processed++;
      }
      catch (\Exception $e) {
        $queue->releaseItem($item);
        $failed++;
        if ($failed >= 10) {
          break;
        }
      }
    }

    $duration = round(microtime(TRUE) - $startTime, 2);
    $remaining = $queue->numberOfItems();

    return [
      'success' => TRUE,
      'message' => "Processed $processed items from queue '$queueName'.",
      'queue' => $queueName,
      'processed' => $processed,
      'failed' => $failed,
      'remaining' => $remaining,
      'duration_seconds' => $duration,
    ];
  }

  /**
   * Update cron settings.
   *
   * @param int|null $threshold
   *   Autorun threshold in seconds.
   *
   * @return array
   *   Result of the update.
   */
  public function updateSettings(?int $threshold = NULL): array {
    $config = $this->configFactory->getEditable('system.cron');
    $changes = [];

    if ($threshold !== NULL) {
      if ($threshold < 0) {
        return [
          'success' => FALSE,
          'error' => 'Threshold must be a positive number or 0.',
          'code' => 'VALIDATION_ERROR',
        ];
      }
      $oldThreshold = $config->get('threshold.autorun');
      $config->set('threshold.autorun', $threshold);
      $changes['threshold'] = [
        'old' => $oldThreshold,
        'new' => $threshold,
      ];
    }

    if (empty($changes)) {
      return [
        'success' => FALSE,
        'error' => 'No settings provided to update.',
        'code' => 'VALIDATION_ERROR',
      ];
    }

    $config->save();

    return [
      'success' => TRUE,
      'message' => 'Cron settings updated successfully.',
      'changes' => $changes,
    ];
  }

  /**
   * Reset the cron key.
   *
   * @return array
   *   Result with new cron key.
   */
  public function resetCronKey(): array {
    $newKey = bin2hex(random_bytes(16));
    $this->state->set('system.cron_key', $newKey);

    return [
      'success' => TRUE,
      'message' => 'Cron key has been reset.',
      'new_key' => $newKey,
    ];
  }

  /**
   * Get all registered cron jobs.
   *
   * @return array
   *   List of cron jobs from hook_cron implementations.
   */
  protected function getCronJobs(): array {
    $jobs = [];

    // Get modules implementing hook_cron.
    $implementations = $this->moduleHandler->getImplementations('cron');
    foreach ($implementations as $module) {
      $jobs[] = [
        'module' => $module,
        'hook' => $module . '_cron',
      ];
    }

    // Get queue workers with cron settings.
    $queueDefinitions = $this->queueWorkerManager->getDefinitions();
    foreach ($queueDefinitions as $name => $definition) {
      if (!empty($definition['cron'])) {
        $jobs[] = [
          'module' => $definition['provider'] ?? 'unknown',
          'queue' => $name,
          'title' => (string) ($definition['title'] ?? $name),
          'cron_time' => $definition['cron']['time'] ?? NULL,
        ];
      }
    }

    return $jobs;
  }

  /**
   * Format duration in human-readable format.
   *
   * @param int $seconds
   *   Duration in seconds.
   *
   * @return string
   *   Human-readable duration.
   */
  protected function formatDuration(int $seconds): string {
    if ($seconds < 60) {
      return $seconds . ' seconds';
    }
    if ($seconds < 3600) {
      return round($seconds / 60) . ' minutes';
    }
    return round($seconds / 3600, 1) . ' hours';
  }

}
