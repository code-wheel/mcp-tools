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
 * The import runs on Drupal's Migrate API.
 *
 * Each test was a fault of the old loop, seen on a real site on
 * 2026-11-22 (INTERNAL_PLANNING, "Batch and migration: deep dive"). Each
 * one answered success.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_migration')]
final class ImportOnMigrateKernelTest extends KernelTestBase {

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
    $this->installConfig(['system', 'filter', 'node', 'mcp_tools']);
    $type = NodeType::create(['type' => 'article', 'name' => 'Article']);
    $type->save();
    // The body field a content type made in the UI has.
    // Drupal 10's node module ships the storage; 11 does not.
    if (!FieldStorageConfig::loadByName('node', 'body')) {
      FieldStorageConfig::create(['field_name' => 'body', 'entity_type' => 'node', 'type' => 'text_with_summary'])->save();
    }
    FieldConfig::create(['field_name' => 'body', 'entity_type' => 'node', 'bundle' => 'article', 'label' => 'Body'])->save();
    Vocabulary::create(['vid' => 'tags', 'name' => 'Tags'])->save();
    FieldStorageConfig::create([
      'field_name' => 'field_tags',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'cardinality' => -1,
      'settings' => ['target_type' => 'taxonomy_term'],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_tags',
      'entity_type' => 'node',
      'bundle' => 'article',
      'settings' => ['handler' => 'default:taxonomy_term', 'handler_settings' => ['target_bundles' => ['tags' => 'tags']]],
    ])->save();
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
   * The titles of the articles, in id order.
   *
   * @return string[]
   *   The titles.
   */
  private function titles(): array {
    $ids = $this->container->get('entity_type.manager')->getStorage('node')->getQuery()->accessCheck(FALSE)->sort('nid')->execute();
    return array_values(array_map(fn(Node $n) => $n->label(), Node::loadMultiple($ids)));
  }

  /**
   * An import that names no key is refused: a second run would duplicate.
   */
  public function testTheKeyIsRequired(): void {
    $result = $this->import()->importFromJson('article', [['title' => 'One']]);
    $this->assertFalse($result['success'], 'An import without a key went through.');
    $this->assertStringContainsString('key', $result['error']);
    $this->assertSame([], $this->titles());
  }

  /**
   * A quoted comma is part of the value, and a quoted newline too.
   */
  public function testQuotedCommasAndNewlinesSurvive(): void {
    $csv = "id,title\n1,\"Hello, world\"\n2,\"Two\nlines\"\n";
    $result = $this->import()->importFromCsv('article', $csv, [], 'id');
    $this->assertTrue($result['success'], json_encode($result));
    $this->assertSame(['Hello, world', "Two\nlines"], $this->titles());
  }

  /**
   * The key may name a column as the CSV has it, with a mapping that renames it.
   *
   * Every row failed with "no value for the key", and the call answered
   * success with nothing made. A key no row has is refused, with the
   * columns there are.
   */
  public function testTheKeyFollowsTheMapping(): void {
    $csv = "Source ID,Headline\n7,Seven\n8,Eight\n";
    $mapping = ['Source ID' => 'source_id', 'Headline' => 'title'];
    $result = $this->import()->importFromCsv('article', $csv, $mapping, 'Source ID');
    $this->assertTrue($result['success'], json_encode($result));
    $this->assertSame(2, $result['data']['created_count'], json_encode($result['data']));

    // The mapped name is the same import: a second run updates.
    $result = $this->import()->importFromCsv('article', "Source ID,Headline\n7,Seven again\n", $mapping, 'source_id');
    $this->assertTrue($result['success'], json_encode($result));
    $this->assertSame(0, $result['data']['created_count'], json_encode($result['data']));
    $this->assertSame(['Seven again', 'Eight'], $this->titles());

    $result = $this->import()->importFromCsv('article', $csv, $mapping, 'nid');
    $this->assertFalse($result['success'], 'A key no row has answered success.');
    $this->assertStringContainsString('source_id, title', $result['error']);
  }

  /**
   * A yes or no in a CSV is what it says.
   *
   * The import cast "false" and "no" to TRUE: a CSV that said not to
   * promote a page promoted it.
   */
  public function testANoInACsvIsNo(): void {
    $csv = "id,title,promote\n1,One,false\n2,Two,no\n3,Three,true\n";
    $result = $this->import()->importFromCsv('article', $csv, [], 'id');
    $this->assertTrue($result['success'], json_encode($result));
    $promoted = [];
    foreach (Node::loadMultiple() as $node) {
      $promoted[$node->label()] = (bool) $node->get('promote')->value;
    }
    $this->assertSame(['One' => FALSE, 'Two' => FALSE, 'Three' => TRUE], $promoted);
  }

  /**
   * A row with the wrong number of columns is an error in the answer.
   */
  public function testAShortRowIsReported(): void {
    $csv = "id,title,body\n1,Full,Text\n2,Short\n";
    $result = $this->import()->importFromCsv('article', $csv, [], 'id');
    $this->assertSame(1, $result['data']['failed_count'] ?? NULL, json_encode($result));
    $this->assertStringContainsString('row 2', strtolower(json_encode($result['data']['failed'])));
    $this->assertSame(['Full'], $this->titles());
  }

  /**
   * A field the content type does not have refuses the import.
   */
  public function testAnUnknownFieldIsRefused(): void {
    $result = $this->import()->importFromJson('article', [['id' => '1', 'title' => 'One', 'field_nope' => 'x']], 'id');
    $this->assertFalse($result['success'], 'A field the type does not have was dropped without a word.');
    $this->assertStringContainsString('field_nope', $result['error']);
    $this->assertSame([], $this->titles());
  }

  /**
   * The same import twice updates what it made the first time.
   */
  public function testARunTwiceMakesNoDuplicates(): void {
    $rows = [['id' => 'a', 'title' => 'First'], ['id' => 'b', 'title' => 'Second']];
    $this->assertTrue($this->import()->importFromJson('article', $rows, 'id')['success']);
    $rows[1]['title'] = 'Second, changed';
    $again = $this->import()->importFromJson('article', $rows, 'id');
    $this->assertTrue($again['success'], json_encode($again));
    $this->assertSame(['First', 'Second, changed'], $this->titles());
    $this->assertSame(1, $again['data']['updated_count'] ?? NULL, json_encode($again['data']));
  }

  /**
   * Tags by name, and "|" for several.
   */
  public function testTagsByNameAndSeveralValues(): void {
    $news = Term::create(['vid' => 'tags', 'name' => 'News']);
    $news->save();
    $sport = Term::create(['vid' => 'tags', 'name' => 'Sport']);
    $sport->save();
    $result = $this->import()->importFromJson('article', [
      ['id' => '1', 'title' => 'Both', 'field_tags' => 'News|Sport'],
      ['id' => '2', 'title' => 'Unknown tag', 'field_tags' => 'Weather'],
    ], 'id');
    $this->assertSame(1, $result['data']['failed_count'] ?? NULL, json_encode($result));
    $this->assertStringContainsString('Weather', json_encode($result['data']['failed']));
    $node = Node::load(array_key_first(Node::loadMultiple()));
    $this->assertSame([(int) $news->id(), (int) $sport->id()], array_map('intval', array_column($node->get('field_tags')->getValue(), 'target_id')));
  }

  /**
   * The export reaches every node, and its rows import back as updates.
   */
  public function testExportReachesEverythingAndRoundTrips(): void {
    foreach (range(1, 105) as $i) {
      Node::create(['type' => 'article', 'title' => "Node $i"])->save();
    }
    $second = $this->import()->exportToJson('article', 100, 100);
    $this->assertSame(['Node 101', 'Node 102', 'Node 103', 'Node 104', 'Node 105'], array_column($second['data']['items'] ?? [], 'title'), 'The export stopped at 100.');
    $rows = $second['data']['items'];
    $rows[0]['title'] = 'Node 101, changed';
    $result = $this->import()->importFromJson('article', $rows, 'uuid');
    $this->assertTrue($result['success'], json_encode($result));
    $this->assertCount(105, $this->titles(), 'The round trip made copies.');
    $this->assertContains('Node 101, changed', $this->titles());
  }

  /**
   * Undo removes what one import made, and nothing else.
   */
  public function testRollbackUndoesOneImport(): void {
    Node::create(['type' => 'article', 'title' => 'Made by hand'])->save();
    $first = $this->import()->importFromJson('article', [['id' => '1', 'title' => 'Imported']], 'id');
    $this->assertTrue($first['success'], json_encode($first));
    $undo = $this->import()->rollbackImport($first['data']['import_id']);
    $this->assertTrue($undo['success'], json_encode($undo));
    $this->assertSame(['Made by hand'], $this->titles());
  }

  /**
   * Undo after a second run that updated a row still deletes that row.
   *
   * Drupal's entity destination marked an updated row to be kept on undo
   * (with overwrite_properties it updates a copy of the row without its id
   * map), so the undo left it.
   */
  public function testUndoAfterARerun(): void {
    $rows = [['id' => '1', 'title' => 'One'], ['id' => '2', 'title' => 'Two']];
    $first = $this->import()->importFromJson('article', $rows, 'id');
    $rows[0]['title'] = 'One, changed';
    $this->assertSame(1, $this->import()->importFromJson('article', $rows, 'id')['data']['updated_count']);
    $this->assertTrue($this->import()->rollbackImport($first['data']['import_id'])['success']);
    $this->assertSame([], $this->titles(), 'The undo left a node the import made.');
  }

  /**
   * A row whose node was deleted by hand is made again on the next run.
   *
   * Migrate's record still said the row was imported and unchanged, so the
   * run skipped it and answered success with nothing made.
   */
  public function testARowWhoseNodeWasDeletedIsMadeAgain(): void {
    $rows = [['id' => '1', 'title' => 'Kept'], ['id' => '2', 'title' => 'Deleted by hand']];
    $this->import()->importFromJson('article', $rows, 'id');
    foreach (Node::loadMultiple() as $node) {
      if ($node->label() === 'Deleted by hand') {
        $node->delete();
      }
    }
    $again = $this->import()->importFromJson('article', $rows, 'id');
    $this->assertTrue($again['success'], json_encode($again));
    $this->assertSame(['Kept', 'Deleted by hand'], $this->titles(), 'The deleted node was not made again.');
    $this->assertSame(1, $again['data']['created_count'] ?? NULL, json_encode($again['data']));
  }

  /**
   * A plan says what each row would do, and writes nothing.
   */
  public function testAPlanWritesNothing(): void {
    $this->import()->importFromJson('article', [['id' => '1', 'title' => 'There']], 'id');
    $plan = $this->import()->importFromJson('article', [
      ['id' => '1', 'title' => 'There, changed'],
      ['id' => '2', 'title' => 'New'],
    ], 'id', ['dry_run' => TRUE]);
    $this->assertTrue($plan['success'], json_encode($plan));
    $this->assertSame(['update', 'create'], array_column($plan['data']['plan'] ?? [], 'action'));
    $this->assertSame(['There'], $this->titles());
  }

  /**
   * A big import is done in parts, and says what is left.
   */
  public function testABigImportEndsInParts(): void {
    $rows = array_map(fn($i) => ['id' => (string) $i, 'title' => "Row $i"], range(1, 250));
    $calls = 0;
    do {
      $result = $this->import()->importFromJson('article', $rows, 'id');
      $this->assertTrue($result['success'], json_encode($result));
      $calls++;
    } while (empty($result['data']['done']) && $calls < 5);
    $this->assertSame(3, $calls, 'A run of 250 rows did not end in three parts of 100.');
    $this->assertSame(0, $result['data']['remaining']);
    $this->assertCount(250, $this->titles());
  }

}
