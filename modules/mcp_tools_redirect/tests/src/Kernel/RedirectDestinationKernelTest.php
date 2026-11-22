<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_redirect\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\path_alias\Entity\PathAlias;
use Drupal\redirect\Entity\Redirect;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * A redirect to a path where nothing is says so.
 *
 * A model was asked to send "old-news" to the news item "Hello world". It
 * did not look the item up. It made the path up, "/news/hello-world", and
 * the tool made the redirect and said success. A redirect to a page that
 * comes later is a fair thing to make, so it is still made: the answer
 * says that nothing answers there now, and how to find what was meant.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_redirect')]
final class RedirectDestinationKernelTest extends KernelTestBase {

  use UserCreationTrait;

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
    'link',
    'views',
    'path_alias',
    'redirect',
    'dblog',
    'update',
    'tool',
    'mcp_tools',
    'mcp_tools_redirect',
  ];

  /**
   * The redirect service.
   *
   * @var \Drupal\mcp_tools_redirect\Service\RedirectService
   */
  private $service;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('redirect');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('dblog', ['watchdog']);
    $this->installConfig(['system', 'filter', 'mcp_tools']);
    NodeType::create(['type' => 'news', 'name' => 'News'])->save();
    Node::create(['type' => 'news', 'title' => 'Hello world', 'status' => 1])->save();
    PathAlias::create(['path' => '/node/1', 'alias' => '/news/hello', 'langcode' => 'en'])->save();
    $this->setUpCurrentUser([], [], TRUE);
    $this->container->get('mcp_tools.access_manager')->setScopes(['read', 'write']);
    $this->container->get('router.builder')->rebuild();
    $this->service = $this->container->get('mcp_tools_redirect.redirect');
  }

  /**
   * A path that was made up: the redirect is made, and the answer warns.
   */
  public function testAPathWhereNothingIsGetsAWarning(): void {
    $result = $this->service->createRedirect('old-news', '/news/hello-world');
    $this->assertTrue($result['success'], (string) ($result['error'] ?? ''));
    $this->assertCount(1, Redirect::loadMultiple());
    $this->assertArrayHasKey('warning', $result['data'], 'A redirect to a path where nothing is said success and nothing else.');
    $this->assertStringContainsString('/news/hello-world', $result['data']['warning']);
    $this->assertStringContainsString('Nothing answers', $result['data']['warning']);
    $this->assertStringContainsString('mcp_tools_search_content', $result['data']['warning']);
    // The message is what a model reads.
    $this->assertStringContainsString($result['data']['warning'], $result['data']['message']);

    // A content item that is not there is nothing, too.
    $result = $this->service->createRedirect('old-two', '/node/999');
    $this->assertTrue($result['success']);
    $this->assertArrayHasKey('warning', $result['data']);
  }

  /**
   * A path that leads somewhere, and another site, get no warning.
   */
  public function testARealDestinationGetsNoWarning(): void {
    foreach (['a' => '/node/1', 'b' => 'node/1', 'c' => '/news/hello', 'd' => '/node/1?page=2#top', 'e' => 'https://example.com/anything', 'f' => '<front>', 'g' => 'internal:/node/1'] as $source => $destination) {
      $result = $this->service->createRedirect('from-' . $source, $destination);
      $this->assertTrue($result['success'], $destination . ': ' . ($result['error'] ?? ''));
      $this->assertArrayNotHasKey('warning', $result['data'], $destination . ' got: ' . ($result['data']['warning'] ?? ''));
      $this->assertStringNotContainsString('Nothing answers', $result['data']['message']);
    }
  }

  /**
   * A changed destination is looked at the same way.
   */
  public function testAnUpdateWarnsToo(): void {
    $created = $this->service->createRedirect('old-news', '/node/1');
    $id = (int) $created['data']['redirect']['id'];

    $result = $this->service->updateRedirect($id, ['destination' => '/no/such/page']);
    $this->assertTrue($result['success'], (string) ($result['error'] ?? ''));
    $this->assertArrayHasKey('warning', $result['data']);
    $this->assertStringContainsString('/no/such/page', $result['data']['warning']);

    // Another field changed, the destination left alone: nothing to say
    // about a destination nobody gave.
    $result = $this->service->updateRedirect($id, ['status_code' => 302]);
    $this->assertTrue($result['success']);
    $this->assertArrayNotHasKey('warning', $result['data']);

    $result = $this->service->updateRedirect($id, ['destination' => '/news/hello']);
    $this->assertTrue($result['success']);
    $this->assertArrayNotHasKey('warning', $result['data']);
  }

  /**
   * The two tools declare the warning, so a client's check lets it through.
   */
  public function testTheToolsDeclareTheWarning(): void {
    $tools = $this->container->get('plugin.manager.tool');
    foreach (['mcp_redirect_create', 'mcp_redirect_update'] as $id) {
      $this->assertArrayHasKey('warning', $tools->getDefinition($id)->getOutputDefinitions(), $id);
    }
    $tool = $tools->createInstance('mcp_redirect_create');
    $tool->setInputValue('source', 'old-news');
    $tool->setInputValue('destination', '/news/hello-world');
    $tool->execute();
    $this->assertTrue($tool->getResult()->isSuccess(), (string) $tool->getResult()->getMessage());
    $this->assertStringContainsString('Nothing answers', (string) $tool->getOutputValues()['warning']);
  }

}
