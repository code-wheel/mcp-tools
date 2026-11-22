<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Unit\Mcp;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\mcp_tools\Mcp\ToolApiGateway;
use Drupal\mcp_tools\Mcp\ToolApiSchemaConverter;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\Tests\UnitTestCase;
use Drupal\tool\Tool\ToolDefinition;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinition;
use Mcp\Server\RequestContext;
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

      /**
       * Drupal 12 added this method to the interface.
       */
      public function selectPluralForm(int|float $count, string $string, ?string $langcode = NULL): string {
        return $string;
      }

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

  /**
   * The execute tool says "read only" when the session can only read.
   *
   * It can run every tool, so it was always marked as one that may change
   * the site. A client that asks its user before such a call (Codex does)
   * then asks before every call in gateway mode, and refuses them all when
   * nobody is there to ask. A session without the write and admin scopes,
   * or a site in read-only mode, cannot change anything through it.
   */
  public function testExecuteToolHintFollowsWhatTheSessionMayDo(): void {
    $this->toolManager->method('getDefinitions')->willReturn([]);
    $hint = function (array $scopes, bool $readOnlyMode = FALSE): array {
      $access = $this->createMock(AccessManager::class);
      $access->method('hasScope')->willReturnCallback(static fn(string $scope): bool => in_array($scope, $scopes, TRUE));
      $access->method('isReadOnlyMode')->willReturn($readOnlyMode);
      $gateway = new ToolApiGateway($this->toolManager, $this->schemaConverter, $this->logger, FALSE, 'mcp_tools', NULL, NULL, $access);
      foreach ($gateway->getGatewayTools() as $tool) {
        if ($tool['name'] === ToolApiGateway::EXECUTE_TOOL) {
          return [$tool['annotations']->readOnlyHint, $tool['annotations']->destructiveHint];
        }
      }
      $this->fail('No execute tool.');
    };

    $this->assertSame([TRUE, FALSE], $hint(['read']), 'A read session can only read through it.');
    $this->assertSame([TRUE, FALSE], $hint(['read', 'write', 'admin'], TRUE), 'So can any session while the site is in read-only mode.');
    $this->assertFalse($hint(['read', 'write'])[0], 'With the write scope it may change the site.');
    $this->assertFalse($hint(['read', 'admin'])[0], 'With the admin scope too.');

    // A gateway that was built without the access manager knows nothing
    // of the session and keeps the careful answer.
    $plain = array_column($this->createGateway()->getGatewayTools(), 'annotations', 'name')[ToolApiGateway::EXECUTE_TOOL];
    $this->assertFalse($plain->readOnlyHint);
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
    $this->assertSame('overview', $result->structuredContent['mode']);
    $this->assertSame(0, $result->structuredContent['total']);
    $this->assertEmpty($result->structuredContent['groups']);
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

    // No query: a compact grouped overview, not the flat catalog.
    $this->assertSame('overview', $result->structuredContent['mode']);
    // The hint names the tool as the client sees it in the list.
    $this->assertStringContainsString(ToolApiGateway::GET_INFO_TOOL, $result->structuredContent['hint']);
    $this->assertStringNotContainsString('get-tool-info', $result->structuredContent['hint']);
    $this->assertSame(1, $result->structuredContent['total']);
    $this->assertSame('mcp_tools', $result->structuredContent['groups'][0]['provider']);
    $this->assertContains('mcp_tools___test_tool', $result->structuredContent['groups'][0]['examples']);

    // A query returns the flat listing with details.
    $queried = $gateway->discoverTools('test');
    $this->assertSame(1, $queried->structuredContent['count']);
    $this->assertSame('mcp_tools___test_tool', $queried->structuredContent['tools'][0]['name']);
    $this->assertSame('Test Tool', $queried->structuredContent['tools'][0]['label']);
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

    $this->assertSame(1, $result->structuredContent['total']);
    $this->assertSame(['mcp_tools'], array_column($result->structuredContent['groups'], 'provider'));
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

    $this->assertSame(2, $result->structuredContent['total']);
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
    $this->assertSame(1, $result->structuredContent['total_matches']);
    $this->assertSame('Create Content', $result->structuredContent['tools'][0]['label']);
  }

  /**
   * A question in the model's own words still finds the tool.
   *
   * The search took the whole query as one piece of text. A local model
   * asked for "node type", found nothing, and gave up on a task the
   * "content type" tool would have done. Words are now tried one by one
   * when the phrase is nowhere, and an empty answer says what to try next.
   */
  public function testDiscoverToolsFindsByWordsWhenThePhraseIsNowhere(): void {
    $this->toolManager->method('getDefinitions')->willReturn([
      'mcp_structure:create_content_type' => $this->createToolDefinition('mcp_structure:create_content_type', 'mcp_tools_structure', 'Create Content Type', 'Create a new content type.'),
      'mcp_structure:add_field' => $this->createToolDefinition('mcp_structure:add_field', 'mcp_tools_structure', 'Add Field', 'Add a field to a content type or another bundle.'),
      'mcp_cache:clear_all' => $this->createToolDefinition('mcp_cache:clear_all', 'mcp_tools_cache', 'Clear All Caches', 'Clear every cache.'),
    ]);
    $gateway = $this->createGateway();
    $labels = static fn($result): array => array_column($result->structuredContent['tools'], 'label');

    // The phrase itself, as before.
    $phrase = $gateway->discoverTools('content type');
    $this->assertSame(['Create Content Type', 'Add Field'], $labels($phrase));
    $this->assertArrayNotHasKey('match', $phrase->structuredContent);

    // Every word, in any order.
    $all = $gateway->discoverTools('create type');
    $this->assertSame(['Create Content Type'], $labels($all));
    $this->assertSame('all words', $all->structuredContent['match']);

    // Some of the words: the tools with the most of them come first.
    $any = $gateway->discoverTools('node type');
    $this->assertSame(['Create Content Type', 'Add Field'], $labels($any));
    $this->assertSame('some words', $any->structuredContent['match']);
    $this->assertSame(2, $any->structuredContent['total_matches']);

    // One word that says something next to one that says nothing: "cache
    // tools", "the caches". The one word is enough to search by.
    $one = $gateway->discoverTools('cache tools');
    $this->assertSame(['Clear All Caches'], $labels($one));
    $this->assertSame('all words', $one->structuredContent['match']);

    // Nothing at all: say so, and say what there is.
    $none = $gateway->discoverTools('zebra crossing');
    $this->assertSame(0, $none->structuredContent['count']);
    $this->assertStringContainsString('No tool matches', $none->structuredContent['hint']);
    $this->assertStringContainsString('mcp_tools_structure', $none->structuredContent['hint']);
    $this->assertFalse($none->isError);
  }

  /**
   * A found tool comes with the names of its inputs.
   *
   * A model that has found a tool often calls it at once and guesses the
   * names of the arguments: "title" where the search tool takes "query".
   * In the runs of five local models that was half of all failed calls.
   */
  public function testDiscoverToolsNamesTheInputsOfEachTool(): void {
    $search = new ToolDefinition([
      'id' => 'mcp_tools_search_content',
      'provider' => 'mcp_tools',
      'label' => $this->markup('Search Content'),
      'description' => $this->markup('Search for content by title text.'),
      'operation' => ToolOperation::Read,
      'destructive' => FALSE,
      'input_definitions' => [
        'query' => new InputDefinition('string', $this->markup('Query'), $this->markup('Text to search for.'), TRUE),
        'limit' => new InputDefinition('integer', $this->markup('Limit'), $this->markup('How many to return.'), FALSE),
      ],
    ]);
    $status = $this->createToolDefinition('mcp_tools_search_status', 'mcp_tools', 'Search Status', 'Takes no input.');
    $this->toolManager->method('getDefinitions')->willReturn([
      'mcp_tools_search_content' => $search,
      'mcp_tools_search_status' => $status,
    ]);

    $tools = array_column($this->createGateway()->discoverTools('search')->structuredContent['tools'], NULL, 'name');

    $this->assertSame(['required' => ['query'], 'optional' => ['limit']], $tools['mcp_tools_search_content']['inputs']);
    $this->assertSame(['required' => [], 'optional' => []], $tools['mcp_tools_search_status']['inputs']);
  }

  /**
   * One of the gateway's own tools, named in the run tool, is explained.
   *
   * A model asked the run tool to run the find tool. The answer was
   * "Unknown tool", with the advice to use the tool it had just named.
   */
  public function testExecuteToolSaysAGatewayToolIsCalledDirectly(): void {
    $this->toolManager->method('getDefinitions')->willReturn([]);
    // The answer comes before the context is used, and the class is final.
    $context = (new \ReflectionClass(RequestContext::class))->newInstanceWithoutConstructor();

    foreach ([ToolApiGateway::DISCOVER_TOOL, ToolApiGateway::GET_INFO_TOOL, ToolApiGateway::EXECUTE_TOOL] as $name) {
      $result = $this->createGateway()->executeTool($name, ['query' => 'content'], $context);

      $this->assertTrue($result->isError);
      $this->assertStringContainsString($name . ' is not run through ' . ToolApiGateway::EXECUTE_TOOL, $result->structuredContent['error']);
      $this->assertStringNotContainsString('Unknown tool', $result->structuredContent['error']);
    }
  }

  /**
   * The tools whose name has the search words come first.
   *
   * Matches came in the order of the catalog. A model that asked for "view"
   * with a limit of ten got the config tool first ("View a specific
   * configuration object") and never saw the tool that makes a view of a
   * content type. It asked for "content" and did not get the tool that
   * creates content among forty-six matches.
   */
  public function testDiscoverToolsPutsNameMatchesFirst(): void {
    $catalog = [
      ['mcp_tools_get_config', 'Get Configuration', 'View a specific configuration object by name.'],
      ['mcp_config_preview', 'Preview Config Changes', 'Show what an import would change.'],
      ['mcp_analysis_security', 'Security Audit', 'Review permissions and exposed views of data.'],
      ['mcp_batch_create_content', 'Batch Create Content', 'Create many content items at once.'],
      ['mcp_structure_scaffold_content_type', 'Scaffold Content Type', 'Create a content type with fields.'],
      ['mcp_create_content_list_view', 'Create Content List View', 'Create a view that lists content of one type.'],
      ['mcp_create_view', 'Create View', 'Create a new view.'],
      ['mcp_create_content', 'Create Content', 'Create a content item.'],
      ['mcp_publish_content', 'Publish/Unpublish Content', 'Change the publish status of a content item.'],
    ];
    $definitions = [];
    foreach ($catalog as [$id, $label, $description]) {
      $definitions[$id] = $this->createToolDefinition($id, 'mcp_tools', $label, $description);
    }
    $this->toolManager->method('getDefinitions')->willReturn($definitions);
    $gateway = $this->createGateway();
    $names = static fn(string $query, int $limit): array => array_column($gateway->discoverTools($query, $limit)->structuredContent['tools'], 'name');

    // A name that has the word beats a description that has it, and a
    // short name that is mostly the word beats a long one.
    $this->assertSame(['mcp_create_view', 'mcp_create_content_list_view'], $names('view', 2));
    $this->assertSame('mcp_create_content', $names('content', 1)[0]);
    // More words of the question in the name is better still.
    $this->assertSame('mcp_create_content_list_view', $names('content view', 1)[0]);
    $this->assertSame('mcp_structure_scaffold_content_type', $names('content type', 1)[0]);
    // "view" is not in "preview", and a word in the label counts too.
    $this->assertNotContains('mcp_config_preview', $names('view', 2));
    $this->assertSame('mcp_publish_content', $names('unpublish', 1)[0]);
    // Nothing is dropped: the description matches follow.
    $this->assertSame(['mcp_create_view', 'mcp_create_content_list_view', 'mcp_tools_get_config', 'mcp_config_preview', 'mcp_analysis_security'], $names('view', 25));
  }

  /**
   * A plural word finds what its singular finds.
   *
   * In the runs of 2026-11-21 a model asked for "views", "users" and
   * "content types". The tools are named ..._view, ..._user and
   * ..._content_type, so a plural found only the tools that happen to have
   * it in a description, in catalog order.
   */
  public function testAPluralWordFindsWhatItsSingularFinds(): void {
    $catalog = [
      ['mcp_templates_apply', 'Apply Template', 'Apply a site template: content types, views and roles in one step.'],
      ['mcp_create_view', 'Create View', 'Create a new view.'],
      ['mcp_create_content_list_view', 'Create Content List View', 'Create a view that lists content of one type.'],
      ['mcp_structure_create_content_type', 'Create Content Type', 'Create a new content type.'],
      ['mcp_tools_list_content_types', 'List Content Types', 'List all content types.'],
      ['mcp_pathauto_create', 'Create Pattern', 'Create a URL alias pattern.'],
      ['mcp_tools_get_vocabularies', 'Get Vocabularies', 'List the taxonomy vocabularies.'],
      ['mcp_structure_create_vocabulary', 'Create Vocabulary', 'Create a taxonomy vocabulary.'],
      ['mcp_tools_get_site_status', 'Get Site Status', 'Get the status of the site.'],
      ['mcp_tools_get_menu_tree', 'Get Menu Tree', 'Get the hierarchical structure of a specific menu.'],
      ['mcp_tools_get_menus', 'Get Menus', 'List all menus.'],
    ];
    $definitions = [];
    foreach ($catalog as [$id, $label, $description]) {
      $definitions[$id] = $this->createToolDefinition($id, 'mcp_tools', $label, $description);
    }
    $this->toolManager->method('getDefinitions')->willReturn($definitions);
    $gateway = $this->createGateway();
    $names = static fn(string $query): array => array_column($gateway->discoverTools($query, 25)->structuredContent['tools'], 'name');

    // A plural that ends in -us: on the real catalog "menus" did not find
    // the menu tree tool, and the first version of this change left every
    // word in -us alone for the sake of "status".
    $this->assertSame(['mcp_tools_get_menus', 'mcp_tools_get_menu_tree'], $names('menus'));

    // The tools that make a view come first, the one that only speaks of
    // views after them.
    $this->assertSame(['mcp_create_view', 'mcp_create_content_list_view', 'mcp_templates_apply'], $names('views'));
    // The last word of a phrase.
    $this->assertSame(['mcp_tools_list_content_types', 'mcp_structure_create_content_type', 'mcp_templates_apply'], $names('content types'));
    // -ies and -es.
    $this->assertSame(['mcp_tools_get_vocabularies', 'mcp_structure_create_vocabulary'], $names('vocabularies'));
    $this->assertSame(['mcp_pathauto_create'], $names('aliases'));
    // A word that only ends in s still finds itself, and a short one is
    // left alone: "news" must not become "new" and find every "Create a
    // new ...".
    $this->assertSame(['mcp_tools_get_site_status'], $names('status'));
    $this->assertSame([], $names('news'));
    $this->assertNotContains('mcp_create_view', $names('site news'));
    // A tool name as the question is still found as it is.
    $this->assertSame(['mcp_tools_list_content_types'], $names('mcp_tools_list_content_types'));
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
    $result = $gateway->discoverTools('operation');

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

  /**
   * A made-up name is answered with the closest names and where to look.
   */
  public function testGetToolInfoAnswersAMadeUpNameWithTheClosestNames(): void {
    $this->toolManager->method('getDefinitions')->willReturn([
      'mcp_tools_list_content_types' => $this->createToolDefinition('mcp_tools_list_content_types', 'mcp_tools', 'List Content Types', 'List the content types.'),
      'mcp_cache:clear_all' => $this->createToolDefinition('mcp_cache:clear_all', 'mcp_tools_cache', 'Clear All Caches', 'Clear every cache.'),
    ]);

    $result = $this->createGateway()->getToolInfo('mcp_content_types_list');

    $this->assertTrue($result->isError);
    $this->assertSame(['mcp_tools_list_content_types'], $result->structuredContent['did_you_mean']);
    $this->assertSame('Unknown tool: mcp_content_types_list. Did you mean mcp_tools_list_content_types? Find tools with mcp_tools_discover_tools.', $result->structuredContent['error']);

    // A name with only "tools_" left out is described, under its real name.
    $short = $this->createGateway()->getToolInfo('mcp_list_content_types');
    $this->assertFalse($short->isError, json_encode($short->structuredContent));
    $this->assertSame('mcp_tools_list_content_types', $short->structuredContent['name']);

    $none = $this->createGateway()->getToolInfo('zebra_crossing');
    $this->assertSame('Unknown tool: zebra_crossing. Find tools with mcp_tools_discover_tools.', $none->structuredContent['error']);
    $this->assertArrayNotHasKey('did_you_mean', $none->structuredContent);
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
    $this->assertSame('mcp_tools_discover_tools', ToolApiGateway::DISCOVER_TOOL);
    $this->assertSame('mcp_tools_get_tool_info', ToolApiGateway::GET_INFO_TOOL);
    $this->assertSame('mcp_tools_execute_tool', ToolApiGateway::EXECUTE_TOOL);
  }


  public function testDiscoverToolsLimitTruncatesWithHint(): void {
    $this->toolManager->method('getDefinitions')->willReturn([
      'mcp_tools:alpha_thing' => $this->createToolDefinition('mcp_tools:alpha_thing', 'mcp_tools', 'Alpha Thing', 'Shared keyword widget'),
      'mcp_tools:beta_thing' => $this->createToolDefinition('mcp_tools:beta_thing', 'mcp_tools', 'Beta Thing', 'Shared keyword widget'),
    ]);

    $gateway = $this->createGateway();
    $result = $gateway->discoverTools('widget', 1);

    $this->assertSame(1, $result->structuredContent['count']);
    $this->assertSame(2, $result->structuredContent['total_matches']);
    $this->assertStringContainsString('truncated', $result->structuredContent['hint']);
  }

}
