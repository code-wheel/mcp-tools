<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_analysis\Unit\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountInterface;
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

  public function testAuditSecurityReturnsStructuredResult(): void {
    // Mock system.site config.
    $siteConfig = $this->createMock(ImmutableConfig::class);
    $siteConfig->method('get')->willReturn('Test Site');
    $this->configFactory->method('get')->willReturn($siteConfig);

    // Mock role storage.
    $roleStorage = $this->createMock(\Drupal\Core\Entity\EntityStorageInterface::class);
    $roleStorage->method('loadMultiple')->willReturn([]);

    // Mock user storage.
    $userQuery = $this->createMock(\Drupal\Core\Entity\Query\QueryInterface::class);
    $userQuery->method('condition')->willReturnSelf();
    $userQuery->method('accessCheck')->willReturnSelf();
    $userQuery->method('execute')->willReturn([]);

    $userStorage = $this->createMock(\Drupal\Core\Entity\EntityStorageInterface::class);
    $userStorage->method('getQuery')->willReturn($userQuery);
    $userStorage->method('loadMultiple')->willReturn([]);

    $this->entityTypeManager->method('getStorage')->willReturnMap([
      ['user_role', $roleStorage],
      ['user', $userStorage],
    ]);

    $this->moduleHandler->method('moduleExists')->willReturn(FALSE);

    $result = $this->auditor->auditSecurity();

    $this->assertTrue($result['success']);
    $this->assertArrayHasKey('findings', $result['data']);
    $this->assertArrayHasKey('summary', $result['data']);
  }

  public function testAuditSecurityDetectsRiskyPermissions(): void {
    $siteConfig = $this->createMock(ImmutableConfig::class);
    $siteConfig->method('get')->willReturn('Test Site');
    $this->configFactory->method('get')->willReturn($siteConfig);

    // Create role with risky permission.
    $role = $this->createMock(RoleInterface::class);
    $role->method('id')->willReturn('authenticated');
    $role->method('label')->willReturn('Authenticated user');
    $role->method('getPermissions')->willReturn(['administer site configuration']);
    $role->method('isAdmin')->willReturn(FALSE);

    $roleStorage = $this->createMock(\Drupal\Core\Entity\EntityStorageInterface::class);
    $roleStorage->method('loadMultiple')->willReturn(['authenticated' => $role]);

    $userQuery = $this->createMock(\Drupal\Core\Entity\Query\QueryInterface::class);
    $userQuery->method('condition')->willReturnSelf();
    $userQuery->method('accessCheck')->willReturnSelf();
    $userQuery->method('execute')->willReturn([]);

    $userStorage = $this->createMock(\Drupal\Core\Entity\EntityStorageInterface::class);
    $userStorage->method('getQuery')->willReturn($userQuery);
    $userStorage->method('loadMultiple')->willReturn([]);

    $this->entityTypeManager->method('getStorage')->willReturnMap([
      ['user_role', $roleStorage],
      ['user', $userStorage],
    ]);

    $this->moduleHandler->method('moduleExists')->willReturn(FALSE);

    $result = $this->auditor->auditSecurity();

    $this->assertTrue($result['success']);
    // Should have findings about risky permissions.
    $highFindings = array_filter($result['data']['findings'], fn($f) => $f['severity'] === 'high');
    $this->assertNotEmpty($highFindings);
  }

}
