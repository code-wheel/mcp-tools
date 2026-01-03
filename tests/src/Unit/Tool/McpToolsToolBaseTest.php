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
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @coversDefaultClass \Drupal\mcp_tools\Tool\McpToolsToolBase
 * @group mcp_tools
 */
final class McpToolsToolBaseTest extends UnitTestCase {

  private ?object $previousContainer = NULL;

  protected function setUp(): void {
    parent::setUp();

    $this->previousContainer = \Drupal::hasContainer() ? \Drupal::getContainer() : NULL;

    $container = new ContainerBuilder();
    $cacheContextsManager = $this->createMock(CacheContextsManager::class);
    $cacheContextsManager->method('assertValidTokens')->willReturn(TRUE);
    $container->set('cache_contexts_manager', $cacheContextsManager);

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

  /**
   * @covers ::doExecute
   */
  public function testDoExecuteWrapsLegacySuccessWithData(): void {
    $definition = $this->definition('mcp_tools:test_success', ToolOperation::Read);
    $currentUser = $this->createMock(AccountInterface::class);

    $tool = new class([], 'mcp_tools:test_success', $definition, $currentUser) extends McpToolsToolBase {
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
    $this->assertSame('OK', $result->getMessage()->getUntranslatedString());
    $this->assertSame(['foo' => 'bar'], $result->getContextValues());
  }

  /**
   * @covers ::doExecute
   */
  public function testDoExecuteUsesLegacyPayloadAsContextWhenNoDataKey(): void {
    $definition = $this->definition('mcp_tools:test_context', ToolOperation::Read);
    $currentUser = $this->createMock(AccountInterface::class);

    $tool = new class([], 'mcp_tools:test_context', $definition, $currentUser) extends McpToolsToolBase {
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
    $this->assertSame('Done', $result->getMessage()->getUntranslatedString());
    $this->assertSame(['extra' => 'value'], $result->getContextValues());
  }

  /**
   * @covers ::doExecute
   */
  public function testDoExecuteWrapsLegacyFailure(): void {
    $definition = $this->definition('mcp_tools:test_failure', ToolOperation::Read);
    $currentUser = $this->createMock(AccountInterface::class);

    $tool = new class([], 'mcp_tools:test_failure', $definition, $currentUser) extends McpToolsToolBase {
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
    $this->assertSame('Nope', $result->getMessage()->getUntranslatedString());
  }

  /**
   * @covers ::checkAccess
   * @covers ::access
   */
  public function testAccessGatesReadToolsByPermissionAndScope(): void {
    $definition = $this->definition('mcp_tools:get_site_status', ToolOperation::Read);

    $currentUser = $this->createMock(AccountInterface::class);
    $tool = new class([], 'mcp_tools:get_site_status', $definition, $currentUser) extends McpToolsToolBase {
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

  /**
   * @covers ::checkAccess
   */
  public function testAccessGatesWriteToolsByScopeAndConfigOnlyPolicy(): void {
    $definition = $this->definition('mcp_cache:clear_all', ToolOperation::Write);

    $currentUser = $this->createMock(AccountInterface::class);
    $tool = new class([], 'mcp_cache:clear_all', $definition, $currentUser) extends McpToolsToolBase {
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

}
