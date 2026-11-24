<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_migration\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * What the export gives, the import takes back, on a site with other ids.
 *
 * The export was for nodes only, gave references as ids that mean nothing
 * on another site, and the CSV kept the first value of a field only.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_migration')]
final class ExportRoundTripKernelTest extends KernelTestBase {

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
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    Vocabulary::create(['vid' => 'tags', 'name' => 'Tags'])->save();
    FieldStorageConfig::create(['field_name' => 'field_tags', 'entity_type' => 'node', 'type' => 'entity_reference', 'cardinality' => -1, 'settings' => ['target_type' => 'taxonomy_term']])->save();
    FieldConfig::create(['field_name' => 'field_tags', 'entity_type' => 'node', 'bundle' => 'article', 'settings' => ['handler' => 'default:taxonomy_term', 'handler_settings' => ['target_bundles' => ['tags' => 'tags']]]])->save();
    $this->setUpCurrentUser([], [], TRUE);
    $this->container->get('mcp_tools.access_manager')->setScopes(['read', 'write']);
  }

  /**
   * The service.
   */
  private function service(): object {
    return $this->container->get('mcp_tools_migration.migration');
  }

  /**
   * The terms of tags: name => parent name.
   */
  private function tree(): array {
    $tree = [];
    foreach ($this->container->get('entity_type.manager')->getStorage('taxonomy_term')->loadTree('tags', 0, NULL, TRUE) as $term) {
      $parent = (int) ($term->parents[0] ?? 0);
      $tree[$term->label()] = $parent ? Term::load($parent)->label() : '';
    }
    ksort($tree);
    return $tree;
  }

  /**
   * Remove every entity of a type.
   */
  private function wipe(string $type): void {
    $storage = $this->container->get('entity_type.manager')->getStorage($type);
    $storage->delete($storage->loadMultiple());
  }

  /**
   * A term tree: export by name, wipe, import, the same tree.
   */
  public function testATermTreeGoesRoundTrip(): void {
    $fruit = Term::create(['vid' => 'tags', 'name' => 'Fruit']);
    $fruit->save();
    $apple = Term::create(['vid' => 'tags', 'name' => 'Apple', 'parent' => $fruit->id(), 'weight' => 3]);
    $apple->save();
    Term::create(['vid' => 'tags', 'name' => 'Gala', 'parent' => $apple->id()])->save();
    $before = $this->tree();

    foreach (['json', 'csv'] as $format) {
      $export = $format === 'json'
        ? $this->service()->exportToJson('tags', 100, 0, ['entity_type' => 'taxonomy_term', 'references' => 'names'])
        : $this->service()->exportToCsv('tags', 100, 0, ['entity_type' => 'taxonomy_term', 'references' => 'names']);
      $this->assertTrue($export['success'], json_encode($export));
      $this->wipe('taxonomy_term');
      $this->container->get('keyvalue')->get('mcp_tools_migration.import_rows')->deleteAll();
      foreach ($this->container->get('entity_type.manager')->getStorage('migration')->loadMultiple() as $migration) {
        $migration->delete();
      }
      $import = $format === 'json'
        ? $this->service()->importFromJson('tags', $export['data']['items'], '', ['entity_type' => 'taxonomy_term'])
        : $this->service()->importFromCsv('tags', $export['data']['csv_data'], [], '', ['entity_type' => 'taxonomy_term']);
      $this->assertTrue($import['success'], json_encode($import));
      $this->assertSame($before, $this->tree(), "The tree after an export and import as $format.");
      $this->assertSame(3, (int) current(\Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['name' => 'Apple']))->getWeight());
    }
  }

  /**
   * Content with tags by name: export, wipe the content, import, the same.
   */
  public function testContentWithTagsGoesRoundTrip(): void {
    $news = Term::create(['vid' => 'tags', 'name' => 'News']);
    $news->save();
    $sport = Term::create(['vid' => 'tags', 'name' => 'Sport']);
    $sport->save();
    Node::create(['type' => 'article', 'title' => 'Both', 'status' => 1, 'field_tags' => [$news->id(), $sport->id()]])->save();
    Node::create(['type' => 'article', 'title' => 'One', 'status' => 0, 'field_tags' => [$sport->id()]])->save();

    $csv = $this->service()->exportToCsv('article', 100, 0, ['references' => 'names']);
    $this->assertTrue($csv['success'], json_encode($csv));
    $this->assertStringContainsString('News|Sport', $csv['data']['csv_data'], 'The CSV kept the first tag only.');
    $json = $this->service()->exportToJson('article', 100, 0, ['references' => 'names']);
    $this->wipe('node');

    $import = $this->service()->importFromJson('article', $json['data']['items'], 'uuid');
    $this->assertTrue($import['success'], json_encode($import));
    $this->assertSame(2, $import['data']['created_count'], json_encode($import['data']));
    $got = [];
    foreach (Node::loadMultiple() as $node) {
      $got[$node->label()] = [(int) $node->isPublished(), array_map(fn($t) => $t->label(), $node->get('field_tags')->referencedEntities())];
    }
    ksort($got);
    $this->assertSame(['Both' => [1, ['News', 'Sport']], 'One' => [0, ['Sport']]], $got);
  }

  /**
   * An export of a type the import does not take is refused.
   */
  public function testAnUnknownTypeIsRefused(): void {
    $result = $this->service()->exportToJson('x', 10, 0, ['entity_type' => 'user']);
    $this->assertFalse($result['success']);
    $this->assertStringContainsString('node, taxonomy_term, redirect', $result['error']);
  }

  /**
   * A references value that is neither ids nor names is refused.
   *
   * "name" gave ids, which mean nothing on another site, and said success.
   */
  public function testAnUnknownReferencesValueIsRefused(): void {
    $result = $this->service()->exportToJson('article', 10, 0, ['references' => 'name']);
    $this->assertFalse($result['success'], 'references "name" exported ids with success.');
    $this->assertStringContainsString('references is ids or names', $result['error']);
  }

}
