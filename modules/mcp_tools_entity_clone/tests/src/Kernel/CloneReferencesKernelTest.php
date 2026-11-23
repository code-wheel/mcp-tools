<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_entity_clone\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Clone with references: real field names, every clone counted.
 *
 * A wrong field name was skipped and the clone shared the original's
 * references, and the write limit counted one for all the clones.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_entity_clone')]
final class CloneReferencesKernelTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'field', 'text', 'filter', 'node', 'taxonomy', 'entity_clone', 'dblog', 'update', 'tool', 'mcp_tools', 'mcp_tools_entity_clone'];

  /**
   * A typo is refused; the real field clones its term; the limit counts two.
   */
  public function testReferencesAreClonedAndCounted(): void {
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('dblog', ['watchdog']);
    $this->installConfig(['system', 'filter', 'node', 'mcp_tools']);
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    Vocabulary::create(['vid' => 'tags', 'name' => 'Tags'])->save();
    FieldStorageConfig::create(['field_name' => 'field_tags', 'entity_type' => 'node', 'type' => 'entity_reference', 'settings' => ['target_type' => 'taxonomy_term']])->save();
    FieldConfig::create(['field_name' => 'field_tags', 'entity_type' => 'node', 'bundle' => 'article', 'label' => 'Tags'])->save();
    $this->setUpCurrentUser([], [], TRUE);
    $this->container->get('mcp_tools.access_manager')->setScopes(['read', 'write']);
    $term = Term::create(['vid' => 'tags', 'name' => 'Original tag']);
    $term->save();
    $node = Node::create(['type' => 'article', 'title' => 'Source', 'field_tags' => [$term->id()]]);
    $node->save();
    $service = $this->container->get('mcp_tools_entity_clone.entity_clone');

    $typo = $service->cloneWithReferences('node', $node->id(), ['field_tagz']);
    $this->assertFalse($typo['success'], 'A field name that is not there was skipped: ' . json_encode($typo));
    $this->assertStringContainsString('field_tags', $typo['error']);

    $this->config('mcp_tools.settings')->set('rate_limiting.enabled', TRUE)->set('rate_limiting.max_writes_per_minute', 1)->save();
    $this->container->get('mcp_tools.rate_limiter')->resetLimits();
    $this->assertFalse($service->cloneWithReferences('node', $node->id(), ['field_tags'])['success'], 'Two clones went through a limit of one write.');
    $this->config('mcp_tools.settings')->set('rate_limiting.enabled', FALSE)->save();

    $done = $service->cloneWithReferences('node', $node->id(), ['field_tags']);
    $this->assertTrue($done['success'], (string) ($done['error'] ?? ''));
  }

}
