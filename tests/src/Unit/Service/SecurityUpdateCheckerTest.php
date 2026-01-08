<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Service;

/**
 * Test stub for update_get_available().
 *
 * SecurityUpdateChecker calls update_get_available() unqualified from within the
 * Drupal\mcp_tools\Service namespace, so this function is preferred over the
 * global update module implementation in unit tests.
 *
 * @internal
 */
function update_get_available(bool $refresh = TRUE): array|false {
  $fn = $GLOBALS['mcp_tools_test_update_get_available'] ?? NULL;
  if (is_callable($fn)) {
    return $fn($refresh);
  }
  return FALSE;
}

/**
 * Test stub for update_calculate_project_data().
 *
 * @internal
 */
function update_calculate_project_data(array $available): array {
  $fn = $GLOBALS['mcp_tools_test_update_calculate_project_data'] ?? NULL;
  if (is_callable($fn)) {
    return $fn($available);
  }
  return [];
}

namespace Drupal\Tests\mcp_tools\Unit\Service;

use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\mcp_tools\Service\SecurityUpdateChecker;
use Drupal\Tests\UnitTestCase;
use Drupal\update\UpdateManagerInterface;
use Drupal\update\UpdateProcessorInterface;

#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools\Service\SecurityUpdateChecker::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
final class SecurityUpdateCheckerTest extends UnitTestCase {

  protected function tearDown(): void {
    unset($GLOBALS['mcp_tools_test_update_get_available'], $GLOBALS['mcp_tools_test_update_calculate_project_data']);
    parent::tearDown();
  }

  private function createChecker(): SecurityUpdateChecker {
    return new SecurityUpdateChecker(
      $this->createMock(UpdateManagerInterface::class),
      $this->createMock(UpdateProcessorInterface::class),
      $this->createMock(ModuleExtensionList::class),
    );
  }

  public function testGetAvailableUpdatesReturnsErrorWhenUpdateDataUnavailable(): void {
    $GLOBALS['mcp_tools_test_update_get_available'] = static fn(): array|false => FALSE;
    $GLOBALS['mcp_tools_test_update_calculate_project_data'] = static fn(): array => [];

    $checker = $this->createChecker();
    $result = $checker->getAvailableUpdates();

    $this->assertArrayHasKey('error', $result);
    $this->assertSame([], $result['updates']);
  }

  public function testGetAvailableUpdatesFiltersAndSortsSecurityFirst(): void {
    $GLOBALS['mcp_tools_test_update_get_available'] = static fn(): array|false => ['dummy' => []];
    $GLOBALS['mcp_tools_test_update_calculate_project_data'] = static function (): array {
      return [
        // Current projects are skipped entirely.
        'current' => [
          'status' => 5,
          'existing_version' => '1.0.0',
          'info' => ['name' => 'Current'],
        ],
        'security' => [
          'status' => 1,
          'existing_version' => '1.0.0',
          'recommended' => '1.0.1',
          'latest_version' => '1.0.2',
          'info' => ['name' => 'Security'],
          'project_type' => 'module',
          'link' => 'https://example.com/security',
        ],
        'normal' => [
          'status' => 4,
          'existing_version' => '2.0.0',
          'recommended' => '2.1.0',
          'latest_version' => '2.1.0',
          'info' => ['name' => 'Normal'],
          'project_type' => 'module',
          'link' => 'https://example.com/normal',
        ],
      ];
    };

    $checker = $this->createChecker();
    $result = $checker->getAvailableUpdates(FALSE);

    $this->assertSame(2, $result['total_updates']);
    $this->assertSame(1, $result['security_updates']);
    $this->assertTrue($result['has_security_issues']);

    $updates = $result['updates'];
    $this->assertSame('security', $updates[0]['name']);
    $this->assertTrue($updates[0]['is_security_update']);

    $securityOnly = $checker->getSecurityUpdates();
    $this->assertSame(1, $securityOnly['total_updates']);
    $this->assertSame('security', $securityOnly['updates'][0]['name']);
  }

  public function testGetCoreStatusReturnsErrorWhenCoreMissing(): void {
    $GLOBALS['mcp_tools_test_update_get_available'] = static fn(): array|false => ['not_drupal' => []];
    $GLOBALS['mcp_tools_test_update_calculate_project_data'] = static fn(): array => [];

    $checker = $this->createChecker();
    $result = $checker->getCoreStatus();

    $this->assertArrayHasKey('error', $result);
  }

  public function testGetCoreStatusReturnsMappedStatus(): void {
    $GLOBALS['mcp_tools_test_update_get_available'] = static fn(): array|false => ['drupal' => []];
    $GLOBALS['mcp_tools_test_update_calculate_project_data'] = static function (): array {
      return [
        'drupal' => [
          'status' => 4,
          'recommended' => '11.0.0',
          'latest_version' => '11.0.1',
          'security updates' => [],
          'releases' => [
            '11.0.0' => ['release_link' => 'https://example.com/release'],
          ],
        ],
      ];
    };

    $checker = $this->createChecker();
    $result = $checker->getCoreStatus();

    $this->assertSame('not_current', $result['status']);
    $this->assertSame('11.0.0', $result['recommended_version']);
    $this->assertSame('https://example.com/release', $result['release_link']);
  }

}

