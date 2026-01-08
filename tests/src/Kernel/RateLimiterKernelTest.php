<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\mcp_tools\Service\RateLimiter;

/**
 * Kernel tests for MCP Tools rate limiter integration.
 *
 */
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
class RateLimiterKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'mcp_tools',
    'tool',
    'user',
    'system',
    'update',
    'dblog',
  ];

  /**
   * The rate limiter service.
   *
   * @var \Drupal\mcp_tools\Service\RateLimiter
   */
  protected RateLimiter $rateLimiter;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['mcp_tools']);
    $this->rateLimiter = $this->container->get('mcp_tools.rate_limiter');
  }

  /**
   * Tests rate limiting is disabled by default.
   */
  public function testRateLimitingDisabledByDefault(): void {
    $status = $this->rateLimiter->getStatus();
    $this->assertFalse($status['enabled']);
  }

  /**
   * Tests rate limiting can be enabled via config.
   */
  public function testEnableRateLimiting(): void {
    $config = $this->config('mcp_tools.settings');
    $config->set('rate_limiting.enabled', TRUE)->save();

    // Recreate the rate limiter to pick up new config.
    $this->rateLimiter = new RateLimiter(
      $this->container->get('config.factory'),
      $this->container->get('state'),
      $this->container->get('request_stack')
    );

    $status = $this->rateLimiter->getStatus();
    $this->assertTrue($status['enabled']);
  }

  /**
   * Tests rate limit enforcement when enabled.
   */
  public function testRateLimitEnforcement(): void {
    $config = $this->config('mcp_tools.settings');
    $config->set('rate_limiting.enabled', TRUE);
    $config->set('rate_limiting.max_writes_per_minute', 2);
    $config->save();

    $this->rateLimiter = new RateLimiter(
      $this->container->get('config.factory'),
      $this->container->get('state'),
      $this->container->get('request_stack')
    );

    // First two should succeed.
    $result1 = $this->rateLimiter->checkLimit('write');
    $this->assertTrue($result1['allowed']);

    $result2 = $this->rateLimiter->checkLimit('write');
    $this->assertTrue($result2['allowed']);

    // Third should be blocked.
    $result3 = $this->rateLimiter->checkLimit('write');
    $this->assertFalse($result3['allowed']);
    $this->assertArrayHasKey('retry_after', $result3);
  }

  /**
   * Tests status includes correct limits from config.
   */
  public function testStatusReflectsConfiguredLimits(): void {
    $config = $this->config('mcp_tools.settings');
    $config->set('rate_limiting.enabled', TRUE);
    $config->set('rate_limiting.max_writes_per_minute', 25);
    $config->set('rate_limiting.max_writes_per_hour', 400);
    $config->set('rate_limiting.max_deletes_per_hour', 40);
    $config->save();

    $this->rateLimiter = new RateLimiter(
      $this->container->get('config.factory'),
      $this->container->get('state'),
      $this->container->get('request_stack')
    );

    $status = $this->rateLimiter->getStatus();

    $this->assertEquals(25, $status['limits']['writes_per_minute']);
    $this->assertEquals(400, $status['limits']['writes_per_hour']);
    $this->assertEquals(40, $status['limits']['deletes_per_hour']);
  }

  /**
   * Tests reset limits clears state.
   */
  public function testResetLimits(): void {
    $config = $this->config('mcp_tools.settings');
    $config->set('rate_limiting.enabled', TRUE);
    $config->set('rate_limiting.max_writes_per_minute', 1);
    $config->save();

    $this->rateLimiter = new RateLimiter(
      $this->container->get('config.factory'),
      $this->container->get('state'),
      $this->container->get('request_stack')
    );

    // Use up the limit.
    $this->rateLimiter->checkLimit('write');
    $result = $this->rateLimiter->checkLimit('write');
    $this->assertFalse($result['allowed']);

    // Reset.
    $this->rateLimiter->resetLimits();

    // Should work again.
    $result = $this->rateLimiter->checkLimit('write');
    $this->assertTrue($result['allowed']);
  }

  /**
   * Tests delete operations have separate limits.
   */
  public function testDeleteSeparateLimits(): void {
    $config = $this->config('mcp_tools.settings');
    $config->set('rate_limiting.enabled', TRUE);
    $config->set('rate_limiting.max_writes_per_minute', 100);
    $config->set('rate_limiting.max_writes_per_hour', 100);
    $config->set('rate_limiting.max_deletes_per_hour', 1);
    $config->save();

    $this->rateLimiter = new RateLimiter(
      $this->container->get('config.factory'),
      $this->container->get('state'),
      $this->container->get('request_stack')
    );

    // Write should work.
    $result = $this->rateLimiter->checkLimit('write');
    $this->assertTrue($result['allowed']);

    // First delete should work.
    $result = $this->rateLimiter->checkLimit('delete');
    $this->assertTrue($result['allowed']);

    // Second delete should be blocked.
    $result = $this->rateLimiter->checkLimit('delete');
    $this->assertFalse($result['allowed']);

    // But write should still work.
    $result = $this->rateLimiter->checkLimit('write');
    $this->assertTrue($result['allowed']);
  }

  /**
   * Tests structure changes have separate limits.
   */
  public function testStructureSeparateLimits(): void {
    $config = $this->config('mcp_tools.settings');
    $config->set('rate_limiting.enabled', TRUE);
    $config->set('rate_limiting.max_writes_per_minute', 100);
    $config->set('rate_limiting.max_writes_per_hour', 100);
    $config->set('rate_limiting.max_structure_changes_per_hour', 1);
    $config->save();

    $this->rateLimiter = new RateLimiter(
      $this->container->get('config.factory'),
      $this->container->get('state'),
      $this->container->get('request_stack')
    );

    // First structure change should work.
    $result = $this->rateLimiter->checkLimit('structure');
    $this->assertTrue($result['allowed']);

    // Second should be blocked.
    $result = $this->rateLimiter->checkLimit('structure');
    $this->assertFalse($result['allowed']);
    $this->assertStringContainsString('structure', $result['error']);
  }

  /**
   * Tests read operation limits can be configured and enforced.
   */
  public function testReadLimitEnforcement(): void {
    $config = $this->config('mcp_tools.settings');
    $config->set('rate_limits.content_search.max_per_minute', 1)->save();

    $this->rateLimiter = new RateLimiter(
      $this->container->get('config.factory'),
      $this->container->get('state'),
      $this->container->get('request_stack')
    );

    $result1 = $this->rateLimiter->checkReadLimit('content_search');
    $this->assertTrue($result1['allowed']);

    $result2 = $this->rateLimiter->checkReadLimit('content_search');
    $this->assertFalse($result2['allowed']);
    $this->assertArrayHasKey('retry_after', $result2);
  }

}
