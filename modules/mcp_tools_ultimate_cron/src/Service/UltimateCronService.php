<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_ultimate_cron\Service;

use CodeWheel\McpErrorCodes\ErrorCode;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Service for Ultimate Cron job management operations.
 */
class UltimateCronService {

  /**
   * The ultimate_cron_job entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $jobStorage;

  /**
   * Constructs an UltimateCronService object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   The logger factory.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected Connection $database,
    protected LoggerChannelFactoryInterface $loggerFactory,
  ) {
    $this->jobStorage = $entityTypeManager->getStorage('ultimate_cron_job');
  }

  /**
   * List all Ultimate Cron jobs with status.
   *
   * @return array
   *   Result array with list of jobs.
   */
  public function listJobs(): array {
    try {
      $jobs = $this->jobStorage->loadMultiple();
      $jobList = [];

      foreach ($jobs as $job) {
        /** @var \Drupal\ultimate_cron\Entity\CronJob $job */
        $jobList[] = $this->formatJobSummary($job);
      }

      // Sort by ID.
      usort($jobList, fn($a, $b) => strcmp($a['id'], $b['id']));

      return [
        'success' => TRUE,
        'data' => [
          'jobs' => $jobList,
          'count' => count($jobList),
        ],
      ];
    }
    catch (\Exception $e) {
      return [
        'success' => FALSE,
        'error' => 'Failed to list Ultimate Cron jobs: ' . $e->getMessage(),
        'code' => ErrorCode::INTERNAL_ERROR,
      ];
    }
  }

  /**
   * Get details of a specific Ultimate Cron job.
   *
   * @param string $id
   *   The job ID.
   *
   * @return array
   *   Result array with job details.
   */
  public function getJob(string $id): array {
    try {
      $job = $this->jobStorage->load($id);

      if (!$job) {
        return [
          'success' => FALSE,
          'error' => "Ultimate Cron job '$id' not found.",
          'code' => ErrorCode::NOT_FOUND,
        ];
      }

      /** @var \Drupal\ultimate_cron\Entity\CronJob $job */
      return [
        'success' => TRUE,
        'data' => $this->formatJobDetails($job),
      ];
    }
    catch (\Exception $e) {
      return [
        'success' => FALSE,
        'error' => 'Failed to get job details: ' . $e->getMessage(),
        'code' => ErrorCode::INTERNAL_ERROR,
      ];
    }
  }

  /**
   * Run a specific Ultimate Cron job immediately.
   *
   * @param string $id
   *   The job ID.
   *
   * @return array
   *   Result array.
   */
  public function runJob(string $id): array {
    try {
      $job = $this->jobStorage->load($id);

      if (!$job) {
        return [
          'success' => FALSE,
          'error' => "Ultimate Cron job '$id' not found.",
          'code' => ErrorCode::NOT_FOUND,
        ];
      }

      /** @var \Drupal\ultimate_cron\Entity\CronJob $job */
      if (!$job->status()) {
        return [
          'success' => FALSE,
          'error' => "Job '$id' is disabled. Enable it first before running.",
          'code' => 'JOB_DISABLED',
        ];
      }

      $startTime = microtime(TRUE);

      // Check if job is already running.
      if ($job->isLocked()) {
        return [
          'success' => FALSE,
          'error' => "Job '$id' is currently locked/running.",
          'code' => 'JOB_LOCKED',
        ];
      }

      // Run the job.
      $job->run();

      $endTime = microtime(TRUE);
      $duration = round($endTime - $startTime, 2);

      return [
        'success' => TRUE,
        'message' => "Job '$id' executed successfully.",
        'data' => [
          'id' => $id,
          'title' => $job->label(),
          'duration_seconds' => $duration,
          'executed_at' => date('Y-m-d H:i:s'),
        ],
      ];
    }
    catch (\Exception $e) {
      return [
        'success' => FALSE,
        'error' => "Failed to run job '$id': " . $e->getMessage(),
        'code' => 'EXECUTION_ERROR',
      ];
    }
  }

  /**
   * Enable an Ultimate Cron job.
   *
   * @param string $id
   *   The job ID.
   *
   * @return array
   *   Result array.
   */
  public function enableJob(string $id): array {
    try {
      $job = $this->jobStorage->load($id);

      if (!$job) {
        return [
          'success' => FALSE,
          'error' => "Ultimate Cron job '$id' not found.",
          'code' => ErrorCode::NOT_FOUND,
        ];
      }

      /** @var \Drupal\ultimate_cron\Entity\CronJob $job */
      if ($job->status()) {
        return [
          'success' => TRUE,
          'message' => "Job '$id' is already enabled.",
          'data' => [
            'id' => $id,
            'title' => $job->label(),
            'status' => 'enabled',
            'changed' => FALSE,
          ],
        ];
      }

      $job->enable();
      $job->save();

      return [
        'success' => TRUE,
        'message' => "Job '$id' has been enabled.",
        'data' => [
          'id' => $id,
          'title' => $job->label(),
          'status' => 'enabled',
          'changed' => TRUE,
        ],
      ];
    }
    catch (\Exception $e) {
      return [
        'success' => FALSE,
        'error' => "Failed to enable job '$id': " . $e->getMessage(),
        'code' => ErrorCode::INTERNAL_ERROR,
      ];
    }
  }

  /**
   * Disable an Ultimate Cron job.
   *
   * @param string $id
   *   The job ID.
   *
   * @return array
   *   Result array.
   */
  public function disableJob(string $id): array {
    try {
      $job = $this->jobStorage->load($id);

      if (!$job) {
        return [
          'success' => FALSE,
          'error' => "Ultimate Cron job '$id' not found.",
          'code' => ErrorCode::NOT_FOUND,
        ];
      }

      /** @var \Drupal\ultimate_cron\Entity\CronJob $job */
      if (!$job->status()) {
        return [
          'success' => TRUE,
          'message' => "Job '$id' is already disabled.",
          'data' => [
            'id' => $id,
            'title' => $job->label(),
            'status' => 'disabled',
            'changed' => FALSE,
          ],
        ];
      }

      $job->disable();
      $job->save();

      return [
        'success' => TRUE,
        'message' => "Job '$id' has been disabled.",
        'data' => [
          'id' => $id,
          'title' => $job->label(),
          'status' => 'disabled',
          'changed' => TRUE,
        ],
      ];
    }
    catch (\Exception $e) {
      return [
        'success' => FALSE,
        'error' => "Failed to disable job '$id': " . $e->getMessage(),
        'code' => ErrorCode::INTERNAL_ERROR,
      ];
    }
  }

  /**
   * Get recent logs for an Ultimate Cron job.
   *
   * @param string $id
   *   The job ID.
   * @param int $limit
   *   Maximum number of log entries to return.
   *
   * @return array
   *   Result array with log entries.
   */
  public function getJobLogs(string $id, int $limit = 50): array {
    try {
      $job = $this->jobStorage->load($id);

      if (!$job) {
        return [
          'success' => FALSE,
          'error' => "Ultimate Cron job '$id' not found.",
          'code' => ErrorCode::NOT_FOUND,
        ];
      }

      /** @var \Drupal\ultimate_cron\Entity\CronJob $job */
      $logs = [];

      // Get log entries from the database.
      $logEntries = $this->database->select('ultimate_cron_log', 'ucl')
        ->fields('ucl')
        ->condition('name', $id)
        ->orderBy('start_time', 'DESC')
        ->range(0, $limit)
        ->execute()
        ->fetchAll();

      foreach ($logEntries as $entry) {
        $logs[] = [
          'lid' => $entry->lid,
          'start_time' => $entry->start_time ? date('Y-m-d H:i:s', (int) $entry->start_time) : NULL,
          'end_time' => $entry->end_time ? date('Y-m-d H:i:s', (int) $entry->end_time) : NULL,
          'duration' => ($entry->start_time && $entry->end_time)
            ? round((float) $entry->end_time - (float) $entry->start_time, 2)
            : NULL,
          'init_message' => $entry->init_message ?? '',
          'message' => $entry->message ?? '',
          'severity' => $this->getSeverityLabel((int) ($entry->severity ?? 6)),
          'severity_code' => (int) ($entry->severity ?? 6),
        ];
      }

      return [
        'success' => TRUE,
        'data' => [
          'job_id' => $id,
          'job_title' => $job->label(),
          'logs' => $logs,
          'count' => count($logs),
          'limit' => $limit,
        ],
      ];
    }
    catch (\Exception $e) {
      return [
        'success' => FALSE,
        'error' => "Failed to get logs for job '$id': " . $e->getMessage(),
        'code' => ErrorCode::INTERNAL_ERROR,
      ];
    }
  }

  /**
   * Format job summary for list view.
   *
   * @param \Drupal\ultimate_cron\Entity\CronJob $job
   *   The cron job entity.
   *
   * @return array
   *   Formatted job summary.
   */
  protected function formatJobSummary($job): array {
    $lastRun = $this->getLastRunInfo($job->id());

    return [
      'id' => $job->id(),
      'title' => $job->label(),
      'module' => $job->getModule(),
      'status' => $job->status() ? 'enabled' : 'disabled',
      'is_locked' => $job->isLocked(),
      'last_run' => $lastRun['start_time'] ?? NULL,
      'last_duration' => $lastRun['duration'] ?? NULL,
      'last_status' => $lastRun['severity'] ?? NULL,
    ];
  }

  /**
   * Format detailed job information.
   *
   * @param \Drupal\ultimate_cron\Entity\CronJob $job
   *   The cron job entity.
   *
   * @return array
   *   Formatted job details.
   */
  protected function formatJobDetails($job): array {
    $lastRun = $this->getLastRunInfo($job->id());
    $schedulerPlugin = $job->getPlugin('scheduler');
    $launcherPlugin = $job->getPlugin('launcher');

    $details = [
      'id' => $job->id(),
      'title' => $job->label(),
      'module' => $job->getModule(),
      'callback' => $job->getCallback(),
      'status' => $job->status() ? 'enabled' : 'disabled',
      'is_locked' => $job->isLocked(),
      'scheduler' => [
        'id' => $schedulerPlugin ? $schedulerPlugin->getPluginId() : NULL,
      ],
      'launcher' => [
        'id' => $launcherPlugin ? $launcherPlugin->getPluginId() : NULL,
      ],
      'last_run' => $lastRun,
    ];

    // Get the scheduler configuration if available.
    $configuration = $job->get('scheduler');
    if ($configuration && isset($configuration['configuration'])) {
      $details['scheduler']['configuration'] = $configuration['configuration'];
    }

    return $details;
  }

  /**
   * Get last run information for a job.
   *
   * @param string $jobId
   *   The job ID.
   *
   * @return array
   *   Last run information.
   */
  protected function getLastRunInfo(string $jobId): array {
    try {
      $entry = $this->database->select('ultimate_cron_log', 'ucl')
        ->fields('ucl')
        ->condition('name', $jobId)
        ->orderBy('start_time', 'DESC')
        ->range(0, 1)
        ->execute()
        ->fetchObject();

      if ($entry) {
        return [
          'lid' => $entry->lid,
          'start_time' => $entry->start_time ? date('Y-m-d H:i:s', (int) $entry->start_time) : NULL,
          'end_time' => $entry->end_time ? date('Y-m-d H:i:s', (int) $entry->end_time) : NULL,
          'duration' => ($entry->start_time && $entry->end_time)
            ? round((float) $entry->end_time - (float) $entry->start_time, 2)
            : NULL,
          'message' => $entry->message ?? '',
          'severity' => $this->getSeverityLabel((int) ($entry->severity ?? 6)),
          'severity_code' => (int) ($entry->severity ?? 6),
        ];
      }
    }
    catch (\Exception $e) {
      // Log table might not exist or other error.
    }

    return [];
  }

  /**
   * Get severity label from severity code.
   *
   * @param int $severity
   *   The severity code (0-7, RFC 5424).
   *
   * @return string
   *   Human-readable severity label.
   */
  protected function getSeverityLabel(int $severity): string {
    $labels = [
      0 => 'emergency',
      1 => 'alert',
      2 => 'critical',
      3 => 'error',
      4 => 'warning',
      5 => 'notice',
      6 => 'info',
      7 => 'debug',
    ];

    return $labels[$severity] ?? 'unknown';
  }

}
