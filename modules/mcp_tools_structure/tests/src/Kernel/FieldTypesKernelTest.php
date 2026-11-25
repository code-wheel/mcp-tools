<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_structure\Kernel;

use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mcp_tools\Service\FieldInput;
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

  /**
   * Options given as {value, label} items are stored as options.
   *
   * A model sends the options of a list field this way. They were stored
   * as one option called "Array", and the call said success.
   */
  public function testOptionsGivenAsItems(): void {
    $this->enableModules(['options']);
    $this->container->get('mcp_tools.access_manager')->setScopes(['read', 'write']);
    $service = $this->container->get('mcp_tools_structure.field');

    $result = $service->addField('node', 'news', 'stage', 'list_string', 'Stage', [
      'allowed_values' => [['value' => 'd', 'label' => 'Draft'], ['value' => 'f', 'label' => 'Final']],
    ]);
    $this->assertTrue($result['success'], (string) ($result['error'] ?? ''));
    $this->assertSame(['d' => 'Draft', 'f' => 'Final'], FieldStorageConfig::load('node.field_stage')->getSetting('allowed_values'));

    $result = $service->addField('node', 'news', 'rank', 'list_integer', 'Rank', [
      'allowed_values' => [['value' => 1, 'label' => 'One'], ['value' => 2, 'label' => 'Two']],
    ]);
    $this->assertTrue($result['success'], (string) ($result['error'] ?? ''));
    $this->assertSame([1 => 'One', 2 => 'Two'], FieldStorageConfig::load('node.field_rank')->getSetting('allowed_values'));

    // A label alone is as a plain label, a value alone is its own label.
    $result = $service->addField('node', 'news', 'size', 'list_string', 'Size', [
      'allowed_values' => [['label' => 'Big one'], ['value' => 'small']],
    ]);
    $this->assertTrue($result['success'], (string) ($result['error'] ?? ''));
    $this->assertSame(['big_one' => 'Big one', 'small' => 'small'], FieldStorageConfig::load('node.field_size')->getSetting('allowed_values'));

    // An item that is no option is not skipped: the call is refused and
    // nothing is made.
    $result = $service->addField('node', 'news', 'odd', 'list_string', 'Odd', [
      'allowed_values' => ['Fine', ['name' => 'No value here']],
    ]);
    $this->assertFalse($result['success'], 'An option without a value or a label was taken.');
    $this->assertStringContainsString('allowed_values', $result['error']);
    $this->assertNull(FieldStorageConfig::load('node.field_odd'));
  }

  /**
   * Options on a type that has none are not dropped without a word.
   *
   * "integer" with allowed_values made a plain number field and the call
   * said success. A text or a whole number with options can only be meant
   * as a list, so that is made, and the answer says so. Any other type is
   * refused.
   */
  public function testOptionsOnATypeWithoutOptions(): void {
    $this->enableModules(['options']);
    $this->container->get('mcp_tools.access_manager')->setScopes(['read', 'write']);
    $service = $this->container->get('mcp_tools_structure.field');

    $result = $service->addField('node', 'news', 'stars', 'integer', 'Stars', ['allowed_values' => [1, 2, 3]]);
    $this->assertTrue($result['success'], (string) ($result['error'] ?? ''));
    $storage = FieldStorageConfig::load('node.field_stars');
    $this->assertSame('list_integer', $storage->getType());
    $this->assertSame([1 => '1', 2 => '2', 3 => '3'], $storage->getSetting('allowed_values'));
    $this->assertSame('list_integer', $result['data']['field_type']);
    $this->assertStringContainsString('list_integer', $result['data']['message']);

    $result = $service->addField('node', 'news', 'mood', 'string', 'Mood', ['allowed_values' => ['Calm', 'Loud']]);
    $this->assertTrue($result['success'], (string) ($result['error'] ?? ''));
    $storage = FieldStorageConfig::load('node.field_mood');
    $this->assertSame('list_string', $storage->getType());
    $this->assertSame(['calm' => 'Calm', 'loud' => 'Loud'], $storage->getSetting('allowed_values'));

    // A yes/no field with options cannot be meant as a list.
    $result = $service->addField('node', 'news', 'flag', 'boolean', 'Flag', ['allowed_values' => ['Yes', 'No']]);
    $this->assertFalse($result['success'], 'Options on a yes/no field were dropped without a word.');
    $this->assertStringContainsString('list_string', $result['error']);
    $this->assertNull(FieldStorageConfig::load('node.field_flag'));

    // Without options a plain type stays what it is.
    $result = $service->addField('node', 'news', 'count', 'integer', 'Count');
    $this->assertTrue($result['success'], (string) ($result['error'] ?? ''));
    $this->assertSame('integer', FieldStorageConfig::load('node.field_count')->getType());
  }

  /**
   * The two tools that take a field type name every type there is.
   *
   * The text of add_field named 6 of the 19. A model picked text_long where
   * plain long text was asked for, and did not know list_integer exists.
   */
  public function testTheToolsNameEveryFieldType(): void {
    $types = array_keys(FieldInput::TYPES);
    $this->assertCount(20, $types);

    $tools = $this->container->get('plugin.manager.tool');
    $texts = [
      'mcp_structure_add_field field_type' => (string) $tools->getDefinition('mcp_structure_add_field')->getInputDefinition('field_type')->getDescription(),
      'mcp_structure_scaffold_content_type fields' => (string) $tools->getDefinition('mcp_structure_scaffold_content_type')->getInputDefinition('fields')->getDescription(),
    ];
    foreach ($texts as $where => $text) {
      $missing = array_values(array_filter($types, static fn(string $type): bool => !preg_match('/(?<![a-z_])' . $type . '(?![a-z_])/', $text)));
      $this->assertSame([], $missing, $where . ' does not name: ' . implode(', ', $missing));
    }
  }

  /**
   * A bundle that is not there is refused with the ones there are.
   *
   * Drupal gives the base fields of any bundle name, so the check never
   * fired: the answer was "Missing bundle entity, entity type node_type,
   * entity id ..." or "Getting the base fields is not supported ...".
   */
  public function testAnUnknownBundleNamesTheOnesThereAre(): void {
    $service = $this->container->get('mcp_tools_structure.field');

    $unknown = $service->addField('node', 'no_such_type', 'subtitle', 'string', 'Subtitle');
    $this->assertFalse($unknown['success']);
    $this->assertStringContainsString("Bundle 'no_such_type' does not exist for entity type 'node'", $unknown['error']);
    $this->assertStringContainsString('It has: news', $unknown['error']);

    $config = $service->addField('node_type', 'news', 'subtitle', 'string', 'Subtitle');
    $this->assertFalse($config['success']);
    $this->assertStringContainsString("entity_type 'node' and bundle 'news'", $config['error']);

    $this->assertTrue($service->addField('node', 'news', 'subtitle', 'string', 'Subtitle')['success']);
    $user = $service->addField('user', 'user', 'nickname', 'string', 'Nickname');
    $this->assertTrue($user['success'], (string) ($user['error'] ?? ''));
  }

}
