<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_structure\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;

/**
 * A body field on a site that has no text_with_summary field type.
 *
 * Drupal 12 moved the type out of core, to the module of that name. A new
 * site on 12 does not have it. A content type with a body could not be made
 * there: "Attempt to create a field storage of unknown type
 * text_with_summary" (seen in a trial run on 12.0.0-beta2).
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_structure')]
final class BodyWithoutSummaryTypeKernelTest extends KernelTestBase {

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
    'no_summary_type_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Not the config of node: on Drupal 10 it brings a body field storage
    // of the type this site does not have.
    $this->installConfig(['mcp_tools']);
    $this->installSchema('dblog', ['watchdog']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->container->get('mcp_tools.access_manager')->setScopes(['read', 'write']);
  }

  /**
   * The body of a new content type is a long formatted text.
   */
  public function testBodyIsALongText(): void {
    $this->assertFalse($this->container->get('plugin.manager.field.field_type')->hasDefinition('text_with_summary'));

    $result = $this->container->get('mcp_tools_structure.content_type')->createContentType('plain', 'Plain', ['create_body' => TRUE]);
    $this->assertTrue($result['success'], (string) ($result['error'] ?? ''));

    $storage = FieldStorageConfig::load('node.body');
    $this->assertNotNull($storage, 'No body field was made.');
    $this->assertSame('text_long', $storage->getType());
    $this->assertNotNull(FieldConfig::load('node.plain.body'));
    $form = $this->container->get('entity_type.manager')->getStorage('entity_form_display')->load('node.plain.default');
    $this->assertSame('text_textarea', $form->getComponent('body')['type']);
  }

  /**
   * A field asked for with a summary is a long text, and the answer says so.
   */
  public function testAFieldWithSummaryBecomesALongText(): void {
    $this->container->get('mcp_tools_structure.content_type')->createContentType('plain', 'Plain', ['create_body' => FALSE]);
    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();
    $this->container->get('entity_type.bundle.info')->clearCachedBundles();
    $service = $this->container->get('mcp_tools_structure.field');

    $this->assertNotContains('text_with_summary', array_column($service->getFieldTypes()['types'], 'id'));

    $result = $service->addField('node', 'plain', 'story', 'text_with_summary', 'Story');
    $this->assertTrue($result['success'], (string) ($result['error'] ?? ''));
    $this->assertSame('text_long', FieldStorageConfig::load('node.field_story')->getType());
    $this->assertSame('text_long', $result['data']['field_type']);
    $this->assertStringContainsString('text_long', $result['data']['message']);
    $this->assertStringContainsString('text_with_summary', $result['data']['message']);
  }

}
