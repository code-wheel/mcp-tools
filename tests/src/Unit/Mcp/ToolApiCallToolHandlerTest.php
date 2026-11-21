<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Unit\Mcp;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\mcp_tools\Mcp\ToolApiCallToolHandler;
use Drupal\mcp_tools\Mcp\ToolApiSchemaConverter;
use Drupal\mcp_tools\Mcp\ToolInputValidator;
use Drupal\Tests\UnitTestCase;
use Drupal\tool\ExecutableResult;
use Drupal\tool\Tool\ToolBase;
use Drupal\tool\Tool\ToolDefinition;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinition;
use Drupal\tool\TypedData\InputDefinitionInterface;
use Drupal\tool\TypedData\ListInputDefinition;
use Drupal\tool\TypedData\OutputDefinition;
use Mcp\Schema\JsonRpc\Error;
use Mcp\Schema\JsonRpc\Response;
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Server\Session\SessionInterface;
use Psr\Log\NullLogger;
use Drupal\Core\Logger\LoggerChannelInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Tests for ToolApiCallToolHandler.
 *
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools\Mcp\ToolApiCallToolHandler::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
final class ToolApiCallToolHandlerTest extends UnitTestCase {

  public function testUnknownToolReturnsMethodNotFoundError(): void {
    $toolManager = $this->createMock(PluginManagerInterface::class);
    $toolManager->method('getDefinitions')->willReturn([]);

    $handler = new ToolApiCallToolHandler(
      $toolManager,
      new NullLogger(),
      FALSE,
      'mcp_tools',
      new ToolInputValidator(new ToolApiSchemaConverter(), new NullLogger()),
    );

    $request = (new CallToolRequest('mcp_tools___missing', []))->withId(1);
    $session = $this->createMock(SessionInterface::class);

    $result = $handler->handle($request, $session);

    $this->assertInstanceOf(Error::class, $result);
    $this->assertSame(1, $result->id);
    $this->assertSame(Error::METHOD_NOT_FOUND, $result->code);
    $this->assertStringContainsString('Unknown tool:', $result->message);
  }

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

    $handler = new ToolApiCallToolHandler(
      $toolManager,
      new NullLogger(),
      FALSE,
      'mcp_tools',
      new ToolInputValidator(new ToolApiSchemaConverter(), new NullLogger()),
    );

    $request = (new CallToolRequest('other___test', []))->withId('req-1');
    $session = $this->createMock(SessionInterface::class);

    $result = $handler->handle($request, $session);

    $this->assertInstanceOf(Error::class, $result);
    $this->assertSame('req-1', $result->id);
    $this->assertSame(Error::METHOD_NOT_FOUND, $result->code);
  }

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
    $tool = new class([], 'mcp_tools:test', $definition, $currentUser, $this->createMock(EventDispatcherInterface::class), $this->createMock(LoggerChannelInterface::class)) extends ToolBase {

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

    $handler = new ToolApiCallToolHandler(
      $toolManager,
      new NullLogger(),
      FALSE,
      'mcp_tools',
      new ToolInputValidator(new ToolApiSchemaConverter(), new NullLogger()),
    );

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
   * An empty map goes out as {} and not as [].
   *
   * PHP has one empty array for both. A client that checks the result
   * against the outputSchema rejects [] for an output declared as a map.
   */
  public function testEmptyMapOutputsAreSentAsObjects(): void {
    $definitions = [
      'by_type' => new OutputDefinition('map', new TranslatableMarkup('By type'), new TranslatableMarkup('Counts by type.')),
      'items' => new OutputDefinition('list', new TranslatableMarkup('Items'), new TranslatableMarkup('Items.')),
      'filled' => new OutputDefinition('map', new TranslatableMarkup('Filled'), new TranslatableMarkup('A map with values.')),
      'total' => new OutputDefinition('integer', new TranslatableMarkup('Total'), new TranslatableMarkup('Total.')),
    ];
    $shape = new \ReflectionMethod(ToolApiCallToolHandler::class, 'emptyMapsAsObjects');
    $output = $shape->invoke(NULL, [
      'by_type' => [],
      'items' => [],
      'filled' => ['a' => 1],
      'total' => 0,
      'undeclared' => [],
    ], $definitions);

    $this->assertSame(
      '{"by_type":{},"items":[],"filled":{"a":1},"total":0,"undeclared":[]}',
      json_encode($output),
    );
  }

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
    $tool = new class([], 'mcp_tools:denied', $definition, $currentUser, $this->createMock(EventDispatcherInterface::class), $this->createMock(LoggerChannelInterface::class)) extends ToolBase {

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

    $handler = new ToolApiCallToolHandler(
      $toolManager,
      new NullLogger(),
      FALSE,
      'mcp_tools',
      new ToolInputValidator(new ToolApiSchemaConverter(), new NullLogger()),
    );

    $request = (new CallToolRequest('mcp_tools___denied', []))->withId('req-2');
    $session = $this->createMock(SessionInterface::class);

    $result = $handler->handle($request, $session);

    $this->assertInstanceOf(Response::class, $result);
    $this->assertInstanceOf(CallToolResult::class, $result->result);
    $this->assertTrue($result->result->isError);
    $this->assertSame('Access denied.', $result->result->structuredContent['error'] ?? NULL);
  }

  public function testInvalidArgumentsReturnErrorResult(): void {
    $toolManager = $this->createMock(PluginManagerInterface::class);

    $countDefinition = $this->createMock(InputDefinitionInterface::class);
    $countDefinition->method('getDataType')->willReturn('integer');
    $countDefinition->method('isRequired')->willReturn(TRUE);
    $countDefinition->method('getDescription')->willReturn(NULL);
    $countDefinition->method('getConstraints')->willReturn([]);
    $countDefinition->method('isMultiple')->willReturn(FALSE);

    $definition = new ToolDefinition([
      'id' => 'mcp_tools:validate',
      'provider' => 'mcp_tools',
      'label' => new TranslatableMarkup('Validate'),
      'description' => new TranslatableMarkup('Validation tool'),
      'operation' => ToolOperation::Read,
      'destructive' => FALSE,
      'input_definitions' => [
        'count' => $countDefinition,
      ],
    ]);

    $toolManager->method('getDefinitions')->willReturn([
      'mcp_tools:validate' => $definition,
    ]);

    $handler = new ToolApiCallToolHandler(
      $toolManager,
      new NullLogger(),
      FALSE,
      'mcp_tools',
      new ToolInputValidator(new ToolApiSchemaConverter(), new NullLogger()),
    );

    $request = (new CallToolRequest('mcp_tools___validate', ['count' => 'not-a-number']))->withId('invalid-1');
    $session = $this->createMock(SessionInterface::class);

    $result = $handler->handle($request, $session);

    $this->assertInstanceOf(Response::class, $result);
    $this->assertInstanceOf(CallToolResult::class, $result->result);
    $this->assertTrue($result->result->isError);
    $this->assertSame(FALSE, $result->result->structuredContent['success'] ?? NULL);
    $this->assertArrayHasKey('validation_errors', $result->result->structuredContent);
  }

  /**
   * A call with a real Tool API list input is checked, not crashed.
   *
   * Other modules' tools (Tool Belt, for one) declare lists with
   * ListInputDefinition. Preparing such an argument called a method that
   * class does not have.
   */
  public function testListInputArgumentsAreValidated(): void {
    $toolManager = $this->createMock(PluginManagerInterface::class);

    // The schema converter prints descriptions, which needs a translation
    // service the unit test does not have: give the markup a stub.
    $t = fn(string $text): TranslatableMarkup => new TranslatableMarkup($text, [], [], $this->getStringTranslationStub());
    $tags = new ListInputDefinition(
      label: $t('Tags'),
      description: $t('Tag names.'),
      required: TRUE,
      constraints: [],
      item_definition: new InputDefinition('string', $t('Tag'), $t('A tag name.')),
    );
    $countDefinition = $this->createMock(InputDefinitionInterface::class);
    $countDefinition->method('getDataType')->willReturn('integer');
    $countDefinition->method('isRequired')->willReturn(TRUE);
    $countDefinition->method('getDescription')->willReturn(NULL);
    $countDefinition->method('getConstraints')->willReturn([]);
    $countDefinition->method('isMultiple')->willReturn(FALSE);

    $definition = new ToolDefinition([
      'id' => 'other_module:tagged',
      'provider' => 'other_module',
      'label' => $t('Tagged'),
      'description' => $t('Takes a list of tags'),
      'operation' => ToolOperation::Read,
      'destructive' => FALSE,
      'input_definitions' => [
        'tags' => $tags,
        'count' => $countDefinition,
      ],
    ]);
    $toolManager->method('getDefinitions')->willReturn([
      'other_module:tagged' => $definition,
    ]);

    $handler = new ToolApiCallToolHandler(
      $toolManager,
      new NullLogger(),
      TRUE,
      'mcp_tools',
      new ToolInputValidator(new ToolApiSchemaConverter(), new NullLogger()),
    );

    // The list is fine; the bad count stops the call at the input check, so
    // no tool has to run.
    $request = (new CallToolRequest('other_module___tagged', ['tags' => ['a', 'b'], 'count' => 'many']))->withId('list-1');
    $result = $handler->handle($request, $this->createMock(SessionInterface::class));

    $this->assertInstanceOf(Response::class, $result);
    $this->assertTrue($result->result->isError);
    $errors = $result->result->structuredContent['validation_errors'] ?? [];
    $paths = array_column($errors, 'path');
    $this->assertContains('count', $paths);
    $this->assertNotContains('tags', $paths, 'The list of strings is accepted.');
  }

}
