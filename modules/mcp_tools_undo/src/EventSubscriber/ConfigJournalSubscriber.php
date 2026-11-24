<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_undo\EventSubscriber;

use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\mcp_tools_undo\Service\ChangeJournal;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Writes down config a tool call saves or deletes, with its data before.
 */
class ConfigJournalSubscriber implements EventSubscriberInterface {

  public function __construct(
    protected ChangeJournal $journal,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      ConfigEvents::SAVE => 'onSave',
      ConfigEvents::DELETE => 'onDelete',
    ];
  }

  /**
   * Config saved.
   */
  public function onSave(ConfigCrudEvent $event): void {
    $config = $event->getConfig();
    $this->journal->configChanged($config->getName(), (array) ($config->getOriginal('', FALSE) ?? []), $config->getRawData());
  }

  /**
   * Config deleted.
   */
  public function onDelete(ConfigCrudEvent $event): void {
    $config = $event->getConfig();
    $this->journal->configChanged($config->getName(), (array) ($config->getOriginal('', FALSE) ?? []), NULL);
  }

}
