<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_redirect\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\redirect\Entity\Redirect;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Redirects go through the import on Migrate, by their source path.
 *
 * mcp_redirect_import ran any number of redirects in one request, and
 * could not be run again to update them or be undone. It stays for sites
 * without migrate_plus; this is the import of mcp_tools_migration.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_redirect')]
final class RedirectImportOnMigrateKernelTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system', 'user', 'field', 'text', 'filter', 'node', 'link', 'views', 'path_alias', 'redirect',
    'dblog', 'update', 'migrate', 'migrate_plus', 'tool', 'mcp_tools', 'mcp_tools_migration',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('redirect');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('dblog', ['watchdog']);
    $this->installConfig(['system', 'filter', 'node', 'mcp_tools']);
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
    $this->setUpCurrentUser([], [], TRUE);
    $this->container->get('mcp_tools.access_manager')->setScopes(['read', 'write']);
  }

  /**
   * The import service.
   */
  private function import(): object {
    return $this->container->get('mcp_tools_migration.migration');
  }

  /**
   * Redirects by their source path: run again updates, a hand-made one stays.
   */
  public function testRedirectsByTheirSource(): void {
    Node::create(['type' => 'page', 'title' => 'Target', 'status' => 1])->save();
    $this->container->get('router.builder')->rebuild();
    $handMade = Redirect::create(['redirect_source' => ['path' => 'kept', 'query' => []], 'redirect_redirect' => ['uri' => 'internal:/node/1'], 'status_code' => 301, 'language' => 'und']);
    $handMade->save();
    $rows = [
      ['source' => '/old-a', 'destination' => '/node/1'],
      ['source' => 'old-b', 'destination' => '/node/1', 'status_code' => 302],
      ['source' => 'kept', 'destination' => '/node/999'],
    ];
    $result = $this->import()->importFromJson('redirect', $rows, '', ['entity_type' => 'redirect']);
    $this->assertTrue($result['success'], json_encode($result));
    $this->assertSame(2, $result['data']['created_count'], json_encode($result['data']));
    $this->assertCount(1, $result['data']['existing'] ?? []);
    $stored = [];
    foreach (Redirect::loadMultiple() as $redirect) {
      $stored[$redirect->getSourcePathWithQuery()] = [$redirect->getRedirect()['uri'], (int) $redirect->getStatusCode()];
    }
    ksort($stored);
    $this->assertSame([
      '/kept' => ['internal:/node/1', 301],
      '/old-a' => ['internal:/node/1', 301],
      '/old-b' => ['internal:/node/1', 302],
    ], $stored);

    // A destination that leads nowhere is said, as the redirect import says it.
    $rows[0]['destination'] = '/no-such-page';
    $again = $this->import()->importFromJson('redirect', $rows, '', ['entity_type' => 'redirect']);
    $this->assertSame(1, $again['data']['updated_count'], json_encode($again['data']));
    $this->assertNotEmpty($again['data']['warnings'] ?? [], 'A destination that leads nowhere was not said.');
    $this->assertCount(3, Redirect::loadMultiple());

    $this->assertTrue($this->import()->rollbackImport($result['data']['import_id'])['success']);
    $this->assertSame([(int) $handMade->id()], array_map('intval', array_keys(Redirect::loadMultiple())));
  }

  /**
   * Redirects export as the import reads them, and come back the same.
   */
  public function testRedirectsGoRoundTrip(): void {
    Redirect::create(['redirect_source' => ['path' => 'old-a', 'query' => []], 'redirect_redirect' => ['uri' => 'internal:/node/1'], 'status_code' => 301, 'language' => 'und'])->save();
    Redirect::create(['redirect_source' => ['path' => 'old-b', 'query' => []], 'redirect_redirect' => ['uri' => 'https://example.com/b'], 'status_code' => 302, 'language' => 'und'])->save();
    $read = function (): array {
      $out = [];
      foreach (Redirect::loadMultiple() as $redirect) {
        $out[$redirect->getSourcePathWithQuery()] = [$redirect->getRedirect()['uri'], (int) $redirect->getStatusCode()];
      }
      ksort($out);
      return $out;
    };
    $before = $read();
    $export = $this->import()->exportToCsv('', 100, 0, ['entity_type' => 'redirect']);
    $this->assertTrue($export['success'], json_encode($export));
    $storage = $this->container->get('entity_type.manager')->getStorage('redirect');
    $storage->delete($storage->loadMultiple());
    $result = $this->import()->importFromCsv('', $export['data']['csv_data'], [], '', ['entity_type' => 'redirect']);
    $this->assertTrue($result['success'], json_encode($result));
    $this->assertSame(2, $result['data']['created_count'], json_encode($result['data']));
    $this->assertSame($before, $read());
  }

}
