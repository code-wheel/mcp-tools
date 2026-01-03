<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Unit\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests for AuditLogger service.
 *
 * @coversDefaultClass \Drupal\mcp_tools\Service\AuditLogger
 * @group mcp_tools
 */
class AuditLoggerTest extends UnitTestCase {

  protected ConfigFactoryInterface $configFactory;
  protected AccountProxyInterface $currentUser;
  protected LoggerChannelFactoryInterface $loggerFactory;
  protected LoggerInterface $logger;
  protected ImmutableConfig $config;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->config = $this->createMock(ImmutableConfig::class);
    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->configFactory->method('get')
      ->with('mcp_tools.settings')
      ->willReturn($this->config);

    $this->currentUser = $this->createMock(AccountProxyInterface::class);
    $this->currentUser->method('getAccountName')->willReturn('testuser');
    $this->currentUser->method('id')->willReturn(42);

    $this->logger = $this->createMock(LoggerInterface::class);
    $this->loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $this->loggerFactory->method('get')
      ->with('mcp_tools')
      ->willReturn($this->logger);
  }

  /**
   * Creates an AuditLogger instance with the mocked dependencies.
   */
  protected function createAuditLogger(): AuditLogger {
    return new AuditLogger(
      $this->configFactory,
      $this->currentUser,
      $this->loggerFactory
    );
  }

  /**
   * @covers ::log
   * @covers ::logSuccess
   */
  public function testLogSuccessLogsNotice(): void {
    $this->config->method('get')
      ->with('audit_logging')
      ->willReturn(TRUE);

    $this->logger->expects($this->once())
      ->method('notice')
      ->with(
        $this->stringContains('MCP:'),
        $this->callback(function ($context) {
          return $context['@operation'] === 'create_content'
            && $context['@entity_type'] === 'node'
            && $context['@entity_id'] === '123'
            && $context['@user'] === 'testuser'
            && $context['@uid'] === 42;
        })
      );

    $auditLogger = $this->createAuditLogger();
    $auditLogger->logSuccess('create_content', 'node', '123');
  }

  /**
   * @covers ::log
   * @covers ::logFailure
   */
  public function testLogFailureLogsError(): void {
    $this->config->method('get')
      ->with('audit_logging')
      ->willReturn(TRUE);

    $this->logger->expects($this->once())
      ->method('error')
      ->with(
        $this->stringContains('MCP:'),
        $this->callback(function ($context) {
          return $context['@operation'] === 'delete_content'
            && $context['@entity_type'] === 'node'
            && $context['@entity_id'] === '456';
        })
      );

    $auditLogger = $this->createAuditLogger();
    $auditLogger->logFailure('delete_content', 'node', '456');
  }

  /**
   * @covers ::log
   */
  public function testLogWithDetails(): void {
    $this->config->method('get')
      ->with('audit_logging')
      ->willReturn(TRUE);

    $this->logger->expects($this->once())
      ->method('notice')
      ->with(
        $this->stringContains('Details:'),
        $this->callback(function ($context) {
          return isset($context['@details'])
            && str_contains($context['@details'], 'Test Article');
        })
      );

    $auditLogger = $this->createAuditLogger();
    $auditLogger->logSuccess('create_content', 'node', '789', [
      'title' => 'Test Article',
      'type' => 'article',
    ]);
  }

  /**
   * @covers ::log
   */
  public function testLogDisabledByConfig(): void {
    $this->config->method('get')
      ->with('audit_logging')
      ->willReturn(FALSE);

    $this->logger->expects($this->never())->method('notice');
    $this->logger->expects($this->never())->method('error');

    $auditLogger = $this->createAuditLogger();
    $auditLogger->logSuccess('create_content', 'node', '123');
  }

  /**
   * @covers ::sanitizeDetails
   */
  public function testSanitizeDetailsRedactsPassword(): void {
    $this->config->method('get')
      ->with('audit_logging')
      ->willReturn(TRUE);

    $this->logger->expects($this->once())
      ->method('notice')
      ->with(
        $this->anything(),
        $this->callback(function ($context) {
          if (!isset($context['@details'])) {
            return FALSE;
          }
          $details = json_decode($context['@details'], TRUE);
          return $details['password'] === '[REDACTED]'
            && $details['username'] === 'john';
        })
      );

    $auditLogger = $this->createAuditLogger();
    $auditLogger->logSuccess('create_user', 'user', '100', [
      'username' => 'john',
      'password' => 'secret123',
    ]);
  }

  /**
   * @covers ::sanitizeDetails
   */
  public function testSanitizeDetailsRedactsMultipleSensitiveKeys(): void {
    $this->config->method('get')
      ->with('audit_logging')
      ->willReturn(TRUE);

    $this->logger->expects($this->once())
      ->method('notice')
      ->with(
        $this->anything(),
        $this->callback(function ($context) {
          if (!isset($context['@details'])) {
            return FALSE;
          }
          $details = json_decode($context['@details'], TRUE);
          return $details['api_key'] === '[REDACTED]'
            && $details['secret_token'] === '[REDACTED]'
            && $details['credentials'] === '[REDACTED]'
            && $details['user_pass'] === '[REDACTED]'
            && $details['name'] === 'visible';
        })
      );

    $auditLogger = $this->createAuditLogger();
    $auditLogger->logSuccess('operation', 'config', 'test', [
      'api_key' => 'abc123',
      'secret_token' => 'xyz789',
      'credentials' => ['user' => 'admin'],
      'user_pass' => 'password',
      'name' => 'visible',
    ]);
  }

  /**
   * @covers ::sanitizeDetails
   */
  public function testSanitizeDetailsHandlesNestedArrays(): void {
    $this->config->method('get')
      ->with('audit_logging')
      ->willReturn(TRUE);

    $this->logger->expects($this->once())
      ->method('notice')
      ->with(
        $this->anything(),
        $this->callback(function ($context) {
          if (!isset($context['@details'])) {
            return FALSE;
          }
          $details = json_decode($context['@details'], TRUE);
          return $details['config']['database']['password'] === '[REDACTED]'
            && $details['config']['database']['host'] === 'localhost'
            && $details['config']['api']['key'] === '[REDACTED]';
        })
      );

    $auditLogger = $this->createAuditLogger();
    $auditLogger->logSuccess('update_config', 'config', 'settings', [
      'config' => [
        'database' => [
          'host' => 'localhost',
          'password' => 'dbpass123',
        ],
        'api' => [
          'key' => 'apikey456',
        ],
      ],
    ]);
  }

  /**
   * @covers ::log
   */
  public function testLogWithAnonymousUser(): void {
    $anonymousUser = $this->createMock(AccountProxyInterface::class);
    $anonymousUser->method('getAccountName')->willReturn('');
    $anonymousUser->method('id')->willReturn(0);

    $auditLogger = new AuditLogger(
      $this->configFactory,
      $anonymousUser,
      $this->loggerFactory
    );

    $this->config->method('get')
      ->with('audit_logging')
      ->willReturn(TRUE);

    $this->logger->expects($this->once())
      ->method('notice')
      ->with(
        $this->anything(),
        $this->callback(function ($context) {
          return $context['@user'] === 'anonymous'
            && $context['@uid'] === 0;
        })
      );

    $auditLogger->logSuccess('view_content', 'node', '1');
  }

}
