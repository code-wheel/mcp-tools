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
use Drupal\mcp_tools_analysis\Service\AnalysisService;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @coversDefaultClass \Drupal\mcp_tools_analysis\Service\AnalysisService
 * @group mcp_tools_analysis
 */
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

  /**
   * @covers ::findBrokenLinks
   */
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

  /**
   * @covers ::findBrokenLinks
   */
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

  /**
   * @covers ::findBrokenLinks
   */
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

  /**
   * @covers ::findBrokenLinks
   */
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
  ) {}

  public function id(): int {
    return $this->id;
  }

  public function getTitle(): string {
    return $this->title;
  }

  /**
   * @return array<string, object>
   *   A map of field name to a field item list-like object.
   */
  public function getFields(): array {
    return $this->fields;
  }

}

