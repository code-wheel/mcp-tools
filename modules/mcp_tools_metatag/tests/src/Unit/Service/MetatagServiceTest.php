<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_metatag\Unit\Service;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\mcp_tools_metatag\Service\MetatagService;
use Drupal\metatag\MetatagGroupPluginManager;
use Drupal\metatag\MetatagManagerInterface;
use Drupal\metatag\MetatagTagPluginManager;
use Drupal\Tests\UnitTestCase;

#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools_metatag\Service\MetatagService::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_metatag')]
final class MetatagServiceTest extends UnitTestCase {

  private function createService(array $overrides = []): MetatagService {
    return new MetatagService(
      $overrides['entity_type_manager'] ?? $this->createMock(EntityTypeManagerInterface::class),
      $overrides['tag_plugin_manager'] ?? $this->createMock(MetatagTagPluginManager::class),
      $overrides['group_plugin_manager'] ?? $this->createMock(MetatagGroupPluginManager::class),
      $overrides['metatag_manager'] ?? $this->createMock(MetatagManagerInterface::class),
      $overrides['access_manager'] ?? $this->createMock(AccessManager::class),
      $overrides['audit_logger'] ?? $this->createMock(AuditLogger::class),
    );
  }

  public function testGetMetatagDefaultsReturnsAllDefaults(): void {
    $default1 = $this->createMock(\Drupal\Core\Config\Entity\ConfigEntityInterface::class);
    $default1->method('id')->willReturn('global');
    $default1->method('label')->willReturn('Global');
    $default1->method('get')->with('tags')->willReturn(['title' => '[site:name]']);

    $default2 = $this->createMock(\Drupal\Core\Config\Entity\ConfigEntityInterface::class);
    $default2->method('id')->willReturn('node');
    $default2->method('label')->willReturn('Content');
    $default2->method('get')->with('tags')->willReturn(['title' => '[node:title]']);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadMultiple')->willReturn(['global' => $default1, 'node' => $default2]);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('metatag_defaults')->willReturn($storage);

    $service = $this->createService(['entity_type_manager' => $entityTypeManager]);
    $result = $service->getMetatagDefaults();

    $this->assertTrue($result['success']);
    $this->assertSame(2, $result['data']['total']);
    $this->assertCount(2, $result['data']['defaults']);
  }

  public function testGetMetatagDefaultsForSpecificTypeNotFound(): void {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn(NULL);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('metatag_defaults')->willReturn($storage);

    $service = $this->createService(['entity_type_manager' => $entityTypeManager]);
    $result = $service->getMetatagDefaults('nonexistent');

    $this->assertTrue($result['success']);
    $this->assertStringContainsString('No metatag defaults found', $result['data']['message']);
  }

  public function testGetEntityMetatagsNotFound(): void {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with(999)->willReturn(NULL);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('node')->willReturn($storage);

    $service = $this->createService(['entity_type_manager' => $entityTypeManager]);
    $result = $service->getEntityMetatags('node', 999);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  public function testSetEntityMetatagsRequiresWriteAccess(): void {
    $accessManager = $this->createMock(AccessManager::class);
    $accessManager->method('canWrite')->willReturn(FALSE);
    $accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
    ]);

    $service = $this->createService(['access_manager' => $accessManager]);
    $result = $service->setEntityMetatags('node', 1, ['title' => 'Test']);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('denied', $result['error']);
  }

  public function testListMetatagGroups(): void {
    $groupPluginManager = $this->createMock(MetatagGroupPluginManager::class);
    $groupPluginManager->method('getDefinitions')->willReturn([
      'basic' => ['label' => 'Basic tags', 'description' => 'Basic meta tags', 'weight' => 0],
      'open_graph' => ['label' => 'Open Graph', 'description' => 'OG tags', 'weight' => 10],
    ]);

    $service = $this->createService(['group_plugin_manager' => $groupPluginManager]);
    $result = $service->listMetatagGroups();

    $this->assertTrue($result['success']);
    $this->assertSame(2, $result['data']['total']);
    // Verify sort order by weight.
    $this->assertSame('basic', $result['data']['groups'][0]['id']);
    $this->assertSame('open_graph', $result['data']['groups'][1]['id']);
  }

  public function testListAvailableTags(): void {
    $tagPluginManager = $this->createMock(MetatagTagPluginManager::class);
    $tagPluginManager->method('getDefinitions')->willReturn([
      'title' => [
        'label' => 'Page title',
        'description' => 'The page title',
        'group' => 'basic',
        'weight' => 0,
        'type' => 'string',
        'secure' => FALSE,
        'multiple' => FALSE,
      ],
      'og_title' => [
        'label' => 'OG Title',
        'description' => 'Open Graph title',
        'group' => 'open_graph',
        'weight' => 0,
        'type' => 'string',
        'secure' => FALSE,
        'multiple' => FALSE,
      ],
    ]);

    $groupPluginManager = $this->createMock(MetatagGroupPluginManager::class);
    $groupPluginManager->method('getDefinitions')->willReturn([
      'basic' => ['label' => 'Basic tags'],
      'open_graph' => ['label' => 'Open Graph'],
    ]);

    $service = $this->createService([
      'tag_plugin_manager' => $tagPluginManager,
      'group_plugin_manager' => $groupPluginManager,
    ]);
    $result = $service->listAvailableTags();

    $this->assertTrue($result['success']);
    $this->assertSame(2, $result['data']['total']);
    $this->assertCount(2, $result['data']['by_group']);
    $this->assertCount(2, $result['data']['all_tags']);
  }

  /**
   * Each tag is described once, in plain text; a group gives its slice.
   *
   * On the full site the list was 19,166 characters: every tag twice (in
   * by_group and in all_tags), each with its group again, a sort weight,
   * and false flags. by_group now names its tags by id.
   */
  public function testTagsAreDescribedOnceAndCanBeAskedForByGroup(): void {
    $tagPluginManager = $this->createMock(MetatagTagPluginManager::class);
    $tagPluginManager->method('getDefinitions')->willReturn([
      'robots' => ['label' => 'Robots', 'description' => 'Tell <a href="https://example.com">search engines</a> what to do.', 'group' => 'advanced', 'weight' => 2, 'type' => 'string', 'secure' => FALSE, 'multiple' => TRUE],
      'title' => ['label' => 'Page title', 'group' => 'basic', 'weight' => 0, 'type' => 'label', 'secure' => FALSE, 'multiple' => FALSE],
      'canonical_url' => ['label' => 'Canonical URL', 'description' => 'The preferred URL.', 'group' => 'advanced', 'weight' => 1, 'type' => 'uri'],
    ]);
    $groupPluginManager = $this->createMock(MetatagGroupPluginManager::class);
    $groupPluginManager->method('getDefinitions')->willReturn([
      'basic' => ['label' => 'Basic tags'],
      'advanced' => ['label' => 'Advanced'],
    ]);
    $service = $this->createService(['tag_plugin_manager' => $tagPluginManager, 'group_plugin_manager' => $groupPluginManager]);

    $all = $service->listAvailableTags()['data'];
    $this->assertSame(['id' => 'robots', 'label' => 'Robots', 'description' => 'Tell search engines what to do.', 'group' => 'advanced', 'type' => 'string', 'multiple' => TRUE], $all['all_tags'][0]);
    $this->assertSame(['id' => 'title', 'label' => 'Page title', 'group' => 'basic', 'type' => 'label'], $all['all_tags'][1]);
    // Groups name their tags, in weight order.
    $this->assertSame(['group_id' => 'advanced', 'group_label' => 'Advanced', 'tags' => ['canonical_url', 'robots']], $all['by_group'][0]);

    $advanced = $service->listAvailableTags('advanced')['data'];
    $this->assertSame(['robots', 'canonical_url'], array_column($advanced['all_tags'], 'id'));
    $this->assertCount(1, $advanced['by_group']);
    $this->assertSame(3, $advanced['total']);
    $this->assertSame(2, $advanced['matching']);

    $unknown = $service->listAvailableTags('opengraph');
    $this->assertFalse($unknown['success']);
    $this->assertStringContainsString('advanced, basic', $unknown['error']);
  }

}
