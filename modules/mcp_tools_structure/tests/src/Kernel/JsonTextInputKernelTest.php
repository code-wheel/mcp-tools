<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_structure\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\mcp_tools\Mcp\ToolApiCallToolHandler;
use Drupal\mcp_tools\Mcp\ToolApiSchemaConverter;
use Drupal\mcp_tools\Mcp\ToolInputValidator;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\Role;
use Mcp\Schema\JsonRpc\Response;
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Server\Session\SessionInterface;
use Psr\Log\NullLogger;

/**
 * A list or an object sent as its JSON text is read as the list or object.
 *
 * Two local models sent structured inputs that way: nemotron-3.5-lightning
 * its "fields" and "updates", mimo-v2.6 a list of permissions, which then
 * read as one permission called ["access content"] and was refused.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_structure')]
final class JsonTextInputKernelTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'field', 'text', 'filter', 'node', 'dblog', 'update', 'tool', 'mcp_tools', 'mcp_tools_structure'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installSchema('dblog', ['watchdog']);
    $this->installConfig(['mcp_tools']);
    $this->createUser();
    $this->setUpCurrentUser([], ['mcp_tools use structure', 'administer permissions']);
    $this->config('mcp_tools.settings')->set('access.allowed_scopes', ['read', 'write'])->set('access.read_only_mode', FALSE)->save();
    $this->container->get('mcp_tools.access_manager')->setScopes(['read', 'write']);
  }

  /**
   * The permissions of a new role, given as a JSON list in a string.
   */
  public function testAListAsJsonText(): void {
    $handler = new ToolApiCallToolHandler(
      $this->container->get('plugin.manager.tool'),
      new NullLogger(),
      FALSE,
      'mcp_tools',
      new ToolInputValidator(new ToolApiSchemaConverter(), new NullLogger()),
    );
    $request = (new CallToolRequest('mcp_structure_create_role', ['id' => 'reviewer', 'label' => 'Reviewer', 'permissions' => '["access content"]']))->withId(1);
    $response = $handler->handle($request, $this->createMock(SessionInterface::class));
    $this->assertInstanceOf(Response::class, $response);
    $this->assertFalse($response->result->isError, (string) $response->result->content[0]->text);
    $this->assertTrue(Role::load('reviewer')->hasPermission('access content'));

    // A plain string that is not JSON stays what it was: one item.
    $request = (new CallToolRequest('mcp_structure_create_role', ['id' => 'reader', 'label' => 'Reader', 'permissions' => 'access content']))->withId(2);
    $response = $handler->handle($request, $this->createMock(SessionInterface::class));
    $this->assertFalse($response->result->isError, (string) $response->result->content[0]->text);
    $this->assertTrue(Role::load('reader')->hasPermission('access content'));
  }

}
