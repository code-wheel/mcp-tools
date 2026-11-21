<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_views\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\views\Entity\View;
use Drupal\views\Plugin\views\field\Broken;

/**
 * A view the tools create is a view Views can run.
 *
 * Creating a view only wrote config, and no test ever ran one. So the sort
 * values the tools document were not understood, a view of terms broke on
 * its first query, and a field such as "body" gave a view that could not be
 * shown.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_views')]
final class ViewCreationKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'field',
    'text',
    'filter',
    'taxonomy',
    'views',
    'dblog',
    'update',
    'tool',
    'mcp_tools',
    'mcp_tools_views',
  ];

  /**
   * The views service under test.
   *
   * @var \Drupal\mcp_tools_views\Service\ViewsService
   */
  private $service;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('dblog', ['watchdog']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');
    $this->installConfig(['system', 'mcp_tools']);
    $this->container->get('mcp_tools.access_manager')->setScopes(['read', 'write']);
    $this->service = $this->container->get('mcp_tools_views.views');
  }

  /**
   * The sort of the default display, as "field ORDER".
   */
  private function storedSort(string $viewId): string {
    $sorts = View::load($viewId)->getDisplay('default')['display_options']['sorts'] ?? [];
    $parts = [];
    foreach ($sorts as $sort) {
      $parts[] = $sort['table'] . '.' . $sort['field'] . ' ' . $sort['order'];
    }
    return implode(', ', $parts);
  }

  /**
   * Runs every display of a view and returns the field handlers it used.
   */
  private function runView(string $viewId): array {
    $handlers = [];
    foreach (array_keys(View::load($viewId)->get('display')) as $displayId) {
      $executable = View::load($viewId)->getExecutable();
      $executable->setDisplay($displayId);
      $executable->execute();
      $handlers = $executable->field;
    }
    return $handlers;
  }

  /**
   * The four sort values the tools document do what they say.
   */
  public function testDocumentedSortValues(): void {
    $expected = [
      'newest' => 'node_field_data.created DESC',
      'oldest' => 'node_field_data.created ASC',
      'title' => 'node_field_data.title ASC',
      'changed' => 'node_field_data.changed DESC',
    ];
    foreach ($expected as $sort => $stored) {
      $result = $this->service->createView('sort_' . $sort, 'Sort ' . $sort, 'node_field_data', ['sort' => $sort]);
      $this->assertTrue($result['success'], (string) ($result['error'] ?? ''));
      $this->assertSame($stored, $this->storedSort('sort_' . $sort), $sort);
      $this->runView('sort_' . $sort);
    }
  }

  /**
   * A sort value nobody knows is refused, not replaced by another.
   */
  public function testUnknownSortIsRefused(): void {
    $result = $this->service->createView('sort_nope', 'Sort nope', 'node_field_data', ['sort' => 'sideways']);
    $this->assertFalse($result['success']);
    $this->assertStringContainsString('sideways', $result['error']);
    $this->assertNull(View::load('sort_nope'));
  }

  /**
   * A view of terms or users runs, with the sort its table can do.
   */
  public function testOtherBaseTablesRun(): void {
    // Terms have no "created" column. Asking for nothing gives a view
    // without a sort on it, not a broken query.
    $result = $this->service->createView('terms_plain', 'Terms', 'taxonomy_term_field_data', ['page_path' => '/terms-plain']);
    $this->assertTrue($result['success'], (string) ($result['error'] ?? ''));
    $this->assertSame('', $this->storedSort('terms_plain'));
    $this->runView('terms_plain');

    // "title" means the name of a term or a user.
    $result = $this->service->createView('terms_named', 'Terms by name', 'taxonomy_term_field_data', ['sort' => 'title']);
    $this->assertTrue($result['success'], (string) ($result['error'] ?? ''));
    $this->assertSame('taxonomy_term_field_data.name ASC', $this->storedSort('terms_named'));
    $this->runView('terms_named');

    $result = $this->service->createView('users_named', 'Users by name', 'users_field_data', ['sort' => 'title']);
    $this->assertTrue($result['success'], (string) ($result['error'] ?? ''));
    $this->assertSame('users_field_data.name ASC', $this->storedSort('users_named'));
    $this->runView('users_named');

    // Asking terms for "newest" cannot work and says so.
    $result = $this->service->createView('terms_newest', 'Terms newest', 'taxonomy_term_field_data', ['sort' => 'newest']);
    $this->assertFalse($result['success']);
    $this->assertStringContainsString('created', $result['error']);
    $this->assertNull(View::load('terms_newest'));
  }

  /**
   * A field of the content type can be shown, an unknown one is refused.
   */
  public function testFieldsOfAContentType(): void {
    NodeType::create(['type' => 'news', 'name' => 'News'])->save();
    FieldStorageConfig::create(['field_name' => 'field_note', 'entity_type' => 'node', 'type' => 'string'])->save();
    FieldConfig::create(['field_name' => 'field_note', 'entity_type' => 'node', 'bundle' => 'news', 'label' => 'Note'])->save();

    $result = $this->service->createContentListView('news_list', 'News list', 'news', ['fields' => ['title', 'field_note']]);
    $this->assertTrue($result['success'], (string) ($result['error'] ?? ''));

    $handlers = $this->runView('news_list');
    $this->assertArrayHasKey('field_note', $handlers);
    foreach ($handlers as $id => $handler) {
      $this->assertNotInstanceOf(Broken::class, $handler, $id . ' has no handler');
    }
    $fields = View::load('news_list')->getDisplay('default')['display_options']['fields'];
    $this->assertSame('node__field_note', $fields['field_note']['table']);
    $this->assertSame('node_field_data', $fields['title']['table']);

    $result = $this->service->createContentListView('news_bad', 'News bad', 'news', ['fields' => ['title', 'field_nope']]);
    $this->assertFalse($result['success']);
    $this->assertStringContainsString('field_nope', $result['error']);
    $this->assertNull(View::load('news_bad'));
  }

  /**
   * A content list leads with a linked title, unless that is switched off.
   *
   * The tool has a "show title" input and says the title is always
   * included. Neither was true: the input did nothing, and a field list
   * without the title gave a list without it.
   */
  public function testTitleOfAContentList(): void {
    NodeType::create(['type' => 'news', 'name' => 'News'])->save();

    $result = $this->service->createContentListView('with_title', 'With title', 'news', ['fields' => ['created']]);
    $this->assertTrue($result['success'], (string) ($result['error'] ?? ''));
    $fields = View::load('with_title')->getDisplay('default')['display_options']['fields'];
    $this->assertSame(['title', 'created'], array_keys($fields));
    $this->assertTrue($fields['title']['settings']['link_to_entity'] ?? FALSE);
    $this->runView('with_title');

    $result = $this->service->createContentListView('no_title', 'No title', 'news', ['show_title' => FALSE]);
    $this->assertTrue($result['success'], (string) ($result['error'] ?? ''));
    $fields = View::load('no_title')->getDisplay('default')['display_options']['fields'];
    $this->assertSame(['created'], array_keys($fields));
    $this->runView('no_title');
  }

  /**
   * A base table Views has no data for is refused.
   */
  public function testBaseTableWithoutItsModule(): void {
    // The comment module is not installed here.
    $result = $this->service->createView('comments', 'Comments', 'comment_field_data');
    $this->assertFalse($result['success']);
    $this->assertStringContainsString('comment_field_data', $result['error']);
    $this->assertNull(View::load('comments'));
  }

}
