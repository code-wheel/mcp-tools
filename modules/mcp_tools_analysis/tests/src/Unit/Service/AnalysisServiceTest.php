<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_analysis\Unit\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Database\StatementInterface;
use Drupal\mcp_tools_analysis\Service\AnalysisService;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use Symfony\Component\HttpFoundation\RequestStack;

#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools_analysis\Service\AnalysisService::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_analysis')]
final class AnalysisServiceTest extends UnitTestCase {

  private function createService(array $overrides = []): AnalysisService {
    return new AnalysisService(
      $overrides['entity_type_manager'] ?? $this->createMock(EntityTypeManagerInterface::class),
      $overrides['database'] ?? $this->createMock(Connection::class),
      $overrides['http_client'] ?? $this->createMock(ClientInterface::class),
      $overrides['config_factory'] ?? $this->createMock(ConfigFactoryInterface::class),
      $overrides['module_handler'] ?? $this->createMock(ModuleHandlerInterface::class),
      $overrides['request_stack'] ?? $this->createMock(RequestStack::class),
    );
  }

  public function testFindBrokenLinksFailsWhenAllowedHostsEmpty(): void {
    $settings = $this->createMock(ImmutableConfig::class);
    $settings->method('get')->with('allowed_hosts')->willReturn([]);

    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->with('mcp_tools.settings')->willReturn($settings);

    $service = $this->createService([
      'config_factory' => $configFactory,
    ]);

    $result = $service->findBrokenLinks(10, 'https://example.com');
    $this->assertFalse($result['success']);
    $this->assertSame('URL_FETCH_DISABLED', $result['code']);
  }

  public function testFindBrokenLinksFailsWhenBaseUrlMissing(): void {
    $settings = $this->createMock(ImmutableConfig::class);
    $settings->method('get')->with('allowed_hosts')->willReturn(['example.com']);

    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->with('mcp_tools.settings')->willReturn($settings);

    $requestStack = $this->createMock(RequestStack::class);
    $requestStack->method('getCurrentRequest')->willReturn(NULL);

    $service = $this->createService([
      'config_factory' => $configFactory,
      'request_stack' => $requestStack,
    ]);

    $result = $service->findBrokenLinks(10, NULL);
    $this->assertFalse($result['success']);
    $this->assertSame('MISSING_BASE_URL', $result['code']);
  }

  public function testFindBrokenLinksFailsWhenHostNotAllowed(): void {
    $settings = $this->createMock(ImmutableConfig::class);
    $settings->method('get')->with('allowed_hosts')->willReturn(['allowed.example']);

    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->with('mcp_tools.settings')->willReturn($settings);

    $service = $this->createService([
      'config_factory' => $configFactory,
    ]);

    $result = $service->findBrokenLinks(10, 'https://example.com');
    $this->assertFalse($result['success']);
    $this->assertSame('HOST_NOT_ALLOWED', $result['code']);
    $this->assertSame('example.com', $result['host']);
  }

  public function testFindBrokenLinksDetectsBrokenInternalLink(): void {
    $settings = $this->createMock(ImmutableConfig::class);
    $settings->method('get')->with('allowed_hosts')->willReturn(['example.com']);

    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->with('mcp_tools.settings')->willReturn($settings);

    $node = new FakeNode(1, 'Test node', [
      'body' => new FakeFieldItemList('text_long', [
        (object) ['value' => '<p><a href="/missing">Missing</a></p>'],
      ]),
    ]);

    $query = $this->createMock(QueryInterface::class);
    $query->method('condition')->willReturnSelf();
    $query->method('accessCheck')->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('execute')->willReturn([1]);

    $nodeStorage = $this->createMock(EntityStorageInterface::class);
    $nodeStorage->method('getQuery')->willReturn($query);
    $nodeStorage->method('loadMultiple')->with([1])->willReturn([1 => $node]);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('node')->willReturn($nodeStorage);

    $httpClient = $this->createMock(ClientInterface::class);
    $httpClient->expects($this->once())
      ->method('request')
      ->with(
        'HEAD',
        'https://example.com/missing',
        $this->isType('array'),
      )
      ->willReturn(new Response(404));

    $service = $this->createService([
      'entity_type_manager' => $entityTypeManager,
      'http_client' => $httpClient,
      'config_factory' => $configFactory,
    ]);

    $result = $service->findBrokenLinks(10, 'https://example.com');
    $this->assertTrue($result['success']);
    $this->assertSame(1, $result['data']['total_checked']);
    $this->assertSame(1, $result['data']['broken_count']);
    $this->assertSame('/missing', $result['data']['broken_links'][0]['url']);
    $this->assertSame(404, $result['data']['broken_links'][0]['status']);
    $this->assertSame(1, $result['data']['broken_links'][0]['source_nid']);
  }

  public function testContentAuditReturnsStaleAndDraftsFallback(): void {
    $staleNode = new FakeNode(
      1,
      'Stale node',
      [],
      'article',
      createdTime: time() - 800 * 86400,
      changedTime: time() - 400 * 86400,
    );
    $orphanedNode = new FakeNode(
      2,
      'Orphaned node',
      [],
      'article',
      createdTime: time() - 30 * 86400,
      changedTime: time() - 30 * 86400,
      published: FALSE,
    );

    $staleQuery = $this->createMock(QueryInterface::class);
    $staleQuery->method('condition')->willReturnSelf();
    $staleQuery->method('accessCheck')->willReturnSelf();
    $staleQuery->method('range')->willReturnSelf();
    $staleQuery->method('execute')->willReturn([1]);

    $orphanedQuery = $this->createMock(QueryInterface::class);
    $orphanedQuery->method('condition')->willReturnSelf();
    $orphanedQuery->method('accessCheck')->willReturnSelf();
    $orphanedQuery->method('range')->willReturnSelf();
    $orphanedQuery->method('execute')->willReturn([2]);

    $nodeStorage = $this->createMock(EntityStorageInterface::class);
    $nodeStorage->expects($this->exactly(2))
      ->method('getQuery')
      ->willReturnOnConsecutiveCalls($staleQuery, $orphanedQuery);
    $nodeStorage->method('loadMultiple')->willReturnMap([
      [[1], [1 => $staleNode]],
      [[2], [2 => $orphanedNode]],
    ]);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('node')->willReturn($nodeStorage);
    $entityTypeManager->method('hasDefinition')->with('content_moderation_state')->willReturn(FALSE);

    $service = $this->createService([
      'entity_type_manager' => $entityTypeManager,
    ]);

    $result = $service->contentAudit();
    $this->assertTrue($result['success']);
    $this->assertSame(1, $result['data']['stale_count']);
    $this->assertSame(1, $result['data']['orphaned_count']);
    $this->assertSame(1, $result['data']['draft_count']);
    $this->assertSame($result['data']['orphaned_content'], $result['data']['drafts']);
  }

  public function testAnalyzeSeoFlagsMissingMetaDescriptionAndAltText(): void {
    $entity = new FakeNode(1, 'Short title', [
      'body' => new FakeFieldItemList('text_long', [
        (object) ['value' => '<p>Intro</p><img src="/a.png"><p>More</p>'],
      ]),
    ]);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with(1)->willReturn($entity);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('node')->willReturn($storage);

    $moduleHandler = $this->createMock(ModuleHandlerInterface::class);
    $moduleHandler->method('moduleExists')->with('metatag')->willReturn(FALSE);

    $service = $this->createService([
      'entity_type_manager' => $entityTypeManager,
      'module_handler' => $moduleHandler,
    ]);

    $result = $service->analyzeSeo('node', 1);
    $this->assertTrue($result['success']);
    $issues = $result['data']['issues'] ?? [];
    $types = array_column($issues, 'type');
    $this->assertContains('missing_meta_description', $types);
    $this->assertContains('missing_alt_text', $types);
  }

  public function testAnalyzeSeoParsesMetatagDescriptionWhenSafe(): void {
    $metatag = serialize(['description' => str_repeat('x', 130)]);
    $entity = new FakeNode(1, str_repeat('A', 40), [
      'field_metatag' => new FakeFieldItemList('metatag', [
        (object) ['value' => $metatag],
      ]),
      'body' => new FakeFieldItemList('text_long', [
        (object) ['value' => '<h2>Heading</h2><p>' . str_repeat('word ', 310) . '</p>'],
      ]),
    ]);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with(1)->willReturn($entity);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('node')->willReturn($storage);

    $moduleHandler = $this->createMock(ModuleHandlerInterface::class);
    $moduleHandler->method('moduleExists')->with('metatag')->willReturn(TRUE);

    $service = $this->createService([
      'entity_type_manager' => $entityTypeManager,
      'module_handler' => $moduleHandler,
    ]);

    $result = $service->analyzeSeo('node', 1);
    $this->assertTrue($result['success']);

    $types = array_column($result['data']['issues'] ?? [], 'type');
    $this->assertNotContains('missing_meta_description', $types);
  }

  public function testSecurityAuditReportsIssuesAndWarnings(): void {
    $anonymous = new class() {
      public function id(): string { return 'anonymous'; }
      public function label(): string { return 'Anonymous'; }
      public function getPermissions(): array { return ['administer nodes']; }
    };
    $authenticated = new class() {
      public function id(): string { return 'authenticated'; }
      public function label(): string { return 'Authenticated'; }
      public function getPermissions(): array { return ['administer users']; }
    };
    $editor = new class() {
      public function id(): string { return 'editor'; }
      public function label(): string { return 'Editor'; }
      public function getPermissions(): array { return ['bypass node access']; }
    };

    $roleStorage = $this->createMock(EntityStorageInterface::class);
    $roleStorage->method('load')->willReturnMap([
      ['anonymous', $anonymous],
      ['authenticated', $authenticated],
    ]);
    $roleStorage->method('loadMultiple')->willReturn([$anonymous, $authenticated, $editor]);

    $adminQuery = $this->createMock(QueryInterface::class);
    $adminQuery->method('condition')->willReturnSelf();
    $adminQuery->method('accessCheck')->willReturnSelf();
    $adminQuery->method('execute')->willReturn([1, 2, 3, 4, 5, 6]);

    $blockedQuery = $this->createMock(QueryInterface::class);
    $blockedQuery->method('condition')->willReturnSelf();
    $blockedQuery->method('accessCheck')->willReturnSelf();
    $blockedQuery->method('execute')->willReturn([7]);

    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->expects($this->exactly(2))
      ->method('getQuery')
      ->willReturnOnConsecutiveCalls($adminQuery, $blockedQuery);

    $userSettings = $this->createMock(ImmutableConfig::class);
    $userSettings->method('get')->with('register')->willReturn('visitors');

    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->willReturnMap([
      ['user.settings', $userSettings],
    ]);

    $moduleHandler = $this->createMock(ModuleHandlerInterface::class);
    $moduleHandler->method('moduleExists')->willReturnMap([
      ['php', TRUE],
    ]);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->willReturnMap([
      ['user_role', $roleStorage],
      ['user', $userStorage],
    ]);

    $service = $this->createService([
      'entity_type_manager' => $entityTypeManager,
      'config_factory' => $configFactory,
      'module_handler' => $moduleHandler,
    ]);

    $result = $service->securityAudit();
    $this->assertTrue($result['success']);
    $this->assertGreaterThan(0, $result['data']['critical_count']);
    $this->assertGreaterThan(0, $result['data']['warning_count']);
  }

  public function testFindUnusedFieldsDetectsUnusedCustomField(): void {
    $fieldConfig = new class() {
      public function getTargetEntityTypeId(): string { return 'node'; }
      public function getTargetBundle(): string { return 'article'; }
      public function getName(): string { return 'field_unused'; }
      public function getType(): string { return 'string'; }
      public function getLabel(): string { return 'Unused'; }
    };

    $fieldConfigStorage = $this->createMock(EntityStorageInterface::class);
    $fieldConfigStorage->method('loadMultiple')->willReturn([$fieldConfig]);

    $query = $this->createMock(QueryInterface::class);
    $query->method('condition')->willReturnSelf();
    $query->method('accessCheck')->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('count')->willReturnSelf();
    $query->method('execute')->willReturn(0);

    $nodeStorage = $this->createMock(EntityStorageInterface::class);
    $nodeStorage->method('getQuery')->willReturn($query);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->willReturnMap([
      ['field_config', $fieldConfigStorage],
      ['node', $nodeStorage],
    ]);

    $service = $this->createService([
      'entity_type_manager' => $entityTypeManager,
    ]);

    $result = $service->findUnusedFields();
    $this->assertTrue($result['success']);
    $this->assertSame(1, $result['data']['unused_count']);
    $this->assertSame('field_unused', $result['data']['unused_fields'][0]['field_name']);
  }

  public function testAnalyzePerformanceIncludesCacheSuggestionsAndDbInfo(): void {
    $perfConfig = $this->createMock(ImmutableConfig::class);
    $perfConfig->method('get')->willReturnMap([
      ['cache.page.max_age', 0],
      ['css.preprocess', FALSE],
      ['js.preprocess', FALSE],
    ]);

    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->with('system.performance')->willReturn($perfConfig);

    $moduleHandler = $this->createMock(ModuleHandlerInterface::class);
    $moduleHandler->method('moduleExists')->with('dblog')->willReturn(FALSE);

    $statement = $this->createMock(StatementInterface::class);
    $statement->method('fetchAll')->willReturn([
      (object) ['table_name' => 'cache_data', 'size_mb' => 12.3],
    ]);

    $database = $this->createMock(Connection::class);
    $database->method('query')->willReturn($statement);

    $service = $this->createService([
      'config_factory' => $configFactory,
      'module_handler' => $moduleHandler,
      'database' => $database,
    ]);

    $result = $service->analyzePerformance();
    $this->assertTrue($result['success']);
    $this->assertSame('cache_data', $result['data']['database']['largest_tables'][0]['table']);
    $this->assertNotEmpty($result['data']['suggestions']);
  }

  public function testCheckAccessibilityFindsCommonIssues(): void {
    $entity = new FakeNode(1, 'A11y page', [
      'body' => new FakeFieldItemList('text_long', [
        (object) ['value' => implode(' ', [
          '<img src="/a.png">',
          '<h2>Start</h2><h4>Skip</h4>',
          '<a href="/x"></a>',
          '<a href="/y">Click here</a>',
          '<table><tr><td>A</td></tr></table>',
          '<p>Click the colored button</p>',
        ])],
      ]),
    ]);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with(1)->willReturn($entity);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('node')->willReturn($storage);

    $service = $this->createService([
      'entity_type_manager' => $entityTypeManager,
    ]);

    $result = $service->checkAccessibility('node', 1);
    $this->assertTrue($result['success']);
    $types = array_column($result['data']['issues'] ?? [], 'type');
    $this->assertContains('missing_alt', $types);
    $this->assertContains('heading_skip', $types);
    $this->assertContains('empty_link', $types);
    $this->assertContains('generic_link_text', $types);
    $this->assertContains('table_no_headers', $types);
    $this->assertContains('color_reference', $types);
  }

  public function testFindDuplicateContentDetectsDuplicateTitles(): void {
    $nodeTypeStorage = $this->createMock(EntityStorageInterface::class);
    $nodeTypeStorage->method('load')->with('article')->willReturn(new \stdClass());

    $query = $this->createMock(QueryInterface::class);
    $query->method('condition')->willReturnSelf();
    $query->method('accessCheck')->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('execute')->willReturn([1, 2]);

    $nodeStorage = $this->createMock(EntityStorageInterface::class);
    $nodeStorage->method('getQuery')->willReturn($query);
    $nodeStorage->method('loadMultiple')->with([1, 2])->willReturn([
      1 => new FakeNode(1, 'Hello world', [], 'article'),
      2 => new FakeNode(2, 'Hello world', [], 'article'),
    ]);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->willReturnMap([
      ['node', $nodeStorage],
      ['node_type', $nodeTypeStorage],
    ]);

    $service = $this->createService([
      'entity_type_manager' => $entityTypeManager,
    ]);

    $result = $service->findDuplicateContent('article', 'title', 0.8);
    $this->assertTrue($result['success']);
    $this->assertSame(1, $result['data']['duplicate_count']);
  }

}

final class FakeFieldDefinition {

  public function __construct(private readonly string $type) {}

  public function getType(): string {
    return $this->type;
  }

}

final class FakeFieldItemList implements \IteratorAggregate {

  /**
   * @param object[] $items
   */
  public function __construct(
    private readonly string $type,
    private readonly array $items,
  ) {}

  public function getFieldDefinition(): FakeFieldDefinition {
    return new FakeFieldDefinition($this->type);
  }

  public function __get(string $name): mixed {
    $first = $this->items[0] ?? NULL;
    if (is_array($first)) {
      return $first[$name] ?? NULL;
    }
    if (is_object($first)) {
      return $first->{$name} ?? NULL;
    }
    return NULL;
  }

  /**
   * @return array<int, array<string, mixed>>
   */
  public function getValue(): array {
    $values = [];
    foreach ($this->items as $item) {
      if (is_array($item)) {
        $values[] = $item;
        continue;
      }
      if (is_object($item)) {
        $values[] = get_object_vars($item);
        continue;
      }
      $values[] = ['value' => $item];
    }
    return $values;
  }

  public function getIterator(): \Traversable {
    return new \ArrayIterator($this->items);
  }

}

final class FakeNode {

  /**
   * @param array<string, object> $fields
   */
  public function __construct(
    private readonly int $id,
    private readonly string $title,
    private readonly array $fields,
    private readonly string $bundle = 'page',
    private readonly int $createdTime = 0,
    private readonly int $changedTime = 0,
    private readonly bool $published = TRUE,
    private readonly string $ownerName = 'admin',
  ) {}

  public function id(): int {
    return $this->id;
  }

  public function label(): string {
    return $this->getTitle();
  }

  public function getTitle(): string {
    return $this->title;
  }

  public function bundle(): string {
    return $this->bundle;
  }

  public function getCreatedTime(): int {
    return $this->createdTime ?: time();
  }

  public function getChangedTime(): int {
    return $this->changedTime ?: time();
  }

  public function isPublished(): bool {
    return $this->published;
  }

  public function hasField(string $name): bool {
    return array_key_exists($name, $this->fields);
  }

  public function get(string $name): mixed {
    return $this->fields[$name] ?? new FakeFieldItemList('string', []);
  }

  public function getOwner(): object {
    return new class($this->ownerName) {
      public function __construct(private readonly string $name) {}
      public function getDisplayName(): string { return $this->name; }
    };
  }

  /**
   * @return array<string, object>
   *   A map of field name to a field item list-like object.
   */
  public function getFields(): array {
    return $this->fields;
  }

}
