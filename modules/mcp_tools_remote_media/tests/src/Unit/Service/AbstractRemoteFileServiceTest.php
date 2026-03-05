<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_remote_media\Unit\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\mcp_tools_remote_media\Service\AbstractRemoteFileService;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\Client;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for the shared validation logic in AbstractRemoteFileService.
 *
 * Uses an anonymous class stub to instantiate the abstract class so that
 * the base methods can be tested in isolation, independently of any concrete
 * subclass implementation.
 */
#[CoversClass(AbstractRemoteFileService::class)]
#[Group('mcp_tools_remote_media')]
class AbstractRemoteFileServiceTest extends UnitTestCase {

  /**
   * Creates an anonymous concrete stub of AbstractRemoteFileService.
   *
   * The stub returns a minimal but valid implementation of all abstract
   * methods, sufficient for testing the base class logic.
   *
   * @return \Drupal\mcp_tools_remote_media\Service\AbstractRemoteFileService
   *   The stub instance.
   */
  protected function buildServiceStub(): AbstractRemoteFileService {
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $fileSystem        = $this->createMock(FileSystemInterface::class);
    $httpClient        = $this->getMockBuilder(Client::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['get', 'request', 'send'])
      ->getMock();
    $accessManager     = $this->createMock(AccessManager::class);
    $auditLogger       = $this->createMock(AuditLogger::class);
    $time              = $this->createMock(TimeInterface::class);

    return new class(
      $entityTypeManager,
      $fileSystem,
      $httpClient,
      $accessManager,
      $auditLogger,
      $time,
    ) extends AbstractRemoteFileService {

      /**
       * {@inheritdoc}
       */
      protected function getAllowedMimeTypes(): array {
        return ['image/jpeg', 'image/png'];
      }

      /**
       * {@inheritdoc}
       */
      protected function getMimeToExtMap(): array {
        return ['image/jpeg' => 'jpg', 'image/png' => 'png'];
      }

      /**
       * {@inheritdoc}
       */
      protected function getOperationName(): string {
        return 'test_operation';
      }

      /**
       * Exposes validateUrl() as public for testing.
       */
      public function exposeValidateUrl(string $url): ?array {
        return $this->validateUrl($url);
      }

      /**
       * Exposes validateDirectory() as public for testing.
       */
      public function exposeValidateDirectory(string $directory): ?array {
        return $this->validateDirectory($directory);
      }

      /**
       * Exposes validateBody() as public for testing.
       */
      public function exposeValidateBody(string $body): ?array {
        return $this->validateBody($body);
      }

    };
  }

  /**
   * Tests that an invalid URL returns an error.
   */
  public function testValidateUrlRejectsInvalidUrl(): void {
    $stub = $this->buildServiceStub();
    $result = $stub->exposeValidateUrl('not-a-url');
    $this->assertIsArray($result);
    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Invalid URL', $result['error']);
  }

  /**
   * Tests that a non-HTTP scheme returns an error.
   */
  public function testValidateUrlRejectsNonHttpScheme(): void {
    $stub = $this->buildServiceStub();
    $result = $stub->exposeValidateUrl('ftp://example.com/image.jpg');
    $this->assertIsArray($result);
    $this->assertFalse($result['success']);
    $this->assertStringContainsString('http', $result['error']);
  }

  /**
   * Tests that a valid http URL passes validation.
   */
  public function testValidateUrlAcceptsHttpUrl(): void {
    $stub = $this->buildServiceStub();
    $this->assertNull($stub->exposeValidateUrl('http://example.com/image.jpg'));
  }

  /**
   * Tests that a valid https URL passes validation.
   */
  public function testValidateUrlAcceptsHttpsUrl(): void {
    $stub = $this->buildServiceStub();
    $this->assertNull($stub->exposeValidateUrl('https://example.com/image.jpg'));
  }

  /**
   * Tests that invalid directory paths return an error.
   *
   * @param string $directory
   *   The directory path to test.
   */
  #[DataProvider('invalidDirectoryProvider')]
  public function testValidateDirectoryRejectsInvalidPaths(string $directory): void {
    $stub = $this->buildServiceStub();
    $result = $stub->exposeValidateDirectory($directory);
    $this->assertIsArray($result);
    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Invalid directory', $result['error']);
  }

  /**
   * Data provider for invalid directories.
   *
   * @return array<string, array<int, string>>
   *   Array of invalid directory paths.
   */
  public static function invalidDirectoryProvider(): array {
    return [
      'path traversal'  => ['public://../../../etc'],
      'relative path'   => ['../uploads'],
      'absolute path'   => ['/var/www/uploads'],
      'http url'        => ['http://example.com/uploads'],
      'invalid scheme'  => ['ftp://uploads'],
      'dots in path'    => ['public://test/../secret'],
    ];
  }

  /**
   * Tests that a valid public:// directory passes validation.
   */
  public function testValidateDirectoryAcceptsPublicStream(): void {
    $stub = $this->buildServiceStub();
    $this->assertNull($stub->exposeValidateDirectory('public://mcp-uploads'));
  }

  /**
   * Tests that a valid private:// directory passes validation.
   */
  public function testValidateDirectoryAcceptsPrivateStream(): void {
    $stub = $this->buildServiceStub();
    $this->assertNull($stub->exposeValidateDirectory('private://secure-uploads'));
  }

  /**
   * Tests that an empty body returns an error.
   */
  public function testValidateBodyRejectsEmpty(): void {
    $stub = $this->buildServiceStub();
    $result = $stub->exposeValidateBody('');
    $this->assertIsArray($result);
    $this->assertFalse($result['success']);
    $this->assertStringContainsString('empty', $result['error']);
  }

  /**
   * Tests that a body exceeding 10 MiB returns an error.
   */
  public function testValidateBodyRejectsOversized(): void {
    $stub = $this->buildServiceStub();
    $bigContent = str_repeat('A', 11 * 1024 * 1024);
    $result = $stub->exposeValidateBody($bigContent);
    $this->assertIsArray($result);
    $this->assertFalse($result['success']);
    $this->assertStringContainsString('too large', $result['error']);
  }

}
