<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\mcp_tools\Traits\DeclaredOutputTypesTrait;
use Drupal\mcp_tools\Mcp\ToolApiCallToolHandler;
use Drupal\mcp_tools\Mcp\ToolApiSchemaConverter;
use Drupal\mcp_tools\Mcp\ToolInputValidator;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\tool\Tool\ToolDefinition;
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Server\Session\SessionInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Uid\Uuid;

/**
 * Tests that tool results pass Tool API's output validation.
 *
 * Tool API 1.0.0-beta10 and later fail a result whose required outputs are
 * not set. MCP Tools results fill only the outputs that apply and backfill
 * the rest with NULL, and the advertised outputSchema already allows null for
 * every output, so every output has to be optional.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
final class ToolOutputContractKernelTest extends KernelTestBase {

  use DeclaredOutputTypesTrait;

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'field',
    'text',
    'filter',
    'dblog',
    'update',
    'tool',
    'mcp_tools',
    'mcp_tools_cache',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('dblog', ['watchdog']);
    $this->installConfig(['system', 'mcp_tools']);
    $this->setUpCurrentUser([], [], TRUE);
    $this->config('mcp_tools.settings')
      ->set('access.allowed_scopes', ['read', 'write', 'admin'])
      ->set('access.read_only_mode', FALSE)
      ->save();
    $this->container->get('mcp_tools.access_manager')->setScopes(['read', 'write', 'admin']);
  }

  /**
   * Every output of every MCP Tools tool is optional.
   */
  public function testAllOutputsAreOptional(): void {
    $required = [];
    foreach ($this->container->get('plugin.manager.tool')->getDefinitions() as $id => $definition) {
      if (!$definition instanceof ToolDefinition || !str_starts_with($definition->getProvider(), 'mcp_tools')) {
        continue;
      }
      foreach ($definition->getOutputDefinitions() as $name => $output) {
        if ($output->isRequired()) {
          $required[] = "$id:$name";
        }
      }
    }
    $this->assertSame([], $required);
  }

  /**
   * Tool definitions use Tool API's own definition classes.
   *
   * Declaring outputs with core's ContextDefinition is deprecated in
   * tool:1.0.0-beta9 and removed in tool:1.0.0-rc1 (#3613062).
   */
  public function testDiscoveryRaisesNoToolDeprecations(): void {
    $deprecations = [];
    set_error_handler(static function (int $level, string $message) use (&$deprecations): bool {
      if (str_contains($message, 'deprecated in tool:')) {
        $deprecations[] = $message;
      }
      return TRUE;
    }, E_USER_DEPRECATED);
    try {
      $manager = $this->container->get('plugin.manager.tool');
      $manager->clearCachedDefinitions();
      $manager->getDefinitions();
    }
    finally {
      restore_error_handler();
    }
    $this->assertSame([], array_slice($deprecations, 0, 3), sprintf('%d Tool API deprecations.', count($deprecations)));
  }

  /**
   * Tools whose results leave outputs unset still succeed.
   *
   * Both failed on Tool API 1.0.0-beta12 with "This output is required but
   * was not provided": the cache clear because its declared success and
   * message outputs came back as the result envelope, the cron check because
   * a site where cron never ran has no seconds_since_last_run.
   */
  public function testResultsWithUnsetOutputsSucceed(): void {
    $manager = $this->container->get('plugin.manager.tool');

    $tool = $manager->createInstance('mcp_tools_check_cron_status');
    $tool->execute();
    $this->assertTrue($tool->getResult()->isSuccess(), (string) $tool->getResult()->getMessage());
    $this->assertOutputsHaveDeclaredTypes($tool, (string) $tool->getPluginId());

    // Runs last: clearing all caches rebuilds the container.
    $tool = $manager->createInstance('mcp_cache_clear_all');
    $tool->execute();
    $this->assertTrue($tool->getResult()->isSuccess(), (string) $tool->getResult()->getMessage());
    $this->assertOutputsHaveDeclaredTypes($tool, (string) $tool->getPluginId());
    // Declared outputs the result returns at its top level are filled from
    // there, not backfilled with NULL.
    $outputs = $tool->getOutputValues();
    $this->assertTrue($outputs['success']);
    $this->assertSame('All caches have been cleared.', $outputs['message']);
  }

  /**
   * A failed call carries its message and no block of empty outputs.
   *
   * With every output optional, reading the outputs of a failed tool no
   * longer throws, so the handler must not report them as data.
   */
  public function testFailedCallCarriesNoOutputData(): void {
    $handler = new ToolApiCallToolHandler(
      $this->container->get('plugin.manager.tool'),
      new NullLogger(),
      FALSE,
      'mcp_tools',
      new ToolInputValidator(new ToolApiSchemaConverter(), new NullLogger()),
    );
    $session = $this->createMock(SessionInterface::class);
    $session->method('get')->willReturnArgument(1);
    $session->method('getId')->willReturn(Uuid::v4());

    $request = (new CallToolRequest('mcp_get_text_format', ['id' => 'does_not_exist']))->withId(1);
    $result = $handler->handle($request, $session)->result;

    $this->assertTrue($result->isError);
    $this->assertFalse($result->structuredContent['success']);
    $this->assertSame([], $result->structuredContent['data']);
    $this->assertSame($result->structuredContent['message'], $result->content[0]->text);
  }

}
