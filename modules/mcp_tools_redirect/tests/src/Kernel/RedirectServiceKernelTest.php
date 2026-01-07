<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_redirect\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools_redirect\Service\RedirectService;

/**
 * Kernel tests for RedirectService.
 *
 * These tests require the redirect module to be installed.
 * They are run as part of the contrib integration CI matrix.
 *
 * @group mcp_tools_redirect
 * @requires module redirect
 */
final class RedirectServiceKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'link',
    'path_alias',
    'redirect',
    'dblog',
    'update',
    'tool',
    'mcp_tools',
    'mcp_tools_redirect',
  ];

  /**
   * The redirect service under test.
   */
  private RedirectService $redirectService;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['mcp_tools']);
    $this->installSchema('dblog', ['watchdog']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('redirect');
    $this->installEntitySchema('path_alias');

    $this->redirectService = $this->container->get('mcp_tools_redirect.redirect');

    // Enable write scope for testing write operations.
    $this->container->get('mcp_tools.access_manager')->setScopes([
      AccessManager::SCOPE_READ,
      AccessManager::SCOPE_WRITE,
    ]);
  }

  /**
   * Test listing redirects when none exist.
   */
  public function testListRedirectsEmpty(): void {
    $result = $this->redirectService->listRedirects();

    $this->assertTrue($result['success']);
    $this->assertSame(0, $result['data']['total']);
    $this->assertEmpty($result['data']['redirects']);
  }

  /**
   * Test creating and getting a redirect.
   */
  public function testCreateAndGetRedirect(): void {
    // Create a redirect.
    $result = $this->redirectService->createRedirect(
      'old-page',
      '/new-page',
      301
    );

    $this->assertTrue($result['success'], 'Create redirect should succeed');
    $this->assertArrayHasKey('redirect', $result['data']);
    $this->assertSame('old-page', $result['data']['redirect']['source']);
    $this->assertSame(301, $result['data']['redirect']['status_code']);

    $redirectId = $result['data']['redirect']['id'];

    // Get the redirect.
    $getResult = $this->redirectService->getRedirect($redirectId);
    $this->assertTrue($getResult['success']);
    $this->assertSame($redirectId, $getResult['data']['id']);
  }

  /**
   * Test listing redirects after creation.
   */
  public function testListRedirectsAfterCreate(): void {
    // Create two redirects.
    $this->redirectService->createRedirect('page-one', '/destination-one', 301);
    $this->redirectService->createRedirect('page-two', '/destination-two', 302);

    // List redirects.
    $result = $this->redirectService->listRedirects();

    $this->assertTrue($result['success']);
    $this->assertSame(2, $result['data']['total']);
    $this->assertCount(2, $result['data']['redirects']);
  }

  /**
   * Test pagination in listing redirects.
   */
  public function testListRedirectsPagination(): void {
    // Create 5 redirects.
    for ($i = 1; $i <= 5; $i++) {
      $this->redirectService->createRedirect("page-$i", "/destination-$i", 301);
    }

    // List with limit.
    $result = $this->redirectService->listRedirects(2, 0);
    $this->assertTrue($result['success']);
    $this->assertSame(5, $result['data']['total']);
    $this->assertCount(2, $result['data']['redirects']);

    // List with offset.
    $result = $this->redirectService->listRedirects(2, 2);
    $this->assertTrue($result['success']);
    $this->assertSame(5, $result['data']['total']);
    $this->assertCount(2, $result['data']['redirects']);
  }

  /**
   * Test creating redirect with invalid status code.
   */
  public function testCreateRedirectInvalidStatusCode(): void {
    $result = $this->redirectService->createRedirect(
      'old-page',
      '/new-page',
      404
    );

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Invalid status code', $result['error']);
  }

  /**
   * Test creating redirect with empty source.
   */
  public function testCreateRedirectEmptySource(): void {
    $result = $this->redirectService->createRedirect(
      '',
      '/new-page',
      301
    );

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Source path cannot be empty', $result['error']);
  }

  /**
   * Test creating redirect with empty destination.
   */
  public function testCreateRedirectEmptyDestination(): void {
    $result = $this->redirectService->createRedirect(
      'old-page',
      '',
      301
    );

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Destination cannot be empty', $result['error']);
  }

  /**
   * Test duplicate redirect prevention.
   */
  public function testCreateRedirectDuplicate(): void {
    // Create first redirect.
    $result1 = $this->redirectService->createRedirect('old-page', '/new-page', 301);
    $this->assertTrue($result1['success']);

    // Try to create duplicate.
    $result2 = $this->redirectService->createRedirect('old-page', '/other-page', 302);
    $this->assertFalse($result2['success']);
    $this->assertStringContainsString('already exists', $result2['error']);
  }

  /**
   * Test updating a redirect.
   */
  public function testUpdateRedirect(): void {
    // Create a redirect.
    $createResult = $this->redirectService->createRedirect('old-page', '/new-page', 301);
    $this->assertTrue($createResult['success']);
    $redirectId = $createResult['data']['redirect']['id'];

    // Update the redirect.
    $updateResult = $this->redirectService->updateRedirect($redirectId, [
      'status_code' => 302,
      'destination' => '/updated-destination',
    ]);

    $this->assertTrue($updateResult['success']);
    $this->assertContains('status_code', $updateResult['data']['updated_fields']);
    $this->assertContains('destination', $updateResult['data']['updated_fields']);
    $this->assertSame(302, $updateResult['data']['redirect']['status_code']);
  }

  /**
   * Test updating a non-existent redirect.
   */
  public function testUpdateRedirectNotFound(): void {
    $result = $this->redirectService->updateRedirect(99999, [
      'status_code' => 302,
    ]);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * Test updating redirect with invalid status code.
   */
  public function testUpdateRedirectInvalidStatusCode(): void {
    // Create a redirect.
    $createResult = $this->redirectService->createRedirect('old-page', '/new-page', 301);
    $redirectId = $createResult['data']['redirect']['id'];

    // Try to update with invalid status code.
    $updateResult = $this->redirectService->updateRedirect($redirectId, [
      'status_code' => 404,
    ]);

    $this->assertFalse($updateResult['success']);
    $this->assertStringContainsString('Invalid status code', $updateResult['error']);
  }

  /**
   * Test deleting a redirect.
   */
  public function testDeleteRedirect(): void {
    // Create a redirect.
    $createResult = $this->redirectService->createRedirect('old-page', '/new-page', 301);
    $redirectId = $createResult['data']['redirect']['id'];

    // Delete the redirect.
    $deleteResult = $this->redirectService->deleteRedirect($redirectId);
    $this->assertTrue($deleteResult['success']);
    $this->assertSame($redirectId, $deleteResult['data']['id']);

    // Verify it's gone.
    $getResult = $this->redirectService->getRedirect($redirectId);
    $this->assertFalse($getResult['success']);
    $this->assertStringContainsString('not found', $getResult['error']);
  }

  /**
   * Test deleting a non-existent redirect.
   */
  public function testDeleteRedirectNotFound(): void {
    $result = $this->redirectService->deleteRedirect(99999);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * Test finding redirect by source.
   */
  public function testFindBySource(): void {
    // Create a redirect.
    $this->redirectService->createRedirect('searchable-page', '/destination', 301);

    // Find it.
    $result = $this->redirectService->findBySource('searchable-page');
    $this->assertTrue($result['success']);
    $this->assertTrue($result['data']['found']);
    $this->assertSame('searchable-page', $result['data']['redirect']['source']);
  }

  /**
   * Test finding non-existent redirect by source.
   */
  public function testFindBySourceNotFound(): void {
    $result = $this->redirectService->findBySource('non-existent');

    $this->assertTrue($result['success']);
    $this->assertFalse($result['data']['found']);
    $this->assertStringContainsString('No redirect found', $result['data']['message']);
  }

  /**
   * Test finding redirect by source with leading slash.
   */
  public function testFindBySourceNormalization(): void {
    // Create a redirect.
    $this->redirectService->createRedirect('normalized-page', '/destination', 301);

    // Find with leading slash (should be normalized).
    $result = $this->redirectService->findBySource('/normalized-page');
    $this->assertTrue($result['success']);
    $this->assertTrue($result['data']['found']);
  }

  /**
   * Test bulk import of redirects.
   */
  public function testImportRedirects(): void {
    $redirects = [
      ['source' => 'import-1', 'destination' => '/dest-1', 'status_code' => 301],
      ['source' => 'import-2', 'destination' => '/dest-2', 'status_code' => 302],
      ['source' => 'import-3', 'destination' => '/dest-3'],
    ];

    $result = $this->redirectService->importRedirects($redirects);

    $this->assertTrue($result['success']);
    $this->assertSame(3, $result['data']['total_requested']);
    $this->assertSame(3, $result['data']['created_count']);
    $this->assertSame(0, $result['data']['skipped_count']);
    $this->assertSame(0, $result['data']['error_count']);
  }

  /**
   * Test bulk import skips duplicates.
   */
  public function testImportRedirectsSkipsDuplicates(): void {
    // Create existing redirect.
    $this->redirectService->createRedirect('existing', '/existing-dest', 301);

    // Try to import including the existing one.
    $redirects = [
      ['source' => 'existing', 'destination' => '/new-dest'],
      ['source' => 'new-one', 'destination' => '/new-dest'],
    ];

    $result = $this->redirectService->importRedirects($redirects);

    $this->assertTrue($result['success']);
    $this->assertSame(1, $result['data']['created_count']);
    $this->assertSame(1, $result['data']['skipped_count']);
  }

  /**
   * Test bulk import handles errors.
   */
  public function testImportRedirectsWithErrors(): void {
    $redirects = [
      ['source' => '', 'destination' => '/dest'],
      ['source' => 'valid', 'destination' => ''],
      ['source' => 'good-one', 'destination' => '/dest'],
    ];

    $result = $this->redirectService->importRedirects($redirects);

    $this->assertTrue($result['success']);
    $this->assertSame(1, $result['data']['created_count']);
    $this->assertSame(2, $result['data']['error_count']);
  }

  /**
   * Test write operations require write scope.
   */
  public function testWriteOperationsRequireWriteScope(): void {
    // Disable write scope.
    $this->container->get('mcp_tools.access_manager')->setScopes([
      AccessManager::SCOPE_READ,
    ]);

    // Test create.
    $result = $this->redirectService->createRedirect('test', '/dest', 301);
    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Write operations not allowed', $result['error']);

    // Re-enable write scope to create a redirect for update/delete tests.
    $this->container->get('mcp_tools.access_manager')->setScopes([
      AccessManager::SCOPE_READ,
      AccessManager::SCOPE_WRITE,
    ]);
    $createResult = $this->redirectService->createRedirect('test', '/dest', 301);
    $redirectId = $createResult['data']['redirect']['id'];

    // Disable write scope again.
    $this->container->get('mcp_tools.access_manager')->setScopes([
      AccessManager::SCOPE_READ,
    ]);

    // Test update.
    $result = $this->redirectService->updateRedirect($redirectId, ['status_code' => 302]);
    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Write operations not allowed', $result['error']);

    // Test delete.
    $result = $this->redirectService->deleteRedirect($redirectId);
    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Write operations not allowed', $result['error']);

    // Test import.
    $result = $this->redirectService->importRedirects([['source' => 'x', 'destination' => '/y']]);
    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Write operations not allowed', $result['error']);
  }

  /**
   * Test getting non-existent redirect.
   */
  public function testGetRedirectNotFound(): void {
    $result = $this->redirectService->getRedirect(99999);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

}
