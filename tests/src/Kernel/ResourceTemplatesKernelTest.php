<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Parameterized MCP resource templates (drupal://node/{nid}, config/{name}).
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
class ResourceTemplatesKernelTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'text',
    'node',
    'dblog',
    'update',
    'tool',
    'mcp_tools',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('user', ['users_data']);
    $this->installConfig(['system', 'field', 'filter', 'node', 'mcp_tools']);

    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    // Resources answer to the same rule as the tools that show the same
    // data: the read scope plus the tool category's permission.
    $this->setUpCurrentUser([], [
      'access content',
      'mcp_tools use content',
      'mcp_tools use config',
      'mcp_tools use site_health',
    ]);
  }

  /**
   * The registry exposes the node and config templates.
   */
  public function testTemplatesAreRegistered(): void {
    $templates = $this->container->get('mcp_tools.resource_registry')->getResourceTemplates();
    $uris = array_column($templates, 'uriTemplate');

    $this->assertContains('drupal://node/{nid}', $uris);
    $this->assertContains('drupal://config/{name}', $uris);
    foreach ($templates as $template) {
      $this->assertIsCallable($template['handler']);
    }
  }

  /**
   * The node template returns a summary and honors missing nodes.
   */
  public function testNodeTemplate(): void {
    $node = Node::create([
      'type' => 'article',
      'title' => 'Resource me',
      'langcode' => 'en',
      'status' => 1,
    ]);
    $node->save();

    $provider = $this->container->get('mcp_tools.resource_provider.core');

    $summary = $provider->readNode((string) $node->id());
    $this->assertSame('Resource me', $summary['title']);
    $this->assertSame('article', $summary['type']);
    $this->assertSame('published', $summary['status']);
    $this->assertSame(['en'], $summary['languages']);

    $missing = $provider->readNode('99999');
    $this->assertArrayHasKey('error', $missing);
  }

  /**
   * A resource read starts like a new request, as a tool call does.
   *
   * In the long-running STDIO server a node read once as a resource stayed
   * in this process's memory: a change made by another process did not show
   * until some tool call happened to reset it.
   */
  public function testResourcesSeeContentSavedElsewhere(): void {
    $node = Node::create(['type' => 'article', 'title' => 'Before', 'status' => 1]);
    $node->save();
    $nid = (int) $node->id();

    $read = NULL;
    foreach ($this->container->get('mcp_tools.resource_provider.core')->getResourceTemplates() as $template) {
      if ($template['name'] === 'node-summary') {
        $read = $template['handler'];
      }
    }
    $this->assertNotNull($read);
    $this->assertSame('Before', $read((string) $nid)['title']);

    // Another process saves the node: the tables change and that process
    // clears the entity cache all processes share.
    $database = $this->container->get('database');
    foreach (['node_field_data', 'node_field_revision'] as $table) {
      $database->update($table)->fields(['title' => 'After'])->condition('nid', $nid)->execute();
    }
    $this->container->get('cache.entity')->deleteAll();

    $this->assertSame('After', $read((string) $nid)['title']);
  }

  /**
   * The config template reads through the redacting analysis service.
   */
  public function testConfigTemplate(): void {
    $provider = $this->container->get('mcp_tools.resource_provider.core');

    $result = $provider->readConfig('system.site');
    $this->assertSame('system.site', $result['name']);
    $this->assertArrayHasKey('data', $result);

    $missing = $provider->readConfig('no.such.config');
    $this->assertNotEmpty($missing['error'] ?? $missing['success'] === FALSE ? 'err' : '');
  }

  /**
   * The snapshot only shows what the user may see through the tools.
   *
   * It was open to anyone with the site health permission, yet it also
   * lists content types and their fields, role names and changed config
   * names. The tools that show those need the structure, users and config
   * permissions.
   */
  public function testSnapshotShowsEachPartToThoseAllowedToSeeIt(): void {
    $this->setUpCurrentUser([], ['mcp_tools use site_health']);
    $snapshot = $this->container->get('mcp_tools.resource_provider.core')->getSiteSnapshot();

    $this->assertArrayHasKey('site', $snapshot, 'The site health part is there.');
    $this->assertArrayNotHasKey('content_types', $snapshot['blueprint']);
    $this->assertArrayNotHasKey('roles', $snapshot['blueprint']);
    $this->assertArrayNotHasKey('sample', $snapshot['config_drift']);
    $this->assertStringNotContainsString('article', json_encode($snapshot));

    $this->setUpCurrentUser([], [
      'mcp_tools use site_health',
      'mcp_tools use structure',
      'mcp_tools use users',
      'mcp_tools use config',
    ]);
    $snapshot = $this->container->get('mcp_tools.resource_provider.core')->getSiteSnapshot();

    $this->assertArrayHasKey('content_types', $snapshot['blueprint']);
    $this->assertArrayHasKey('roles', $snapshot['blueprint']);
    $this->assertStringContainsString('article', json_encode($snapshot['blueprint']['content_types']));

    // Structure without users: everything but the roles.
    $this->setUpCurrentUser([], ['mcp_tools use site_health', 'mcp_tools use structure']);
    $snapshot = $this->container->get('mcp_tools.resource_provider.core')->getSiteSnapshot();
    $this->assertArrayHasKey('content_types', $snapshot['blueprint']);
    $this->assertArrayNotHasKey('roles', $snapshot['blueprint']);
  }

  /**
   * Resources and the prompt refuse a user without the tool permissions.
   */
  public function testResourcesAndPromptRefuseUsersWithoutToolPermissions(): void {
    $node = Node::create(['type' => 'article', 'title' => 'Not for you', 'status' => 1]);
    $node->save();

    // This user can view content on the site, but holds no MCP Tools
    // permission.
    $this->setUpCurrentUser([], ['access content']);
    $provider = $this->container->get('mcp_tools.resource_provider.core');

    $results = [
      'site status' => $provider->getSiteStatus(),
      'site snapshot' => $provider->getSiteSnapshot(),
      'system requirements' => $provider->getSystemRequirements(),
      'config' => $provider->readConfig('system.site'),
      'node' => $provider->readNode((string) $node->id()),
    ];
    foreach ($results as $label => $result) {
      $this->assertSame(['error' => 'Access denied.'], $result, "The $label resource is refused, with no data.");
    }

    $messages = $this->container->get('mcp_tools.prompt_provider.core')->siteBriefPrompt(NULL, TRUE);
    $text = $messages[0]->content->text;
    $this->assertStringContainsString('Access denied', $text);
    $this->assertStringNotContainsString('drupal_version', $text, 'The prompt carries no site data.');
  }

  /**
   * The system status includes Drupal core's own checks.
   *
   * Drupal 11.2 moved core's checks from system_requirements() to
   * hook_runtime_requirements() classes. Only the old functions were called,
   * so on Drupal 11.2 and later the status had no core entry at all: no PHP
   * version, no cron, no database, and has_errors stayed FALSE.
   */
  public function testSystemStatusIncludesCoreChecks(): void {
    $requirements = $this->container->get('mcp_tools.system_status')->getRequirements();
    $keys = array_column($requirements['items'], 'key');

    $this->assertContains('drupal', $keys, 'The Drupal version check from the system module is there.');
    $this->assertContains('php', $keys, 'The PHP version check from the system module is there.');
    $this->assertContains('cron', $keys, 'The cron check from the system module is there.');
  }

  /**
   * Resources need the read scope, like read tools do.
   */
  public function testResourcesNeedTheReadScope(): void {
    $provider = $this->container->get('mcp_tools.resource_provider.core');
    $this->assertArrayHasKey('drupal_version', $provider->getSiteStatus(), 'Allowed with the default read scope.');

    $this->container->get('mcp_tools.access_manager')->setScopes(['write']);
    $this->assertSame(['error' => 'Access denied.'], $provider->getSiteStatus());
    $this->assertSame(['error' => 'Access denied.'], $provider->readConfig('system.site'));
  }

  /**
   * Resource and prompt handlers keep Drupal's Fiber suspensions to themselves.
   *
   * The SDK runs each handler in a Fiber. Loading an entity can suspend it
   * for Drupal's own scheduling; mcp/sdk 0.10 fails the request when such a
   * suspension reaches it.
   */
  public function testHandlersDoNotLeakFiberSuspensions(): void {
    $node = Node::create(['type' => 'article', 'title' => 'In a fiber', 'status' => 1]);
    $node->save();

    $handlers = [];
    $registry = $this->container->get('mcp_tools.resource_registry');
    foreach ($registry->getResources() as $resource) {
      $handlers[$resource['uri']] = [$resource['handler'], []];
    }
    foreach ($registry->getResourceTemplates() as $template) {
      $argument = str_contains($template['uriTemplate'], '{nid}') ? (string) $node->id() : 'system.site';
      $handlers[$template['uriTemplate']] = [$template['handler'], [$argument]];
    }
    foreach ($this->container->get('mcp_tools.prompt_registry')->getPrompts() as $prompt) {
      $handlers[$prompt['name']] = [$prompt['handler'], [NULL, TRUE]];
    }
    $this->assertCount(6, $handlers);

    foreach ($handlers as $label => [$handler, $arguments]) {
      // Play the SDK: anything the handler yields would have reached it.
      $fiber = new \Fiber(static fn(): mixed => $handler(...$arguments));
      $seen = [];
      $value = $fiber->start();
      while (!$fiber->isTerminated()) {
        $seen[] = $value;
        $value = $fiber->resume();
      }
      $this->assertSame([], $seen, "$label: no suspension reached the SDK.");
      $this->assertNotEmpty($fiber->getReturn(), "$label: the handler answered.");
    }
  }

}
