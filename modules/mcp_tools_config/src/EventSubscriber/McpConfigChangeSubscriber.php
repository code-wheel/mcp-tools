<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_config\EventSubscriber;

use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\ConfigRenameEvent;
use Drupal\mcp_tools\Service\McpToolCallContext;
use Drupal\mcp_tools_config\Service\ConfigManagementService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Tracks configuration changes performed during MCP tool execution.
 */
final class McpConfigChangeSubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly ConfigManagementService $configManagement,
    private readonly McpToolCallContext $toolCallContext,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      ConfigEvents::SAVE => 'onConfigSave',
      ConfigEvents::DELETE => 'onConfigDelete',
      ConfigEvents::RENAME => 'onConfigRename',
    ];
  }

  public function onConfigSave(ConfigCrudEvent $event): void {
    if (!$this->toolCallContext->isActive()) {
      return;
    }

    $config = $event->getConfig();
    $name = (string) $config->getName();
    if ($name === '') {
      return;
    }

    // Best-effort create/update detection. Core sets isNew=FALSE before SAVE,
    // so we use empty original data as an indicator of "create".
    $original = $config->getOriginal();
    $operation = $original === [] ? 'create' : 'update';

    $this->configManagement->trackChange($name, $operation);
  }

  public function onConfigDelete(ConfigCrudEvent $event): void {
    if (!$this->toolCallContext->isActive()) {
      return;
    }

    $name = (string) $event->getConfig()->getName();
    if ($name !== '') {
      $this->configManagement->trackChange($name, 'delete');
    }
  }

  public function onConfigRename(ConfigRenameEvent $event): void {
    if (!$this->toolCallContext->isActive()) {
      return;
    }

    $oldName = (string) $event->getOldName();
    $newName = (string) $event->getConfig()->getName();

    if ($oldName !== '') {
      $this->configManagement->trackChange($oldName, 'rename');
    }
    if ($newName !== '') {
      $this->configManagement->trackChange($newName, 'rename');
    }
  }

}

