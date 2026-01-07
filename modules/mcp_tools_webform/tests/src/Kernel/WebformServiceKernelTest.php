<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_webform\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools_webform\Service\WebformService;

/**
 * Kernel tests for WebformService.
 *
 * These tests require the webform module to be installed.
 * They are run as part of the contrib integration CI matrix.
 *
 * @group mcp_tools_webform
 * @requires module webform
 */
final class WebformServiceKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'path_alias',
    'webform',
    'dblog',
    'update',
    'tool',
    'mcp_tools',
    'mcp_tools_webform',
  ];

  /**
   * The webform service under test.
   */
  private WebformService $webformService;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['mcp_tools']);
    $this->installSchema('dblog', ['watchdog']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('webform');
    $this->installEntitySchema('webform_submission');
    $this->installEntitySchema('path_alias');

    $this->webformService = $this->container->get('mcp_tools_webform.webform');

    // Enable write scope for testing write operations.
    $this->container->get('mcp_tools.access_manager')->setScopes([
      AccessManager::SCOPE_READ,
      AccessManager::SCOPE_WRITE,
    ]);
  }

  /**
   * Test listing webforms when none exist.
   */
  public function testListWebformsEmpty(): void {
    $result = $this->webformService->listWebforms();

    $this->assertTrue($result['success']);
    $this->assertSame(0, $result['data']['total']);
    $this->assertEmpty($result['data']['webforms']);
  }

  /**
   * Test creating and getting a webform.
   */
  public function testCreateAndGetWebform(): void {
    // Create a webform.
    $result = $this->webformService->createWebform(
      'test_form',
      'Test Form'
    );

    $this->assertTrue($result['success'], 'Create webform should succeed');
    $this->assertSame('test_form', $result['data']['id']);
    $this->assertSame('Test Form', $result['data']['title']);
    $this->assertSame('open', $result['data']['status']);

    // Get the webform.
    $getResult = $this->webformService->getWebform('test_form');
    $this->assertTrue($getResult['success']);
    $this->assertSame('test_form', $getResult['data']['id']);
    $this->assertSame('Test Form', $getResult['data']['title']);
  }

  /**
   * Test listing webforms after creation.
   */
  public function testListWebformsAfterCreate(): void {
    $this->webformService->createWebform('form_one', 'Form One');
    $this->webformService->createWebform('form_two', 'Form Two');

    $result = $this->webformService->listWebforms();

    $this->assertTrue($result['success']);
    $this->assertSame(2, $result['data']['total']);
    $this->assertCount(2, $result['data']['webforms']);
  }

  /**
   * Test creating webform with elements.
   */
  public function testCreateWebformWithElements(): void {
    $elements = [
      'name' => [
        '#type' => 'textfield',
        '#title' => 'Name',
        '#required' => TRUE,
      ],
      'email' => [
        '#type' => 'email',
        '#title' => 'Email',
        '#required' => TRUE,
      ],
    ];

    $result = $this->webformService->createWebform(
      'contact_form',
      'Contact Form',
      $elements
    );

    $this->assertTrue($result['success']);

    // Get the webform and check elements.
    $getResult = $this->webformService->getWebform('contact_form');
    $this->assertTrue($getResult['success']);
    $this->assertNotEmpty($getResult['data']['elements']);
  }

  /**
   * Test creating duplicate webform fails.
   */
  public function testCreateWebformDuplicate(): void {
    $this->webformService->createWebform('duplicate', 'Duplicate');

    $result = $this->webformService->createWebform('duplicate', 'Duplicate 2');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('already exists', $result['error']);
  }

  /**
   * Test creating webform with invalid ID.
   */
  public function testCreateWebformInvalidId(): void {
    // Starts with number.
    $result = $this->webformService->createWebform('123form', 'Invalid');
    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Invalid webform ID', $result['error']);

    // Contains uppercase.
    $result = $this->webformService->createWebform('MyForm', 'Invalid');
    $this->assertFalse($result['success']);

    // Contains special characters.
    $result = $this->webformService->createWebform('my-form', 'Invalid');
    $this->assertFalse($result['success']);
  }

  /**
   * Test updating a webform.
   */
  public function testUpdateWebform(): void {
    $this->webformService->createWebform('updateme', 'Update Me');

    $result = $this->webformService->updateWebform('updateme', [
      'title' => 'Updated Title',
      'description' => 'A description',
      'status' => 'closed',
    ]);

    $this->assertTrue($result['success']);
    $this->assertSame('Updated Title', $result['data']['title']);
    $this->assertSame('closed', $result['data']['status']);
  }

  /**
   * Test updating a non-existent webform.
   */
  public function testUpdateWebformNotFound(): void {
    $result = $this->webformService->updateWebform('nonexistent', [
      'title' => 'Updated',
    ]);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * Test deleting a webform.
   */
  public function testDeleteWebform(): void {
    $this->webformService->createWebform('deleteme', 'Delete Me');

    $result = $this->webformService->deleteWebform('deleteme');

    $this->assertTrue($result['success']);
    $this->assertSame('deleteme', $result['data']['id']);

    // Verify it's gone.
    $getResult = $this->webformService->getWebform('deleteme');
    $this->assertFalse($getResult['success']);
    $this->assertStringContainsString('not found', $getResult['error']);
  }

  /**
   * Test deleting a non-existent webform.
   */
  public function testDeleteWebformNotFound(): void {
    $result = $this->webformService->deleteWebform('nonexistent');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * Test getting a non-existent webform.
   */
  public function testGetWebformNotFound(): void {
    $result = $this->webformService->getWebform('nonexistent');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * Test getting submissions for non-existent webform.
   */
  public function testGetSubmissionsWebformNotFound(): void {
    $result = $this->webformService->getSubmissions('nonexistent');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  /**
   * Test getting submissions when none exist.
   */
  public function testGetSubmissionsEmpty(): void {
    $this->webformService->createWebform('empty_form', 'Empty Form');

    $result = $this->webformService->getSubmissions('empty_form');

    $this->assertTrue($result['success']);
    $this->assertSame(0, $result['data']['total']);
    $this->assertEmpty($result['data']['submissions']);
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
    $result = $this->webformService->createWebform('test', 'Test');
    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Write operations not allowed', $result['error']);

    // Re-enable to create a webform for update/delete tests.
    $this->container->get('mcp_tools.access_manager')->setScopes([
      AccessManager::SCOPE_READ,
      AccessManager::SCOPE_WRITE,
    ]);
    $this->webformService->createWebform('writetest', 'Write Test');

    // Disable again.
    $this->container->get('mcp_tools.access_manager')->setScopes([
      AccessManager::SCOPE_READ,
    ]);

    // Test update.
    $result = $this->webformService->updateWebform('writetest', ['title' => 'New']);
    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Write operations not allowed', $result['error']);

    // Test delete.
    $result = $this->webformService->deleteWebform('writetest');
    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Write operations not allowed', $result['error']);
  }

}
