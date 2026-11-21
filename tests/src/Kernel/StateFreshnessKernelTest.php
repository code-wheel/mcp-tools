<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Kernel;

use Drupal\Core\State\State;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Tests that tools see State changed by another process.
 *
 * The stdio server is one long-running process. Drupal's State service loads
 * its values once per object, so without a reset every later tool call saw
 * the values from the first read (#3613067).
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
final class StateFreshnessKernelTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'dblog',
    'update',
    'tool',
    'mcp_tools',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installSchema('dblog', ['watchdog']);
    $this->installConfig(['system', 'mcp_tools']);
    $this->setUpCurrentUser([], [], TRUE);
  }

  /**
   * A second tool call sees a cron run recorded by another process.
   */
  public function testToolCallSeesStateWrittenElsewhere(): void {
    $this->container->get('state')->set('system.cron_last', 1700000000);
    $this->assertSame(1700000000, $this->lastCronRun());

    // Another process (a web request, drush cron) records a newer cron run.
    $this->newState()->set('system.cron_last', 1700003600);

    $this->assertSame(1700003600, $this->lastCronRun());
  }

  /**
   * A tool call leaves the State cache that all requests share in place.
   */
  public function testToolCallKeepsTheSharedStateCache(): void {
    // Drupal 10 only caches State when this is set.
    $this->setSetting('state_cache', TRUE);
    $cache = $this->container->get('cache.bootstrap');

    // Another request leaves a valid shared cache entry.
    $writer = $this->newState();
    $writer->set('system.cron_last', 1700000000);
    $writer->destruct();
    $this->assertNotFalse($cache->get('state', TRUE));

    $this->assertSame(1700000000, $this->lastCronRun());

    $this->assertNotFalse($cache->get('state', TRUE), 'The shared State cache entry was deleted.');
  }

  /**
   * A key deleted in one tool call does not come back through the cache.
   *
   * A delete only invalidates the shared cache entry and removes the key
   * from it at the end of the request. Forgetting that pending removal at
   * the start of the next call would write the old entry back.
   */
  public function testDeletedKeyDoesNotComeBackThroughTheStateCache(): void {
    $this->setSetting('state_cache', TRUE);

    // Another request leaves a valid shared cache entry that holds the key.
    $writer = $this->newState();
    $writer->set('mcp_tools.config_changes', ['a']);
    $writer->destruct();

    $state = $this->container->get('state');
    $context = $this->container->get('mcp_tools.tool_call_context');

    // Tool call 1 deletes the key, as mcp_config_export does.
    $context->enter();
    $state->delete('mcp_tools.config_changes');
    $context->leave();

    // Tool call 2 writes another key, then the process ends.
    $context->enter();
    $state->set('system.cron_last', 1700000000);
    $context->leave();
    $state->destruct();

    $this->assertNull($this->newState()->get('mcp_tools.config_changes'));
  }

  /**
   * Builds a State object as another process would hold it.
   */
  private function newState(): State {
    return new State(
      $this->container->get('keyvalue'),
      $this->container->get('cache.bootstrap'),
      $this->container->get('lock'),
    );
  }

  /**
   * Runs mcp_tools_check_cron_status and returns the last run timestamp.
   */
  private function lastCronRun(): mixed {
    $tool = $this->container->get('plugin.manager.tool')->createInstance('mcp_tools_check_cron_status');
    $tool->execute();
    $this->assertTrue($tool->getResult()->isSuccess(), (string) $tool->getResult()->getMessage());
    return $tool->getOutputValues()['last_run_timestamp'];
  }

}
