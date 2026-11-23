<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_migration\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigrateIdMapMessageEvent;
use Drupal\migrate\Event\MigratePostRowSaveEvent;
use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Drupal\migrate\Plugin\migrate\id_map\Sql;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Runs an import as a Drupal migration.
 *
 * An import is a migrate_plus migration, one per content type and key, with
 * its rows in key-value storage (the mcp_import_rows source). Migrate's id
 * map gives what the old loop lacked: a
 * second run updates what the first made, a run that stops goes on where
 * it was, status and undo come from what was really made, and
 * `drush migrate:import <id>` (migrate_tools) runs the same import.
 */
class MigrateImport {

  /**
   * Rows saved or failed in one call, at most.
   */
  public const RUN_SIZE = 100;

  /**
   * The start of every import's migration id.
   */
  public const PREFIX = 'mcp_import_';

  /**
   * The key-value collection that holds each import's rows.
   */
  public const ROWS = 'mcp_tools_migration.import_rows';

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ?MigrationPluginManagerInterface $migrationManager,
    protected EventDispatcherInterface $eventDispatcher,
    protected KeyValueFactoryInterface $keyValue,
  ) {}

  /**
   * The migration id of an import: the same type and key, the same import.
   */
  public static function id(string $entityType, string $bundle, string $key): string {
    $name = $entityType === 'node' ? "{$bundle}__{$key}" : "{$entityType}__{$bundle}__{$key}";
    return substr(self::PREFIX . preg_replace('/[^a-z0-9_]+/', '_', strtolower($name)), 0, 160);
  }

  /**
   * Whether Migrate and migrate_plus are on, so an import can run.
   */
  public function ready(): bool {
    return $this->migrationManager !== NULL && $this->entityTypeManager->hasDefinition('migration');
  }

  /**
   * Whether an id names an import of ours that is there.
   */
  public function exists(string $id): bool {
    return str_starts_with($id, self::PREFIX) && $this->entityTypeManager->getStorage('migration')->load($id) !== NULL;
  }

  /**
   * The keys of these that an earlier run of this import already made.
   *
   * @param string $id
   *   The import's migration id.
   * @param string[] $keys
   *   Source keys.
   *
   * @return array<string, true>
   *   The keys that have a node, as keys.
   */
  public function made(string $id, array $keys): array {
    if (!$this->exists($id)) {
      return [];
    }
    $map = $this->migrationManager->createInstance($id)->getIdMap();
    $made = [];
    foreach ($keys as $key) {
      foreach ($map->lookupDestinationIds([(string) $key]) as $destination) {
        if (array_filter($destination, fn($v) => $v !== NULL && $v !== '')) {
          $made[(string) $key] = TRUE;
        }
      }
    }
    return $made;
  }

  /**
   * Saves the import's migration with these rows.
   *
   * @param string $id
   *   The import's migration id.
   * @param string $entityType
   *   The entity type.
   * @param string $bundle
   *   The bundle.
   * @param string $key
   *   The source key column.
   * @param array<int, array<string, mixed>> $rows
   *   Rows ready for the destination, the key column included.
   * @param string[] $fields
   *   The fields the rows set.
   * @param array<string, mixed> $defaults
   *   Values a new entity gets when the import has no column for them.
   * @param string[] $selfFields
   *   Reference fields whose values may be other rows of this import: the
   *   rows hold _self_<field> (keys) and _ids_<field> (ids there already).
   */
  public function save(string $id, string $entityType, string $bundle, string $key, array $rows, array $fields, array $defaults = [], array $selfFields = []): void {
    $process = [];
    foreach ($fields as $field) {
      $process[$field] = in_array($field, $selfFields, TRUE)
        ? ['plugin' => 'mcp_import_reference', 'source' => ["_self_$field", "_ids_$field"]]
        : $field;
    }
    foreach ($defaults as $field => $value) {
      $process[$field] = ['plugin' => 'default_value', 'default_value' => $value];
    }
    $storage = $this->entityTypeManager->getStorage('migration');
    $migration = $storage->load($id) ?? $storage->create(['id' => $id]);
    $migration->set('label', "MCP Tools import: $entityType $bundle by $key");
    $migration->set('migration_tags', ['MCP Tools']);
    $migration->set('migration_dependencies', ['required' => [], 'optional' => []]);
    $this->keyValue->get(self::ROWS)->set($id, $rows);
    $migration->set('source', [
      'plugin' => 'mcp_import_rows',
      'import' => $id,
      'ids' => [$key => ['type' => 'string']],
      'track_changes' => TRUE,
    ]);
    $migration->set('process', $process);
    $migration->set('destination', [
      'plugin' => "entity:$entityType",
      'default_bundle' => $bundle,
      'validate' => TRUE,
      // An update sets only the columns the import has. A default, such as
      // unpublished for a new node, is not forced on a node that is there.
      'overwrite_properties' => array_values(array_diff($fields, ['nid', 'uuid'])),
    ]);
    $migration->save();
  }

  /**
   * Runs up to RUN_SIZE rows of an import.
   *
   * @return array{saved: string[], failed: array<string, string>, done: bool, remaining: int, reset: bool}
   *   The keys saved, the keys that failed with why, whether the import is
   *   done, the rows not yet run, and whether a stale run had to be reset.
   */
  public function run(string $id, int $rowCount): array {
    $migration = $this->migrationManager->createInstance($id);
    $reset = FALSE;
    // A run that died leaves the migration busy for good. In one request
    // nothing else of ours runs it, so busy here is stale.
    if ($migration->getStatus() !== MigrationInterface::STATUS_IDLE) {
      $migration->setStatus(MigrationInterface::STATUS_IDLE);
      $reset = TRUE;
    }
    $saved = [];
    $failed = [];
    $stopWhenFull = function () use ($migration, &$saved, &$failed): void {
      if (count($saved) + count($failed) >= self::RUN_SIZE) {
        $migration->interruptMigration(MigrationInterface::RESULT_INCOMPLETE);
      }
    };
    $onSave = function (MigratePostRowSaveEvent $event) use ($id, &$saved, $stopWhenFull): void {
      if ($event->getMigration()->id() === $id) {
        $ids = $event->getRow()->getSourceIdValues();
        $saved[] = (string) reset($ids);
        $stopWhenFull();
      }
    };
    $onMessage = function (MigrateIdMapMessageEvent $event) use ($id, &$failed, $stopWhenFull): void {
      if ($event->getMigration()->id() === $id) {
        $ids = $event->getSourceIdValues();
        $key = (string) reset($ids);
        $failed[$key] = isset($failed[$key]) ? $failed[$key] . ' ' . $event->getMessage() : (string) $event->getMessage();
        $stopWhenFull();
      }
    };
    $deleteOnUndo = $this->deleteOnUndo($migration);
    $this->eventDispatcher->addListener(MigrateEvents::POST_ROW_SAVE, $onSave);
    $this->eventDispatcher->addListener(MigrateEvents::IDMAP_MESSAGE, $onMessage);
    try {
      $result = (new MigrateExecutable($migration, new MigrateMessage()))->import();
    }
    finally {
      $this->eventDispatcher->removeListener(MigrateEvents::POST_ROW_SAVE, $onSave);
      $this->eventDispatcher->removeListener(MigrateEvents::IDMAP_MESSAGE, $onMessage);
    }
    $this->keepDeleteOnUndo($migration, array_intersect_key($deleteOnUndo, array_flip($saved)));
    $failed = array_diff_key($failed, array_flip($saved));
    $remaining = max(0, $rowCount - (int) $migration->getIdMap()->processedCount());
    $done = $result !== MigrationInterface::RESULT_INCOMPLETE;
    return [
      'saved' => $saved,
      'failed' => $failed,
      'done' => $done,
      'remaining' => $done ? 0 : $remaining,
      'reset' => $reset,
    ];
  }

  /**
   * The keys whose node (term, redirect) an undo deletes: the import made it.
   *
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *   The import's migration.
   *
   * @return array<string, true>
   *   Keys, as keys.
   */
  protected function deleteOnUndo(MigrationInterface $migration): array {
    $map = $migration->getIdMap();
    if (!$map instanceof Sql) {
      return [];
    }
    $keys = $map->getDatabase()->select($map->mapTableName(), 'm')
      ->fields('m', ['sourceid1'])
      ->condition('rollback_action', MigrateIdMapInterface::ROLLBACK_DELETE)
      ->execute()
      ->fetchCol();
    return array_fill_keys(array_map('strval', $keys), TRUE);
  }

  /**
   * Gives back "delete on undo" to rows a run updated.
   *
   * With overwrite_properties, Drupal's entity destination updates a copy
   * of the row that has lost its id map (Row::cloneWithoutDestination), so
   * it takes the entity for one that was there before the import and marks
   * it to be kept on rollback. An undo then left every row a second run had
   * updated. Rows the import made are set back to delete.
   *
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *   The import's migration.
   * @param array<string, true> $keys
   *   Keys that were delete-on-undo before the run and ran in it.
   */
  protected function keepDeleteOnUndo(MigrationInterface $migration, array $keys): void {
    $map = $migration->getIdMap();
    if ($keys === [] || !$map instanceof Sql) {
      return;
    }
    $map->getDatabase()->update($map->mapTableName())
      ->fields(['rollback_action' => MigrateIdMapInterface::ROLLBACK_DELETE])
      ->condition('sourceid1', array_keys($keys), 'IN')
      ->execute();
  }

  /**
   * What an import made and what failed, from its id map.
   *
   * @return array<string, mixed>
   *   The counts and up to 20 messages.
   */
  public function status(string $id): array {
    $migration = $this->migrationManager->createInstance($id);
    $map = $migration->getIdMap();
    $messages = [];
    foreach ($map->getMessages() as $message) {
      if (count($messages) >= 20) {
        break;
      }
      $key = '';
      foreach ((array) $message as $column => $value) {
        if (str_starts_with((string) $column, 'src_') || $column === 'sourceid1') {
          $key = (string) $value;
          break;
        }
      }
      $messages[] = ['key' => $key, 'message' => (string) $message->message];
    }
    return [
      'import_id' => $id,
      'status' => (string) $migration->getStatusLabel(),
      'total_rows' => count((array) $this->keyValue->get(self::ROWS)->get($id, [])),
      'imported' => (int) $map->importedCount(),
      'failed' => (int) $map->errorCount(),
      'processed' => (int) $map->processedCount(),
      'messages' => $messages,
    ];
  }

  /**
   * How many nodes an undo of this import would delete.
   */
  public function madeCount(string $id): int {
    return (int) $this->migrationManager->createInstance($id)->getIdMap()->importedCount();
  }

  /**
   * Undoes an import: deletes the nodes it made, keeps the ones it updated.
   *
   * @return bool
   *   Whether the rollback completed.
   */
  public function rollback(string $id): bool {
    $migration = $this->migrationManager->createInstance($id);
    if ($migration->getStatus() !== MigrationInterface::STATUS_IDLE) {
      $migration->setStatus(MigrationInterface::STATUS_IDLE);
    }
    $result = (new MigrateExecutable($migration, new MigrateMessage()))->rollback();
    if ($result !== MigrationInterface::RESULT_COMPLETED) {
      return FALSE;
    }
    $migration->getIdMap()->destroy();
    $this->entityTypeManager->getStorage('migration')->load($id)?->delete();
    $this->keyValue->get(self::ROWS)->delete($id);
    return TRUE;
  }

}
