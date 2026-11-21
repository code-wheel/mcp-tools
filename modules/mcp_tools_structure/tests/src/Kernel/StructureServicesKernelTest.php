<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_structure\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\mcp_tools\Traits\DeclaredOutputTypesTrait;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools_structure\Service\ContentTypeService;
use Drupal\mcp_tools_structure\Service\FieldService;
use Drupal\Tests\user\Traits\UserCreationTrait;

#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_structure')]
final class StructureServicesKernelTest extends KernelTestBase {

  use DeclaredOutputTypesTrait;

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

  use UserCreationTrait;

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

  /**
   * The scaffold tool creates the type, the core body field and its fields.
   */
  public function testScaffoldContentTypeTool(): void {
    $this->setUpCurrentUser([], [], TRUE);

    $tool = $this->container->get('plugin.manager.tool')->createInstance('mcp_structure_scaffold_content_type');
    $tool->setInputValue('id', 'event');
    $tool->setInputValue('label', 'Event');
    $tool->setInputValue('description', 'Things that happen.');
    $tool->setInputValue('fields', [
      ['name' => 'venue', 'type' => 'string', 'required' => TRUE],
    ]);
    $tool->execute();

    $result = $tool->getResult();
    $this->assertTrue($result->isSuccess(), (string) $result->getMessage());
    $this->assertOutputsHaveDeclaredTypes($tool, (string) $tool->getPluginId());
    $this->assertSame([], $tool->getOutputValues()['fields_failed']);

    $entityTypeManager = $this->container->get('entity_type.manager');
    $this->assertSame('Things that happen.', $entityTypeManager->getStorage('node_type')->load('event')->getDescription());
    $this->assertNotNull($entityTypeManager->getStorage('field_config')->load('node.event.body'));
    $venue = $entityTypeManager->getStorage('field_config')->load('node.event.field_venue');
    $this->assertNotNull($venue);
    $this->assertTrue($venue->isRequired());
  }

}
