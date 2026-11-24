<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_undo;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Url;

/**
 * The tool calls that changed something, newest first, with an undo.
 */
class McpToolCallListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  protected $limit = 50;

  /**
   * {@inheritdoc}
   */
  protected function getEntityIds() {
    return $this->getStorage()->getQuery()->accessCheck(TRUE)->sort('created', 'DESC')->sort('id', 'DESC')->pager($this->limit)->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    return [
      'time' => $this->t('Time'),
      'user' => $this->t('User'),
      'summary' => $this->t('What it changed'),
      'state' => $this->t('State'),
    ] + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    $owner = $entity->get('uid')->entity;
    return [
      'time' => \Drupal::service('date.formatter')->format((int) $entity->get('created')->value, 'short'),
      'user' => $owner ? $owner->getDisplayName() : $this->t('Anonymous'),
      'summary' => (string) $entity->get('summary')->value,
      'state' => $entity->get('state')->value === 'undone' ? $this->t('Undone') : $this->t('Applied'),
    ] + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultOperations(EntityInterface $entity): array {
    $operations = [];
    if ($entity->access('undo')) {
      $operations['undo'] = [
        'title' => $this->t('Undo'),
        'weight' => 0,
        'url' => Url::fromRoute('entity.mcp_tool_call.undo_form', ['mcp_tool_call' => $entity->id()]),
      ];
    }
    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  public function render(): array {
    $build = parent::render();
    $build['table']['#empty'] = $this->t('No tool call has changed anything yet. Only calls made while MCP Tools - Undo is on are kept.');
    return $build;
  }

}
