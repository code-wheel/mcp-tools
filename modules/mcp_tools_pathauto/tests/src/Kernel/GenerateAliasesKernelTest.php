<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_pathauto\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\pathauto\Entity\PathautoPattern;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Alias generation goes through every entity, a part a call.
 *
 * It took the first 500 in no order every time: on a bigger site the rest
 * never got an alias, and each call said "processed 500".
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_pathauto')]
final class GenerateAliasesKernelTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'field', 'text', 'filter', 'node', 'path', 'path_alias', 'token', 'ctools', 'pathauto', 'dblog', 'update', 'tool', 'mcp_tools', 'mcp_tools_pathauto'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('path_alias');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('dblog', ['watchdog']);
    $this->installConfig(['system', 'filter', 'node', 'pathauto', 'mcp_tools']);
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
    $this->setUpCurrentUser([], [], TRUE);
    $this->container->get('mcp_tools.access_manager')->setScopes(['read', 'write']);
    // 120 pages with no alias yet, then a pattern for them.
    for ($i = 1; $i <= 120; $i++) {
      Node::create(['type' => 'page', 'title' => "Page $i"])->save();
    }
    PathautoPattern::create(['id' => 'pages', 'label' => 'Pages', 'type' => 'canonical_entities:node', 'pattern' => 'p/[node:title]', 'weight' => 0])->save();
  }

  /**
   * Three calls: 50, 50, 20, and every page has its alias.
   */
  public function testEveryEntityGetsItsAlias(): void {
    $service = $this->container->get('mcp_tools_pathauto.pathauto');
    $after = 0;
    $calls = 0;
    do {
      $result = $service->generateAliases('node', 'page', FALSE, $after);
      $this->assertTrue($result['success'], (string) ($result['error'] ?? ''));
      $after = $result['data']['next_after'] ?? NULL;
      $this->assertNotNull($after, 'The answer did not say where to go on.');
      $calls++;
    } while (empty($result['data']['done']) && $calls < 10);

    $this->assertSame(3, $calls);
    $aliases = $this->container->get('entity_type.manager')->getStorage('path_alias')->getQuery()->accessCheck(FALSE)->count()->execute();
    $this->assertSame(120, (int) $aliases, 'Not every page got its alias.');
  }

}
