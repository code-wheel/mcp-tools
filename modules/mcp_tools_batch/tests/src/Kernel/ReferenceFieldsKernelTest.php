<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_batch\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * A reference to something that is not there is refused, on every way in.
 *
 * The content and batch tools saved without Drupal's validation: a tag
 * given by name, or by an id that is not there, was saved with "success"
 * and the page showed no tag.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_batch')]
final class ReferenceFieldsKernelTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'field', 'text', 'filter', 'node', 'taxonomy', 'dblog', 'update', 'tool', 'mcp_tools', 'mcp_tools_content', 'mcp_tools_batch'];

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
    $this->installConfig(['system', 'field', 'filter', 'node', 'mcp_tools']);
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    Vocabulary::create(['vid' => 'tags', 'name' => 'Tags'])->save();
    Vocabulary::create(['vid' => 'other', 'name' => 'Other'])->save();
    Term::create(['vid' => 'tags', 'name' => 'Technology'])->save();
    Term::create(['vid' => 'other', 'name' => 'Elsewhere'])->save();
    FieldStorageConfig::create(['field_name' => 'field_tags', 'entity_type' => 'node', 'type' => 'entity_reference', 'settings' => ['target_type' => 'taxonomy_term'], 'cardinality' => -1])->save();
    FieldConfig::create(['field_name' => 'field_tags', 'entity_type' => 'node', 'bundle' => 'article', 'label' => 'Tags', 'settings' => ['handler' => 'default:taxonomy_term', 'handler_settings' => ['target_bundles' => ['tags' => 'tags']]]])->save();
    $this->setUpCurrentUser([], [], TRUE);
    $this->container->get('mcp_tools.access_manager')->setScopes(['read', 'write']);
  }

  /**
   * The nodes there are.
   */
  private function nodeCount(): int {
    return count($this->container->get('entity_type.manager')->getStorage('node')->loadMultiple());
  }

  /**
   * Create and update, one at a time and in a batch.
   */
  public function testAReferenceToNothingIsRefused(): void {
    $content = $this->container->get('mcp_tools_content.content');
    $batch = $this->container->get('mcp_tools_batch.batch');

    $ok = $content->createContent('article', 'Fine', ['field_tags' => [1]]);
    $this->assertTrue($ok['success'], (string) ($ok['error'] ?? ''));

    foreach ([999, 'Technology', 2] as $given) {
      $before = $this->nodeCount();
      $result = $content->createContent('article', 'Wrong', ['field_tags' => [$given]]);
      $this->assertFalse($result['success'], "A tag given as '$given' was saved.");
      $this->assertStringContainsString('field_tags', $result['error']);
      $this->assertSame($before, $this->nodeCount(), 'Something was saved all the same.');
    }
    $this->assertStringContainsString('not its name', $content->createContent('article', 'Wrong', ['field_tags' => ['Technology']])['error']);

    $nid = (int) $ok['data']['nid'];
    $result = $content->updateContent($nid, ['field_tags' => [999]]);
    $this->assertFalse($result['success'], 'An update to a tag that is not there was saved.');
    $this->container->get('entity_type.manager')->getStorage('node')->resetCache([$nid]);
    $this->assertSame('1', (string) Node::load($nid)->get('field_tags')->target_id);

    $made = $batch->createMultipleContent('article', [
      ['title' => 'Batch fine', 'fields' => ['field_tags' => [1]]],
      ['title' => 'Batch wrong', 'fields' => ['field_tags' => [999]]],
    ]);
    $this->assertSame(1, $made['data']['created_count'] ?? NULL, json_encode($made));
    $this->assertStringContainsString('field_tags', $made['data']['errors'][0]['error'] ?? '');

    $updated = $batch->updateMultipleContent([['id' => $nid, 'fields' => ['field_tags' => ['Technology']]]]);
    // When no item could be done, the batch answers with the first error.
    $this->assertFalse($updated['success']);
    $this->assertStringContainsString('not its name', $updated['error'] ?? '', json_encode($updated));
  }

}
