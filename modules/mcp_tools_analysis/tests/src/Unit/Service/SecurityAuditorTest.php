<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_analysis\Unit\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\mcp_tools_analysis\Service\SecurityAuditor;
use Drupal\Tests\UnitTestCase;
use Drupal\user\RoleInterface;

/**
 * Tests for SecurityAuditor.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools_analysis\Service\SecurityAuditor::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_analysis')]
final class SecurityAuditorTest extends UnitTestCase {

  private EntityTypeManagerInterface $entityTypeManager;
  private ConfigFactoryInterface $configFactory;
  private ModuleHandlerInterface $moduleHandler;
  private SecurityAuditor $auditor;

  protected function setUp(): void {
    parent::setUp();
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->moduleHandler = $this->createMock(ModuleHandlerInterface::class);

    $this->auditor = new SecurityAuditor(
      $this->entityTypeManager,
      $this->configFactory,
      $this->moduleHandler,
    );
  }

  public function testSecurityAuditReturnsStructuredResult(): void {
    // Mock user.settings config.
    $userConfig = $this->createMock(ImmutableConfig::class);
    $userConfig->method('get')->willReturn('admin_only');
    $this->configFactory->method('get')->willReturn($userConfig);

    // Mock role storage.
    $roleStorage = $this->createMock(EntityStorageInterface::class);
    $roleStorage->method('load')->willReturn(NULL);
    $roleStorage->method('loadMultiple')->willReturn([]);

    // Mock user storage.
    $userQuery = $this->createMock(QueryInterface::class);
    $userQuery->method('condition')->willReturnSelf();
    $userQuery->method('accessCheck')->willReturnSelf();
    $userQuery->method('execute')->willReturn([]);

    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->method('getQuery')->willReturn($userQuery);
    $userStorage->method('loadMultiple')->willReturn([]);

    $this->entityTypeManager->method('getStorage')->willReturnMap([
      ['user_role', $roleStorage],
      ['user', $userStorage],
    ]);

    $this->moduleHandler->method('moduleExists')->willReturn(FALSE);

    $result = $this->auditor->securityAudit();

    $this->assertTrue($result['success']);
    $this->assertArrayHasKey('critical_issues', $result['data']);
    $this->assertArrayHasKey('warnings', $result['data']);
    $this->assertArrayHasKey('critical_count', $result['data']);
    $this->assertArrayHasKey('warning_count', $result['data']);
  }

  public function testSecurityAuditDetectsSensitiveAuthenticatedPermissions(): void {
    // Mock user.settings config.
    $userConfig = $this->createMock(ImmutableConfig::class);
    $userConfig->method('get')->willReturn('admin_only');
    $this->configFactory->method('get')->willReturn($userConfig);

    // Create authenticated role with sensitive permission.
    $authenticatedRole = $this->createMock(RoleInterface::class);
    $authenticatedRole->method('id')->willReturn('authenticated');
    $authenticatedRole->method('label')->willReturn('Authenticated user');
    $authenticatedRole->method('getPermissions')->willReturn(['administer site configuration']);

    $roleStorage = $this->createMock(EntityStorageInterface::class);
    $roleStorage->method('load')->willReturnMap([
      ['anonymous', NULL],
      ['authenticated', $authenticatedRole],
    ]);
    $roleStorage->method('loadMultiple')->willReturn(['authenticated' => $authenticatedRole]);

    $userQuery = $this->createMock(QueryInterface::class);
    $userQuery->method('condition')->willReturnSelf();
    $userQuery->method('accessCheck')->willReturnSelf();
    $userQuery->method('execute')->willReturn([]);

    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->method('getQuery')->willReturn($userQuery);
    $userStorage->method('loadMultiple')->willReturn([]);

    $this->entityTypeManager->method('getStorage')->willReturnMap([
      ['user_role', $roleStorage],
      ['user', $userStorage],
    ]);

    $this->moduleHandler->method('moduleExists')->willReturn(FALSE);

    $result = $this->auditor->securityAudit();

    $this->assertTrue($result['success']);
    // Should have warnings about sensitive permissions for authenticated role.
    $this->assertGreaterThan(0, $result['data']['warning_count']);
  }

}
