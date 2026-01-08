<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Unit\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\mcp_tools\Service\FileSystemService;
use Drupal\Tests\UnitTestCase;

#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools\Service\FileSystemService::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
final class FileSystemServiceTest extends UnitTestCase {

  public function testDirectorySizeAndByteFormatting(): void {
    $service = new class(
      $this->createMock(FileSystemInterface::class),
      $this->createMock(StreamWrapperManagerInterface::class),
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(\Drupal\Core\Database\Connection::class),
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

}
