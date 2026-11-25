<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_undo\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\Entity\ConfigEntityStorageInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\Core\Session\AccountInterface;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\mcp_tools\Service\McpToolCallContext;

/**
 * Undoes what one tool call changed, when nothing changed since.
 *
 * Each change is checked first: an entity or config that is not as the
 * call left it refuses the whole undo, so later work is not overwritten.
 * Then the changes are undone newest first: what the call made is deleted,
 * what it changed gets the revision or the values before, what it deleted
 * is made again. Drupal's own APIs do each, so a config entity's side
 * effects (a field's table, a content type's settings) happen as on a
 * save by hand.
 */
class UndoService {

  public function __construct(
    protected ChangeJournal $journal,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ConfigFactoryInterface $configFactory,
    protected ConfigManagerInterface $configManager,
    protected AccessManager $accessManager,
    protected AuditLogger $auditLogger,
    protected AccountInterface $currentUser,
    protected McpToolCallContext $toolCallContext,
  ) {}

  /**
   * The newest calls that changed something, and what they changed.
   */
  public function recent(int $limit = 20): array {
    $calls = [];
    foreach ($this->journal->recent($limit) as $callId) {
      $record = $this->journal->get($callId);
      $calls[] = [
        'call_id' => $callId,
        'time' => date('c', (int) $record['time']),
        'uid' => $record['uid'],
        'undone' => isset($record['undone_by']),
        'changes' => array_map([$this, 'describe'], $record['changes']),
      ];
    }
    return [
      'success' => TRUE,
      'data' => [
        'calls' => $calls,
        'message' => $calls ? sprintf('%d recent calls changed something. mcp_undo_call undoes one.', count($calls)) : 'No tool call has changed anything that is kept.',
      ],
    ];
  }

  /**
   * Undoes one call.
   *
   * @param string $callId
   *   The call.
   * @param bool $byPerson
   *   TRUE from the Reports page: the route checked the person's Drupal
   *   permission, so the scopes of an MCP connection do not apply.
   */
  public function undo(string $callId, bool $byPerson = FALSE): array {
    $record = $this->journal->get($callId);
    if ($record === NULL) {
      return [
        'success' => FALSE,
        'error' => "No changes are kept for the call '$callId'. mcp_undo_recent lists the recent calls.",
        'code' => 'NOT_FOUND',
      ];
    }
    if (isset($record['undone_by'])) {
      return ['success' => FALSE, 'error' => "The call '$callId' was undone already.", 'code' => 'ALREADY_UNDONE'];
    }
    // Another account's call needs the admin scope.
    $mine = (int) $record['uid'] === (int) $this->currentUser->id();
    $allowed = $byPerson || ($mine ? $this->accessManager->canWrite('write', count($record['changes'])) : $this->accessManager->canAdmin('structure', count($record['changes'])));
    if (!$allowed) {
      return $this->accessManager->getWriteAccessDenied();
    }

    // Config-only mode holds for what an undo writes.
    foreach ($byPerson ? [] : $record['changes'] as $change) {
      $kind = $change['kind'] === 'config' ? AccessManager::WRITE_KIND_CONFIG : AccessManager::WRITE_KIND_CONTENT;
      if (!$this->accessManager->isWriteKindAllowed($kind)) {
        return [
          'success' => FALSE,
          'error' => "Nothing was undone: the call changed $kind, and config-only mode does not allow that.",
          'code' => 'ACCESS_DENIED',
        ];
      }
    }

    $changes = array_reverse($record['changes']);
    $conflicts = [];
    foreach ($changes as $change) {
      if (($why = $this->conflict($change)) !== NULL) {
        $conflicts[] = $this->describe($change) + ['why' => $why];
      }
    }
    if ($conflicts) {
      return [
        'success' => FALSE,
        'error' => sprintf('Nothing was undone: %d of the call\'s changes were changed again since, and undoing them would overwrite that. %s', count($conflicts), implode(' ', array_column($conflicts, 'why'))),
        'code' => 'CONFLICT',
        'conflicts' => $conflicts,
      ];
    }

    $done = [];
    foreach ($changes as $change) {
      $change['kind'] === 'entity' ? $this->undoEntity($change, $callId) : $this->undoConfig($change);
      $done[] = $this->describe($change);
    }
    $this->journal->markUndone($callId, $byPerson ? 'a person' : (string) ($this->toolCallContext->getCorrelationId() ?? 'direct'));
    $this->auditLogger->logSuccess('undo_call', 'mcp_call', $callId, ['changes' => count($done)]);
    return [
      'success' => TRUE,
      'data' => [
        'call_id' => $callId,
        'undone_count' => count($done),
        'undone' => $done,
        'message' => sprintf('Undone: %d changes of the call %s.', count($done), $callId),
      ],
    ];
  }

  /**
   * Why a change cannot be undone now, or NULL.
   */
  protected function conflict(array $change): ?string {
    if ($change['kind'] === 'config') {
      $config = $this->configFactory->get($change['name']);
      if ($change['op'] === 'deleted') {
        return $config->isNew() ? NULL : "The config {$change['name']} was made again since.";
      }
      if ($config->isNew()) {
        return $change['op'] === 'made' ? NULL : "The config {$change['name']} was deleted since.";
      }
      return ChangeJournal::hash($config->getRawData()) === $change['after'] ? NULL : "The config {$change['name']} was changed since.";
    }
    $storage = $this->entityTypeManager->getStorage($change['entity_type']);
    $storage->resetCache([$change['id']]);
    $entity = $storage->load($change['id']);
    $what = "{$change['entity_type']} {$change['id']}";
    if ($change['op'] === 'deleted') {
      return $entity === NULL ? NULL : "The $what was made again since.";
    }
    if (!$entity instanceof ContentEntityInterface) {
      return $change['op'] === 'made' ? NULL : "The $what was deleted since.";
    }
    $now = ChangeJournal::stamp($entity);
    $after = $change['after'];
    $same = isset($after['revision']) ? $now['revision'] === $after['revision']
      : (isset($after['changed']) ? $now['changed'] === $after['changed'] : $now['hash'] === $after['hash']);
    return $same ? NULL : "The $what was changed since.";
  }

  /**
   * Undoes a change to a content entity.
   */
  protected function undoEntity(array $change, string $callId): void {
    $storage = $this->entityTypeManager->getStorage($change['entity_type']);
    $entity = $storage->load($change['id']);
    switch ($change['op']) {
      case 'made':
        $entity?->delete();
        return;

      case 'deleted':
        $made = $storage->create($change['values_before']);
        $made->enforceIsNew();
        $made->save();
        return;
    }
    if (isset($change['revision_before'])) {
      // Back to the revision before, as a new revision: the history stays.
      $before = $storage->loadRevision($change['revision_before']);
      $revert = $storage->createRevision($before, TRUE);
      if ($revert instanceof RevisionLogInterface) {
        $revert->setRevisionLogMessage("Undo of the MCP Tools call $callId");
      }
      $revert->save();
      return;
    }
    $keys = $entity->getEntityType()->getKeys();
    $never = array_filter([$keys['id'] ?? NULL, $keys['uuid'] ?? NULL, $keys['revision'] ?? NULL, 'changed']);
    foreach ($change['values_before'] as $field => $items) {
      if (in_array($field, $never, TRUE) || !$entity->hasField($field) || $entity->get($field)->getValue() == $items) {
        continue;
      }
      $entity->set($field, $items);
      // A password comes back as the hash it was, not hashed again.
      if ($field === 'pass' && isset($entity->get('pass')->pre_hashed)) {
        $entity->get('pass')->pre_hashed = TRUE;
      }
    }
    $entity->save();
  }

  /**
   * Undoes a change to config, through the config entity when it is one.
   */
  protected function undoConfig(array $change): void {
    $name = $change['name'];
    $entityTypeId = $this->configManager->getEntityTypeIdByName($name);
    if ($entityTypeId !== NULL) {
      $storage = $this->entityTypeManager->getStorage($entityTypeId);
      $entity = $this->configManager->loadConfigEntityByName($name);
      if ($change['op'] === 'made') {
        $entity?->delete();
      }
      elseif ($storage instanceof ConfigEntityStorageInterface) {
        $entity = $change['op'] === 'deleted' || $entity === NULL
          ? $storage->createFromStorageRecord($change['data_before'])
          : $storage->updateFromStorageRecord($entity, $change['data_before']);
        $entity->save();
      }
      return;
    }
    $config = $this->configFactory->getEditable($name);
    $change['op'] === 'made' ? $config->delete() : $config->setData($change['data_before'])->save();
  }

  /**
   * A change in a few words.
   */
  protected function describe(array $change): array {
    return $change['kind'] === 'config'
      ? ['op' => $change['op'], 'config' => $change['name']]
      : [
        'op' => $change['op'],
        'entity_type' => $change['entity_type'],
        'id' => $change['id'],
        'label' => $change['label'] ?? '',
      ];
  }

}
