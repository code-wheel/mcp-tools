<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_structure\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;

/**
 * The bundles a reference field may point to.
 *
 * A bundle that does not exist, or a label in place of a machine name, was
 * stored as a target, and the call said success. The field then offered
 * nothing to refer to.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_structure')]
final class TargetBundlesKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'field', 'text', 'filter', 'node', 'dblog', 'update', 'tool', 'mcp_tools', 'mcp_tools_structure'];

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
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
    $this->container->get('mcp_tools.access_manager')->setScopes(['read', 'write']);
  }

  /**
   * A bundle that is not there is refused; real ones in any shape are taken.
   */
  public function testOnlyBundlesThatExist(): void {
    $service = $this->container->get('mcp_tools_structure.field');

    $refused = $service->addField('node', 'news', 'related', 'entity_reference', 'Related', ['target_type' => 'node', 'target_bundles' => ['News']]);
    $this->assertFalse($refused['success'], 'A target bundle that does not exist was stored.');
    $this->assertStringContainsString('news, page', $refused['error']);
    $this->assertNull(FieldConfig::loadByName('node', 'news', 'field_related'), 'A refused field was made all the same.');

    $made = $service->addField('node', 'news', 'related', 'entity_reference', 'Related', ['target_type' => 'node', 'target_bundles' => 'page']);
    $this->assertTrue($made['success'], (string) ($made['error'] ?? ''));
    $this->assertSame(['page' => 'page'], FieldConfig::loadByName('node', 'news', 'field_related')->getSetting('handler_settings')['target_bundles']);

    $made = $service->addField('node', 'page', 'see_also', 'entity_reference', 'See also', ['target_type' => 'node', 'target_bundles' => ['news' => 'news', 'page' => 'page']]);
    $this->assertTrue($made['success'], (string) ($made['error'] ?? ''));
  }

}
