<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Unit\Mcp;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\mcp_tools\Mcp\ToolApiCallToolHandler;
use Drupal\Tests\UnitTestCase;
use Drupal\tool\ExecutableResult;
use Drupal\tool\Tool\ToolBase;
use Drupal\tool\Tool\ToolDefinition;
use Drupal\tool\Tool\ToolOperation;
use Mcp\Schema\JsonRpc\Error;
use Mcp\Schema\JsonRpc\Response;
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Server\Session\SessionInterface;
use Psr\Log\NullLogger;

/**
 * Tests for ToolApiCallToolHandler.
 *
 * @coversDefaultClass \Drupal\mcp_tools\Mcp\ToolApiCallToolHandler
 * @group mcp_tools
 */
final class ToolApiCallToolHandlerTest extends UnitTestCase {

  /**
   * @covers ::handle
   */
  public function testUnknownToolReturnsMethodNotFoundError(): void {
    $toolManager = $this->createMock(PluginManagerInterface::class);
    $toolManager->method('getDefinitions')->willReturn([]);

    $handler = new ToolApiCallToolHandler($toolManager, new NullLogger());

    $request = (new CallToolRequest('mcp_tools___missing', []))->withId(1);
    $session = $this->createMock(SessionInterface::class);

    $result = $handler->handle($request, $session);

    $this->assertInstanceOf(Error::class, $result);
    $this->assertSame(1, $result->id);
    $this->assertSame(Error::METHOD_NOT_FOUND, $result->code);
    $this->assertStringContainsString('Unknown tool:', $result->message);
  }

  /**
   * @covers ::handle
   */
  public function testProviderIsFilteredWhenIncludeAllToolsFalse(): void {
    $toolManager = $this->createMock(PluginManagerInterface::class);

    $otherDefinition = new ToolDefinition([
      'id' => 'other:test',
      'provider' => 'other_module',
      'label' => new TranslatableMarkup('Other'),
      'description' => new TranslatableMarkup('Other tool'),
      'operation' => ToolOperation::Read,
      'destructive' => FALSE,
    ]);

    $toolManager->method('getDefinitions')->willReturn([
      'other:test' => $otherDefinition,
    ]);

    $handler = new ToolApiCallToolHandler($toolManager, new NullLogger(), FALSE, 'mcp_tools');

    $request = (new CallToolRequest('other___test', []))->withId('req-1');
    $session = $this->createMock(SessionInterface::class);

    $result = $handler->handle($request, $session);

    $this->assertInstanceOf(Error::class, $result);
    $this->assertSame('req-1', $result->id);
    $this->assertSame(Error::METHOD_NOT_FOUND, $result->code);
  }

  /**
   * @covers ::handle
   */
  public function testCallToolReturnsResponseWithStructuredContent(): void {
    $toolManager = $this->createMock(PluginManagerInterface::class);

    $definition = new ToolDefinition([
      'id' => 'mcp_tools:test',
      'provider' => 'mcp_tools',
      'label' => new TranslatableMarkup('Test'),
      'description' => new TranslatableMarkup('Test tool'),
      'operation' => ToolOperation::Read,
      'destructive' => FALSE,
    ]);

    $currentUser = $this->createMock(AccountInterface::class);
    $tool = new class([], 'mcp_tools:test', $definition, $currentUser) extends ToolBase {

      /**
       * {@inheritdoc}
       */
      protected function doExecute(array $values): ExecutableResult {
        $translation = new class implements TranslationInterface {

          /**
           * {@inheritdoc}
           */
          public function translate($string, array $args = [], array $options = []): TranslatableMarkup {
            return new TranslatableMarkup((string) $string, $args, $options, $this);
          }

          /**
           * {@inheritdoc}
           */
          public function translateString(TranslatableMarkup $translated_string): string {
            return $translated_string->getUntranslatedString();
          }

          /**
           * {@inheritdoc}
           */
          public function formatPlural($count, $singular, $plural, array $args = [], array $options = []): TranslatableMarkup {
            $useSingular = (int) $count === 1;
            $string = $useSingular ? (string) $singular : str_replace('@count', (string) $count, (string) $plural);
            return new TranslatableMarkup($string, $args, $options, $this);
          }

        };

        return ExecutableResult::success(new TranslatableMarkup('OK', [], [], $translation), ['foo' => 'bar']);
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

    $handler = new ToolApiCallToolHandler($toolManager, new NullLogger());

    $request = (new CallToolRequest('mcp_tools___test', []))->withId(99);

    $session = $this->createMock(SessionInterface::class);

    $result = $handler->handle($request, $session);

    $this->assertInstanceOf(Response::class, $result);
    $this->assertInstanceOf(CallToolResult::class, $result->result);
    $this->assertFalse($result->result->isError);
    $this->assertSame(TRUE, $result->result->structuredContent['success'] ?? NULL);
    $this->assertSame('OK', $result->result->structuredContent['message'] ?? NULL);
    $this->assertSame(['foo' => 'bar'], $result->result->structuredContent['data'] ?? NULL);
  }

  /**
   * @covers ::handle
   */
  public function testAccessDeniedReturnsIsErrorResult(): void {
    $toolManager = $this->createMock(PluginManagerInterface::class);

    $definition = new ToolDefinition([
      'id' => 'mcp_tools:denied',
      'provider' => 'mcp_tools',
      'label' => new TranslatableMarkup('Denied'),
      'description' => new TranslatableMarkup('Denied tool'),
      'operation' => ToolOperation::Read,
      'destructive' => FALSE,
    ]);

    $currentUser = $this->createMock(AccountInterface::class);
    $tool = new class([], 'mcp_tools:denied', $definition, $currentUser) extends ToolBase {

      /**
       * {@inheritdoc}
       */
      protected function doExecute(array $values): ExecutableResult {
        return ExecutableResult::success(new TranslatableMarkup('OK'));
      }

      /**
       * {@inheritdoc}
       */
      protected function checkAccess(array $values, AccountInterface $account, bool $return_as_object = FALSE): bool|AccessResultInterface {
        return FALSE;
      }

    };

    $toolManager->method('getDefinitions')->willReturn([
      'mcp_tools:denied' => $definition,
    ]);
    $toolManager->method('createInstance')->with('mcp_tools:denied')->willReturn($tool);

    $handler = new ToolApiCallToolHandler($toolManager, new NullLogger());

    $request = (new CallToolRequest('mcp_tools___denied', []))->withId('req-2');
    $session = $this->createMock(SessionInterface::class);

    $result = $handler->handle($request, $session);

    $this->assertInstanceOf(Response::class, $result);
    $this->assertInstanceOf(CallToolResult::class, $result->result);
    $this->assertTrue($result->result->isError);
    $this->assertSame('Access denied.', $result->result->structuredContent['error'] ?? NULL);
  }

}
