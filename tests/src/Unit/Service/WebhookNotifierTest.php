<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Unit\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\mcp_tools\Service\WebhookNotifier;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Tests for WebhookNotifier.
 *
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools\Service\WebhookNotifier::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
final class WebhookNotifierTest extends UnitTestCase {

  /**
   * Create a ConfigFactory mock returning configured values.
   *
   * @param array $values
   *   Key/value map for mcp_tools.settings.
   *
   * @return \Drupal\Core\Config\ConfigFactoryInterface
   *   Config factory mock.
   */
  private function createConfigFactory(array $values): ConfigFactoryInterface {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturnCallback(
      static fn(string $key): mixed => $values[$key] ?? NULL
    );

    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->with('mcp_tools.settings')->willReturn($config);

    return $configFactory;
  }

  /**
   * Create a notifier with common mocks.
   */
  private function createNotifier(
    array $configValues,
    ClientInterface $httpClient,
    LoggerChannelFactoryInterface $loggerFactory,
    AccountProxyInterface $currentUser,
  ): WebhookNotifier {
    return new WebhookNotifier(
      $this->createConfigFactory($configValues),
      $httpClient,
      $loggerFactory,
      $currentUser,
    );
  }

  public function testNotifyReturnsTrueWhenWebhooksDisabled(): void {
    $httpClient = $this->createMock(ClientInterface::class);
    $httpClient->expects($this->never())->method('request');

    $loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $loggerFactory->method('get')->willReturn($this->createMock(LoggerInterface::class));

    $currentUser = $this->createMock(AccountProxyInterface::class);

    $notifier = $this->createNotifier([
      'webhooks.enabled' => FALSE,
    ], $httpClient, $loggerFactory, $currentUser);

    $result = $notifier->notify(WebhookNotifier::OP_CREATE, 'node', 1, 'Test node');
    $this->assertTrue($result);
  }

  public function testNotifyQueuesWhenBatchingEnabled(): void {
    $httpClient = $this->createMock(ClientInterface::class);
    $httpClient->expects($this->never())->method('request');

    $loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $loggerFactory->method('get')->willReturn($this->createMock(LoggerInterface::class));

    $currentUser = $this->createMock(AccountProxyInterface::class);
    $currentUser->method('id')->willReturn(1);
    $currentUser->method('getAccountName')->willReturn('admin');

    $notifier = $this->createNotifier([
      'webhooks.enabled' => TRUE,
      'webhooks.url' => 'https://8.8.8.8/hook',
      'webhooks.batch_notifications' => TRUE,
    ], $httpClient, $loggerFactory, $currentUser);

    $result = $notifier->notify(WebhookNotifier::OP_UPDATE, 'node', 1, 'Test node');

    $this->assertTrue($result);
    $this->assertSame(1, $notifier->getPendingCount());
  }

  public function testFlushSendsQueuedPayloadAndSignsAndRedactsDetails(): void {
    $response = $this->createMock(ResponseInterface::class);
    $response->method('getStatusCode')->willReturn(200);

    $httpClient = $this->createMock(ClientInterface::class);
    $httpClient->expects($this->once())
      ->method('request')
      ->with(
        'POST',
        'https://8.8.8.8/hook',
        $this->callback(function (array $options): bool {
          $this->assertArrayHasKey('headers', $options);
          $this->assertArrayHasKey('body', $options);
          $this->assertArrayHasKey('X-MCP-Signature', $options['headers']);

          $body = (string) $options['body'];
          $this->assertSame(
            'sha256=' . hash_hmac('sha256', $body, 'test-secret'),
            $options['headers']['X-MCP-Signature']
          );

          $decoded = json_decode($body, TRUE, 512, JSON_THROW_ON_ERROR);
          $this->assertSame(1, $decoded['count']);
          $this->assertSame('[REDACTED]', $decoded['events'][0]['details']['api_key']);

          return TRUE;
        })
      )
      ->willReturn($response);

    $loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $loggerFactory->method('get')->willReturn($this->createMock(LoggerInterface::class));

    $currentUser = $this->createMock(AccountProxyInterface::class);
    $currentUser->method('id')->willReturn(1);
    $currentUser->method('getAccountName')->willReturn('admin');

    $notifier = $this->createNotifier([
      'webhooks.enabled' => TRUE,
      'webhooks.url' => 'https://8.8.8.8/hook',
      'webhooks.secret' => 'test-secret',
      'webhooks.batch_notifications' => TRUE,
      'webhooks.timeout' => 5,
      'webhooks.allowed_hosts' => [],
    ], $httpClient, $loggerFactory, $currentUser);

    $this->assertTrue($notifier->notify(
      WebhookNotifier::OP_STRUCTURE,
      'field_config',
      'node.article.field_test',
      'field_test',
      ['api_key' => 'should-not-leak']
    ));

    $this->assertSame(1, $notifier->getPendingCount());
    $this->assertTrue($notifier->flush());
    $this->assertSame(0, $notifier->getPendingCount());
  }

  public function testNotifyRejectsBlockedWebhookUrl(): void {
    $httpClient = $this->createMock(ClientInterface::class);
    $httpClient->expects($this->never())->method('request');

    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())->method('error');

    $loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $loggerFactory->method('get')->with('mcp_tools')->willReturn($logger);

    $currentUser = $this->createMock(AccountProxyInterface::class);
    $currentUser->method('id')->willReturn(1);
    $currentUser->method('getAccountName')->willReturn('admin');

    $notifier = $this->createNotifier([
      'webhooks.enabled' => TRUE,
      'webhooks.url' => 'https://127.0.0.1/hook',
      'webhooks.batch_notifications' => FALSE,
      'webhooks.allowed_hosts' => [],
    ], $httpClient, $loggerFactory, $currentUser);

    $result = $notifier->notify(WebhookNotifier::OP_DELETE, 'node', 1, 'Test node');
    $this->assertFalse($result);
  }

}

