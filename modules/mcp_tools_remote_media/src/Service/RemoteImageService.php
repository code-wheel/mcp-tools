<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_remote_media\Service;

/**
 * Service for fetching remote images and creating Drupal file/media entities.
 *
 * Supports JPEG, PNG, GIF, and WebP formats up to 10 MiB.
 *
 * @see \Drupal\mcp_tools_remote_media\Service\AbstractRemoteFileService
 */
class RemoteImageService extends AbstractRemoteFileService {

  /**
   * Allowed image MIME types.
   */
  private const ALLOWED_MIME_TYPES = [
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp',
  ];

  /**
   * MIME type to file extension map.
   */
  private const MIME_TO_EXT = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/gif' => 'gif',
    'image/webp' => 'webp',
  ];

  /**
   * {@inheritdoc}
   */
  protected function getAllowedMimeTypes(): array {
    return self::ALLOWED_MIME_TYPES;
  }

  /**
   * {@inheritdoc}
   */
  protected function getMimeToExtMap(): array {
    return self::MIME_TO_EXT;
  }

  /**
   * {@inheritdoc}
   */
  protected function getOperationName(): string {
    return 'fetch_remote_image';
  }

  /**
   * Fetches a remote image from a URL and optionally creates a media entity.
   *
   * @param string $url
   *   The remote image URL (http/https only).
   * @param string $name
   *   The media entity name shown in the media library.
   * @param string $directory
   *   Target Drupal stream wrapper directory.
   * @param string $bundle
   *   Media type bundle (default: 'image').
   * @param bool $createMedia
   *   Whether to create a media entity after saving the file.
   *
   * @return array
   *   Result array with success status and data.
   */
  public function fetchRemoteImage(
    string $url,
    string $name,
    string $directory = 'public://mcp-uploads',
    string $bundle = 'image',
    bool $createMedia = TRUE,
  ): array {
    $result = $this->fetchAndCreate(
      $url, $name, $directory, $bundle, $createMedia,
    );

    // Add image-specific success message.
    if ($result['success'] && isset($result['data'])) {
      $fid = $result['data']['fid'];
      $mid = $result['data']['mid'] ?? NULL;
      $result['data']['message'] = $mid
        ? "Remote image fetched and media '$name' created (fid: $fid, mid: $mid)."
        : "Remote image fetched and saved as file (fid: $fid).";
    }

    return $result;
  }

}
