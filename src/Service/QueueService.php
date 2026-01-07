<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerManagerInterface;

/**
 * Service for queue monitoring.
 */
class QueueService {

  public function __construct(
    protected QueueFactory $queueFactory,
    protected QueueWorkerManagerInterface $queueWorkerManager,
    protected Connection $database,
  ) {}

  /**
   * Get status of all queues.
   *
   * @return array
   *   Queue status data.
   */
  public function getQueueStatus(): array {
    $queues = [];

    // Get all defined queue workers.
    $definitions = $this->queueWorkerManager->getDefinitions();

    foreach ($definitions as $name => $definition) {
      $queue = $this->queueFactory->get($name);

      $queues[] = [
        'name' => $name,
        'title' => (string) ($definition['title'] ?? $name),
        'items' => $queue->numberOfItems(),
        'cron' => isset($definition['cron']),
        'cron_time' => $definition['cron']['time'] ?? NULL,
      ];
    }

    // Also check database queue table for any queues not in workers.
    if ($this->database->schema()->tableExists('queue')) {
      $dbQueues = $this->database->select('queue', 'q')
        ->fields('q', ['name'])
        ->groupBy('name')
        ->execute()
        ->fetchCol();

      $workerNames = array_column($queues, 'name');
      foreach ($dbQueues as $dbQueue) {
        if (!in_array($dbQueue, $workerNames)) {
          $queue = $this->queueFactory->get($dbQueue);
          $queues[] = [
            'name' => $dbQueue,
            'title' => $dbQueue,
            'items' => $queue->numberOfItems(),
            'cron' => FALSE,
            'note' => 'No worker defined',
          ];
        }
      }
    }

    // Sort by item count descending.
    usort($queues, fn($a, $b) => $b['items'] - $a['items']);

    $totalItems = array_sum(array_column($queues, 'items'));
    $queuesWithItems = count(array_filter($queues, fn($q) => $q['items'] > 0));

    return [
      'total_queues' => count($queues),
      'queues_with_items' => $queuesWithItems,
      'total_items' => $totalItems,
      'queues' => $queues,
    ];
  }

  /**
   * Get detailed info about a specific queue.
   *
   * @param string $name
   *   Queue name.
   *
   * @return array
   *   Queue details.
   */
  public function getQueueDetails(string $name): array {
    $queue = $this->queueFactory->get($name);
    $definitions = $this->queueWorkerManager->getDefinitions();
    $definition = $definitions[$name] ?? NULL;

    $info = [
      'name' => $name,
      'items' => $queue->numberOfItems(),
      'has_worker' => $definition !== NULL,
    ];

    if ($definition) {
      $info['title'] = (string) ($definition['title'] ?? $name);
      $info['cron'] = isset($definition['cron']);
      $info['cron_time'] = $definition['cron']['time'] ?? NULL;
    }

    // Get oldest item age if using database queue.
    if ($this->database->schema()->tableExists('queue')) {
      try {
        $oldest = $this->database->select('queue', 'q')
          ->fields('q', ['created'])
          ->condition('name', $name)
          ->orderBy('created', 'ASC')
          ->range(0, 1)
          ->execute()
          ->fetchField();

        if ($oldest) {
          $info['oldest_item_age'] = time() - $oldest;
          $info['oldest_item_date'] = date('Y-m-d H:i:s', $oldest);
        }
      }
      catch (\Exception $e) {
        // Ignore.
      }
    }

    return $info;
  }

}
