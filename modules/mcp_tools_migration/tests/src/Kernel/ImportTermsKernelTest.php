<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_migration\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Terms go through the same import as content.
 *
 * The batch term tool made terms one by one with a parent by id only, so a
 * tree took one call a level, and nothing could undo it.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_migration')]
final class ImportTermsKernelTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system', 'user', 'field', 'text', 'filter', 'node', 'taxonomy', 'dblog', 'update',
    'migrate', 'migrate_plus', 'tool', 'mcp_tools', 'mcp_tools_migration',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('dblog', ['watchdog']);
    $this->installConfig(['system', 'filter', 'node', 'taxonomy', 'mcp_tools']);
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
    Vocabulary::create(['vid' => 'food', 'name' => 'Food'])->save();
    $this->setUpCurrentUser([], [], TRUE);
    $this->container->get('mcp_tools.access_manager')->setScopes(['read', 'write']);
  }

  /**
   * The import service.
   */
  private function import(): object {
    return $this->container->get('mcp_tools_migration.migration');
  }

  /**
   * The terms of the vocabulary: name => parent name ('' for the top).
   *
   * @return array<string, string>
   *   The tree.
   */
  private function tree(): array {
    $tree = [];
    foreach ($this->container->get('entity_type.manager')->getStorage('taxonomy_term')->loadTree('food', 0, NULL, TRUE) as $term) {
      $parent = (int) ($term->parents[0] ?? 0);
      $tree[$term->label()] = $parent ? Term::load($parent)->label() : '';
    }
    ksort($tree);
    return $tree;
  }

  /**
   * A tree in one call, the children given before their parent.
   */
  public function testATermTreeInOneCall(): void {
    $result = $this->import()->importFromJson('food', [
      ['name' => 'Apple', 'parent' => 'Fruit'],
      ['name' => 'Fruit'],
      ['name' => 'Gala', 'parent' => 'Apple'],
      ['name' => 'Pear', 'parent' => 'Fruit'],
    ], '', ['entity_type' => 'taxonomy_term']);
    $this->assertTrue($result['success'], json_encode($result));
    $this->assertSame(4, $result['data']['created_count'], json_encode($result['data']));
    $this->assertSame(['Apple' => 'Fruit', 'Fruit' => '', 'Gala' => 'Apple', 'Pear' => 'Fruit'], $this->tree());
  }

  /**
   * A term that was there is kept, and a child can hang from it.
   */
  public function testATermThatWasThereIsKept(): void {
    $fruit = Term::create(['vid' => 'food', 'name' => 'Fruit', 'weight' => 7]);
    $fruit->save();
    $result = $this->import()->importFromJson('food', [
      ['name' => 'Fruit', 'weight' => 1],
      ['name' => 'Apple', 'parent' => 'Fruit'],
    ], '', ['entity_type' => 'taxonomy_term']);
    $this->assertTrue($result['success'], json_encode($result));
    $this->assertSame(1, $result['data']['created_count'], json_encode($result['data']));
    $this->assertSame([(string) $fruit->id()], array_map('strval', array_column($result['data']['existing'] ?? [], 'id')));
    $this->assertSame(7, (int) Term::load($fruit->id())->getWeight(), 'A term made by hand was changed.');
    $this->assertSame(['Apple' => 'Fruit', 'Fruit' => ''], $this->tree());

    // Run again: nothing new, and undo leaves the hand-made term.
    $again = $this->import()->importFromJson('food', [['name' => 'Fruit'], ['name' => 'Apple', 'parent' => 'Fruit']], '', ['entity_type' => 'taxonomy_term']);
    $this->assertSame(0, $again['data']['created_count'], json_encode($again['data']));
    $this->assertTrue($this->import()->rollbackImport($result['data']['import_id'])['success']);
    $this->assertSame(['Fruit' => ''], $this->tree());
  }

  /**
   * A parent that is no row and no term refuses its row.
   */
  public function testAParentThatIsNowhere(): void {
    $result = $this->import()->importFromJson('food', [['name' => 'Apple', 'parent' => 'Fruit']], '', ['entity_type' => 'taxonomy_term']);
    $this->assertSame(1, $result['data']['failed_count'] ?? NULL, json_encode($result));
    $this->assertStringContainsString('Fruit', json_encode($result['data']['failed']));
    $this->assertSame([], $this->tree());
  }

}
