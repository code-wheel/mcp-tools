<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_remote_media\Service;

use enshrined\svgSanitize\Sanitizer;

/**
 * Service for fetching remote images and creating Drupal file/media entities.
 *
 * Supports JPEG, PNG, GIF, WebP, and SVG formats up to 10 MiB.
 * SVG files are sanitized using enshrined/svg-sanitize to strip scripts,
 * event handlers, foreign objects, and remote references.
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
    'image/svg+xml',
  ];

  /**
   * MIME type to file extension map.
   */
  private const MIME_TO_EXT = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/gif' => 'gif',
    'image/webp' => 'webp',
    'image/svg+xml' => 'svg',
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
   * {@inheritdoc}
   */
  protected function sanitizeContent(string $body, string $mimeType): array {
    if ($mimeType !== 'image/svg+xml') {
      return ['body' => $body];
    }

    $sanitizer = new Sanitizer();
    $sanitizer->removeRemoteReferences(TRUE);
    $sanitizer->minify(FALSE);

    $clean = $sanitizer->sanitize($body);

    if ($clean === FALSE || $clean === '') {
      return [
        'success' => FALSE,
        'error' => 'SVG sanitization failed: file contains invalid XML.',
      ];
    }

    return ['body' => $clean];
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
