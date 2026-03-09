<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_remote_media\Unit\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\mcp_tools_remote_media\Service\RemoteImageService;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for RemoteImageService.
 *
 * Covers image-specific logic (MIME validation, orchestration) and
 * integration with the abstract base class via the full service stack.
 */
#[CoversClass(RemoteImageService::class)]
#[Group('mcp_tools_remote_media')]
class RemoteImageServiceTest extends UnitTestCase {

  /**
   * The entity type manager mock.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The file system mock.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected FileSystemInterface $fileSystem;

  /**
   * The HTTP client mock.
   *
   * @var \GuzzleHttp\Client
   */
  protected Client $httpClient;

  /**
   * The access manager mock.
   *
   * @var \Drupal\mcp_tools\Service\AccessManager
   */
  protected AccessManager $accessManager;

  /**
   * The audit logger mock.
   *
   * @var \Drupal\mcp_tools\Service\AuditLogger
   */
  protected AuditLogger $auditLogger;

  /**
   * The time service mock.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected TimeInterface $time;

  /**
   * The media type storage mock.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $mediaTypeStorage;

  /**
   * The media storage mock.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $mediaStorage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->fileSystem        = $this->createMock(FileSystemInterface::class);
    $this->httpClient        = $this->getMockBuilder(Client::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['get', 'request', 'send'])
      ->getMock();
    $this->accessManager     = $this->createMock(AccessManager::class);
    $this->auditLogger       = $this->createMock(AuditLogger::class);
    $this->time              = $this->createMock(TimeInterface::class);

    $this->time->method('getCurrentTime')->willReturn(1234567890);

    $this->mediaTypeStorage = $this->createMock(EntityStorageInterface::class);
    $this->mediaStorage     = $this->createMock(EntityStorageInterface::class);

    $this->entityTypeManager->method('getStorage')
      ->willReturnMap([
        ['media_type', $this->mediaTypeStorage],
        ['media', $this->mediaStorage],
      ]);
  }

  /**
   * Creates a RemoteImageService instance.
   */
  protected function createService(): RemoteImageService {
    return new RemoteImageService(
      $this->entityTypeManager,
      $this->fileSystem,
      $this->httpClient,
      $this->accessManager,
      $this->auditLogger,
      $this->time,
    );
  }

  /**
   * Tests that getAllowedMimeTypes() returns exactly the 5 image MIME types.
   */
  public function testGetAllowedMimeTypesReturnsImageTypes(): void {
    $service = $this->createService();

    $reflection = new \ReflectionClass($service);
    $method = $reflection->getMethod('getAllowedMimeTypes');
    $method->setAccessible(TRUE);
    $mimeTypes = $method->invoke($service);

    $this->assertCount(5, $mimeTypes);
    $this->assertContains('image/jpeg', $mimeTypes);
    $this->assertContains('image/png', $mimeTypes);
    $this->assertContains('image/gif', $mimeTypes);
    $this->assertContains('image/webp', $mimeTypes);
    $this->assertContains('image/svg+xml', $mimeTypes);
  }

  /**
   * Tests that getMimeToExtMap() has an entry for every allowed MIME type.
   */
  public function testGetMimeToExtMapCoversAllAllowedTypes(): void {
    $service = $this->createService();

    $reflection = new \ReflectionClass($service);

    $mimeTypesMethod = $reflection->getMethod('getAllowedMimeTypes');
    $mimeTypesMethod->setAccessible(TRUE);
    $allowedMimes = $mimeTypesMethod->invoke($service);

    $mimeToExtMethod = $reflection->getMethod('getMimeToExtMap');
    $mimeToExtMethod->setAccessible(TRUE);
    $mimeToExt = $mimeToExtMethod->invoke($service);

    foreach ($allowedMimes as $mime) {
      $this->assertArrayHasKey($mime, $mimeToExt, "getMimeToExtMap() is missing an entry for '$mime'");
      $this->assertNotEmpty($mimeToExt[$mime], "getMimeToExtMap() has an empty extension for '$mime'");
    }
  }

  /**
   * Tests that getOperationName() returns the correct string.
   */
  public function testGetOperationNameReturnsCorrectString(): void {
    $service = $this->createService();

    $reflection = new \ReflectionClass($service);
    $method = $reflection->getMethod('getOperationName');
    $method->setAccessible(TRUE);

    $this->assertSame('fetch_remote_image', $method->invoke($service));
  }

  /**
   * Tests that access is denied when the user cannot write.
   */
  public function testAccessDeniedWhenCannotWrite(): void {
    $this->accessManager->method('canWrite')->willReturn(FALSE);
    $this->accessManager->method('getWriteAccessDenied')->willReturn([
      'success' => FALSE,
      'error'   => 'Write access denied.',
    ]);

    $result = $this->createService()->fetchRemoteImage('https://example.com/image.jpg', 'Test');
    $this->assertFalse($result['success']);
    $this->assertStringContainsString('denied', $result['error']);
  }

  /**
   * Tests that an unsupported MIME type (e.g. PDF) returns an error.
   */
  public function testUnsupportedMimeTypeReturnsFalse(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);

    $response = new Response(200, ['Content-Type' => 'application/pdf'], 'fake-content');
    $this->httpClient->method('get')->willReturn($response);

    $result = $this->createService()->fetchRemoteImage('https://example.com/file.pdf', 'Test');
    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Unsupported content type', $result['error']);
  }

  /**
   * Tests that a Guzzle HTTP failure returns an error.
   */
  public function testHttpRequestFailureReturnsFalse(): void {
    $this->accessManager->method('canWrite')->willReturn(TRUE);

    $this->httpClient->method('get')->willThrowException(
      new RequestException('Connection refused', new Request('GET', 'https://example.com/image.jpg'))
    );

    $result = $this->createService()->fetchRemoteImage('https://example.com/image.jpg', 'Test');
    $this->assertFalse($result['success']);
    $this->assertStringContainsString('HTTP request failed', $result['error']);
  }

}
