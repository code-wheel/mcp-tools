<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_structure\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools_structure\Service\ContentTypeService;
use Drupal\mcp_tools_structure\Service\FieldService;

#[\PHPUnit\Framework\Attributes\Group('mcp_tools_structure')]
final class StructureServicesKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'node',
    'dblog',
    'update',
    'tool',
    'mcp_tools',
    'mcp_tools_structure',
  ];

  private ContentTypeService $contentTypeService;

  private FieldService $fieldService;

  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['mcp_tools']);
    $this->installConfig(['node']);
    $this->installSchema('dblog', ['watchdog']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');

    $this->contentTypeService = $this->container->get('mcp_tools_structure.content_type');
    $this->fieldService = $this->container->get('mcp_tools_structure.field');

    // Structure services enforce write scope via AccessManager.
    $this->container->get('mcp_tools.access_manager')->setScopes([
      AccessManager::SCOPE_READ,
      AccessManager::SCOPE_WRITE,
    ]);
  }

  public function testCreateAndDeleteContentType(): void {
    $create = $this->contentTypeService->createContentType('foo', 'Foo', [
      'create_body' => TRUE,
    ]);
    $this->assertTrue($create['success']);

    $nodeType = $this->container->get('entity_type.manager')->getStorage('node_type')->load('foo');
    $this->assertNotNull($nodeType);

    $delete = $this->contentTypeService->deleteContentType('foo');
    $this->assertTrue($delete['success']);
  }

  public function testAddAndDeleteField(): void {
    $create = $this->contentTypeService->createContentType('bar', 'Bar', [
      'create_body' => FALSE,
    ]);
    $this->assertTrue($create['success']);

    // Field definition caches need to be cleared after creating a new bundle.
    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();
    $this->container->get('entity_type.bundle.info')->clearCachedBundles();

    $add = $this->fieldService->addField('node', 'bar', 'subtitle', 'string', 'Subtitle');
    $this->assertTrue($add['success']);
    $this->assertSame('field_subtitle', $add['data']['field_name']);

    $fieldConfig = $this->container->get('entity_type.manager')->getStorage('field_config')->load('node.bar.field_subtitle');
    $this->assertNotNull($fieldConfig);

    $delete = $this->fieldService->deleteField('node', 'bar', 'subtitle');
    $this->assertTrue($delete['success']);
  }

}
