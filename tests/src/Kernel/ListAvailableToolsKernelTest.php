<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\mcp_tools\Mcp\ToolApiCallToolHandler;
use Drupal\mcp_tools\Mcp\ToolApiSchemaConverter;
use Drupal\mcp_tools\Mcp\ToolInputValidator;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Server\Session\SessionInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Uid\Uuid;

/**
 * The tool list's categories are the tools' own, so a category narrows it.
 *
 * Each tool's category is a protected constant of its class, which a
 * sibling class cannot read: all but one of 230 tools came back as
 * "other", and a category filter found nothing.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
final class ListAvailableToolsKernelTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'dblog', 'update', 'tool', 'mcp_tools', 'mcp_tools_cache'];

  /**
   * A category gives that category's tools, and the counts say so.
   *
   * Each tool is its id, category and description, without a label.
   */
  public function testCategoriesAreTheToolsOwn(): void {
    $this->installEntitySchema('user');
    $this->installConfig(['system', 'mcp_tools']);
    $this->setUpCurrentUser([], [], TRUE);
    $this->config('mcp_tools.settings')->set('access.allowed_scopes', ['read', 'write', 'admin'])->save();
    $this->container->get('mcp_tools.access_manager')->setScopes(['read', 'write', 'admin']);

    $handler = new ToolApiCallToolHandler(
      $this->container->get('plugin.manager.tool'),
      new NullLogger(),
      FALSE,
      'mcp_tools',
      new ToolInputValidator(new ToolApiSchemaConverter(), new NullLogger()),
    );
    $session = $this->createMock(SessionInterface::class);
    $session->method('get')->willReturnArgument(1);
    $session->method('getId')->willReturn(Uuid::v4());
    $call = fn(array $arguments): array => $handler->handle((new CallToolRequest('mcp_tools_list_available', $arguments))->withId(1), $session)->result->structuredContent['data'];

    $all = $call([]);
    $this->assertArrayHasKey('cache', $all['categories'], json_encode($all['categories']));
    $this->assertArrayNotHasKey('other', $all['categories'], json_encode($all['categories']));

    $cache = $call(['category' => 'cache']);
    $this->assertNotSame([], $cache['tools']);
    $this->assertSame(['cache'], array_values(array_unique(array_column($cache['tools'], 'category'))));
    $this->assertContains('mcp_cache_get_status', array_column($cache['tools'], 'id'));
    // The label said what the id says ("Get Status", mcp_cache_get_status),
    // for every tool: 42,000 characters with all of them.
    $this->assertArrayNotHasKey('label', $cache['tools'][0]);
  }

}
