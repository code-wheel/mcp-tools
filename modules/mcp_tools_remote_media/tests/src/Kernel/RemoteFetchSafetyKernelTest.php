<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_remote_media\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

/**
 * What a fetch of a remote image may reach, and what it may save.
 *
 * The internal-network check looked at the first address only, and the
 * fetch then followed redirects. And an SVG was cleaned only when the
 * server called it one: sent as image/png, it was saved with its script.
 * A fake server stands in for the remote one: nothing leaves the test.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_remote_media')]
final class RemoteFetchSafetyKernelTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'field', 'file', 'image', 'media', 'text', 'filter', 'dblog', 'update', 'tool', 'mcp_tools', 'mcp_tools_media', 'mcp_tools_remote_media'];

  /**
   * A public address, given as an IP so no name is looked up.
   */
  private const PUBLIC = 'http://93.184.215.14';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('dblog', ['watchdog']);
    $this->installConfig(['system', 'mcp_tools']);
    $this->setUpCurrentUser([], [], TRUE);
    $this->container->get('mcp_tools.access_manager')->setScopes(['read', 'write']);
  }

  /**
   * The service, with a fake remote server that answers these responses.
   */
  private function fetch(string $url, Response ...$responses): array {
    $mock = new MockHandler($responses);
    $this->container->set('http_client', new Client(['handler' => HandlerStack::create($mock)]));
    $result = $this->container->get('mcp_tools_remote_media.remote_image')->fetchRemoteImage($url, 'Probe', 'public://mcp-uploads', 'image', FALSE);
    $result['requests'] = count($responses) - $mock->count();
    return $result;
  }

  /**
   * A one-pixel PNG.
   */
  private function png(): string {
    return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
  }

  /**
   * A redirect to an internal address is not followed.
   */
  public function testARedirectInsideIsNotFollowed(): void {
    $result = $this->fetch(self::PUBLIC . '/a.png',
      new Response(302, ['Location' => 'http://127.0.0.1/secret.png']),
      new Response(200, ['Content-Type' => 'image/png'], $this->png()),
    );
    $this->assertFalse($result['success'], 'A redirect to 127.0.0.1 was followed and its answer saved.');
    $this->assertSame(1, $result['requests'], 'The internal address was asked.');

    // A redirect to another public address is fine.
    $result = $this->fetch(self::PUBLIC . '/b.png',
      new Response(301, ['Location' => 'http://93.184.215.15/b.png']),
      new Response(200, ['Content-Type' => 'image/png'], $this->png()),
    );
    $this->assertTrue($result['success'], (string) ($result['error'] ?? ''));
  }

  /**
   * An SVG is cleaned whatever the server calls it.
   */
  public function testAnSvgSentAsPngIsCleaned(): void {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><script>alert(2)</script><rect width="1" height="1"/></svg>';
    $result = $this->fetch(self::PUBLIC . '/x.svg', new Response(200, ['Content-Type' => 'image/png'], $svg));
    if ($result['success']) {
      $saved = (string) file_get_contents($result['data']['uri']);
      $this->assertStringNotContainsString('onload', $saved, 'An SVG sent as image/png was saved with its script.');
      $this->assertStringNotContainsString('<script', $saved);
    }
    else {
      $this->assertStringContainsString('content', strtolower($result['error']));
    }
  }

}
