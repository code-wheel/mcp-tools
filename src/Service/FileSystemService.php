<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;

/**
 * Service for file system operations.
 */
class FileSystemService {

  public function __construct(
    protected FileSystemInterface $fileSystem,
    protected StreamWrapperManagerInterface $streamWrapperManager,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Get file system status.
   *
   * @return array
   *   File system status data.
   */
  public function getFileSystemStatus(): array {
    $result = [
      'directories' => [],
      'stream_wrappers' => [],
    ];

    // Check common directories.
    $directories = [
      'public' => 'public://',
      'private' => 'private://',
      'temporary' => 'temporary://',
    ];

    foreach ($directories as $name => $scheme) {
      $wrapper = $this->streamWrapperManager->getViaScheme($name);
      if ($wrapper) {
        $path = $wrapper->realpath();
        $result['directories'][$name] = [
          'scheme' => $scheme,
          'path' => $path,
          'exists' => $path && is_dir($path),
          'writable' => $path && is_writable($path),
        ];

        // Get disk usage if directory exists.
        if ($path && is_dir($path)) {
          $result['directories'][$name]['size'] = $this->getDirectorySize($path);
        }
      }
    }

    // List all stream wrappers.
    $wrappers = $this->streamWrapperManager->getWrappers();
    foreach ($wrappers as $scheme => $info) {
      $result['stream_wrappers'][] = [
        'scheme' => $scheme . '://',
        'name' => (string) ($info['name'] ?? $scheme),
        'description' => (string) ($info['description'] ?? ''),
      ];
    }

    return $result;
  }

  /**
   * Get managed files summary.
   *
   * @param int $limit
   *   Maximum files to list.
   *
   * @return array
   *   Files summary.
   */
  public function getFilesSummary(int $limit = 50): array {
    $fileStorage = $this->entityTypeManager->getStorage('file');

    // Get total count.
    $total = $fileStorage->getQuery()
      ->accessCheck(TRUE)
      ->count()
      ->execute();

    // Get recent files.
    $fids = $fileStorage->getQuery()
      ->accessCheck(TRUE)
      ->sort('created', 'DESC')
      ->range(0, $limit)
      ->execute();

    $files = $fileStorage->loadMultiple($fids);

    $fileList = [];
    $byMime = [];

    foreach ($files as $file) {
      $mime = $file->getMimeType();
      $byMime[$mime] = ($byMime[$mime] ?? 0) + 1;

      $fileList[] = [
        'fid' => $file->id(),
        'filename' => $file->getFilename(),
        'uri' => $file->getFileUri(),
        'mime' => $mime,
        'size' => $file->getSize(),
        'status' => $file->isPermanent() ? 'permanent' : 'temporary',
        'created' => date('Y-m-d H:i:s', $file->getCreatedTime()),
      ];
    }

    arsort($byMime);

    return [
      'total_files' => (int) $total,
      'returned' => count($fileList),
      'by_mime_type' => $byMime,
      'files' => $fileList,
    ];
  }

  /**
   * Find orphaned files (managed files not used by any entity).
   *
   * @param int $limit
   *   Maximum to check.
   *
   * @return array
   *   Orphaned files data.
   */
  public function findOrphanedFiles(int $limit = 100): array {
    $fileStorage = $this->entityTypeManager->getStorage('file');

    // Get files not referenced in file_usage table.
    $query = \Drupal::database()->select('file_managed', 'fm');
    $query->leftJoin('file_usage', 'fu', 'fm.fid = fu.fid');
    $query->fields('fm', ['fid']);
    $query->isNull('fu.fid');
    $query->condition('fm.status', 1); // Only permanent files.
    $query->range(0, $limit);

    $orphanedFids = $query->execute()->fetchCol();

    if (empty($orphanedFids)) {
      return [
        'total_orphaned' => 0,
        'files' => [],
        'note' => 'No orphaned files found (checked up to ' . $limit . ' files).',
      ];
    }

    $files = $fileStorage->loadMultiple($orphanedFids);

    $orphaned = [];
    $totalSize = 0;
    foreach ($files as $file) {
      $size = $file->getSize();
      $totalSize += $size;

      $orphaned[] = [
        'fid' => $file->id(),
        'filename' => $file->getFilename(),
        'uri' => $file->getFileUri(),
        'size' => $size,
        'created' => date('Y-m-d H:i:s', $file->getCreatedTime()),
      ];
    }

    return [
      'total_orphaned' => count($orphaned),
      'total_size_bytes' => $totalSize,
      'total_size_human' => $this->formatBytes($totalSize),
      'files' => $orphaned,
    ];
  }

  /**
   * Get directory size recursively.
   *
   * @param string $path
   *   Directory path.
   *
   * @return int
   *   Size in bytes.
   */
  protected function getDirectorySize(string $path): int {
    $size = 0;
    try {
      $iterator = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
        \RecursiveIteratorIterator::LEAVES_ONLY
      );

      foreach ($iterator as $file) {
        if ($file->isFile()) {
          $size += $file->getSize();
        }
      }
    }
    catch (\Exception $e) {
      // Ignore permission errors.
    }

    return $size;
  }

  /**
   * Format bytes to human readable.
   *
   * @param int $bytes
   *   Bytes.
   *
   * @return string
   *   Human readable size.
   */
  protected function formatBytes(int $bytes): string {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
  }

}
