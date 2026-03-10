<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_remote_media\Unit\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\mcp_tools_media\Service\MediaService;
use Drupal\mcp_tools_remote_media\Service\AbstractRemoteFileService;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\Client;
/**
 * Tests for the shared validation logic in AbstractRemoteFileService.
 *
 * Uses an anonymous class stub to instantiate the abstract class so that
 * the base methods can be tested in isolation, independently of any concrete
 * subclass implementation.
 *
 * @group mcp_tools_remote_media
 */
#[\PHPUnit\Framework\Attributes\CoversClass(AbstractRemoteFileService::class)]
class AbstractRemoteFileServiceTest extends UnitTestCase {

  /**
   * Creates an anonymous concrete stub of AbstractRemoteFileService.
   *
   * @return \Drupal\mcp_tools_remote_media\Service\AbstractRemoteFileService
   *   The stub instance.
   */
  protected function buildServiceStub(): AbstractRemoteFileService {
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $fileSystem = $this->createMock(FileSystemInterface::class);
    $httpClient = $this->getMockBuilder(Client::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['get', 'request', 'send'])
      ->getMock();
    $accessManager = $this->createMock(AccessManager::class);
    $auditLogger = $this->createMock(AuditLogger::class);
    $time = $this->createMock(TimeInterface::class);
    $mediaService = $this->createMock(MediaService::class);

    return new class(
      $entityTypeManager,
      $fileSystem,
      $httpClient,
      $accessManager,
      $auditLogger,
      $time,
      $mediaService,
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
       * Exposes validateUrl() for testing.
       */
      public function exposeValidateUrl(string $url): ?array {
        return $this->validateUrl($url);
      }

      /**
       * Exposes validateDirectory() for testing.
       */
      public function exposeValidateDirectory(string $directory): ?array {
        return $this->validateDirectory($directory);
      }

      /**
       * Exposes validateBody() for testing.
       */
      public function exposeValidateBody(string $body): ?array {
        return $this->validateBody($body);
      }

      /**
       * Exposes validateNotInternalUrl() for testing.
       */
      public function exposeValidateNotInternalUrl(string $url): ?array {
        return $this->validateNotInternalUrl($url);
      }

      /**
       * Exposes validateExtension() for testing.
       */
      public function exposeValidateExtension(string $filename): ?array {
        return $this->validateExtension($filename);
      }

      /**
       * Exposes buildFilename() for testing.
       */
      public function exposeBuildFilename(
        string $url,
        string $name,
        string $mimeType,
      ): string {
        return $this->buildFilename($url, $name, $mimeType);
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
   * @dataProvider invalidDirectoryProvider
   *
   * @param string $directory
   *   The directory path to test.
   */
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
      'path traversal' => ['public://../../../etc'],
      'relative path' => ['../uploads'],
      'absolute path' => ['/var/www/uploads'],
      'http url' => ['http://example.com/uploads'],
      'invalid scheme' => ['ftp://uploads'],
      'dots in path' => ['public://test/../secret'],
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

  /**
   * Tests SSRF: private/internal IPs are blocked.
   *
   * @dataProvider privateIpUrlProvider
   *
   * @param string $url
   *   URL with a private/reserved IP.
   */
  public function testValidateNotInternalUrlBlocksPrivateIps(string $url): void {
    $stub = $this->buildServiceStub();
    $result = $stub->exposeValidateNotInternalUrl($url);
    $this->assertIsArray($result);
    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not allowed', $result['error']);
  }

  /**
   * Data provider for private/reserved IP URLs.
   *
   * @return array<string, array<int, string>>
   *   Array of URLs that resolve to private IPs.
   */
  public static function privateIpUrlProvider(): array {
    return [
      'loopback' => ['http://127.0.0.1/image.jpg'],
      'rfc1918 10.x' => ['http://10.0.0.1/image.jpg'],
      'rfc1918 172.16' => ['http://172.16.0.1/image.jpg'],
      'rfc1918 192.168' => ['http://192.168.1.1/image.jpg'],
      'link-local' => ['http://169.254.169.254/latest/meta-data/'],
      'zero network' => ['http://0.0.0.0/image.jpg'],
    ];
  }

  /**
   * Tests that a public IP passes SSRF validation.
   */
  public function testValidateNotInternalUrlAllowsPublicIp(): void {
    $stub = $this->buildServiceStub();
    // 8.8.8.8 is a well-known public IP.
    $this->assertNull($stub->exposeValidateNotInternalUrl('http://8.8.8.8/image.jpg'));
  }

  /**
   * Tests that .php extension is blocked.
   */
  public function testValidateExtensionBlocksPhp(): void {
    $stub = $this->buildServiceStub();
    $result = $stub->exposeValidateExtension('shell.php');
    $this->assertIsArray($result);
    $this->assertFalse($result['success']);
    $this->assertStringContainsString('not allowed', $result['error']);
  }

  /**
   * Tests that .phar extension is blocked.
   */
  public function testValidateExtensionBlocksPhar(): void {
    $stub = $this->buildServiceStub();
    $result = $stub->exposeValidateExtension('exploit.phar');
    $this->assertIsArray($result);
    $this->assertFalse($result['success']);
  }

  /**
   * Tests that .jpg extension is allowed.
   */
  public function testValidateExtensionAllowsSafeExtension(): void {
    $stub = $this->buildServiceStub();
    $this->assertNull($stub->exposeValidateExtension('photo.jpg'));
  }

  /**
   * Tests buildFilename with a URL that has a valid extension.
   */
  public function testBuildFilenameWithUrlExtension(): void {
    $stub = $this->buildServiceStub();
    $result = $stub->exposeBuildFilename(
      'https://example.com/photo.jpg',
      'My Photo',
      'image/jpeg',
    );
    $this->assertSame('photo.jpg', $result);
  }

  /**
   * Tests buildFilename falls back to name when URL has no extension.
   */
  public function testBuildFilenameWithoutExtension(): void {
    $stub = $this->buildServiceStub();
    $result = $stub->exposeBuildFilename(
      'https://example.com/image',
      'My Photo',
      'image/jpeg',
    );
    $this->assertSame('My_Photo.jpg', $result);
  }

  /**
   * Tests buildFilename with an overly long extension triggers fallback.
   */
  public function testBuildFilenameWithLongExtension(): void {
    $stub = $this->buildServiceStub();
    $result = $stub->exposeBuildFilename(
      'https://example.com/file.toolong',
      'My File',
      'image/png',
    );
    $this->assertSame('My_File.png', $result);
  }

  /**
   * Tests buildFilename sanitises special characters.
   */
  public function testBuildFilenameWithSpecialChars(): void {
    $stub = $this->buildServiceStub();
    $result = $stub->exposeBuildFilename(
      'https://example.com/my photo (1).jpg',
      'test',
      'image/jpeg',
    );
    // Spaces and parens replaced with underscores.
    $this->assertMatchesRegularExpression('/^[a-zA-Z0-9._-]+$/', $result);
    $this->assertStringEndsWith('.jpg', $result);
  }

}
