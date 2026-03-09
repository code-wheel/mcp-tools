<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_remote_media\Service;

use GuzzleHttp\Exception\RequestException;

/**
 * Service for fetching remote images and creating Drupal file/media entities.
 *
 * Supports JPEG, PNG, GIF, WebP, and SVG formats up to 10 MiB.
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
    if ($error = $this->validateAccess()) {
      return $error;
    }

    if ($error = $this->validateUrl($url)) {
      return $error;
    }

    if ($error = $this->validateDirectory($directory)) {
      return $error;
    }

    try {
      $fetchResult = $this->fetchFromRemote($url);
      if (isset($fetchResult['error'])) {
        return $fetchResult;
      }

      $response = $fetchResult['response'];
      $mimeType = $this->parseMimeFromContentType($response->getHeaderLine('Content-Type'));

      if ($error = $this->validateMimeType($mimeType)) {
        return $error;
      }

      $body = (string) $response->getBody();

      if ($error = $this->validateBody($body)) {
        return $error;
      }

      if ($error = $this->validateContentMime($body)) {
        return $error;
      }

      $safeFilename = $this->buildFilename($url, $name, $mimeType);
      $fileData = $this->saveFileEntity($body, $directory, $safeFilename, $mimeType, $url);

      if (!$createMedia) {
        $fileData['message'] = "Remote image fetched and saved as file (fid: {$fileData['fid']}).";
        return ['success' => TRUE, 'data' => $fileData];
      }

      $result = $this->createMediaEntity($bundle, $name, $fileData['fid'], $fileData);

      if ($result['success']) {
        $fid = $result['data']['fid'];
        $mid = $result['data']['mid'];
        $result['data']['message'] = "Remote image fetched and media '$name' created successfully (fid: $fid, mid: $mid).";
      }

      return $result;
    }
    catch (RequestException $e) {
      $this->auditLogger->logFailure($this->getOperationName(), 'file', 'new', [
        'url' => $url,
        'error' => $e->getMessage(),
      ]);
      return ['success' => FALSE, 'error' => 'HTTP request failed: ' . $e->getMessage()];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure($this->getOperationName(), 'file', 'new', [
        'url' => $url,
        'error' => $e->getMessage(),
      ]);
      return ['success' => FALSE, 'error' => 'Failed to fetch remote image: ' . $e->getMessage()];
    }
  }

}
