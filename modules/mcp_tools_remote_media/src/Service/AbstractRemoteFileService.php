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
use Drupal\mcp_tools_media\Service\MediaService;
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

  /**
   * Extensions blocked from uploads (code execution risk).
   */
  protected const BLOCKED_UPLOAD_EXTENSIONS = [
    'php', 'php3', 'php4', 'php5', 'phtml', 'phar',
    'cgi', 'pl', 'py', 'sh',
    'exe', 'bat', 'cmd', 'com', 'msi',
    'jsp', 'asp', 'aspx',
  ];

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected FileSystemInterface $fileSystem,
    protected ClientInterface $httpClient,
    protected AccessManager $accessManager,
    protected AuditLogger $auditLogger,
    protected TimeInterface $time,
    protected MediaService $mediaService,
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
   * Validates that a URL does not resolve to a private/internal IP.
   *
   * Prevents SSRF attacks against cloud metadata endpoints, internal
   * services, and RFC 1918 hosts.
   *
   * @param string $url
   *   The URL to check.
   *
   * @return array|null
   *   Error array if the URL resolves to a blocked IP, NULL if safe.
   */
  protected function validateNotInternalUrl(string $url): ?array {
    $host = parse_url($url, PHP_URL_HOST);
    if ($host === NULL || $host === FALSE) {
      return ['success' => FALSE, 'error' => 'Unable to parse host from URL.'];
    }

    // Resolve hostname to IP.
    $ip = gethostbyname($host);

    // The gethostbyname() function returns the hostname on failure.
    if ($ip === $host && !filter_var($host, FILTER_VALIDATE_IP)) {
      return ['success' => FALSE, 'error' => 'Unable to resolve hostname.'];
    }

    // Reject private ranges, reserved ranges, loopback, and link-local.
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
      return [
        'success' => FALSE,
        'error' => 'URLs pointing to private or internal networks are not allowed.',
      ];
    }

    return NULL;
  }

  /**
   * Validates that a filename extension is not blocked.
   *
   * @param string $filename
   *   The filename to check.
   *
   * @return array|null
   *   Error array if extension is blocked, NULL if safe.
   */
  protected function validateExtension(string $filename): ?array {
    $ext = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
    if ($ext !== '' && in_array($ext, static::BLOCKED_UPLOAD_EXTENSIONS, TRUE)) {
      return [
        'success' => FALSE,
        'error' => "File extension '$ext' is not allowed for uploads.",
      ];
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
   * Sanitizes file content if needed for the given MIME type.
   *
   * Subclasses can override this to apply format-specific sanitization
   * (e.g. SVG script removal). The default implementation returns the
   * content unchanged.
   *
   * @param string $body
   *   The raw file content.
   * @param string $mimeType
   *   The detected MIME type.
   *
   * @return array
   *   Array with 'body' key on success, or 'success' => FALSE + 'error'
   *   if sanitization fails.
   */
  protected function sanitizeContent(string $body, string $mimeType): array {
    return ['body' => $body];
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
   * Delegates to MediaService to avoid duplicating entity creation logic.
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
    $result = $this->mediaService->createMedia($bundle, $name, $fid);

    if (!$result['success']) {
      // Preserve file data so callers know the file was saved.
      $result['data'] = $fileData;
      $result['error'] .= " File was saved (fid: $fid).";
      return $result;
    }

    return [
      'success' => TRUE,
      'data' => array_merge($fileData, [
        'mid' => $result['data']['mid'],
        'media_uuid' => $result['data']['uuid'],
      ]),
    ];
  }

  /**
   * Template method: fetch a remote file and optionally create media.
   *
   * Orchestrates the full validate-fetch-save flow so that concrete
   * subclasses do not need to reimplement it.
   *
   * @param string $url
   *   The remote file URL (http/https only).
   * @param string $name
   *   Human-readable name for the entity.
   * @param string $directory
   *   Target Drupal stream wrapper directory.
   * @param string $bundle
   *   Media type bundle machine name.
   * @param bool $createMedia
   *   Whether to create a media entity after saving the file.
   *
   * @return array
   *   Result array with success status and data.
   */
  protected function fetchAndCreate(
    string $url,
    string $name,
    string $directory,
    string $bundle,
    bool $createMedia,
  ): array {
    if ($error = $this->validateAccess()) {
      return $error;
    }

    if ($error = $this->validateUrl($url)) {
      return $error;
    }

    if ($error = $this->validateNotInternalUrl($url)) {
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
      $contentType = $response->getHeaderLine('Content-Type');
      $mimeType = $this->parseMimeFromContentType($contentType);

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

      $sanitizeResult = $this->sanitizeContent($body, $mimeType);
      if (isset($sanitizeResult['error'])) {
        return $sanitizeResult;
      }
      $body = $sanitizeResult['body'];

      $safeFilename = $this->buildFilename($url, $name, $mimeType);

      if ($error = $this->validateExtension($safeFilename)) {
        return $error;
      }

      $fileData = $this->saveFileEntity(
        $body, $directory, $safeFilename, $mimeType, $url,
      );

      if (!$createMedia) {
        $fid = $fileData['fid'];
        $op = $this->getOperationName();
        $fileData['message'] = "Remote $op: file saved (fid: $fid).";
        return ['success' => TRUE, 'data' => $fileData];
      }

      return $this->createMediaEntity(
        $bundle, $name, $fileData['fid'], $fileData,
      );
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure(
        $this->getOperationName(), 'file', 'new',
        ['url' => $url, 'error' => $e->getMessage()],
      );
      return [
        'success' => FALSE,
        'error' => 'Failed to fetch remote file: ' . $e->getMessage(),
      ];
    }
  }

}
