<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Unit\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Database\StatementInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StreamWrapper\StreamWrapperInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\file\FileInterface;
use Drupal\mcp_tools\Service\FileSystemService;
use Drupal\Tests\UnitTestCase;

#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools\Service\FileSystemService::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
final class FileSystemServiceTest extends UnitTestCase {

  private FileSystemInterface $fileSystem;
  private StreamWrapperManagerInterface $streamWrapperManager;
  private EntityTypeManagerInterface $entityTypeManager;
  private Connection $database;
  private FileSystemService $service;

  protected function setUp(): void {
    parent::setUp();
    $this->fileSystem = $this->createMock(FileSystemInterface::class);
    $this->streamWrapperManager = $this->createMock(StreamWrapperManagerInterface::class);
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->database = $this->createMock(Connection::class);

    $this->service = new FileSystemService(
      $this->fileSystem,
      $this->streamWrapperManager,
      $this->entityTypeManager,
      $this->database,
    );
  }

  public function testDirectorySizeAndByteFormatting(): void {
    $service = new class(
      $this->createMock(FileSystemInterface::class),
      $this->createMock(StreamWrapperManagerInterface::class),
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(Connection::class),
    ) extends FileSystemService {

      public function directorySize(string $path): int {
        return $this->getDirectorySize($path);
      }

      public function bytes(int $bytes): string {
        return $this->formatBytes($bytes);
      }

    };

    $tmpDir = sys_get_temp_dir() . '/mcp_tools_fs_' . bin2hex(random_bytes(6));
    mkdir($tmpDir);

    file_put_contents($tmpDir . '/a.txt', '1234');
    file_put_contents($tmpDir . '/b.txt', '567');

    $size = $service->directorySize($tmpDir);
    $this->assertSame(7, $size);

    $this->assertSame('0 B', $service->bytes(0));
    $this->assertSame('1 KB', $service->bytes(1024));
    $this->assertSame('1 MB', $service->bytes(1024 * 1024));

    unlink($tmpDir . '/a.txt');
    unlink($tmpDir . '/b.txt');
    rmdir($tmpDir);
  }

  public function testGetFileSystemStatusReturnsStructuredResult(): void {
    $this->streamWrapperManager->method('getViaScheme')->willReturn(NULL);
    $this->streamWrapperManager->method('getWrappers')->willReturn([]);

    $result = $this->service->getFileSystemStatus();

    $this->assertArrayHasKey('directories', $result);
    $this->assertArrayHasKey('stream_wrappers', $result);
  }

  public function testGetFileSystemStatusIncludesStreamWrappers(): void {
    $this->streamWrapperManager->method('getViaScheme')->willReturn(NULL);
    $this->streamWrapperManager->method('getWrappers')->willReturn([
      'public' => [
        'name' => 'Public files',
        'description' => 'Public file serving',
      ],
      'private' => [
        'name' => 'Private files',
        'description' => 'Private file serving',
      ],
    ]);

    $result = $this->service->getFileSystemStatus();

    $this->assertCount(2, $result['stream_wrappers']);
    $this->assertSame('public://', $result['stream_wrappers'][0]['scheme']);
    $this->assertSame('Public files', $result['stream_wrappers'][0]['name']);
  }

  public function testGetFileSystemStatusChecksDirectories(): void {
    $wrapper = $this->createMock(StreamWrapperInterface::class);
    $wrapper->method('realpath')->willReturn('/tmp/test_public');

    $this->streamWrapperManager->method('getViaScheme')
      ->willReturnCallback(function ($scheme) use ($wrapper) {
        return $scheme === 'public' ? $wrapper : NULL;
      });
    $this->streamWrapperManager->method('getWrappers')->willReturn([]);

    $result = $this->service->getFileSystemStatus();

    $this->assertArrayHasKey('public', $result['directories']);
    $this->assertSame('public://', $result['directories']['public']['scheme']);
    $this->assertSame('/tmp/test_public', $result['directories']['public']['path']);
  }

  public function testGetFilesSummaryReturnsStructuredResult(): void {
    // Count query returns int.
    $countQuery = $this->createMock(QueryInterface::class);
    $countQuery->method('accessCheck')->willReturnSelf();
    $countQuery->method('count')->willReturnSelf();
    $countQuery->method('execute')->willReturn(0);

    // IDs query returns array.
    $idsQuery = $this->createMock(QueryInterface::class);
    $idsQuery->method('accessCheck')->willReturnSelf();
    $idsQuery->method('sort')->willReturnSelf();
    $idsQuery->method('range')->willReturnSelf();
    $idsQuery->method('execute')->willReturn([]);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')->willReturnOnConsecutiveCalls($countQuery, $idsQuery);
    $storage->method('loadMultiple')->willReturn([]);

    $this->entityTypeManager->method('getStorage')
      ->with('file')
      ->willReturn($storage);

    $result = $this->service->getFilesSummary();

    $this->assertArrayHasKey('total_files', $result);
    $this->assertArrayHasKey('returned', $result);
    $this->assertArrayHasKey('by_mime_type', $result);
    $this->assertArrayHasKey('files', $result);
    $this->assertSame(0, $result['total_files']);
  }

  public function testGetFilesSummaryListsFiles(): void {
    $file = $this->createMock(FileInterface::class);
    $file->method('id')->willReturn(1);
    $file->method('getFilename')->willReturn('test.jpg');
    $file->method('getFileUri')->willReturn('public://test.jpg');
    $file->method('getMimeType')->willReturn('image/jpeg');
    $file->method('getSize')->willReturn(1024);
    $file->method('isPermanent')->willReturn(TRUE);
    $file->method('getCreatedTime')->willReturn(1704067200);

    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('count')->willReturnSelf();
    $query->method('sort')->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('execute')->willReturnOnConsecutiveCalls(1, [1]);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')->willReturn($query);
    $storage->method('loadMultiple')->willReturn([1 => $file]);

    $this->entityTypeManager->method('getStorage')
      ->with('file')
      ->willReturn($storage);

    $result = $this->service->getFilesSummary(10);

    $this->assertSame(1, $result['total_files']);
    $this->assertCount(1, $result['files']);
    $this->assertSame('test.jpg', $result['files'][0]['filename']);
    $this->assertSame('image/jpeg', $result['files'][0]['mime']);
    $this->assertSame('permanent', $result['files'][0]['status']);
    $this->assertSame(1, $result['by_mime_type']['image/jpeg']);
  }

  public function testFindOrphanedFilesReturnsEmptyWhenNoneFound(): void {
    $select = $this->createMock(SelectInterface::class);
    $select->method('leftJoin')->willReturnSelf();
    $select->method('fields')->willReturnSelf();
    $select->method('isNull')->willReturnSelf();
    $select->method('condition')->willReturnSelf();
    $select->method('range')->willReturnSelf();

    $statement = $this->createMock(StatementInterface::class);
    $statement->method('fetchCol')->willReturn([]);
    $select->method('execute')->willReturn($statement);

    $this->database->method('select')->willReturn($select);

    $result = $this->service->findOrphanedFiles();

    $this->assertSame(0, $result['total_orphaned']);
    $this->assertEmpty($result['files']);
    $this->assertStringContainsString('No orphaned files found', $result['note']);
  }

  public function testFindOrphanedFilesReturnsOrphanedFiles(): void {
    $select = $this->createMock(SelectInterface::class);
    $select->method('leftJoin')->willReturnSelf();
    $select->method('fields')->willReturnSelf();
    $select->method('isNull')->willReturnSelf();
    $select->method('condition')->willReturnSelf();
    $select->method('range')->willReturnSelf();

    $statement = $this->createMock(StatementInterface::class);
    $statement->method('fetchCol')->willReturn([1, 2]);
    $select->method('execute')->willReturn($statement);

    $this->database->method('select')->willReturn($select);

    $file1 = $this->createMock(FileInterface::class);
    $file1->method('id')->willReturn(1);
    $file1->method('getFilename')->willReturn('orphan1.txt');
    $file1->method('getFileUri')->willReturn('public://orphan1.txt');
    $file1->method('getSize')->willReturn(100);
    $file1->method('getCreatedTime')->willReturn(1704067200);

    $file2 = $this->createMock(FileInterface::class);
    $file2->method('id')->willReturn(2);
    $file2->method('getFilename')->willReturn('orphan2.txt');
    $file2->method('getFileUri')->willReturn('public://orphan2.txt');
    $file2->method('getSize')->willReturn(200);
    $file2->method('getCreatedTime')->willReturn(1704067200);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadMultiple')->willReturn([1 => $file1, 2 => $file2]);

    $this->entityTypeManager->method('getStorage')
      ->with('file')
      ->willReturn($storage);

    $result = $this->service->findOrphanedFiles();

    $this->assertSame(2, $result['total_orphaned']);
    $this->assertSame(300, $result['total_size_bytes']);
    $this->assertCount(2, $result['files']);
    $this->assertSame('orphan1.txt', $result['files'][0]['filename']);
  }

}
