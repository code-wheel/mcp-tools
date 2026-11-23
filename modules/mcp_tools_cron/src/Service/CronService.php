<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_cron\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\CronInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Queue\DelayableQueueInterface;
use Drupal\Core\Queue\DelayedRequeueException;
use Drupal\Core\Queue\RequeueException;
use Drupal\Core\Queue\SuspendQueueException;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\Session\AnonymousUserSession;

/**
 * Service for cron management operations.
 */
class CronService {

  /**
   * Items a queue run does at most.
   */
  public const QUEUE_LIMIT = 200;

  /**
   * Seconds a queue run takes at most.
   */
  public const QUEUE_SECONDS = 20;

  public function __construct(
    protected CronInterface $cron,
    protected StateInterface $state,
    protected ConfigFactoryInterface $configFactory,
    protected ModuleHandlerInterface $moduleHandler,
    protected QueueWorkerManagerInterface $queueWorkerManager,
    protected QueueFactory $queueFactory,
    protected ?AccountSwitcherInterface $accountSwitcher = NULL,
  ) {}

  /**
   * Get cron status.
   *
   * @return array
   *   Cron status information.
   */
  public function getCronStatus(): array {
    $lastRun = $this->state->get('system.cron_last', 0);
    // How often Drupal runs cron itself: Automated Cron's interval. The
    // tool read system.cron:threshold.autorun, a Drupal 7 key nothing reads.
    // The stored value: Drush overrides it to 0 in its own requests (the
    // STDIO server), and web requests run cron by the stored one.
    $threshold = $this->moduleHandler->moduleExists('automated_cron')
      ? (int) ($this->configFactory->get('automated_cron.settings')->getOriginal('interval', FALSE) ?? 10800)
      : 0;

    $cronJobs = $this->getCronJobs();

    return [
      'success' => TRUE,
      'data' => [
        'last_run' => $lastRun ? date('Y-m-d H:i:s', (int) $lastRun) : 'Never',
        'last_run_timestamp' => $lastRun,
        'seconds_since_last_run' => $lastRun ? time() - $lastRun : NULL,
        'autorun_threshold' => $threshold,
        'threshold_human' => $this->formatDuration($threshold),
        'is_overdue' => $lastRun && (time() - $lastRun) > $threshold,
        // Not the cron key: whoever has it can run cron from outside, and
        // this is a read tool.
        'jobs_count' => count($cronJobs),
        'jobs' => $cronJobs,
      ],
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
          'previous_run' => $lastRunBefore ? date('Y-m-d H:i:s', (int) $lastRunBefore) : 'Never',
          'current_run' => date('Y-m-d H:i:s', (int) $lastRunAfter),
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
    $limit = max(1, min($limit, self::QUEUE_LIMIT));
    // The time core's cron gives this queue, at most QUEUE_SECONDS.
    $seconds = min((int) ($definitions[$queueName]['cron']['time'] ?? self::QUEUE_SECONDS), self::QUEUE_SECONDS);
    $end = microtime(TRUE) + max(1, $seconds);

    $processed = 0;
    $failed = 0;
    $requeued = 0;
    $suspended = NULL;
    $errors = [];
    $startTime = microtime(TRUE);
    // Workers run as cron runs them: as the anonymous user.
    $this->accountSwitcher?->switchTo(new AnonymousUserSession());
    try {
      while ($processed + $failed < $limit && microtime(TRUE) < $end && ($item = $queue->claimItem(max(1, $seconds)))) {
        // As core's cron handles a queue: requeue, delay and suspend are the
        // worker's own answers, not failures.
        try {
          $worker->processItem($item->data);
          $queue->deleteItem($item);
          $processed++;
        }
        catch (DelayedRequeueException $e) {
          if ($queue instanceof DelayableQueueInterface) {
            $queue->delayItem($item, $e->getDelay());
          }
          $requeued++;
        }
        catch (RequeueException) {
          $queue->releaseItem($item);
          $requeued++;
        }
        catch (SuspendQueueException $e) {
          $queue->releaseItem($item);
          $suspended = $e->getMessage() ?: 'The worker suspended the queue.';
          break;
        }
        catch (\Throwable $e) {
          // An \Error too: it left the item claimed for its lease before.
          $queue->releaseItem($item);
          $failed++;
          $errors[] = $e->getMessage();
          if ($failed >= 10) {
            break;
          }
        }
      }
    }
    finally {
      $this->accountSwitcher?->switchBack();
    }

    $duration = round(microtime(TRUE) - $startTime, 2);
    $remaining = (int) $queue->numberOfItems();
    $allFailed = $processed === 0 && $failed > 0;
    $message = "Processed $processed items from queue '$queueName'"
      . ($failed ? ", $failed failed" : '') . ($requeued ? ", $requeued put back by the worker" : '') . '.'
      . ($suspended !== NULL ? " The worker suspended the queue: $suspended" : '')
      . ($remaining > 0 && $suspended === NULL && !$allFailed ? " $remaining are left: call again." : '');

    return [
      'success' => !$allFailed,
      'message' => $message,
      'error' => $allFailed ? "Every item of queue '$queueName' that was tried failed: " . implode(' | ', array_unique(array_slice($errors, 0, 3))) : NULL,
      'queue' => $queueName,
      'processed' => $processed,
      'failed' => $failed,
      'requeued' => $requeued,
      'suspended' => $suspended !== NULL,
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
    $changes = [];

    if ($threshold !== NULL) {
      if ($threshold < 0) {
        return [
          'success' => FALSE,
          'error' => 'Threshold must be a positive number or 0.',
          'code' => 'VALIDATION_ERROR',
        ];
      }
      // The tool wrote system.cron:threshold.autorun, a Drupal 7 key that
      // nothing reads, and said "updated".
      if (!$this->moduleHandler->moduleExists('automated_cron')) {
        return [
          'success' => FALSE,
          'error' => 'Automated Cron is not enabled, so Drupal runs cron only from outside (a crontab, drush cron). Enable the automated_cron module to have Drupal run it every so often.',
          'code' => 'MODULE_NOT_ENABLED',
        ];
      }
      $config = $this->configFactory->getEditable('automated_cron.settings');
      $oldThreshold = $config->get('interval');
      $config->set('interval', $threshold);
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
    $implementations = [];
    $this->moduleHandler->invokeAllWith('cron', static function (callable $implementation, string $module) use (&$implementations): void {
      $implementations[] = $module;
    });

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
