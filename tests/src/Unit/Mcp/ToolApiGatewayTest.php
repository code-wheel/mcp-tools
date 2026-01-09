<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Unit\Mcp;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\mcp_tools\Mcp\ToolApiGateway;
use Drupal\mcp_tools\Mcp\ToolApiSchemaConverter;
use Drupal\Tests\UnitTestCase;
use Drupal\tool\Tool\ToolDefinition;
use Drupal\tool\Tool\ToolOperation;
use Psr\Log\LoggerInterface;

#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools\Mcp\ToolApiGateway::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
final class ToolApiGatewayTest extends UnitTestCase {

  protected PluginManagerInterface $toolManager;
  protected ToolApiSchemaConverter $schemaConverter;
  protected LoggerInterface $logger;

  protected function setUp(): void {
    parent::setUp();
    $this->toolManager = $this->createMock(PluginManagerInterface::class);
    $this->schemaConverter = new ToolApiSchemaConverter();
    $this->logger = $this->createMock(LoggerInterface::class);
  }

  protected function createGateway(
    bool $includeAllTools = FALSE,
    string $allowedProviderPrefix = 'mcp_tools',
  ): ToolApiGateway {
    return new ToolApiGateway(
      $this->toolManager,
      $this->schemaConverter,
      $this->logger,
      $includeAllTools,
      $allowedProviderPrefix,
    );
  }

  protected function createToolDefinition(
    string $id,
    string $provider,
    string $label,
    string $description,
    ToolOperation $operation = ToolOperation::Read,
    bool $destructive = FALSE,
  ): ToolDefinition {
    return new ToolDefinition([
      'id' => $id,
      'provider' => $provider,
      'label' => $this->markup($label),
      'description' => $this->markup($description),
      'operation' => $operation,
      'destructive' => $destructive,
      'input_definitions' => [],
    ]);
  }

  protected function markup(string $string): TranslatableMarkup {
    $translation = new class implements TranslationInterface {

      public function translate($string, array $args = [], array $options = []): TranslatableMarkup {
        return new TranslatableMarkup((string) $string, $args, $options, $this);
      }

      public function translateString(TranslatableMarkup $translated_string): string {
        return $translated_string->getUntranslatedString();
      }

      public function formatPlural($count, $singular, $plural, array $args = [], array $options = []): TranslatableMarkup {
        $useSingular = (int) $count === 1;
        $string = $useSingular ? (string) $singular : str_replace('@count', (string) $count, (string) $plural);
        return new TranslatableMarkup($string, $args, $options, $this);
      }

    };

    return new TranslatableMarkup($string, [], [], $translation);
  }

  public function testGetGatewayToolsReturnsThreeTools(): void {
    $this->toolManager->method('getDefinitions')->willReturn([]);

    $gateway = $this->createGateway();
    $tools = $gateway->getGatewayTools();

    $this->assertCount(3, $tools);

    $names = array_column($tools, 'name');
    $this->assertContains(ToolApiGateway::DISCOVER_TOOL, $names);
    $this->assertContains(ToolApiGateway::GET_INFO_TOOL, $names);
    $this->assertContains(ToolApiGateway::EXECUTE_TOOL, $names);
  }

  public function testGetGatewayToolsHaveCorrectStructure(): void {
    $this->toolManager->method('getDefinitions')->willReturn([]);

    $gateway = $this->createGateway();
    $tools = $gateway->getGatewayTools();

    foreach ($tools as $tool) {
      $this->assertArrayHasKey('handler', $tool);
      $this->assertArrayHasKey('name', $tool);
      $this->assertArrayHasKey('description', $tool);
      $this->assertArrayHasKey('annotations', $tool);
      $this->assertArrayHasKey('inputSchema', $tool);
      $this->assertIsCallable($tool['handler']);
      $this->assertIsString($tool['name']);
      $this->assertIsString($tool['description']);
    }
  }

  public function testDiscoverToolsReturnsEmptyWhenNoTools(): void {
    $this->toolManager->method('getDefinitions')->willReturn([]);

    $gateway = $this->createGateway();
    $result = $gateway->discoverTools();

    $this->assertFalse($result->isError);
    $this->assertNotEmpty($result->structuredContent);
    $this->assertTrue($result->structuredContent['success']);
    $this->assertSame(0, $result->structuredContent['count']);
    $this->assertEmpty($result->structuredContent['tools']);
  }

  public function testDiscoverToolsReturnsAllowedTools(): void {
    $definition = $this->createToolDefinition(
      'mcp_tools:test_tool',
      'mcp_tools',
      'Test Tool',
      'A test tool',
    );

    $this->toolManager->method('getDefinitions')->willReturn([
      'mcp_tools:test_tool' => $definition,
    ]);

    $gateway = $this->createGateway();
    $result = $gateway->discoverTools();

    $this->assertSame(1, $result->structuredContent['count']);
    $this->assertSame('mcp_tools___test_tool', $result->structuredContent['tools'][0]['name']);
    $this->assertSame('Test Tool', $result->structuredContent['tools'][0]['label']);
  }

  public function testDiscoverToolsFiltersNonMatchingProviders(): void {
    $allowedDef = $this->createToolDefinition(
      'mcp_tools:allowed',
      'mcp_tools',
      'Allowed Tool',
      'Should be visible',
    );

    $blockedDef = $this->createToolDefinition(
      'other:blocked',
      'other_module',
      'Blocked Tool',
      'Should not be visible',
    );

    $this->toolManager->method('getDefinitions')->willReturn([
      'mcp_tools:allowed' => $allowedDef,
      'other:blocked' => $blockedDef,
    ]);

    $gateway = $this->createGateway(includeAllTools: FALSE);
    $result = $gateway->discoverTools();

    $this->assertSame(1, $result->structuredContent['count']);
    $this->assertSame('Allowed Tool', $result->structuredContent['tools'][0]['label']);
  }

  public function testDiscoverToolsIncludesAllWhenFlagSet(): void {
    $def1 = $this->createToolDefinition(
      'mcp_tools:first',
      'mcp_tools',
      'First Tool',
      'From mcp_tools',
    );

    $def2 = $this->createToolDefinition(
      'other:second',
      'other_module',
      'Second Tool',
      'From other module',
    );

    $this->toolManager->method('getDefinitions')->willReturn([
      'mcp_tools:first' => $def1,
      'other:second' => $def2,
    ]);

    $gateway = $this->createGateway(includeAllTools: TRUE);
    $result = $gateway->discoverTools();

    $this->assertSame(2, $result->structuredContent['count']);
  }

  public function testDiscoverToolsFiltersWithQuery(): void {
    $def1 = $this->createToolDefinition(
      'mcp_tools:create_content',
      'mcp_tools',
      'Create Content',
      'Create a new content item',
    );

    $def2 = $this->createToolDefinition(
      'mcp_tools:delete_content',
      'mcp_tools',
      'Delete Content',
      'Remove a content item',
    );

    $this->toolManager->method('getDefinitions')->willReturn([
      'mcp_tools:create_content' => $def1,
      'mcp_tools:delete_content' => $def2,
    ]);

    $gateway = $this->createGateway();
    $result = $gateway->discoverTools('create');

    $this->assertSame(1, $result->structuredContent['count']);
    $this->assertSame('Create Content', $result->structuredContent['tools'][0]['label']);
  }

  public function testDiscoverToolsQueryIsCaseInsensitive(): void {
    $definition = $this->createToolDefinition(
      'mcp_tools:test',
      'mcp_tools',
      'Test Tool',
      'A test tool',
    );

    $this->toolManager->method('getDefinitions')->willReturn([
      'mcp_tools:test' => $definition,
    ]);

    $gateway = $this->createGateway();

    $result1 = $gateway->discoverTools('TEST');
    $this->assertSame(1, $result1->structuredContent['count']);

    $result2 = $gateway->discoverTools('test');
    $this->assertSame(1, $result2->structuredContent['count']);
  }

  public function testDiscoverToolsIncludesHints(): void {
    $readDef = $this->createToolDefinition(
      'mcp_tools:read_op',
      'mcp_tools',
      'Read Operation',
      'A read operation',
      ToolOperation::Read,
      FALSE,
    );

    $writeDef = $this->createToolDefinition(
      'mcp_tools:write_op',
      'mcp_tools',
      'Write Operation',
      'A destructive write',
      ToolOperation::Transform,
      TRUE,
    );

    $this->toolManager->method('getDefinitions')->willReturn([
      'mcp_tools:read_op' => $readDef,
      'mcp_tools:write_op' => $writeDef,
    ]);

    $gateway = $this->createGateway();
    $result = $gateway->discoverTools();

    $tools = $result->structuredContent['tools'];
    $readTool = array_values(array_filter($tools, fn($t) => $t['label'] === 'Read Operation'))[0];
    $writeTool = array_values(array_filter($tools, fn($t) => $t['label'] === 'Write Operation'))[0];

    $this->assertTrue($readTool['hints']['read_only']);
    $this->assertFalse($writeTool['hints']['read_only']);
    $this->assertTrue($writeTool['hints']['destructive']);
  }

  public function testGetToolInfoReturnsErrorForUnknownTool(): void {
    $this->toolManager->method('getDefinitions')->willReturn([]);

    $gateway = $this->createGateway();
    $result = $gateway->getToolInfo('nonexistent_tool');

    $this->assertTrue($result->isError);
    $this->assertFalse($result->structuredContent['success']);
    $this->assertStringContainsString('Unknown tool', $result->structuredContent['error']);
  }

  public function testGetToolInfoReturnsToolDetails(): void {
    $definition = $this->createToolDefinition(
      'mcp_tools:test_tool',
      'mcp_tools',
      'Test Tool',
      'A detailed test tool',
    );

    $this->toolManager->method('getDefinitions')->willReturn([
      'mcp_tools:test_tool' => $definition,
    ]);

    $gateway = $this->createGateway();
    $result = $gateway->getToolInfo('mcp_tools___test_tool');

    $this->assertFalse($result->isError);
    $this->assertTrue($result->structuredContent['success']);
    $this->assertSame('mcp_tools___test_tool', $result->structuredContent['name']);
    $this->assertSame('Test Tool', $result->structuredContent['label']);
    $this->assertSame('A detailed test tool', $result->structuredContent['description']);
    $this->assertSame('mcp_tools', $result->structuredContent['provider']);
    $this->assertArrayHasKey('input_schema', $result->structuredContent);
    $this->assertArrayHasKey('annotations', $result->structuredContent);
  }

  public function testGetToolInfoResolvesPluginIdFormat(): void {
    $definition = $this->createToolDefinition(
      'mcp_tools:test_tool',
      'mcp_tools',
      'Test Tool',
      'Test',
    );

    $this->toolManager->method('getDefinitions')->willReturn([
      'mcp_tools:test_tool' => $definition,
    ]);

    $gateway = $this->createGateway();

    // Can resolve using plugin ID format.
    $result1 = $gateway->getToolInfo('mcp_tools:test_tool');
    $this->assertTrue($result1->structuredContent['success']);

    // Can resolve using MCP name format.
    $result2 = $gateway->getToolInfo('mcp_tools___test_tool');
    $this->assertTrue($result2->structuredContent['success']);
  }

  public function testGetToolInfoRespectsProviderFilter(): void {
    $definition = $this->createToolDefinition(
      'other:tool',
      'other_module',
      'Other Tool',
      'From another module',
    );

    $this->toolManager->method('getDefinitions')->willReturn([
      'other:tool' => $definition,
    ]);

    $gateway = $this->createGateway(includeAllTools: FALSE);
    $result = $gateway->getToolInfo('other___tool');

    $this->assertTrue($result->isError);
    $this->assertStringContainsString('Unknown tool', $result->structuredContent['error']);
  }

  public function testToolNameConstantsAreCorrect(): void {
    $this->assertSame('mcp_tools/discover-tools', ToolApiGateway::DISCOVER_TOOL);
    $this->assertSame('mcp_tools/get-tool-info', ToolApiGateway::GET_INFO_TOOL);
    $this->assertSame('mcp_tools/execute-tool', ToolApiGateway::EXECUTE_TOOL);
  }

}
