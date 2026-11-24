<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\mcp_tools\Mcp\GrowingFindHandler;
use Drupal\mcp_tools\Mcp\GrowingToolList;
use Drupal\mcp_tools\Mcp\McpToolsServerFactory;
use Drupal\mcp_tools\Mcp\ToolApiGateway;
use Drupal\mcp_tools\Mcp\ToolApiSchemaConverter;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Schema\Request\ListToolsRequest;
use Mcp\Server\Session\SessionInterface;
use Psr\Log\NullLogger;

/**
 * A session's tool list starts small and grows with what it finds.
 *
 * In gateway mode about half of all calls went to finding a tool: 34%
 * discover and 17% get info, in 406 runs of our own. Shown once found, a
 * tool is called by its name from then on.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
final class GrowingToolListKernelTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'dblog', 'update', 'tool', 'mcp_tools', 'mcp_tools_cache'];

  /**
   * A session that keeps what it is given.
   */
  private function session(): SessionInterface {
    $data = [];
    $session = $this->createMock(SessionInterface::class);
    $session->method('get')->willReturnCallback(function (string $key, mixed $default = NULL) use (&$data) {
      return $data[$key] ?? $default;
    });
    $session->method('set')->willReturnCallback(function (string $key, mixed $value) use (&$data): void {
      $data[$key] = $value;
    });
    $session->method('save')->willReturn(TRUE);
    return $session;
  }

  /**
   * A registry with every tool of the site, as the SDK's gives them.
   */
  private function registry(array $names): object {
    return new class($names) {

      public function __construct(private readonly array $names) {}

      /**
       * The tools, as the SDK's registry gives them.
       */
      public function getTools(?int $limit = NULL): object {
        return (object) ['references' => array_map(fn($n) => (object) ['name' => $n], $this->names)];
      }

    };
  }

  /**
   * Get info adds the tool it was asked about; discover adds nothing.
   *
   * A search shows what is there; a tool joins the list once the model asks
   * about it. With discover adding its first five, a list held 47 tools
   * after twelve tasks and twice the gateway's prompt tokens; this way 13,
   * at 13% over the gateway, with the same calls saved.
   */
  public function testTheListGrowsWithWhatIsFound(): void {
    $this->installEntitySchema('user');
    $this->installConfig(['mcp_tools']);
    $this->setUpCurrentUser([], [], TRUE);
    $access = $this->container->get('mcp_tools.access_manager');
    $access->setScopes(['read', 'write']);
    $gateway = new ToolApiGateway($this->container->get('plugin.manager.tool'), new ToolApiSchemaConverter(), new NullLogger(), FALSE, 'mcp_tools', NULL, NULL, $access, $this->container->get('mcp_tools.tool_call_context'));

    $always = [
      'mcp_tools_discover_tools',
      'mcp_tools_get_tool_info',
      'mcp_tools_execute_tool',
      'mcp_tools_get_site_status',
    ];
    $all = array_merge($always, ['mcp_cache_clear_all', 'mcp_cache_get_status', 'mcp_cache_rebuild']);
    $list = new GrowingToolList($this->registry($all), $always);
    $find = new GrowingFindHandler($gateway);
    $session = $this->session();
    $listed = fn(): array => array_map(fn($t) => $t->name, $list->handle((new ListToolsRequest())->withId(1), $session)->result->tools);

    $this->assertSame($always, $listed());

    $found = $find->handle((new CallToolRequest(ToolApiGateway::DISCOVER_TOOL, ['query' => 'cache']))->withId(2), $session);
    $this->assertFalse($found->result->isError, json_encode($found->result->structuredContent));
    $names = array_column($found->result->structuredContent['tools'], 'name');
    $this->assertContains('mcp_cache_clear_all', $names);
    $this->assertNotContains('mcp_cache_clear_all', $listed(), 'A search alone joined a tool to the list.');

    // Get info on a tool shows it, at the end of the list; an unknown name
    // adds nothing.
    // Asked in the other order than the registry's: the list keeps the
    // order they were asked in, so a client's cached prompt stays valid.
    foreach (['mcp_cache_rebuild', 'mcp_cache_clear_all'] as $id => $name) {
      $info = $find->handle((new CallToolRequest(ToolApiGateway::GET_INFO_TOOL, ['tool_name' => $name]))->withId(10 + $id), $session);
      $this->assertFalse($info->result->isError, json_encode($info->result->structuredContent));
    }
    $this->assertSame(['mcp_cache_rebuild', 'mcp_cache_clear_all'], array_slice($listed(), -2), 'The tools get info named did not join the end of the list, in order.');
    $before = $listed();
    $find->handle((new CallToolRequest(ToolApiGateway::GET_INFO_TOOL, ['tool_name' => 'mcp_tools_no_such_tool']))->withId(4), $session);
    $this->assertSame($before, $listed());

    // The overview (no query) shows no tool: its examples were not asked for.
    $fresh = $this->session();
    $find->handle((new CallToolRequest(ToolApiGateway::DISCOVER_TOOL, []))->withId(5), $fresh);
    $this->assertSame([], (array) $fresh->get(GrowingToolList::SESSION_KEY, []));
  }

  /**
   * The instructions say which call joins a tool to the list: get info.
   *
   * A search adds nothing, so "a tool you find joins your tool list" told
   * a model to wait for a tool that would not come.
   */
  public function testTheInstructionsNameTheCallThatShowsTools(): void {
    $text = McpToolsServerFactory::instructions(TRUE, 0, TRUE);
    $this->assertStringContainsString('A tool you read with ' . ToolApiGateway::GET_INFO_TOOL . ' joins your tool list', $text);
    $this->assertStringNotContainsString('A tool you find joins', $text);
  }

}
