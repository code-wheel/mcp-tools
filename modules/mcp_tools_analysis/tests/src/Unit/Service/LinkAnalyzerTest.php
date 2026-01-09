<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_analysis\Unit\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\mcp_tools_analysis\Service\LinkAnalyzer;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\ClientInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests for LinkAnalyzer.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools_analysis\Service\LinkAnalyzer::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_analysis')]
final class LinkAnalyzerTest extends UnitTestCase {

  private EntityTypeManagerInterface $entityTypeManager;
  private ClientInterface $httpClient;
  private ConfigFactoryInterface $configFactory;
  private RequestStack $requestStack;
  private LinkAnalyzer $analyzer;

  protected function setUp(): void {
    parent::setUp();
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->httpClient = $this->createMock(ClientInterface::class);
    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->requestStack = $this->createMock(RequestStack::class);

    $this->analyzer = new LinkAnalyzer(
      $this->entityTypeManager,
      $this->httpClient,
      $this->configFactory,
      $this->requestStack,
    );
  }

  public function testFindBrokenLinksNoAllowedHosts(): void {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturnMap([
      ['allowed_hosts', []],
    ]);
    $this->configFactory->method('get')->willReturn($config);

    $result = $this->analyzer->findBrokenLinks();

    $this->assertFalse($result['success']);
    $this->assertSame('URL_FETCH_DISABLED', $result['code']);
  }

  public function testFindBrokenLinksMissingBaseUrl(): void {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->with('allowed_hosts')->willReturn(['example.com']);
    $this->configFactory->method('get')->willReturn($config);
    $this->requestStack->method('getCurrentRequest')->willReturn(NULL);

    $result = $this->analyzer->findBrokenLinks();

    $this->assertFalse($result['success']);
    $this->assertSame('MISSING_BASE_URL', $result['code']);
  }

  public function testFindBrokenLinksInvalidBaseUrl(): void {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->with('allowed_hosts')->willReturn(['example.com']);
    $this->configFactory->method('get')->willReturn($config);
    $this->requestStack->method('getCurrentRequest')->willReturn(NULL);

    $result = $this->analyzer->findBrokenLinks(100, 'not-a-valid-url');

    $this->assertFalse($result['success']);
    $this->assertSame('INVALID_BASE_URL', $result['code']);
  }

  public function testFindBrokenLinksHostNotAllowed(): void {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->with('allowed_hosts')->willReturn(['allowed.com']);
    $this->configFactory->method('get')->willReturn($config);
    $this->requestStack->method('getCurrentRequest')->willReturn(NULL);

    $result = $this->analyzer->findBrokenLinks(100, 'https://notallowed.com');

    $this->assertFalse($result['success']);
    $this->assertSame('HOST_NOT_ALLOWED', $result['code']);
    $this->assertSame('notallowed.com', $result['host']);
  }

  public function testFindBrokenLinksWithValidBaseUrl(): void {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->with('allowed_hosts')->willReturn(['example.com']);
    $this->configFactory->method('get')->willReturn($config);

    $request = $this->createMock(Request::class);
    $request->method('getSchemeAndHttpHost')->willReturn('https://example.com');
    $this->requestStack->method('getCurrentRequest')->willReturn($request);

    // Mock node storage returning empty results.
    $query = $this->createMock(\Drupal\Core\Entity\Query\QueryInterface::class);
    $query->method('condition')->willReturnSelf();
    $query->method('accessCheck')->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('execute')->willReturn([]);

    $nodeStorage = $this->createMock(\Drupal\Core\Entity\EntityStorageInterface::class);
    $nodeStorage->method('getQuery')->willReturn($query);
    $nodeStorage->method('loadMultiple')->willReturn([]);

    $this->entityTypeManager->method('getStorage')->with('node')->willReturn($nodeStorage);

    $result = $this->analyzer->findBrokenLinks();

    $this->assertTrue($result['success']);
    $this->assertSame(0, $result['data']['total_checked']);
  }

}
