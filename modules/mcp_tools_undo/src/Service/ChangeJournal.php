<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_undo\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\mcp_tools\Service\McpToolCallContext;
use Drupal\mcp_tools_undo\Entity\McpToolCall;

/**
 * What each tool call changed, so that it can be undone.
 *
 * Content entities made, changed and deleted, and config saved and deleted,
 * while a tool call runs, kept on one mcp_tool_call entity a call. Each
 * change keeps what the undo needs (the revision before, or the values or
 * data before) and what it was after, so that a change made since can be
 * seen. Calls older than the keep_days setting are removed on cron.
 */
class ChangeJournal {

  /**
   * The entity of the running call, by call id: one load a request.
   *
   * @var array<string, int>
   */
  protected array $entityIds = [];

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected McpToolCallContext $toolCallContext,
    protected AccountInterface $currentUser,
    protected TimeInterface $time,
    protected ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * A content entity the call made.
   */
  public function entityMade(ContentEntityInterface $entity): void {
    $this->add([
      'kind' => 'entity',
      'op' => 'made',
      'entity_type' => $entity->getEntityTypeId(),
      'id' => (string) $entity->id(),
      'label' => (string) $entity->label(),
      'after' => self::stamp($entity),
    ]);
  }

  /**
   * A content entity the call changed.
   */
  public function entityChanged(ContentEntityInterface $entity): void {
    // getOriginal() came with Drupal 11.2; before, the "original" property.
    $original = method_exists($entity, 'getOriginal') ? $entity->getOriginal() : ($entity->original ?? NULL);
    if (!$original instanceof ContentEntityInterface) {
      return;
    }
    $revisionable = $entity instanceof RevisionableInterface && $entity->getEntityType()->isRevisionable();
    $change = [
      'kind' => 'entity',
      'op' => 'changed',
      'entity_type' => $entity->getEntityTypeId(),
      'id' => (string) $entity->id(),
      'label' => (string) $original->label(),
      'after' => self::stamp($entity),
    ];
    // A save without a new revision changed the revision in place: only the
    // values before can put it back.
    if ($revisionable && (string) $original->getRevisionId() !== (string) $entity->getRevisionId()) {
      $change['revision_before'] = (string) $original->getRevisionId();
    }
    else {
      $change['values_before'] = $original->toArray();
    }
    $this->add($change);
  }

  /**
   * A content entity the call deleted.
   */
  public function entityDeleted(ContentEntityInterface $entity): void {
    $this->add([
      'kind' => 'entity',
      'op' => 'deleted',
      'entity_type' => $entity->getEntityTypeId(),
      'id' => (string) $entity->id(),
      'label' => (string) $entity->label(),
      'values_before' => $entity->toArray(),
    ]);
  }

  /**
   * Config the call saved or deleted.
   *
   * @param string $name
   *   The config name.
   * @param array $before
   *   Its data before ([] when it was new).
   * @param array|null $after
   *   Its data after, NULL when deleted.
   */
  public function configChanged(string $name, array $before, ?array $after): void {
    $this->add([
      'kind' => 'config',
      'op' => $after === NULL ? 'deleted' : ($before === [] ? 'made' : 'changed'),
      'name' => $name,
      'data_before' => $before,
      'after' => $after === NULL ? NULL : self::hash($after),
    ]);
  }

  /**
   * A call's record, or NULL when there is none (or it was removed).
   *
   * @return array{call_id: string, time: int, uid: int, changes: array, undone_by?: string}|null
   *   The record.
   */
  public function get(string $callId): ?array {
    $call = $this->entity($callId);
    if ($call === NULL) {
      return NULL;
    }
    $record = [
      'call_id' => $callId,
      'time' => (int) $call->get('created')->value,
      'uid' => (int) $call->get('uid')->target_id,
      'changes' => $call->getChanges(),
    ];
    if ($call->get('state')->value === McpToolCall::UNDONE) {
      $record['undone_by'] = (string) $call->get('undone_by')->value;
    }
    return $record;
  }

  /**
   * The newest calls that changed something, newest first.
   *
   * @return string[]
   *   Call ids.
   */
  public function recent(int $limit): array {
    $storage = $this->entityTypeManager->getStorage('mcp_tool_call');
    $ids = $storage->getQuery()->accessCheck(FALSE)->sort('created', 'DESC')->sort('id', 'DESC')->range(0, max(1, $limit))->execute();
    return array_values(array_map(fn($call) => (string) $call->get('call_id')->value, $storage->loadMultiple($ids)));
  }

  /**
   * Marks a call as undone, so it is not undone twice.
   */
  public function markUndone(string $callId, string $byCall): void {
    $call = $this->entity($callId);
    if ($call !== NULL) {
      $call->set('state', McpToolCall::UNDONE)->set('undone_by', $byCall)->save();
    }
  }

  /**
   * Removes the calls older than the keep_days setting.
   *
   * @return int
   *   How many were removed.
   */
  public function removeOld(): int {
    $days = max(1, (int) ($this->configFactory->get('mcp_tools_undo.settings')->get('keep_days') ?: 30));
    $storage = $this->entityTypeManager->getStorage('mcp_tool_call');
    $ids = $storage->getQuery()->accessCheck(FALSE)->condition('created', $this->time->getRequestTime() - $days * 86400, '<')->range(0, 500)->execute();
    if ($ids) {
      $storage->delete($storage->loadMultiple($ids));
    }
    return count($ids);
  }

  /**
   * What identifies an entity's state now: its revision, or its values.
   */
  public static function stamp(ContentEntityInterface $entity): array {
    $stamp = ['hash' => self::hash(self::comparable($entity->toArray()))];
    if ($entity instanceof RevisionableInterface && $entity->getEntityType()->isRevisionable()) {
      $stamp['revision'] = (string) $entity->getRevisionId();
    }
    if ($entity instanceof EntityChangedInterface) {
      $stamp['changed'] = (int) $entity->getChangedTime();
    }
    return $stamp;
  }

  /**
   * A hash of data, the same for the same data.
   */
  public static function hash(array $data): string {
    return hash('sha256', serialize(self::sorted($data)));
  }

  /**
   * Values without the ones every save moves on.
   */
  protected static function comparable(array $values): array {
    unset($values['changed'], $values['revision_timestamp'], $values['revision_log'], $values['revision_log_message']);
    return $values;
  }

  /**
   * Data with its keys sorted, at every level.
   */
  protected static function sorted(array $data): array {
    ksort($data);
    foreach ($data as $key => $value) {
      if (is_array($value)) {
        $data[$key] = self::sorted($value);
      }
    }
    return $data;
  }

  /**
   * Adds a change to the running call's record.
   */
  protected function add(array $change): void {
    if (!$this->toolCallContext->isActive() || ($callId = $this->toolCallContext->getCorrelationId()) === NULL) {
      return;
    }
    $call = $this->entity($callId) ?? $this->entityTypeManager->getStorage('mcp_tool_call')->create([
      'call_id' => $callId,
      'uid' => (int) $this->currentUser->id(),
      'created' => $this->time->getCurrentTime(),
    ]);
    $changes = $call->getChanges();
    $changes[] = $change;
    $call->setChanges($changes)->save();
    $this->entityIds[$callId] = (int) $call->id();
  }

  /**
   * The entity of a call, or NULL.
   */
  protected function entity(string $callId): ?McpToolCall {
    $storage = $this->entityTypeManager->getStorage('mcp_tool_call');
    if (isset($this->entityIds[$callId])) {
      $storage->resetCache([$this->entityIds[$callId]]);
      $call = $storage->load($this->entityIds[$callId]);
      if ($call instanceof McpToolCall) {
        return $call;
      }
    }
    $found = $storage->loadByProperties(['call_id' => $callId]);
    $call = reset($found);
    return $call instanceof McpToolCall ? $call : NULL;
  }

}
