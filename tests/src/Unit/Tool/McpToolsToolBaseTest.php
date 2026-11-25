<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Unit\Tool;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Cache\Context\CacheContextsManager;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Tool\McpToolsToolBase;
use Drupal\Tests\UnitTestCase;
use Drupal\tool\Tool\ToolDefinition;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\OutputDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Drupal\Core\Logger\LoggerChannelInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools\Tool\McpToolsToolBase::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
final class McpToolsToolBaseTest extends UnitTestCase {

  private ?object $previousContainer = NULL;

  protected function setUp(): void {
    parent::setUp();

    $this->previousContainer = \Drupal::hasContainer() ? \Drupal::getContainer() : NULL;

    $container = new ContainerBuilder();
    $cacheContextsManager = $this->createMock(CacheContextsManager::class);
    $cacheContextsManager->method('assertValidTokens')->willReturn(TRUE);
    $container->set('cache_contexts_manager', $cacheContextsManager);
    $container->set('string_translation', $this->getStringTranslationStub());

    \Drupal::setContainer($container);
  }

  protected function tearDown(): void {
    if ($this->previousContainer) {
      \Drupal::setContainer($this->previousContainer);
    }
    else {
      \Drupal::unsetContainer();
    }

    $this->previousContainer = NULL;
    parent::tearDown();
  }

  private function definition(string $id, ToolOperation $operation): ToolDefinition {
    return new ToolDefinition([
      'id' => $id,
      'provider' => 'mcp_tools',
      'label' => new TranslatableMarkup('Test'),
      'description' => new TranslatableMarkup('Test tool'),
      'operation' => $operation,
      'destructive' => FALSE,
    ]);
  }

  public function testDoExecuteWrapsLegacySuccessWithData(): void {
    $definition = $this->definition('mcp_tools:test_success', ToolOperation::Read);
    $currentUser = $this->createMock(AccountInterface::class);

    $tool = new class([], 'mcp_tools:test_success', $definition, $currentUser, $this->createMock(EventDispatcherInterface::class), $this->createMock(LoggerChannelInterface::class)) extends McpToolsToolBase {
      public function setAccessManager(AccessManager $accessManager): void {
        $this->accessManager = $accessManager;
      }

      protected function executeLegacy(array $input): array {
        return [
          'success' => TRUE,
          'message' => 'OK',
          'data' => ['foo' => 'bar'],
        ];
      }
    };

    $tool->setAccessManager($this->createMock(AccessManager::class));

    $tool->execute();
    $result = $tool->getResult();

    $this->assertTrue($result->isSuccess());
    $this->assertSame('OK', (string) $result->getMessage());
    $this->assertSame(['foo' => 'bar'], $result->getContextValues());
  }

  /**
   * A message the service puts in data is the answer's message.
   *
   * It was heard as "Success.": "the entity has no metatag field" read as
   * no tags. A message given at the top still wins.
   */
  public function testAMessageInDataIsTheAnswersMessage(): void {
    $definition = $this->definition('mcp_tools:test_data_message', ToolOperation::Read);
    foreach ([[['data' => ['message' => 'No metatag field.']], 'No metatag field.'], [['message' => 'Top', 'data' => ['message' => 'Inner']], 'Top'], [['data' => ['message' => ['not', 'text']]], 'Success.']] as [$answer, $said]) {
      $tool = new class([], 'mcp_tools:test_data_message', $definition, $this->createMock(AccountInterface::class), $this->createMock(EventDispatcherInterface::class), $this->createMock(LoggerChannelInterface::class)) extends McpToolsToolBase {
        public array $answer = [];

        public function setAccessManager(AccessManager $accessManager): void {
          $this->accessManager = $accessManager;
        }

        protected function executeLegacy(array $input): array {
          return ['success' => TRUE] + $this->answer;
        }
      };
      $tool->answer = $answer;
      $tool->setAccessManager($this->createMock(AccessManager::class));
      $tool->execute();
      $this->assertSame($said, (string) $tool->getResult()->getMessage(), json_encode($answer));
    }
  }

  /**
   * Scalar outputs come back in the type their definition declares.
   *
   * Drupal hands out IDs as strings for loaded entities and as integers for
   * new ones. An output declared "integer" must be an integer either way.
   */
  public function testDeclaredScalarOutputsAreReturnedInTheirType(): void {
    $definition = new ToolDefinition([
      'id' => 'mcp_tools:test_types',
      'provider' => 'mcp_tools',
      'label' => new TranslatableMarkup('Test'),
      'description' => new TranslatableMarkup('Test tool'),
      'operation' => ToolOperation::Read,
      'destructive' => FALSE,
      'output_definitions' => [
        'nid' => new OutputDefinition('integer', new TranslatableMarkup('Node ID'), new TranslatableMarkup('Node ID.')),
        'revision_id' => new OutputDefinition('integer', new TranslatableMarkup('Revision ID'), new TranslatableMarkup('Revision ID.')),
        'count' => new OutputDefinition('integer', new TranslatableMarkup('Count'), new TranslatableMarkup('Count.')),
        'label' => new OutputDefinition('string', new TranslatableMarkup('Label'), new TranslatableMarkup('Label.')),
        'ratio' => new OutputDefinition('float', new TranslatableMarkup('Ratio'), new TranslatableMarkup('Ratio.')),
        'padded' => new OutputDefinition('integer', new TranslatableMarkup('Padded'), new TranslatableMarkup('Padded.')),
        'words' => new OutputDefinition('integer', new TranslatableMarkup('Words'), new TranslatableMarkup('Words.')),
        'flag' => new OutputDefinition('boolean', new TranslatableMarkup('Flag'), new TranslatableMarkup('Flag.')),
        'on' => new OutputDefinition('boolean', new TranslatableMarkup('On'), new TranslatableMarkup('On.')),
        'off' => new OutputDefinition('boolean', new TranslatableMarkup('Off'), new TranslatableMarkup('Off.')),
        'stored' => new OutputDefinition('boolean', new TranslatableMarkup('Stored'), new TranslatableMarkup('Stored.')),
        'two' => new OutputDefinition('boolean', new TranslatableMarkup('Two'), new TranslatableMarkup('Two.')),
      ],
    ]);
    $currentUser = $this->createMock(AccountInterface::class);

    $tool = new class([], 'mcp_tools:test_types', $definition, $currentUser, $this->createMock(EventDispatcherInterface::class), $this->createMock(LoggerChannelInterface::class)) extends McpToolsToolBase {

      /**
       * {@inheritdoc}
       */
      protected function executeLegacy(array $input): array {
        return $this->legacy();
      }

      /**
       * The raw result, as a service would return it.
       */
      public function legacy(): array {
        return [
          'success' => TRUE,
          'data' => [
            'nid' => '42',
            'revision_id' => '7',
            'count' => 3,
            'label' => 15,
            'ratio' => '0.5',
            // Not a clean integer: left alone, for validation to report.
            'padded' => '007',
            'words' => 'seven',
            'flag' => TRUE,
            // Core's maintenance form stores its checkbox as 0 or 1.
            'on' => 1,
            'off' => 0,
            'stored' => '1',
            // Not a yes or no: left alone, for validation to report.
            'two' => 2,
          ],
        ];
      }

    };

    // Called directly: executing a tool with declared outputs needs the
    // typed data manager, which a unit test does not have. The kernel flow
    // tests check the same thing on real tools (DeclaredOutputTypesTrait).
    $convert = new \ReflectionMethod(McpToolsToolBase::class, 'inDeclaredTypes');
    $values = $convert->invoke($tool, $tool->legacy()['data']);

    $this->assertSame(42, $values['nid']);
    $this->assertSame(7, $values['revision_id']);
    $this->assertSame(3, $values['count']);
    $this->assertSame('15', $values['label']);
    $this->assertSame(0.5, $values['ratio']);
    $this->assertSame('007', $values['padded']);
    $this->assertSame('seven', $values['words']);
    $this->assertTrue($values['flag']);
    $this->assertTrue($values['on']);
    $this->assertFalse($values['off']);
    $this->assertTrue($values['stored']);
    $this->assertSame(2, $values['two']);
  }

  public function testDoExecuteUsesLegacyPayloadAsContextWhenNoDataKey(): void {
    $definition = $this->definition('mcp_tools:test_context', ToolOperation::Read);
    $currentUser = $this->createMock(AccountInterface::class);

    $tool = new class([], 'mcp_tools:test_context', $definition, $currentUser, $this->createMock(EventDispatcherInterface::class), $this->createMock(LoggerChannelInterface::class)) extends McpToolsToolBase {
      public function setAccessManager(AccessManager $accessManager): void {
        $this->accessManager = $accessManager;
      }

      protected function executeLegacy(array $input): array {
        return [
          'success' => TRUE,
          'message' => 'Done',
          'extra' => 'value',
        ];
      }
    };

    $tool->setAccessManager($this->createMock(AccessManager::class));

    $tool->execute();
    $result = $tool->getResult();

    $this->assertTrue($result->isSuccess());
    $this->assertSame('Done', (string) $result->getMessage());
    $this->assertSame(['extra' => 'value'], $result->getContextValues());
  }

  public function testDoExecuteWrapsLegacyFailure(): void {
    $definition = $this->definition('mcp_tools:test_failure', ToolOperation::Read);
    $currentUser = $this->createMock(AccountInterface::class);

    $tool = new class([], 'mcp_tools:test_failure', $definition, $currentUser, $this->createMock(EventDispatcherInterface::class), $this->createMock(LoggerChannelInterface::class)) extends McpToolsToolBase {
      public function setAccessManager(AccessManager $accessManager): void {
        $this->accessManager = $accessManager;
      }

      protected function executeLegacy(array $input): array {
        return [
          'success' => FALSE,
          'error' => 'Nope',
        ];
      }
    };

    $tool->setAccessManager($this->createMock(AccessManager::class));

    $tool->execute();
    $result = $tool->getResult();

    $this->assertFalse($result->isSuccess());
    $this->assertSame('Nope', (string) $result->getMessage());
  }

  public function testDoExecuteKeepsRuntimeMessagesOutOfTheSourceString(): void {
    $definition = $this->definition('mcp_tools:test_runtime_message', ToolOperation::Read);
    $currentUser = $this->createMock(AccountInterface::class);

    $tool = new class([], 'mcp_tools:test_runtime_message', $definition, $currentUser, $this->createMock(EventDispatcherInterface::class), $this->createMock(LoggerChannelInterface::class)) extends McpToolsToolBase {
      public function setAccessManager(AccessManager $accessManager): void {
        $this->accessManager = $accessManager;
      }

      protected function executeLegacy(array $input): array {
        return [
          'success' => FALSE,
          'error' => "Node 274 has no 'it' translation & <b>no</b> \"draft\".",
        ];
      }
    };

    $tool->setAccessManager($this->createMock(AccessManager::class));

    $tool->execute();
    $message = $tool->getResult()->getMessage();

    // One fixed source string, so locale records no string per message.
    $this->assertSame('@message', $message->getUntranslatedString());
    // The text reaches clients as built, and a page escapes it.
    $this->assertSame("Node 274 has no 'it' translation & <b>no</b> \"draft\".", McpToolsToolBase::plainMessage($message));
    $this->assertStringNotContainsString('<b>', (string) $message);
  }

  /**
   * An exception's text reaches clients as thrown, not HTML-escaped.
   *
   * It was passed as an escaped placeholder, so a database error read
   * SELECT 1 FROM &quot;cache_backend&quot; in the MCP client.
   */
  public function testExceptionMessagesAreNotEscaped(): void {
    $definition = $this->definition('mcp_tools:test_exception', ToolOperation::Read);
    $currentUser = $this->createMock(AccountInterface::class);

    $tool = new class([], 'mcp_tools:test_exception', $definition, $currentUser, $this->createMock(EventDispatcherInterface::class), $this->createMock(LoggerChannelInterface::class)) extends McpToolsToolBase {
      public function setAccessManager(AccessManager $accessManager): void {
        $this->accessManager = $accessManager;
      }

      protected function executeLegacy(array $input): array {
        throw new \RuntimeException("no such table: \"cache_backend\".sqlite_master, it's <gone> & lost");
      }
    };

    $tool->setAccessManager($this->createMock(AccessManager::class));

    $tool->execute();
    $result = $tool->getResult();

    $this->assertFalse($result->isSuccess());
    $this->assertSame("Tool execution failed: no such table: \"cache_backend\".sqlite_master, it's <gone> & lost", McpToolsToolBase::plainMessage($result->getMessage()));
    // Still one fixed source string for locale.
    $this->assertSame('@message', $result->getMessage()->getUntranslatedString());
  }

  public function testAccessGatesReadToolsByPermissionAndScope(): void {
    $definition = $this->definition('mcp_tools:get_site_status', ToolOperation::Read);

    $currentUser = $this->createMock(AccountInterface::class);
    $tool = new class([], 'mcp_tools:get_site_status', $definition, $currentUser, $this->createMock(EventDispatcherInterface::class), $this->createMock(LoggerChannelInterface::class)) extends McpToolsToolBase {
      protected const MCP_CATEGORY = 'site_health';

      public function setAccessManager(AccessManager $accessManager): void {
        $this->accessManager = $accessManager;
      }

      protected function executeLegacy(array $input): array {
        return ['success' => TRUE];
      }
    };

    $accessManager = $this->createMock(AccessManager::class);
    $accessManager->method('hasScope')->with(AccessManager::SCOPE_READ)->willReturn(TRUE);
    $tool->setAccessManager($accessManager);

    $account = $this->createMock(AccountInterface::class);
    $account->method('hasPermission')
      ->willReturnCallback(static fn(string $permission): bool => $permission === 'mcp_tools use site_health');

    $this->assertTrue($tool->access($account));

    $noScope = $this->createMock(AccessManager::class);
    $noScope->method('hasScope')->with(AccessManager::SCOPE_READ)->willReturn(FALSE);
    $tool->setAccessManager($noScope);
    $this->assertFalse($tool->access($account));
  }

  public function testAccessGatesWriteToolsByScopeAndConfigOnlyPolicy(): void {
    $definition = $this->definition('mcp_cache:clear_all', ToolOperation::Write);

    $currentUser = $this->createMock(AccountInterface::class);
    $tool = new class([], 'mcp_cache:clear_all', $definition, $currentUser, $this->createMock(EventDispatcherInterface::class), $this->createMock(LoggerChannelInterface::class)) extends McpToolsToolBase {
      protected const MCP_CATEGORY = 'cache';

      public function setAccessManager(AccessManager $accessManager): void {
        $this->accessManager = $accessManager;
      }

      protected function executeLegacy(array $input): array {
        return ['success' => TRUE];
      }
    };

    $account = $this->createMock(AccountInterface::class);
    $account->method('hasPermission')
      ->willReturnCallback(static fn(string $permission): bool => $permission === 'mcp_tools use cache');

    $capturedKind = NULL;
    $accessManager = $this->createMock(AccessManager::class);
    $accessManager->method('hasScope')->with(AccessManager::SCOPE_WRITE)->willReturn(TRUE);
    $accessManager->method('isReadOnlyMode')->willReturn(FALSE);
    $accessManager->method('isWriteKindAllowed')
      ->willReturnCallback(static function (string $kind) use (&$capturedKind): bool {
        $capturedKind = $kind;
        return FALSE;
      });
    $tool->setAccessManager($accessManager);

    $this->assertFalse($tool->access($account));
    $this->assertSame(AccessManager::WRITE_KIND_OPS, $capturedKind);

    $accessManagerAllowed = $this->createMock(AccessManager::class);
    $accessManagerAllowed->method('hasScope')->with(AccessManager::SCOPE_WRITE)->willReturn(TRUE);
    $accessManagerAllowed->method('isReadOnlyMode')->willReturn(FALSE);
    $accessManagerAllowed->method('isWriteKindAllowed')->willReturn(TRUE);
    $tool->setAccessManager($accessManagerAllowed);
    $this->assertTrue($tool->access($account));

    $readOnly = $this->createMock(AccessManager::class);
    $readOnly->method('hasScope')->with(AccessManager::SCOPE_WRITE)->willReturn(TRUE);
    $readOnly->method('isReadOnlyMode')->willReturn(TRUE);
    $tool->setAccessManager($readOnly);
    $this->assertFalse($tool->access($account));
  }

  public function testAccessGatesTriggerToolsByAdminScope(): void {
    $definition = $this->definition('mcp_recipes:apply', ToolOperation::Trigger);

    $currentUser = $this->createMock(AccountInterface::class);
    $tool = new class([], 'mcp_recipes:apply', $definition, $currentUser, $this->createMock(EventDispatcherInterface::class), $this->createMock(LoggerChannelInterface::class)) extends McpToolsToolBase {
      protected const MCP_CATEGORY = 'recipes';

      public function setAccessManager(AccessManager $accessManager): void {
        $this->accessManager = $accessManager;
      }

      protected function executeLegacy(array $input): array {
        return ['success' => TRUE];
      }
    };

    $account = $this->createMock(AccountInterface::class);
    $account->method('hasPermission')
      ->willReturnCallback(static fn(string $permission): bool => $permission === 'mcp_tools use recipes');

    $adminAccessManager = $this->createMock(AccessManager::class);
    $adminAccessManager->method('hasScope')->with(AccessManager::SCOPE_ADMIN)->willReturn(TRUE);
    $adminAccessManager->method('isReadOnlyMode')->willReturn(FALSE);
    $adminAccessManager->method('isWriteKindAllowed')->willReturn(TRUE);
    $tool->setAccessManager($adminAccessManager);
    $this->assertTrue($tool->access($account));

    $noAdmin = $this->createMock(AccessManager::class);
    $noAdmin->method('hasScope')->with(AccessManager::SCOPE_ADMIN)->willReturn(FALSE);
    $tool->setAccessManager($noAdmin);
    $this->assertFalse($tool->access($account));

    $readOnly = $this->createMock(AccessManager::class);
    $readOnly->method('hasScope')->with(AccessManager::SCOPE_ADMIN)->willReturn(TRUE);
    $readOnly->method('isReadOnlyMode')->willReturn(TRUE);
    $tool->setAccessManager($readOnly);
    $this->assertFalse($tool->access($account));
  }

}
