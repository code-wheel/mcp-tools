<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_remote_media\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\media\Entity\Media;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;

/**
 * Abstract base service for fetching remote files and creating Drupal entities.
 *
 * Provides shared HTTP fetch, validation, and file/media entity creation logic.
 * Concrete subclasses implement the media-type-specific MIME lists and
 * orchestration logic.
 *
 * To add support for a new media type (e.g. documents, audio), extend this
 * class and implement the three abstract methods. Then create a corresponding
 * Tool plugin that delegates to the concrete service.
 *
 * @see \Drupal\mcp_tools_remote_media\Service\RemoteImageService
 */
abstract class AbstractRemoteFileService {

  /**
   * Maximum file size allowed (10 MiB).
   */
  protected const MAX_FILE_BYTES = 10_485_760;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected FileSystemInterface $fileSystem,
    protected ClientInterface $httpClient,
    protected AccessManager $accessManager,
    protected AuditLogger $auditLogger,
    protected TimeInterface $time,
  ) {}

  /**
   * Returns the list of allowed MIME types for this media type.
   *
   * @return string[]
   *   Array of allowed MIME type strings (e.g. ['image/jpeg', 'image/png']).
   */
  abstract protected function getAllowedMimeTypes(): array;

  /**
   * Returns a map of MIME type to file extension.
   *
   * Used as a fallback when the filename cannot be derived from the URL.
   *
   * @return array<string, string>
   *   Map of MIME type string to extension string (without leading dot).
   */
  abstract protected function getMimeToExtMap(): array;

  /**
   * Returns the operation name used for audit logging.
   *
   * @return string
   *   Operation name string (e.g. 'fetch_remote_image').
   */
  abstract protected function getOperationName(): string;

  /**
   * Checks write access.
   *
   * @return array|null
   *   Error array if access denied, NULL if access granted.
   */
  protected function validateAccess(): ?array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }
    return NULL;
  }

  /**
   * Validates a URL (must be a valid URL with http or https scheme).
   *
   * @param string $url
   *   The URL to validate.
   *
   * @return array|null
   *   Error array if invalid, NULL if valid.
   */
  protected function validateUrl(string $url): ?array {
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
      return ['success' => FALSE, 'error' => 'Invalid URL provided.'];
    }

    $scheme = parse_url($url, PHP_URL_SCHEME);
    if (!in_array($scheme, ['http', 'https'], TRUE)) {
      return ['success' => FALSE, 'error' => 'Only http and https URLs are allowed.'];
    }

    return NULL;
  }

  /**
   * Validates a Drupal stream wrapper directory path.
   *
   * Only public:// and private:// are allowed. Path traversal is rejected.
   *
   * @param string $directory
   *   The directory path to validate.
   *
   * @return array|null
   *   Error array if invalid, NULL if valid.
   */
  protected function validateDirectory(string $directory): ?array {
    if (
      !preg_match('/^(public|private):\\/\\/[a-zA-Z0-9_\\-\\/]*$/', $directory)
      || str_contains($directory, '..')
    ) {
      return [
        'success' => FALSE,
        'error' => 'Invalid directory. Only public:// and private:// stream wrappers allowed.',
      ];
    }

    return NULL;
  }

  /**
   * Fetches a remote file via HTTP GET.
   *
   * @param string $url
   *   The remote URL to fetch.
   *
   * @return array
   *   Array with 'response' key on success, or 'success' => FALSE + 'error'
   *   on failure.
   */
  protected function fetchFromRemote(string $url): array {
    try {
      $response = $this->httpClient->get($url, [
        'timeout' => 30,
        'connect_timeout' => 10,
        'allow_redirects' => ['max' => 3],
        'headers' => [
          'User-Agent' => 'Drupal MCP Tools Remote Media/1.0',
        ],
      ]);

      return ['response' => $response];
    }
    catch (RequestException $e) {
      return ['success' => FALSE, 'error' => 'HTTP request failed: ' . $e->getMessage()];
    }
  }

  /**
   * Parses and normalises a MIME type from a Content-Type header value.
   *
   * @param string $contentTypeHeader
   *   The raw Content-Type header value (e.g. 'image/jpeg; charset=utf-8').
   *
   * @return string
   *   Normalised MIME type (e.g. 'image/jpeg').
   */
  protected function parseMimeFromContentType(string $contentTypeHeader): string {
    return strtolower(trim(explode(';', $contentTypeHeader)[0]));
  }

  /**
   * Validates that a MIME type is in the allowed list.
   *
   * @param string $mimeType
   *   The MIME type to check.
   *
   * @return array|null
   *   Error array if not allowed, NULL if valid.
   */
  protected function validateMimeType(string $mimeType): ?array {
    if (!in_array($mimeType, $this->getAllowedMimeTypes(), TRUE)) {
      return [
        'success' => FALSE,
        'error' => "Unsupported content type '$mimeType'. Allowed: "
        . implode(', ', $this->getAllowedMimeTypes()),
      ];
    }

    return NULL;
  }

  /**
   * Validates the file body (not empty, not too large).
   *
   * @param string $body
   *   The raw file content.
   *
   * @return array|null
   *   Error array if invalid, NULL if valid.
   */
  protected function validateBody(string $body): ?array {
    if (strlen($body) > self::MAX_FILE_BYTES) {
      return [
        'success' => FALSE,
        'error' => 'Remote file too large. Maximum size is '
        . self::MAX_FILE_BYTES . ' bytes.',
      ];
    }

    if ($body === '') {
      return ['success' => FALSE, 'error' => 'Remote file is empty.'];
    }

    return NULL;
  }

  /**
   * Validates the actual content MIME type using finfo.
   *
   * This is a defence-in-depth check that does not rely solely on the
   * Content-Type response header.
   *
   * @param string $body
   *   The raw file content.
   *
   * @return array|null
   *   Error array if the detected MIME type is not in the allowed list,
   *   NULL otherwise (including when detection fails — the header check
   *   already passed at this point).
   */
  protected function validateContentMime(string $body): ?array {
    $detectedMime = $this->detectMimeFromContent($body);
    if ($detectedMime !== NULL && !in_array($detectedMime, $this->getAllowedMimeTypes(), TRUE)) {
      return [
        'success' => FALSE,
        'error' => "File content type '$detectedMime' does not match an allowed type.",
      ];
    }

    return NULL;
  }

  /**
   * Detects MIME type from raw file content using finfo.
   *
   * @param string $content
   *   The raw file content.
   *
   * @return string|null
   *   The detected MIME type, or NULL if detection failed.
   */
  protected function detectMimeFromContent(string $content): ?string {
    $finfo = new \finfo(FILEINFO_MIME_TYPE);
    $detected = $finfo->buffer($content);
    return $detected !== FALSE ? $detected : NULL;
  }

  /**
   * Builds a safe filename from the URL path and MIME type.
   *
   * Derives the filename from the URL's basename if possible. Falls back to
   * a sanitised version of $name with an extension derived from the MIME map.
   *
   * @param string $url
   *   The source URL.
   * @param string $name
   *   The human-readable name (used as fallback base).
   * @param string $mimeType
   *   The MIME type (used to derive the fallback extension).
   *
   * @return string
   *   A sanitised filename safe for use in a Drupal stream wrapper path.
   */
  protected function buildFilename(string $url, string $name, string $mimeType): string {
    $urlPath = parse_url($url, PHP_URL_PATH);
    $basename = $urlPath ? basename($urlPath) : '';
    $ext = strtolower(pathinfo($basename, PATHINFO_EXTENSION));

    if (empty($ext) || strlen($ext) > 5) {
      $mimeToExt = $this->getMimeToExtMap();
      $ext = $mimeToExt[$mimeType] ?? 'bin';
      $basename = preg_replace('/[^a-zA-Z0-9_\\-]/', '_', $name) . '.' . $ext;
    }

    return preg_replace('/[^a-zA-Z0-9._-]/', '_', $basename);
  }

  /**
   * Saves the file content to a stream wrapper and creates a file entity.
   *
   * @param string $body
   *   The raw file content.
   * @param string $directory
   *   The target Drupal stream wrapper directory.
   * @param string $safeFilename
   *   The sanitised filename.
   * @param string $mimeType
   *   The file MIME type.
   * @param string $sourceUrl
   *   The original source URL (used for audit logging).
   *
   * @return array
   *   Array with keys: fid, uuid, filename, uri, url, mime.
   */
  protected function saveFileEntity(
    string $body,
    string $directory,
    string $safeFilename,
    string $mimeType,
    string $sourceUrl,
  ): array {
    $this->fileSystem->prepareDirectory(
      $directory,
      FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS
    );

    $destination = rtrim($directory, '/') . '/' . $safeFilename;
    $uri = $this->fileSystem->saveData($body, $destination, FileExists::Rename);

    // Use current time rather than REQUEST_TIME, which is frozen in
    // long-running processes such as mcp-tools:serve.
    $currentTime = $this->time->getCurrentTime();

    $file = File::create([
      'uri' => $uri,
      'filename' => $safeFilename,
      'filemime' => $mimeType,
      'status' => 1,
      'created' => $currentTime,
      'changed' => $currentTime,
    ]);
    $file->save();
    $fid = (int) $file->id();

    $this->auditLogger->logSuccess($this->getOperationName(), 'file', (string) $fid, [
      'url' => $sourceUrl,
      'filename' => $safeFilename,
      'uri' => $uri,
    ]);

    return [
      'fid' => $fid,
      'uuid' => $file->uuid(),
      'filename' => $safeFilename,
      'uri' => $uri,
      'url' => $file->createFileUrl(FALSE),
      'mime' => $mimeType,
    ];
  }

  /**
   * Creates a Drupal media entity referencing an existing managed file.
   *
   * @param string $bundle
   *   The media type machine name.
   * @param string $name
   *   The media entity name.
   * @param int $fid
   *   The managed file entity ID.
   * @param array $fileData
   *   The file result data array (from saveFileEntity()) to merge into the
   *   response on success.
   *
   * @return array
   *   Result array with success status and merged data (fid + mid fields).
   */
  protected function createMediaEntity(
    string $bundle,
    string $name,
    int $fid,
    array $fileData,
  ): array {
    $mediaType = $this->entityTypeManager->getStorage('media_type')->load($bundle);
    if (!$mediaType) {
      return [
        'success' => FALSE,
        'error' => "Media type '$bundle' not found. File was saved (fid: $fid) but media entity not created. Use mcp_list_media_types to see available types.",
        'data' => $fileData,
      ];
    }

    $sourceConfiguration = $mediaType->get('source_configuration');
    $sourceFieldName = $sourceConfiguration['source_field'] ?? NULL;

    if (!$sourceFieldName) {
      return [
        'success' => FALSE,
        'error' => "Media type '$bundle' has no source field configured. File was saved (fid: $fid).",
        'data' => $fileData,
      ];
    }

    // Use current time rather than REQUEST_TIME, which is frozen in
    // long-running processes such as mcp-tools:serve.
    $currentTime = $this->time->getCurrentTime();

    $media = Media::create([
      'bundle' => $bundle,
      'name' => $name,
      $sourceFieldName => $fid,
    ]);
    $media->setCreatedTime($currentTime);
    $media->setChangedTime($currentTime);
    $media->save();
    $mid = (int) $media->id();

    $this->auditLogger->logSuccess($this->getOperationName(), 'media', (string) $mid, [
      'name' => $name,
      'bundle' => $bundle,
      'fid' => $fid,
    ]);

    return [
      'success' => TRUE,
      'data' => array_merge($fileData, [
        'mid' => $mid,
        'media_uuid' => $media->uuid(),
      ]),
    ];
  }

}
