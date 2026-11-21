<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_media\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\media\Entity\MediaType;

/**
 * Every media source the tool names gives a media type.
 *
 * The tool told clients to pass "audio" or "video". Drupal calls these
 * sources "audio_file" and "video_file", so both documented values failed.
 * Only "image" was ever run.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_media')]
final class MediaTypeSourceKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'text',
    'file',
    'image',
    'media',
    'dblog',
    'update',
    'tool',
    'mcp_tools',
    'mcp_tools_media',
  ];

  /**
   * Each source, by Drupal's name and by the short name.
   */
  public function testEverySource(): void {
    $this->installSchema('dblog', ['watchdog']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installConfig(['system', 'field', 'media', 'mcp_tools']);
    $this->container->get('mcp_tools.access_manager')->setScopes(['read', 'write']);
    $service = $this->container->get('mcp_tools_media.media');

    $expected = [
      'image' => 'image',
      'file' => 'file',
      'audio_file' => 'audio_file',
      'video_file' => 'video_file',
      'oembed:video' => 'oembed:video',
      // The short names the tool used to document.
      'audio' => 'audio_file',
      'video' => 'video_file',
    ];
    $n = 0;
    foreach ($expected as $given => $source) {
      $id = 'type_' . ++$n;
      $result = $service->createMediaType($id, 'Type ' . $given, $given);
      $this->assertTrue($result['success'], $given . ': ' . ($result['error'] ?? ''));
      $this->assertSame($source, $result['data']['source_plugin']);
      $type = MediaType::load($id);
      $this->assertSame($source, $type->getSource()->getPluginId());
      $this->assertNotEmpty($type->getSource()->getSourceFieldDefinition($type));
    }
  }

}
