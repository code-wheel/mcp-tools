<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Unit\Mcp;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp_tools\Mcp\Error\ToolErrorHandlerInterface;
use Drupal\mcp_tools\Mcp\McpToolsServerFactory;
use Drupal\mcp_tools\Mcp\Prompt\PromptRegistry;
use Drupal\mcp_tools\Mcp\Resource\ResourceRegistry;
use Drupal\mcp_tools\Mcp\Sdk;
use Drupal\mcp_tools\Mcp\ToolApiSchemaConverter;
use Drupal\tool\Tool\ToolDefinition;
use Mcp\Server\Transport\StdioTransport;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

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
    // PSR-14: dispatch() returns the event it was given. mcp/sdk 0.9 reads
    // the returned event, so a mock that returns something else breaks it.
    $this->eventDispatcher->method('dispatch')->willReturnArgument(0);
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

    $this->assertInstanceOf(Sdk::serverClass(), $server);
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

    $this->assertInstanceOf(Sdk::serverClass(), $server);
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

    $this->assertInstanceOf(Sdk::serverClass(), $server);
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

    $this->assertInstanceOf(Sdk::serverClass(), $server);
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

    $this->assertInstanceOf(Sdk::serverClass(), $server);
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

    $this->assertInstanceOf(Sdk::serverClass(), $server);
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

    $this->assertInstanceOf(Sdk::serverClass(), $server);
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

    $this->assertInstanceOf(Sdk::serverClass(), $server);
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

    $this->assertInstanceOf(Sdk::serverClass(), $server);
  }

  /**
   * The SDK's debug and info records stay out of the site's logs.
   *
   * The SDK logs every registration at debug level; with dblog that wrote
   * ~170 watchdog rows per request (#3613065).
   */
  public function testSdkDebugAndInfoRecordsDoNotReachTheSiteLogger(): void {
    $served = FALSE;
    $this->toolManager->method('getDefinitions')->willReturn([]);
    $this->resourceRegistry->method('getResources')->willReturn([
      [
        'uri' => 'drupal://site/status',
        'name' => 'site-status',
        'mimeType' => 'application/json',
        'handler' => function () use (&$served) {
          $served = TRUE;
          return ['status' => 'ok'];
        },
      ],
    ]);
    $this->resourceRegistry->method('getResourceTemplates')->willReturn([]);
    $this->promptRegistry->method('getPrompts')->willReturn([
      ['name' => 'site-brief', 'handler' => fn() => 'Site is healthy'],
    ]);

    $logger = new class() extends AbstractLogger {

      /**
       * The levels of the records received.
       *
       * @var string[]
       */
      public array $levels = [];

      /**
       * {@inheritdoc}
       */
      public function log($level, string|\Stringable $message, array $context = []): void {
        $this->levels[] = (string) $level;
      }

    };

    $factory = new McpToolsServerFactory(
      $this->toolManager,
      $this->schemaConverter,
      $logger,
      $this->eventDispatcher,
      $this->resourceRegistry,
      $this->promptRegistry,
      $this->toolErrorHandler,
    );
    // The SDK registers elements, and logs, when it serves the first request.
    // The request goes through the real STDIO transport, reading from a
    // file: that works the same on every supported SDK version, which the
    // in-memory transport does not (from mcp/sdk 0.8 it keeps no session).
    $input = tmpfile();
    $outputPath = tempnam(sys_get_temp_dir(), 'mcp');
    $output = fopen($outputPath, 'w');
    fwrite($input, implode("\n", [
      '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-06-18","capabilities":{},"clientInfo":{"name":"test","version":"1"}}}',
      '{"jsonrpc":"2.0","method":"notifications/initialized"}',
      '{"jsonrpc":"2.0","id":2,"method":"resources/read","params":{"uri":"drupal://site/status"}}',
    ]) . "\n");
    rewind($input);
    // The transport closes both streams when the input ends.
    $factory->create('Test Server', '1.0.0')->run(new StdioTransport($input, $output));

    $answers = (string) file_get_contents($outputPath);
    unlink($outputPath);
    $this->assertTrue($served, 'The request was served. Server output: ' . $answers);
    $this->assertSame([], array_values(array_intersect($logger->levels, [LogLevel::DEBUG, LogLevel::INFO])));
  }

}
