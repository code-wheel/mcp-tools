<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_media\Unit\Service;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\media\MediaTypeInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\mcp_tools_media\Service\MediaService;
use Drupal\Tests\UnitTestCase;

/**
 * Tests for MediaService.
 *
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools_media\Service\MediaService::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_media')]
class MediaServiceTest extends UnitTestCase {

  protected function mockTime(): TimeInterface {
    $time = $this->createMock(TimeInterface::class);
    $time->method('getCurrentTime')->willReturn(time());
    return $time;
  }

  protected EntityTypeManagerInterface $entityTypeManager;
  protected FileSystemInterface $fileSystem;
  protected AccessManager $accessManager;
  protected AuditLogger $auditLogger;
  protected EntityStorageInterface $mediaTypeStorage;
  protected EntityStorageInterface $mediaStorage;
  protected EntityStorageInterface $fileStorage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->fileSystem = $this->createMock(FileSystemInterface::class);
    $this->accessManager = $this->createMock(AccessManager::class);
    $this->auditLogger = $this->createMock(AuditLogger::class);

    $this->mediaTypeStorage = $this->createMock(EntityStorageInterface::class);
    $this->mediaStorage = $this->createMock(EntityStorageInterface::class);
    $this->fileStorage = $this->createMock(EntityStorageInterface::class);

    $this->entityTypeManager->method('getStorage')
      ->willReturnMap([
        ['media_type', $this->mediaTypeStorage],
        ['media', $this->mediaStorage],
        ['file', $this->fileStorage],
      ]);
  }

  /**
   * Creates a MediaService instance.
   */
  protected function createMediaService(): MediaService {
    return new MediaService(
      $this->entityTypeManager,
      $this->fileSystem,
      $this->accessManager,
      $this->auditLogger,
      $this->mockTime(),
    );
  }

  public function testCreateMediaTypeAccessDenied(): void {
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
    ]);

    $service = $this->createMediaService();
    $result = $service->createMediaType('gallery', 'Gallery', 'image');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('denied', $result['error']);
  }

  public function testCreateMediaTypeAlreadyExists(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);

    $existingType = $this->createMock(MediaTypeInterface::class);
    $this->mediaTypeStorage->method('load')->with('image')->willReturn($existingType);

    $service = $this->createMediaService();
    $result = $service->createMediaType('image', 'Image', 'image');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('already exists', $result['error']);
  }

  public function testDeleteMediaTypeAccessDenied(): void {
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
    ]);

    $service = $this->createMediaService();
    $result = $service->deleteMediaType('image');

    $this->assertFalse($result['success']);
  }

  public function testDeleteMediaTypeNotFound(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);
    $this->mediaTypeStorage->method('load')->with('nonexistent')->willReturn(NULL);

    $service = $this->createMediaService();
    $result = $service->deleteMediaType('nonexistent');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not found', $result['error']);
  }

  public function testUploadFileAccessDenied(): void {
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error' => 'Write access denied',
    ]);

    $service = $this->createMediaService();
    $result = $service->uploadFile('test.jpg', base64_encode('test content'));

    $this->assertFalse($result['success']);
  }

  /**
   * @dataProvider invalidDirectoryProvider
   */
  public function testUploadFileInvalidDirectory(string $directory): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);

    $service = $this->createMediaService();
    $result = $service->uploadFile('test.jpg', base64_encode('test content'), $directory);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Invalid directory', $result['error']);
  }

  /**
   * Data provider for invalid directories.
   */
  public static function invalidDirectoryProvider(): array {
    return [
      'path traversal' => ['public://../../../etc'],
      'relative path' => ['../uploads'],
      'absolute path' => ['/var/www/uploads'],
      'http url' => ['http://example.com/uploads'],
      'invalid scheme' => ['ftp://uploads'],
      'dots in path' => ['public://test/../secret'],
    ];
  }

  public function testUploadFileValidDirectory(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);
    $this->fileSystem->method('prepareDirectory')->willReturn(TRUE);
    $this->fileSystem->method('saveData')->willReturn('public://mcp-uploads/test.txt');

    $service = $this->createMediaService();
    // This will fail on the file creation, but we're testing the directory validation passes
    $result = $service->uploadFile('test.txt', base64_encode('test content'), 'public://mcp-uploads');

    // The directory validation passed (no "Invalid directory" error).
    if (!$result['success']) {
      $this->assertStringNotContainsString('Invalid directory', $result['error']);
    }
  }

  public function testUploadFileRejectsInvalidBase64(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);

    $service = $this->createMediaService();
    $result = $service->uploadFile('test.txt', 'not-base64-$$$', 'public://mcp-uploads');

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Invalid base64', $result['error']);
  }

  public function testUploadFileBlocksDangerousExtensions(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);

    $service = $this->createMediaService();
    $result = $service->uploadFile('shell.php', base64_encode('test'), 'public://mcp-uploads');

    $this->assertFalse($result['success']);
    $this->assertSame('INVALID_FILE_TYPE', $result['code']);
  }

  public function testUploadFileRejectsOversizedPayloads(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);

    // Generate a payload that exceeds the pre-decode size estimate.
    $tooLarge = str_repeat('A', 14 * 1024 * 1024);

    $service = $this->createMediaService();
    $result = $service->uploadFile('big.txt', $tooLarge, 'public://mcp-uploads');

    $this->assertFalse($result['success']);
    $this->assertSame('PAYLOAD_TOO_LARGE', $result['code']);
  }

}
