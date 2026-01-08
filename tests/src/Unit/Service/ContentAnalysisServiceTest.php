<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Unit\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Database\Connection;
use Drupal\mcp_tools\Service\ContentAnalysisService;
use Drupal\Tests\UnitTestCase;

#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools\Service\ContentAnalysisService::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
final class ContentAnalysisServiceTest extends UnitTestCase {

  private function createService(): ContentAnalysisService {
    return new ContentAnalysisService(
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(EntityFieldManagerInterface::class),
      $this->createMock(Connection::class),
    );
  }

  public function testSearchContentRequiresMinimumLength(): void {
    $service = $this->createService();
    $result = $service->searchContent('ab');

    $this->assertArrayHasKey('error', $result);
    $this->assertSame([], $result['results']);
  }

  public function testSimplifyFieldValueMapsCommonTypes(): void {
    $service = $this->createService();
    $method = new \ReflectionMethod($service, 'simplifyFieldValue');

    $this->assertNull($method->invoke($service, [], 'string'));
    $this->assertSame('x', $method->invoke($service, [['value' => 'x']], 'string'));
    $this->assertSame(TRUE, $method->invoke($service, [['value' => 1]], 'boolean'));
    $this->assertSame([1, 2], $method->invoke($service, [['target_id' => 1], ['target_id' => 2]], 'entity_reference'));
    $this->assertSame([['uri' => '/a', 'title' => 'A']], $method->invoke($service, [['uri' => '/a', 'title' => 'A']], 'link'));
  }

}
