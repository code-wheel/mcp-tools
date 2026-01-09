<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_config\Unit\EventSubscriber;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\ConfigRenameEvent;
use Drupal\mcp_tools\Service\McpToolCallContext;
use Drupal\mcp_tools_config\EventSubscriber\McpConfigChangeSubscriber;
use Drupal\mcp_tools_config\Service\ConfigManagementService;
use PHPUnit\Framework\TestCase;

/**
 * Tests for McpConfigChangeSubscriber.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools_config\EventSubscriber\McpConfigChangeSubscriber::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_config')]
final class McpConfigChangeSubscriberTest extends TestCase {

  private ConfigManagementService $configManagement;
  private McpToolCallContext $toolCallContext;
  private McpConfigChangeSubscriber $subscriber;

  protected function setUp(): void {
    parent::setUp();
    $this->configManagement = $this->createMock(ConfigManagementService::class);
    $this->toolCallContext = $this->createMock(McpToolCallContext::class);

    $this->subscriber = new McpConfigChangeSubscriber(
      $this->configManagement,
      $this->toolCallContext,
    );
  }

  public function testGetSubscribedEventsReturnsExpectedEvents(): void {
    $events = McpConfigChangeSubscriber::getSubscribedEvents();

    $this->assertArrayHasKey(ConfigEvents::SAVE, $events);
    $this->assertArrayHasKey(ConfigEvents::DELETE, $events);
    $this->assertArrayHasKey(ConfigEvents::RENAME, $events);
    $this->assertSame('onConfigSave', $events[ConfigEvents::SAVE]);
    $this->assertSame('onConfigDelete', $events[ConfigEvents::DELETE]);
    $this->assertSame('onConfigRename', $events[ConfigEvents::RENAME]);
  }

  public function testOnConfigSaveIgnoresWhenNotInMcpContext(): void {
    $this->toolCallContext->method('isActive')->willReturn(FALSE);

    $this->configManagement->expects($this->never())->method('trackChange');

    $config = $this->createMock(Config::class);
    $event = new ConfigCrudEvent($config);

    $this->subscriber->onConfigSave($event);
  }

  public function testOnConfigSaveIgnoresEmptyConfigName(): void {
    $this->toolCallContext->method('isActive')->willReturn(TRUE);

    $config = $this->createMock(Config::class);
    $config->method('getName')->willReturn('');

    $this->configManagement->expects($this->never())->method('trackChange');

    $event = new ConfigCrudEvent($config);
    $this->subscriber->onConfigSave($event);
  }

  public function testOnConfigSaveTracksCreateForNewConfig(): void {
    $this->toolCallContext->method('isActive')->willReturn(TRUE);

    $config = $this->createMock(Config::class);
    $config->method('getName')->willReturn('system.site');
    $config->method('getOriginal')->willReturn([]);

    $this->configManagement->expects($this->once())
      ->method('trackChange')
      ->with('system.site', 'create');

    $event = new ConfigCrudEvent($config);
    $this->subscriber->onConfigSave($event);
  }

  public function testOnConfigSaveTracksUpdateForExistingConfig(): void {
    $this->toolCallContext->method('isActive')->willReturn(TRUE);

    $config = $this->createMock(Config::class);
    $config->method('getName')->willReturn('system.site');
    $config->method('getOriginal')->willReturn(['name' => 'Old Site']);

    $this->configManagement->expects($this->once())
      ->method('trackChange')
      ->with('system.site', 'update');

    $event = new ConfigCrudEvent($config);
    $this->subscriber->onConfigSave($event);
  }

  public function testOnConfigDeleteIgnoresWhenNotInMcpContext(): void {
    $this->toolCallContext->method('isActive')->willReturn(FALSE);

    $this->configManagement->expects($this->never())->method('trackChange');

    $config = $this->createMock(Config::class);
    $event = new ConfigCrudEvent($config);

    $this->subscriber->onConfigDelete($event);
  }

  public function testOnConfigDeleteIgnoresEmptyConfigName(): void {
    $this->toolCallContext->method('isActive')->willReturn(TRUE);

    $config = $this->createMock(Config::class);
    $config->method('getName')->willReturn('');

    $this->configManagement->expects($this->never())->method('trackChange');

    $event = new ConfigCrudEvent($config);
    $this->subscriber->onConfigDelete($event);
  }

  public function testOnConfigDeleteTracksDelete(): void {
    $this->toolCallContext->method('isActive')->willReturn(TRUE);

    $config = $this->createMock(Config::class);
    $config->method('getName')->willReturn('views.view.content');

    $this->configManagement->expects($this->once())
      ->method('trackChange')
      ->with('views.view.content', 'delete');

    $event = new ConfigCrudEvent($config);
    $this->subscriber->onConfigDelete($event);
  }

  public function testOnConfigRenameIgnoresWhenNotInMcpContext(): void {
    $this->toolCallContext->method('isActive')->willReturn(FALSE);

    $this->configManagement->expects($this->never())->method('trackChange');

    $config = $this->createMock(Config::class);
    $event = new ConfigRenameEvent($config, 'old.config');

    $this->subscriber->onConfigRename($event);
  }

  public function testOnConfigRenameTracksRenameForBothNames(): void {
    $this->toolCallContext->method('isActive')->willReturn(TRUE);

    $config = $this->createMock(Config::class);
    $config->method('getName')->willReturn('new.config');

    $this->configManagement->expects($this->exactly(2))
      ->method('trackChange')
      ->willReturnCallback(function (string $name, string $operation): void {
        $this->assertSame('rename', $operation);
        $this->assertTrue(in_array($name, ['old.config', 'new.config'], TRUE));
      });

    $event = new ConfigRenameEvent($config, 'old.config');
    $this->subscriber->onConfigRename($event);
  }

  public function testOnConfigRenameIgnoresEmptyOldName(): void {
    $this->toolCallContext->method('isActive')->willReturn(TRUE);

    $config = $this->createMock(Config::class);
    $config->method('getName')->willReturn('new.config');

    $this->configManagement->expects($this->once())
      ->method('trackChange')
      ->with('new.config', 'rename');

    $event = new ConfigRenameEvent($config, '');
    $this->subscriber->onConfigRename($event);
  }

  public function testOnConfigRenameIgnoresEmptyNewName(): void {
    $this->toolCallContext->method('isActive')->willReturn(TRUE);

    $config = $this->createMock(Config::class);
    $config->method('getName')->willReturn('');

    $this->configManagement->expects($this->once())
      ->method('trackChange')
      ->with('old.config', 'rename');

    $event = new ConfigRenameEvent($config, 'old.config');
    $this->subscriber->onConfigRename($event);
  }

}
