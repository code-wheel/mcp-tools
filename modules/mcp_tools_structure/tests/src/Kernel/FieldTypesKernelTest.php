<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_structure\Kernel;

use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;

/**
 * Field types: only the ones the site has, and list fields with numbers.
 *
 * The tool run only ever added a list of strings, on a site with every
 * module. A list of numbers crashed, and a site without the Options module
 * was still told it could have list fields.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_structure')]
final class FieldTypesKernelTest extends KernelTestBase {

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

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['mcp_tools', 'node']);
    $this->installSchema('dblog', ['watchdog']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    NodeType::create(['type' => 'news', 'name' => 'News'])->save();
    $this->container->get('mcp_tools.access_manager')->setScopes(['read', 'write']);
  }

  /**
   * The type list and the refusal follow the modules the site has.
   */
  public function testOnlyTypesTheSiteHas(): void {
    $service = $this->container->get('mcp_tools_structure.field');

    // The Options module is off: no list fields, and the type list says so.
    $listed = array_column($service->getFieldTypes()['types'], 'id');
    $this->assertContains('string', $listed);
    $this->assertNotContains('list_string', $listed);
    $this->assertNotContains('list_integer', $listed);
    $this->assertSame(count($listed), $service->getFieldTypes()['total']);

    $result = $service->addField('node', 'news', 'kind', 'list_string', 'Kind', ['allowed_values' => ['a', 'b']]);
    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not available on this site', $result['error']);
    $this->assertNotContains('list_string', $result['available_types']);
    $this->assertNull(FieldStorageConfig::load('node.field_kind'));

    // With the module on, both are offered.
    $this->enableModules(['options']);
    $listed = array_column($this->container->get('mcp_tools_structure.field')->getFieldTypes()['types'], 'id');
    $this->assertContains('list_string', $listed);
    $this->assertContains('list_integer', $listed);
  }

  /**
   * A list of numbers is stored with the numbers as its keys.
   */
  public function testListOfNumbers(): void {
    // Enabling a module gives a new container, with new services.
    $this->enableModules(['options']);
    $this->container->get('mcp_tools.access_manager')->setScopes(['read', 'write']);
    $service = $this->container->get('mcp_tools_structure.field');

    $result = $service->addField('node', 'news', 'level', 'list_integer', 'Level', ['allowed_values' => [1, 2, 3]]);
    $this->assertTrue($result['success'], (string) ($result['error'] ?? ''));
    $stored = FieldStorageConfig::load('node.field_level')->getSetting('allowed_values');
    $this->assertSame([1 => '1', 2 => '2', 3 => '3'], $stored);

    // Strings still get a key made from the label.
    $result = $service->addField('node', 'news', 'kind', 'list_string', 'Kind', ['allowed_values' => ['Big one', 'Small']]);
    $this->assertTrue($result['success'], (string) ($result['error'] ?? ''));
    $stored = FieldStorageConfig::load('node.field_kind')->getSetting('allowed_values');
    $this->assertSame(['big_one' => 'Big one', 'small' => 'Small'], $stored);
  }

}
