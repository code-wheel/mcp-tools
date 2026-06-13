<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_ai\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Verifies the Tool API -> drupal/ai FunctionCall bridge.
 *
 * @group mcp_tools_ai
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
final class AiFunctionCallBridgeTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'key',
    'update',
    'ai',
    'tool',
    'mcp_tools',
    'mcp_tools_ai',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['mcp_tools_ai']);
  }

  /**
   * The default (read/explain) curation exposes read tools and executes one.
   */
  public function testReadOnlyCurationAndExecution(): void {
    /** @var \Drupal\Component\Plugin\PluginManagerInterface $manager */
    $manager = $this->container->get('plugin.manager.ai.function_calls');

    $bridged = array_filter(
      array_keys($manager->getDefinitions()),
      static fn (string $id): bool => str_starts_with($id, 'mcp_tools_tool:'),
    );
    $this->assertNotEmpty($bridged, 'Tool API tools are exposed as AI Function Calls.');

    // A known read-only tool must be present under the default curation.
    $site_status = 'mcp_tools_tool:mcp_tools_get_site_status';
    $this->assertContains($site_status, $bridged);

    // And it must execute through the AI manager, returning real output.
    /** @var \Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface $fc */
    $fc = $manager->createInstance($site_status);
    $fc->execute();
    $output = $fc->getReadableOutput();
    $this->assertNotSame('', $output);
    $this->assertStringContainsString('success', $output);
  }

  /**
   * The operation-based curation filter actually filters.
   *
   * Core MCP Tools provides only read-operation tools, so the default
   * (read + explain) exposes them and restricting to write hides them all —
   * proving exposure follows the configured operation set.
   */
  public function testCurationFilterIsApplied(): void {
    $manager = $this->container->get('plugin.manager.ai.function_calls');
    $count = static fn (): int => count(array_filter(
      array_keys($manager->getDefinitions()),
      static fn (string $id): bool => str_starts_with($id, 'mcp_tools_tool:'),
    ));

    // Default read + explain curation exposes the core read tools.
    $this->assertGreaterThan(0, $count());

    // Restricting exposure to write hides every read-only tool (core has no
    // write tools), so nothing is bridged.
    $this->config('mcp_tools_ai.settings')
      ->set('exposed_operations', ['write'])
      ->save();
    $manager->clearCachedDefinitions();
    $this->assertSame(0, $count(), 'Restricting to write hides all read-only core tools.');

    // Re-enabling read brings them back.
    $this->config('mcp_tools_ai.settings')
      ->set('exposed_operations', ['read'])
      ->save();
    $manager->clearCachedDefinitions();
    $this->assertGreaterThan(0, $count());
  }

}
