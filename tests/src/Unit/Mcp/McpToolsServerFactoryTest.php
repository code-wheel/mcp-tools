<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Unit\Mcp;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp_tools\Mcp\Error\ToolErrorHandlerInterface;
use Drupal\mcp_tools\Mcp\McpToolsServerFactory;
use Drupal\mcp_tools\Mcp\Prompt\PromptRegistry;
use Drupal\mcp_tools\Mcp\Resource\ResourceRegistry;
use Drupal\mcp_tools\Mcp\ToolApiSchemaConverter;
use Drupal\tool\Tool\ToolDefinition;
use Mcp\Server;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

/**
 * Tests for McpToolsServerFactory.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools\Mcp\McpToolsServerFactory::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
final class McpToolsServerFactoryTest extends TestCase {

  private PluginManagerInterface $toolManager;
  private ToolApiSchemaConverter $schemaConverter;
  private LoggerInterface $logger;
  private EventDispatcherInterface $eventDispatcher;
  private ResourceRegistry $resourceRegistry;
  private PromptRegistry $promptRegistry;
  private ToolErrorHandlerInterface $toolErrorHandler;

  protected function setUp(): void {
    parent::setUp();
    $this->toolManager = $this->createMock(PluginManagerInterface::class);
    $this->schemaConverter = $this->createMock(ToolApiSchemaConverter::class);
    $this->logger = $this->createMock(LoggerInterface::class);
    $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
    $this->resourceRegistry = $this->createMock(ResourceRegistry::class);
    $this->promptRegistry = $this->createMock(PromptRegistry::class);
    $this->toolErrorHandler = $this->createMock(ToolErrorHandlerInterface::class);
  }

  public function testPluginIdToMcpNameConvertsColons(): void {
    $result = McpToolsServerFactory::pluginIdToMcpName('mcp_tools:get_status');

    $this->assertSame('mcp_tools___get_status', $result);
  }

  public function testPluginIdToMcpNamePreservesSimpleNames(): void {
    $result = McpToolsServerFactory::pluginIdToMcpName('simple_tool_name');

    $this->assertSame('simple_tool_name', $result);
  }

  public function testPluginIdToMcpNameHandlesMultipleColons(): void {
    $result = McpToolsServerFactory::pluginIdToMcpName('module:category:tool');

    $this->assertSame('module___category___tool', $result);
  }

  public function testCreateReturnsServer(): void {
    $this->toolManager->method('getDefinitions')->willReturn([]);
    $this->resourceRegistry->method('getResources')->willReturn([]);
    $this->resourceRegistry->method('getResourceTemplates')->willReturn([]);
    $this->promptRegistry->method('getPrompts')->willReturn([]);

    $factory = new McpToolsServerFactory(
      $this->toolManager,
      $this->schemaConverter,
      $this->logger,
      $this->eventDispatcher,
      $this->resourceRegistry,
      $this->promptRegistry,
      $this->toolErrorHandler,
    );

    $server = $factory->create('Test Server', '1.0.0');

    $this->assertInstanceOf(Server::class, $server);
  }

  public function testCreateWithNoResourcesOrPrompts(): void {
    $this->toolManager->method('getDefinitions')->willReturn([]);

    $factory = new McpToolsServerFactory(
      $this->toolManager,
      $this->schemaConverter,
      $this->logger,
      $this->eventDispatcher,
      NULL,
      NULL,
      $this->toolErrorHandler,
    );

    $server = $factory->create('Test Server', '1.0.0', enableResources: FALSE, enablePrompts: FALSE);

    $this->assertInstanceOf(Server::class, $server);
  }

  public function testCreateFiltersToolsByProvider(): void {
    $mcpToolDescription = $this->createMock(TranslatableMarkup::class);
    $mcpToolDescription->method('__toString')->willReturn('An MCP tool');
    $mcpToolDefinition = $this->createMock(ToolDefinition::class);
    $mcpToolDefinition->method('getProvider')->willReturn('mcp_tools');
    $mcpToolDefinition->method('getDescription')->willReturn($mcpToolDescription);

    $otherToolDescription = $this->createMock(TranslatableMarkup::class);
    $otherToolDescription->method('__toString')->willReturn('Another tool');
    $otherToolDefinition = $this->createMock(ToolDefinition::class);
    $otherToolDefinition->method('getProvider')->willReturn('other_module');
    $otherToolDefinition->method('getDescription')->willReturn($otherToolDescription);

    $this->toolManager->method('getDefinitions')->willReturn([
      'mcp_tools:test_tool' => $mcpToolDefinition,
      'other_module:other_tool' => $otherToolDefinition,
    ]);

    $this->schemaConverter->method('toolDefinitionToAnnotations')
      ->willReturn(['title' => 'Test Tool']);
    $this->schemaConverter->method('toolDefinitionToInputSchema')
      ->willReturn(['type' => 'object', 'properties' => []]);

    $this->resourceRegistry->method('getResources')->willReturn([]);
    $this->resourceRegistry->method('getResourceTemplates')->willReturn([]);
    $this->promptRegistry->method('getPrompts')->willReturn([]);

    $factory = new McpToolsServerFactory(
      $this->toolManager,
      $this->schemaConverter,
      $this->logger,
      $this->eventDispatcher,
      $this->resourceRegistry,
      $this->promptRegistry,
      $this->toolErrorHandler,
    );

    // With includeAllTools = FALSE, should only include mcp_tools tools.
    $server = $factory->create('Test Server', '1.0.0', includeAllTools: FALSE);

    $this->assertInstanceOf(Server::class, $server);
  }

  public function testCreateWithGatewayMode(): void {
    $this->toolManager->method('getDefinitions')->willReturn([]);
    $this->resourceRegistry->method('getResources')->willReturn([]);
    $this->resourceRegistry->method('getResourceTemplates')->willReturn([]);
    $this->promptRegistry->method('getPrompts')->willReturn([]);

    $factory = new McpToolsServerFactory(
      $this->toolManager,
      $this->schemaConverter,
      $this->logger,
      $this->eventDispatcher,
      $this->resourceRegistry,
      $this->promptRegistry,
      $this->toolErrorHandler,
    );

    $server = $factory->create('Test Server', '1.0.0', gatewayMode: TRUE);

    $this->assertInstanceOf(Server::class, $server);
  }

  public function testCreateRegistersResources(): void {
    $this->toolManager->method('getDefinitions')->willReturn([]);

    $this->resourceRegistry->method('getResources')->willReturn([
      [
        'uri' => 'drupal://site/status',
        'name' => 'site-status',
        'description' => 'Site status resource',
        'mimeType' => 'application/json',
        'handler' => fn() => ['status' => 'ok'],
      ],
    ]);
    $this->resourceRegistry->method('getResourceTemplates')->willReturn([]);
    $this->promptRegistry->method('getPrompts')->willReturn([]);

    $factory = new McpToolsServerFactory(
      $this->toolManager,
      $this->schemaConverter,
      $this->logger,
      $this->eventDispatcher,
      $this->resourceRegistry,
      $this->promptRegistry,
      $this->toolErrorHandler,
    );

    $server = $factory->create('Test Server', '1.0.0');

    $this->assertInstanceOf(Server::class, $server);
  }

  public function testCreateSkipsDuplicateResources(): void {
    $this->toolManager->method('getDefinitions')->willReturn([]);

    $this->resourceRegistry->method('getResources')->willReturn([
      [
        'uri' => 'drupal://site/status',
        'name' => 'site-status',
        'handler' => fn() => [],
      ],
      [
        'uri' => 'drupal://site/status',
        'name' => 'site-status-duplicate',
        'handler' => fn() => [],
      ],
    ]);
    $this->resourceRegistry->method('getResourceTemplates')->willReturn([]);
    $this->promptRegistry->method('getPrompts')->willReturn([]);

    $this->logger->expects($this->once())
      ->method('warning')
      ->with(
        'Skipping duplicate MCP resource URI: @uri',
        ['@uri' => 'drupal://site/status']
      );

    $factory = new McpToolsServerFactory(
      $this->toolManager,
      $this->schemaConverter,
      $this->logger,
      $this->eventDispatcher,
      $this->resourceRegistry,
      $this->promptRegistry,
      $this->toolErrorHandler,
    );

    $server = $factory->create('Test Server', '1.0.0');

    $this->assertInstanceOf(Server::class, $server);
  }

  public function testCreateRegistersPrompts(): void {
    $this->toolManager->method('getDefinitions')->willReturn([]);
    $this->resourceRegistry->method('getResources')->willReturn([]);
    $this->resourceRegistry->method('getResourceTemplates')->willReturn([]);

    $this->promptRegistry->method('getPrompts')->willReturn([
      [
        'name' => 'site-brief',
        'description' => 'Brief overview of the site',
        'handler' => fn() => 'Site is healthy',
      ],
    ]);

    $factory = new McpToolsServerFactory(
      $this->toolManager,
      $this->schemaConverter,
      $this->logger,
      $this->eventDispatcher,
      $this->resourceRegistry,
      $this->promptRegistry,
      $this->toolErrorHandler,
    );

    $server = $factory->create('Test Server', '1.0.0');

    $this->assertInstanceOf(Server::class, $server);
  }

  public function testCreateSkipsDuplicatePrompts(): void {
    $this->toolManager->method('getDefinitions')->willReturn([]);
    $this->resourceRegistry->method('getResources')->willReturn([]);
    $this->resourceRegistry->method('getResourceTemplates')->willReturn([]);

    $this->promptRegistry->method('getPrompts')->willReturn([
      [
        'name' => 'site-brief',
        'handler' => fn() => 'First',
      ],
      [
        'name' => 'site-brief',
        'handler' => fn() => 'Duplicate',
      ],
    ]);

    $this->logger->expects($this->once())
      ->method('warning')
      ->with(
        'Skipping duplicate MCP prompt: @name',
        ['@name' => 'site-brief']
      );

    $factory = new McpToolsServerFactory(
      $this->toolManager,
      $this->schemaConverter,
      $this->logger,
      $this->eventDispatcher,
      $this->resourceRegistry,
      $this->promptRegistry,
      $this->toolErrorHandler,
    );

    $server = $factory->create('Test Server', '1.0.0');

    $this->assertInstanceOf(Server::class, $server);
  }

  public function testCreateRegistersResourceTemplates(): void {
    $this->toolManager->method('getDefinitions')->willReturn([]);

    $this->resourceRegistry->method('getResources')->willReturn([]);
    $this->resourceRegistry->method('getResourceTemplates')->willReturn([
      [
        'uriTemplate' => 'drupal://node/{id}',
        'name' => 'node-resource',
        'description' => 'Node content by ID',
        'handler' => fn(string $id) => ['nid' => $id],
      ],
    ]);
    $this->promptRegistry->method('getPrompts')->willReturn([]);

    $factory = new McpToolsServerFactory(
      $this->toolManager,
      $this->schemaConverter,
      $this->logger,
      $this->eventDispatcher,
      $this->resourceRegistry,
      $this->promptRegistry,
      $this->toolErrorHandler,
    );

    $server = $factory->create('Test Server', '1.0.0');

    $this->assertInstanceOf(Server::class, $server);
  }

}
