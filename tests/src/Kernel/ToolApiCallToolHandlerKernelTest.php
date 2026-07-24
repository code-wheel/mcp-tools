<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Kernel;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mcp_tools\Mcp\ToolApiCallToolHandler;
use Drupal\mcp_tools\Mcp\ToolApiSchemaConverter;
use Drupal\mcp_tools\Mcp\ToolInputValidator;
use Drupal\tool\ExecutableResult;
use Drupal\tool\Tool\ToolBase;
use Drupal\tool\Tool\ToolDefinition;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinition;
use Mcp\Schema\JsonRpc\Response;
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Server\Session\SessionInterface;
use Psr\Log\NullLogger;

/**
 * Kernel coverage for ToolApiCallToolHandler input upcasting.
 *
 */
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
final class ToolApiCallToolHandlerKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
  ];

  /**
   * Verifies argument upcasting for Tool API typed inputs.
   */
  public function testArgumentUpcasting(): void {
    $toolManager = $this->createMock(PluginManagerInterface::class);

    $definition = new ToolDefinition([
      'id' => 'mcp_tools:test',
      'provider' => 'mcp_tools',
      'label' => new TranslatableMarkup('Test'),
      'description' => new TranslatableMarkup('Test tool'),
      'operation' => ToolOperation::Read,
      'destructive' => FALSE,
      'input_definitions' => [
        'count' => new InputDefinition('integer', 'Count', 'Count', TRUE),
        'dry_run' => new InputDefinition('boolean', 'Dry run', 'Dry run', FALSE),
      ],
    ]);

    $currentUser = $this->createMock(AccountInterface::class);
    $tool = new class([], 'mcp_tools:test', $definition, $currentUser) extends ToolBase {

      /**
       * {@inheritdoc}
       */
      protected function doExecute(array $values): ExecutableResult {
        return ExecutableResult::success(new TranslatableMarkup('OK'), ['values' => $values]);
      }

      /**
       * {@inheritdoc}
       */
      protected function checkAccess(array $values, AccountInterface $account, bool $return_as_object = FALSE): bool|AccessResultInterface {
        return TRUE;
      }

    };

    $toolManager->method('getDefinitions')->willReturn([
      'mcp_tools:test' => $definition,
    ]);
    $toolManager->method('createInstance')->with('mcp_tools:test')->willReturn($tool);

    $handler = new ToolApiCallToolHandler(
      $toolManager,
      new NullLogger(),
      FALSE,
      'mcp_tools',
      new ToolInputValidator(new ToolApiSchemaConverter(), new NullLogger()),
    );

    $request = (new CallToolRequest('mcp_tools___test', [
      'count' => '5',
      'dry_run' => 'false',
    ]))->withId(1);

    $session = $this->createMock(SessionInterface::class);
    $result = $handler->handle($request, $session);

    $this->assertInstanceOf(Response::class, $result);
    $this->assertInstanceOf(CallToolResult::class, $result->result);
    $this->assertFalse($result->result->isError);

    $values = $result->result->structuredContent['data']['values'] ?? NULL;
    $this->assertIsArray($values);
    $this->assertSame(5, $values['count'] ?? NULL);
    $this->assertSame(FALSE, $values['dry_run'] ?? NULL);
  }

  /**
   * Undeclared arguments fail validation instead of being silently dropped.
   *
   * End-to-end regression for the incident where an undeclared "language"
   * key on a delete call was discarded and the call fell through to a
   * destructive full-node default (#3612659).
   */
  public function testUnknownArgumentIsRejected(): void {
    $handler = $this->buildHandler([
      'nid' => new InputDefinition('integer', 'Node ID', 'Node ID', TRUE),
    ]);
    $session = $this->createMock(SessionInterface::class);

    $request = (new CallToolRequest('mcp_tools___test', [
      'nid' => 5,
      'language' => 'it',
    ]))->withId(2);
    $result = $handler->handle($request, $session);

    $this->assertInstanceOf(Response::class, $result);
    $this->assertInstanceOf(CallToolResult::class, $result->result);
    $this->assertTrue($result->result->isError);
  }

  /**
   * Tools without declared inputs also reject stray arguments.
   */
  public function testZeroInputToolRejectsStrayArguments(): void {
    $handler = $this->buildHandler([]);
    $session = $this->createMock(SessionInterface::class);

    $ok = $handler->handle((new CallToolRequest('mcp_tools___test', []))->withId(3), $session);
    $this->assertInstanceOf(Response::class, $ok);
    $this->assertFalse($ok->result->isError);

    $bad = $handler->handle((new CallToolRequest('mcp_tools___test', ['bogus' => TRUE]))->withId(4), $session);
    $this->assertInstanceOf(Response::class, $bad);
    $this->assertTrue($bad->result->isError);
  }

  /**
   * Builds a handler around a passthrough test tool.
   *
   * @param array<string, \Drupal\tool\TypedData\InputDefinition> $inputDefinitions
   *   Input definitions for the test tool.
   */
  private function buildHandler(array $inputDefinitions): ToolApiCallToolHandler {
    $definition = new ToolDefinition([
      'id' => 'mcp_tools:test',
      'provider' => 'mcp_tools',
      'label' => new TranslatableMarkup('Test'),
      'description' => new TranslatableMarkup('Test tool'),
      'operation' => ToolOperation::Read,
      'destructive' => FALSE,
      'input_definitions' => $inputDefinitions,
    ]);

    $currentUser = $this->createMock(AccountInterface::class);
    $tool = new class([], 'mcp_tools:test', $definition, $currentUser) extends ToolBase {

      /**
       * {@inheritdoc}
       */
      protected function doExecute(array $values): ExecutableResult {
        return ExecutableResult::success(new TranslatableMarkup('OK'), ['values' => $values]);
      }

      /**
       * {@inheritdoc}
       */
      protected function checkAccess(array $values, AccountInterface $account, bool $return_as_object = FALSE): bool|AccessResultInterface {
        return TRUE;
      }

    };

    $toolManager = $this->createMock(PluginManagerInterface::class);
    $toolManager->method('getDefinitions')->willReturn([
      'mcp_tools:test' => $definition,
    ]);
    $toolManager->method('createInstance')->with('mcp_tools:test')->willReturn($tool);

    return new ToolApiCallToolHandler(
      $toolManager,
      new NullLogger(),
      FALSE,
      'mcp_tools',
      new ToolInputValidator(new ToolApiSchemaConverter(), new NullLogger()),
    );
  }

}
