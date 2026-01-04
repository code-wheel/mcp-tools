<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_media\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\media\Entity\MediaType;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;

/**
 * Service for media management operations.
 */
class MediaService {

  /**
   * Maximum decoded upload size for base64 uploads (10 MiB).
   */
  private const MAX_UPLOAD_BYTES = 10485760;

  /**
   * Extensions blocked from being written to file directories.
   *
   * This prevents accidental code execution on misconfigured servers.
   */
  private const BLOCKED_UPLOAD_EXTENSIONS = [
    'php',
    'php3',
    'php4',
    'php5',
    'phtml',
    'phar',
    'cgi',
    'pl',
    'py',
    'sh',
    'exe',
    'bat',
    'cmd',
    'com',
    'msi',
    'jsp',
    'asp',
    'aspx',
  ];

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected FileSystemInterface $fileSystem,
    protected AccessManager $accessManager,
    protected AuditLogger $auditLogger,
  ) {}

  /**
   * Create a new media type.
   */
  public function createMediaType(string $id, string $label, string $sourcePlugin): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    $existingType = $this->entityTypeManager->getStorage('media_type')->load($id);
    if ($existingType) {
      return ['success' => FALSE, 'error' => "Media type '$id' already exists."];
    }

    try {
      $mediaType = MediaType::create([
        'id' => $id,
        'label' => $label,
        'source' => $sourcePlugin,
      ]);
      $mediaType->save();

      // Create source field for the media type.
      $source = $mediaType->getSource();
      $sourceField = $source->createSourceField($mediaType);
      $sourceField->getFieldStorageDefinition()->save();
      $sourceField->save();

      // Set the source field configuration.
      $mediaType->set('source_configuration', [
        'source_field' => $sourceField->getName(),
      ]);
      $mediaType->save();

      $this->auditLogger->logSuccess('create_media_type', 'media_type', $id, [
        'label' => $label,
        'source_plugin' => $sourcePlugin,
      ]);

      return [
        'success' => TRUE,
        'data' => [
          'id' => $id,
          'label' => $label,
          'source_plugin' => $sourcePlugin,
          'source_field' => $sourceField->getName(),
          'message' => "Media type '$label' created successfully.",
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('create_media_type', 'media_type', $id, ['error' => $e->getMessage()]);
      return ['success' => FALSE, 'error' => 'Failed to create media type: ' . $e->getMessage()];
    }
  }

  /**
   * Delete a media type.
   */
  public function deleteMediaType(string $id): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    $mediaType = $this->entityTypeManager->getStorage('media_type')->load($id);
    if (!$mediaType) {
      return ['success' => FALSE, 'error' => "Media type '$id' not found. Use mcp_list_media_types to see available types."];
    }

    // Check if any media entities use this type.
    // SECURITY NOTE: accessCheck(FALSE) is intentional here.
    // This is a system-level count query to prevent deleting media types
    // that still have content. We need to count ALL media entities,
    // not just those the current user can access.
    $mediaCount = $this->entityTypeManager->getStorage('media')
      ->getQuery()
      ->condition('bundle', $id)
      ->accessCheck(FALSE)
      ->count()
      ->execute();

    if ($mediaCount > 0) {
      return [
        'success' => FALSE,
        'error' => "Cannot delete media type '$id': $mediaCount media entities still use this type.",
      ];
    }

    try {
      $label = $mediaType->label();
      $mediaType->delete();

      $this->auditLogger->logSuccess('delete_media_type', 'media_type', $id, ['label' => $label]);

      return [
        'success' => TRUE,
        'data' => [
          'id' => $id,
          'label' => $label,
          'message' => "Media type '$label' deleted successfully.",
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('delete_media_type', 'media_type', $id, ['error' => $e->getMessage()]);
      return ['success' => FALSE, 'error' => 'Failed to delete media type: ' . $e->getMessage()];
    }
  }

  /**
   * Upload a file from base64 data.
   */
  public function uploadFile(string $filename, string $data, string $directory = 'public://mcp-uploads'): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    try {
      // Validate directory to prevent path traversal - only allow public:// and private://.
      if (!preg_match('/^(public|private):\/\/[a-zA-Z0-9_\-\/]+$/', $directory) || str_contains($directory, '..')) {
        return ['success' => FALSE, 'error' => 'Invalid directory. Only public:// and private:// stream wrappers allowed.'];
      }

      // Support data URIs (data:*;base64,...) by stripping the prefix.
      if (str_starts_with($data, 'data:') && str_contains($data, 'base64,')) {
        $data = substr($data, (int) strpos($data, 'base64,') + 7);
      }

      // Reject large payloads before decoding to avoid memory exhaustion.
      $estimatedBytes = (int) floor(strlen($data) * 0.75);
      if ($estimatedBytes > self::MAX_UPLOAD_BYTES) {
        return [
          'success' => FALSE,
          'error' => 'Upload too large. Maximum size is ' . self::MAX_UPLOAD_BYTES . ' bytes.',
          'code' => 'PAYLOAD_TOO_LARGE',
        ];
      }

      // Decode base64 data.
      $decodedData = base64_decode($data, TRUE);
      if ($decodedData === FALSE) {
        return ['success' => FALSE, 'error' => 'Invalid base64 data provided.'];
      }

      if (strlen($decodedData) > self::MAX_UPLOAD_BYTES) {
        return [
          'success' => FALSE,
          'error' => 'Upload too large. Maximum size is ' . self::MAX_UPLOAD_BYTES . ' bytes.',
          'code' => 'PAYLOAD_TOO_LARGE',
        ];
      }

      // Ensure directory exists.
      $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

      // Sanitize filename.
      $safeFilename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
      $safeFilename = (string) $safeFilename;

      if ($safeFilename === '' || str_starts_with($safeFilename, '.')) {
        return ['success' => FALSE, 'error' => 'Invalid filename.'];
      }

      $extension = strtolower((string) pathinfo($safeFilename, PATHINFO_EXTENSION));
      if ($extension !== '' && in_array($extension, self::BLOCKED_UPLOAD_EXTENSIONS, TRUE)) {
        return [
          'success' => FALSE,
          'error' => "File extension '$extension' is not allowed for uploads.",
          'code' => 'INVALID_FILE_TYPE',
        ];
      }

      $destination = $directory . '/' . $safeFilename;

      // Save the file.
      $uri = $this->fileSystem->saveData($decodedData, $destination, FileSystemInterface::EXISTS_RENAME);

      // Create file entity.
      $file = File::create([
        'uri' => $uri,
        'filename' => $safeFilename,
        'status' => 1,
      ]);
      $file->save();

      $this->auditLogger->logSuccess('upload_file', 'file', (string) $file->id(), [
        'filename' => $safeFilename,
        'uri' => $uri,
      ]);

      return [
        'success' => TRUE,
        'data' => [
          'fid' => $file->id(),
          'uuid' => $file->uuid(),
          'filename' => $safeFilename,
          'uri' => $uri,
          'url' => $file->createFileUrl(FALSE),
          'message' => "File '$safeFilename' uploaded successfully.",
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('upload_file', 'file', 'new', ['error' => $e->getMessage()]);
      return ['success' => FALSE, 'error' => 'Failed to upload file: ' . $e->getMessage()];
    }
  }

  /**
   * Create a media entity.
   */
  public function createMedia(string $bundle, string $name, mixed $sourceFieldValue): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    $mediaType = $this->entityTypeManager->getStorage('media_type')->load($bundle);
    if (!$mediaType) {
      return ['success' => FALSE, 'error' => "Media type '$bundle' not found. Use mcp_list_media_types to see available types."];
    }

    try {
      // Get the source field name.
      $sourceConfiguration = $mediaType->get('source_configuration');
      $sourceFieldName = $sourceConfiguration['source_field'] ?? NULL;

      if (!$sourceFieldName) {
        return ['success' => FALSE, 'error' => "Media type '$bundle' has no source field configured."];
      }

      $media = Media::create([
        'bundle' => $bundle,
        'name' => $name,
        $sourceFieldName => $sourceFieldValue,
      ]);
      $media->save();

      $this->auditLogger->logSuccess('create_media', 'media', (string) $media->id(), [
        'name' => $name,
        'bundle' => $bundle,
      ]);

      return [
        'success' => TRUE,
        'data' => [
          'mid' => $media->id(),
          'uuid' => $media->uuid(),
          'name' => $name,
          'bundle' => $bundle,
          'message' => "Media '$name' created successfully.",
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('create_media', 'media', 'new', ['error' => $e->getMessage()]);
      return ['success' => FALSE, 'error' => 'Failed to create media: ' . $e->getMessage()];
    }
  }

  /**
   * Delete a media entity.
   */
  public function deleteMedia(int $mid): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    $media = $this->entityTypeManager->getStorage('media')->load($mid);
    if (!$media) {
      return ['success' => FALSE, 'error' => "Media with ID $mid not found. Use mcp_list_media to browse media items."];
    }

    try {
      $name = $media->getName();
      $bundle = $media->bundle();
      $media->delete();

      $this->auditLogger->logSuccess('delete_media', 'media', (string) $mid, [
        'name' => $name,
        'bundle' => $bundle,
      ]);

      return [
        'success' => TRUE,
        'data' => [
          'mid' => $mid,
          'name' => $name,
          'bundle' => $bundle,
          'message' => "Media '$name' deleted successfully.",
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('delete_media', 'media', (string) $mid, ['error' => $e->getMessage()]);
      return ['success' => FALSE, 'error' => 'Failed to delete media: ' . $e->getMessage()];
    }
  }

  /**
   * List available media types with source plugins.
   */
  public function listMediaTypes(): array {
    // Read-only operation - only requires read access.
    if (!$this->accessManager->canRead()) {
      return $this->accessManager->getReadAccessDenied();
    }

    try {
      $mediaTypes = $this->entityTypeManager->getStorage('media_type')->loadMultiple();
      $types = [];

      foreach ($mediaTypes as $mediaType) {
        $sourceConfiguration = $mediaType->get('source_configuration');
        $types[] = [
          'id' => $mediaType->id(),
          'label' => $mediaType->label(),
          'source_plugin' => $mediaType->getSource()->getPluginId(),
          'source_field' => $sourceConfiguration['source_field'] ?? NULL,
          'description' => $mediaType->getDescription(),
        ];
      }

      $this->auditLogger->logSuccess('list_media_types', 'media_type', 'all', [
        'count' => count($types),
      ]);

      return [
        'success' => TRUE,
        'data' => [
          'types' => $types,
          'count' => count($types),
          'message' => 'Found ' . count($types) . ' media type(s).',
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('list_media_types', 'media_type', 'all', ['error' => $e->getMessage()]);
      return ['success' => FALSE, 'error' => 'Failed to list media types: ' . $e->getMessage()];
    }
  }

}
