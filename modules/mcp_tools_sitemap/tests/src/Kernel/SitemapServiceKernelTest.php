<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_sitemap\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools_sitemap\Service\SitemapService;

/**
 * Kernel tests for SitemapService.
 *
 * @group mcp_tools_sitemap
 * @requires module simple_sitemap
 */
final class SitemapServiceKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'node',
    'path_alias',
    'simple_sitemap',
    'dblog',
    'update',
    'tool',
    'mcp_tools',
    'mcp_tools_sitemap',
  ];

  /**
   * The sitemap service under test.
   */
  private SitemapService $sitemapService;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['mcp_tools', 'simple_sitemap']);
    $this->installSchema('dblog', ['watchdog']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('simple_sitemap');

    $this->sitemapService = $this->container->get('mcp_tools_sitemap.sitemap');

    $this->container->get('mcp_tools.access_manager')->setScopes([
      AccessManager::SCOPE_READ,
      AccessManager::SCOPE_WRITE,
    ]);
  }

  /**
   * Test getting sitemap status.
   */
  public function testGetStatus(): void {
    $result = $this->sitemapService->getStatus();

    $this->assertTrue($result['success']);
    $this->assertArrayHasKey('status', $result['data']);
  }

  /**
   * Test getting sitemaps.
   */
  public function testGetSitemaps(): void {
    $result = $this->sitemapService->getSitemaps();

    $this->assertTrue($result['success']);
    $this->assertArrayHasKey('sitemaps', $result['data']);
  }

  /**
   * Test getting settings.
   */
  public function testGetSettings(): void {
    $result = $this->sitemapService->getSettings();

    $this->assertTrue($result['success']);
    $this->assertArrayHasKey('settings', $result['data']);
  }

  /**
   * Test getting entity settings.
   */
  public function testGetEntitySettings(): void {
    $result = $this->sitemapService->getEntitySettings('node');

    $this->assertTrue($result['success']);
    $this->assertArrayHasKey('entity_type', $result['data']);
    $this->assertSame('node', $result['data']['entity_type']);
  }

  /**
   * Test updating settings requires write scope.
   */
  public function testUpdateSettingsRequiresWriteScope(): void {
    $this->container->get('mcp_tools.access_manager')->setScopes([
      AccessManager::SCOPE_READ,
    ]);

    $result = $this->sitemapService->updateSettings('default', []);
    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Write operations not allowed', $result['error']);
  }

  /**
   * Test regenerate requires write scope.
   */
  public function testRegenerateRequiresWriteScope(): void {
    $this->container->get('mcp_tools.access_manager')->setScopes([
      AccessManager::SCOPE_READ,
    ]);

    $result = $this->sitemapService->regenerate();
    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Write operations not allowed', $result['error']);
  }

  /**
   * Test set entity settings requires write scope.
   */
  public function testSetEntitySettingsRequiresWriteScope(): void {
    $this->container->get('mcp_tools.access_manager')->setScopes([
      AccessManager::SCOPE_READ,
    ]);

    $result = $this->sitemapService->setEntitySettings('node', 'article', []);
    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Write operations not allowed', $result['error']);
  }

}
