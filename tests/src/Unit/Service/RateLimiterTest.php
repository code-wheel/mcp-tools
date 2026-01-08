<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Unit\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\State\StateInterface;
use Drupal\mcp_tools\Service\RateLimiter;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests for RateLimiter service.
 *
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools\Service\RateLimiter::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
class RateLimiterTest extends UnitTestCase {

  protected ConfigFactoryInterface $configFactory;
  protected StateInterface $state;
  protected RequestStack $requestStack;
  protected ImmutableConfig $config;
  protected array $stateStorage = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->stateStorage = [];

    $this->config = $this->createMock(ImmutableConfig::class);
    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->configFactory->method('get')
      ->with('mcp_tools.settings')
      ->willReturn($this->config);

    $this->state = $this->createMock(StateInterface::class);
    $this->state->method('get')
      ->willReturnCallback(function ($key, $default = NULL) {
        return $this->stateStorage[$key] ?? $default;
      });
    $this->state->method('set')
      ->willReturnCallback(function ($key, $value) {
        $this->stateStorage[$key] = $value;
      });
    $this->state->method('delete')
      ->willReturnCallback(function ($key) {
        unset($this->stateStorage[$key]);
      });

    $this->requestStack = $this->createMock(RequestStack::class);
  }

  /**
   * Creates a RateLimiter instance with mocked dependencies.
   */
  protected function createRateLimiter(): RateLimiter {
    return new RateLimiter(
      $this->configFactory,
      $this->state,
      $this->requestStack
    );
  }

  public function testCheckLimitAllowedWhenDisabled(): void {
    $this->config->method('get')
      ->willReturnMap([
        ['rate_limiting.enabled', FALSE],
      ]);

    $this->requestStack->method('getCurrentRequest')->willReturn(NULL);

    $limiter = $this->createRateLimiter();
    $result = $limiter->checkLimit('write');

    $this->assertTrue($result['allowed']);
    $this->assertNull($result['error']);
    $this->assertNull($result['retry_after']);
  }

  public function testCheckLimitAllowedWhenUnderLimit(): void {
    $this->config->method('get')
      ->willReturnMap([
        ['rate_limiting.enabled', TRUE],
        ['rate_limiting.max_writes_per_minute', 30],
        ['rate_limiting.max_writes_per_hour', 500],
      ]);

    $this->requestStack->method('getCurrentRequest')->willReturn(NULL);

    $limiter = $this->createRateLimiter();
    $result = $limiter->checkLimit('write');

    $this->assertTrue($result['allowed']);
    $this->assertNull($result['error']);
  }

  public function testCheckLimitBlockedWhenOverMinuteLimit(): void {
    $this->config->method('get')
      ->willReturnMap([
        ['rate_limiting.enabled', TRUE],
        ['rate_limiting.max_writes_per_minute', 2],
        ['rate_limiting.max_writes_per_hour', 500],
      ]);

    $request = $this->createMock(Request::class);
    $request->method('getClientIp')->willReturn('127.0.0.1');
    $request->headers = $this->createMock(\Symfony\Component\HttpFoundation\HeaderBag::class);
    $request->headers->method('get')->willReturn('');
    $this->requestStack->method('getCurrentRequest')->willReturn($request);

    $limiter = $this->createRateLimiter();

    // First two calls should succeed.
    $result1 = $limiter->checkLimit('write');
    $this->assertTrue($result1['allowed']);

    $result2 = $limiter->checkLimit('write');
    $this->assertTrue($result2['allowed']);

    // Third call should be blocked.
    $result3 = $limiter->checkLimit('write');
    $this->assertFalse($result3['allowed']);
    $this->assertStringContainsString('Rate limit exceeded', $result3['error']);
    $this->assertNotNull($result3['retry_after']);
  }

  public function testCheckLimitBlockedWhenOverHourLimit(): void {
    $this->config->method('get')
      ->willReturnMap([
        ['rate_limiting.enabled', TRUE],
        ['rate_limiting.max_writes_per_minute', 100],
        ['rate_limiting.max_writes_per_hour', 2],
      ]);

    $request = $this->createMock(Request::class);
    $request->method('getClientIp')->willReturn('192.168.1.1');
    $request->headers = $this->createMock(\Symfony\Component\HttpFoundation\HeaderBag::class);
    $request->headers->method('get')->willReturn('');
    $this->requestStack->method('getCurrentRequest')->willReturn($request);

    $limiter = $this->createRateLimiter();

    // First two calls should succeed.
    $result1 = $limiter->checkLimit('write');
    $this->assertTrue($result1['allowed']);

    $result2 = $limiter->checkLimit('write');
    $this->assertTrue($result2['allowed']);

    // Third call should be blocked by hour limit.
    $result3 = $limiter->checkLimit('write');
    $this->assertFalse($result3['allowed']);
    $this->assertStringContainsString('Rate limit exceeded', $result3['error']);
  }

  public function testDeleteOperationUsesSeparateLimit(): void {
    $this->config->method('get')
      ->willReturnMap([
        ['rate_limiting.enabled', TRUE],
        ['rate_limiting.max_writes_per_minute', 100],
        ['rate_limiting.max_writes_per_hour', 100],
        ['rate_limiting.max_deletes_per_hour', 1],
      ]);

    $request = $this->createMock(Request::class);
    $request->method('getClientIp')->willReturn('10.0.0.1');
    $request->headers = $this->createMock(\Symfony\Component\HttpFoundation\HeaderBag::class);
    $request->headers->method('get')->willReturn('');
    $this->requestStack->method('getCurrentRequest')->willReturn($request);

    $limiter = $this->createRateLimiter();

    // First delete should succeed.
    $result1 = $limiter->checkLimit('delete');
    $this->assertTrue($result1['allowed']);

    // Second delete should be blocked.
    $result2 = $limiter->checkLimit('delete');
    $this->assertFalse($result2['allowed']);
    $this->assertStringContainsString('delete', $result2['error']);
  }

  public function testGetStatusWhenDisabled(): void {
    $this->config->method('get')
      ->willReturnMap([
        ['rate_limiting.enabled', FALSE],
        ['rate_limiting.max_writes_per_minute', 30],
        ['rate_limiting.max_writes_per_hour', 500],
        ['rate_limiting.max_deletes_per_hour', 50],
        ['rate_limiting.max_structure_changes_per_hour', 100],
      ]);

    $this->requestStack->method('getCurrentRequest')->willReturn(NULL);

    $limiter = $this->createRateLimiter();
    $status = $limiter->getStatus();

    $this->assertFalse($status['enabled']);
    $this->assertArrayHasKey('limits', $status);
    $this->assertArrayHasKey('client_id', $status);
  }

  public function testGetStatusShowsUsage(): void {
    $this->config->method('get')
      ->willReturnMap([
        ['rate_limiting.enabled', TRUE],
        ['rate_limiting.max_writes_per_minute', 30],
        ['rate_limiting.max_writes_per_hour', 500],
        ['rate_limiting.max_deletes_per_hour', 50],
        ['rate_limiting.max_structure_changes_per_hour', 100],
      ]);

    $request = $this->createMock(Request::class);
    $request->method('getClientIp')->willReturn('127.0.0.1');
    $request->headers = $this->createMock(\Symfony\Component\HttpFoundation\HeaderBag::class);
    $request->headers->method('get')->willReturn('');
    $this->requestStack->method('getCurrentRequest')->willReturn($request);

    $limiter = $this->createRateLimiter();

    // Perform some operations.
    $limiter->checkLimit('write');
    $limiter->checkLimit('write');
    $limiter->checkLimit('delete');

    $status = $limiter->getStatus();

    $this->assertTrue($status['enabled']);
    $this->assertArrayHasKey('current_usage', $status);
    $this->assertArrayHasKey('write', $status['current_usage']);
    $this->assertArrayHasKey('delete', $status['current_usage']);
  }

  public function testResetLimitsClearsCounters(): void {
    $this->config->method('get')
      ->willReturnMap([
        ['rate_limiting.enabled', TRUE],
        ['rate_limiting.max_writes_per_minute', 2],
        ['rate_limiting.max_writes_per_hour', 500],
      ]);

    $request = $this->createMock(Request::class);
    $request->method('getClientIp')->willReturn('127.0.0.1');
    $request->headers = $this->createMock(\Symfony\Component\HttpFoundation\HeaderBag::class);
    $request->headers->method('get')->willReturn('');
    $this->requestStack->method('getCurrentRequest')->willReturn($request);

    $limiter = $this->createRateLimiter();

    // Use up the limit.
    $limiter->checkLimit('write');
    $limiter->checkLimit('write');
    $result = $limiter->checkLimit('write');
    $this->assertFalse($result['allowed']);

    // Reset limits.
    $limiter->resetLimits();

    // Should work again.
    $result = $limiter->checkLimit('write');
    $this->assertTrue($result['allowed']);
  }

  public function testDifferentClientsHaveSeparateLimits(): void {
    $this->config->method('get')
      ->willReturnMap([
        ['rate_limiting.enabled', TRUE],
        ['rate_limiting.max_writes_per_minute', 1],
        ['rate_limiting.max_writes_per_hour', 100],
      ]);

    // First client.
    $request1 = $this->createMock(Request::class);
    $request1->method('getClientIp')->willReturn('192.168.1.1');
    $request1->headers = $this->createMock(\Symfony\Component\HttpFoundation\HeaderBag::class);
    $request1->headers->method('get')->willReturn('');

    $this->requestStack->method('getCurrentRequest')->willReturn($request1);

    $limiter = $this->createRateLimiter();
    $result = $limiter->checkLimit('write');
    $this->assertTrue($result['allowed']);

    // Second client should not be affected by first client's usage.
    $request2 = $this->createMock(Request::class);
    $request2->method('getClientIp')->willReturn('192.168.1.2');
    $request2->headers = $this->createMock(\Symfony\Component\HttpFoundation\HeaderBag::class);
    $request2->headers->method('get')->willReturn('');

    // Create new limiter for second client.
    $this->requestStack = $this->createMock(RequestStack::class);
    $this->requestStack->method('getCurrentRequest')->willReturn($request2);
    $limiter2 = $this->createRateLimiter();

    $result2 = $limiter2->checkLimit('write');
    $this->assertTrue($result2['allowed']);
  }

  public function testCheckReadLimitUnknownOperationIsAllowed(): void {
    $this->requestStack->method('getCurrentRequest')->willReturn(NULL);

    $limiter = $this->createRateLimiter();
    $result = $limiter->checkReadLimit('unknown_operation');

    $this->assertTrue($result['allowed']);
    $this->assertNull($result['error']);
  }

  public function testCheckReadLimitBlocksContentSearchWhenOverLimit(): void {
    $this->config->method('get')
      ->willReturnMap([
        ['rate_limits.content_search.max_per_minute', 2],
      ]);

    $request = $this->createMock(Request::class);
    $request->method('getClientIp')->willReturn('127.0.0.1');
    $request->headers = $this->createMock(\Symfony\Component\HttpFoundation\HeaderBag::class);
    $request->headers->method('get')->willReturn('');
    $this->requestStack->method('getCurrentRequest')->willReturn($request);

    $limiter = $this->createRateLimiter();

    $result1 = $limiter->checkReadLimit('content_search');
    $this->assertTrue($result1['allowed']);

    $result2 = $limiter->checkReadLimit('content_search');
    $this->assertTrue($result2['allowed']);

    $result3 = $limiter->checkReadLimit('content_search');
    $this->assertFalse($result3['allowed']);
    $this->assertStringContainsString('Rate limit exceeded', $result3['error']);
    $this->assertNotNull($result3['retry_after']);
  }

  public function testCheckReadLimitBlocksBrokenLinkScanWhenOverLimit(): void {
    $this->config->method('get')
      ->willReturnMap([
        ['rate_limits.broken_link_scan.max_per_hour', 1],
      ]);

    $request = $this->createMock(Request::class);
    $request->method('getClientIp')->willReturn('127.0.0.1');
    $request->headers = $this->createMock(\Symfony\Component\HttpFoundation\HeaderBag::class);
    $request->headers->method('get')->willReturn('');
    $this->requestStack->method('getCurrentRequest')->willReturn($request);

    $limiter = $this->createRateLimiter();

    $result1 = $limiter->checkReadLimit('broken_link_scan');
    $this->assertTrue($result1['allowed']);

    $result2 = $limiter->checkReadLimit('broken_link_scan');
    $this->assertFalse($result2['allowed']);
    $this->assertStringContainsString('broken_link_scan', $result2['error']);
  }

  public function testClientIdentifierIgnoresClientIdHeaderByDefault(): void {
    $this->config->method('get')
      ->willReturnMap([
        ['rate_limiting.trust_client_id_header', FALSE],
      ]);

    $request = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '127.0.0.1']);
    $request->headers->set('User-Agent', 'ua');
    $request->headers->set('Accept-Language', 'en');
    $request->headers->set('X-MCP-Client-Id', 'client-a');

    $this->requestStack->method('getCurrentRequest')->willReturn($request);

    $limiter = new class($this->configFactory, $this->state, $this->requestStack) extends RateLimiter {
      public function exposedClientIdentifier(): string {
        return $this->getClientIdentifier();
      }
    };

    $idA = $limiter->exposedClientIdentifier();
    $request->headers->set('X-MCP-Client-Id', 'client-b');
    $idB = $limiter->exposedClientIdentifier();

    $this->assertSame($idA, $idB);
  }

  public function testClientIdentifierUsesClientIdHeaderWhenTrusted(): void {
    $this->config->method('get')
      ->willReturnMap([
        ['rate_limiting.trust_client_id_header', TRUE],
      ]);

    $request = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '127.0.0.1']);
    $request->headers->set('User-Agent', 'ua');
    $request->headers->set('Accept-Language', 'en');
    $request->headers->set('X-MCP-Client-Id', 'client-a');

    $this->requestStack->method('getCurrentRequest')->willReturn($request);

    $limiter = new class($this->configFactory, $this->state, $this->requestStack) extends RateLimiter {
      public function exposedClientIdentifier(): string {
        return $this->getClientIdentifier();
      }
    };

    $idA = $limiter->exposedClientIdentifier();
    $request->headers->set('X-MCP-Client-Id', 'client-b');
    $idB = $limiter->exposedClientIdentifier();

    $this->assertNotSame($idA, $idB);
  }

  public function testClientIdentifierPrefersTrustedRequestAttribute(): void {
    $this->config->method('get')
      ->willReturnMap([
        ['rate_limiting.trust_client_id_header', TRUE],
      ]);

    $request = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '127.0.0.1']);
    $request->headers->set('User-Agent', 'ua');
    $request->headers->set('Accept-Language', 'en');
    $request->headers->set('X-MCP-Client-Id', 'client-a');
    $request->attributes->set('mcp_tools.client_id', 'trusted-id');

    $this->requestStack->method('getCurrentRequest')->willReturn($request);

    $limiter = new class($this->configFactory, $this->state, $this->requestStack) extends RateLimiter {
      public function exposedClientIdentifier(): string {
        return $this->getClientIdentifier();
      }
    };

    $trusted = $limiter->exposedClientIdentifier();
    $request->headers->set('X-MCP-Client-Id', 'client-b');
    $stillTrusted = $limiter->exposedClientIdentifier();

    $this->assertSame($trusted, $stillTrusted);
  }

}
