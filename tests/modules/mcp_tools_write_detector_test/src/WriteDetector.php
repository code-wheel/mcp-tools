<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_write_detector_test;

use CodeWheel\McpEvents\ToolExecutionFailedEvent;
use CodeWheel\McpEvents\ToolExecutionStartedEvent;
use CodeWheel\McpEvents\ToolExecutionSucceededEvent;
use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\State\StateInterface;
use Drupal\tool\Tool\ToolDefinition;
use Drupal\tool\Tool\ToolManager;
use Drupal\tool\Tool\ToolOperation;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Catches tools that say they only read and then write.
 *
 * A tool declared as a read operation is advertised to clients as
 * read-only, shown to read-only sessions and let through in config-only
 * mode. A test that goes by the tool's name cannot tell whether that is
 * true. This watches what happens: while a read tool runs, every entity or
 * config write is recorded in State under "mcp_tools_write_detector_test".
 * It works for the tools of any module, not only MCP Tools' own.
 */
final class WriteDetector implements EventSubscriberInterface {

  public const STATE_KEY = 'mcp_tools_write_detector_test';

  /**
   * Where the keys tools returned and did not declare are kept.
   */
  public const DROPPED_KEY = 'mcp_tools_write_detector_test.dropped';

  /**
   * The plugin ID of the read tool that is running, if one is.
   *
   * Static on purpose. In a long session a tool can rebuild the container.
   * The server then still announces tool calls to the service it was built
   * with, while config and entity writes reach the new one. Both have to
   * see the same tool.
   */
  private static ?string $readTool = NULL;

  public function __construct(
    private readonly ToolManager $toolManager,
    private readonly StateInterface $state,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      ToolExecutionStartedEvent::class => 'onStarted',
      ToolExecutionSucceededEvent::class => 'onFinished',
      ToolExecutionFailedEvent::class => 'onFinished',
      ConfigEvents::SAVE => 'onConfigSave',
      ConfigEvents::DELETE => 'onConfigDelete',
    ];
  }

  /**
   * Remembers the tool when it is declared as a read operation.
   */
  public function onStarted(ToolExecutionStartedEvent $event): void {
    self::$readTool = NULL;
    $definition = $this->toolManager->getDefinition($event->pluginId, FALSE);
    if ($definition instanceof ToolDefinition && $definition->getOperation() === ToolOperation::Read) {
      self::$readTool = $event->pluginId;
    }
  }

  /**
   * Forgets the tool once it is done.
   */
  public function onFinished(): void {
    self::$readTool = NULL;
  }

  /**
   * Records a config save.
   */
  public function onConfigSave(ConfigCrudEvent $event): void {
    $this->wrote('saved config ' . $event->getConfig()->getName());
  }

  /**
   * Records a config delete.
   */
  public function onConfigDelete(ConfigCrudEvent $event): void {
    $this->wrote('deleted config ' . $event->getConfig()->getName());
  }

  /**
   * Records a write, when a read tool is running.
   */
  public function wrote(string $what): void {
    if (self::$readTool === NULL) {
      return;
    }
    $seen = $this->state->get(self::STATE_KEY, []);
    $seen[] = self::$readTool . ': ' . $what;
    $this->state->set(self::STATE_KEY, array_values(array_unique($seen)));
  }

  /**
   * A tool returned keys it does not declare, which the client never gets.
   *
   * @param string $tool
   *   The tool's plugin ID.
   * @param string[] $keys
   *   The keys.
   */
  public function dropped(string $tool, array $keys): void {
    $seen = $this->state->get(self::DROPPED_KEY, []);
    $seen[$tool] = array_values(array_unique(array_merge($seen[$tool] ?? [], $keys)));
    $this->state->set(self::DROPPED_KEY, $seen);
  }

}
