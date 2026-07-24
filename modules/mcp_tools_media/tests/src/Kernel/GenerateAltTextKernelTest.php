<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_media\Kernel;

use Drupal\file\Entity\File;
use Drupal\KernelTests\KernelTestBase;
use Drupal\media\Entity\Media;
use Drupal\mcp_tools\Mcp\ToolApiCallToolHandler;
use Drupal\mcp_tools\Mcp\ToolApiSchemaConverter;
use Drupal\mcp_tools\Mcp\ToolInputValidator;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Mcp\Schema\JsonRpc\Response;
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Schema\Request\CreateSamplingMessageRequest;
use Mcp\Server\Session\SessionInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Uid\Uuid;

/**
 * Alt text generation via MCP sampling (the client's own model).
 */
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_media')]
class GenerateAltTextKernelTest extends KernelTestBase {

  use MediaTypeCreationTrait;
  use UserCreationTrait;

  /**
   * A 1x1 transparent PNG.
   */
  protected const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'text',
    'file',
    'image',
    'media',
    'dblog',
    'update',
    'tool',
    'mcp_tools',
    'mcp_tools_media',
  ];

  /**
   * The image media entity under test.
   */
  protected Media $media;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('user', ['users_data']);
    $this->installSchema('dblog', ['watchdog']);
    $this->installConfig(['system', 'field', 'filter', 'image', 'media', 'mcp_tools']);

    $mediaType = $this->createMediaType('image', ['id' => 'image']);
    $sourceField = $mediaType->getSource()->getConfiguration()['source_field'];

    file_put_contents('public://alt-test.png', base64_decode(self::PNG));
    $file = File::create(['uri' => 'public://alt-test.png']);
    $file->setPermanent();
    $file->save();

    $this->media = Media::create([
      'bundle' => 'image',
      'name' => 'Test image',
      $sourceField => ['target_id' => $file->id(), 'alt' => ''],
    ]);
    $this->media->save();

    $this->setUpCurrentUser([], ['mcp_tools use media']);
    $this->config('mcp_tools.settings')
      ->set('access.allowed_scopes', ['read', 'write'])
      ->set('access.read_only_mode', FALSE)
      ->save();
    $this->container->get('mcp_tools.access_manager')->setScopes(['read', 'write']);
  }

  /**
   * Builds the handler under test.
   */
  protected function buildHandler(): ToolApiCallToolHandler {
    return new ToolApiCallToolHandler(
      $this->container->get('plugin.manager.tool'),
      new NullLogger(),
      FALSE,
      'mcp_tools',
      new ToolInputValidator(new ToolApiSchemaConverter(), new NullLogger()),
      NULL,
      NULL,
      $this->container->get('mcp_tools.client_bridge'),
    );
  }

  /**
   * The client's model describes the image; the text lands as alt.
   */
  public function testSamplingWritesAltText(): void {
    $handler = $this->buildHandler();

    $session = $this->createMock(SessionInterface::class);
    $session->method('get')->willReturnCallback(
      static fn(string $key, mixed $default = NULL) => $key === 'client_capabilities'
        ? ['sampling' => []]
        : $default,
    );
    $session->method('getId')->willReturn(Uuid::v4());

    $request = (new CallToolRequest('mcp_generate_alt_text', [
      'mid' => (int) $this->media->id(),
    ]))->withId(1);

    $fiber = new \Fiber(static fn() => $handler->handle($request, $session));
    $value = $fiber->start();
    $sampling = NULL;
    while (!$fiber->isTerminated()) {
      if (is_array($value) && ($value['type'] ?? '') === 'request' && $value['request'] instanceof CreateSamplingMessageRequest) {
        $sampling = $value['request'];
        $value = $fiber->resume(new Response(1, [
          'role' => 'assistant',
          'model' => 'client-model',
          'content' => ['type' => 'text', 'text' => 'A tiny transparent pixel.'],
        ]));
        continue;
      }
      $value = $fiber->resume();
    }
    $response = $fiber->getReturn();

    $this->assertNotNull($sampling, 'The tool asked the client to describe the image.');
    $this->assertTrue($response->result->structuredContent['success'], $response->result->structuredContent['message'] ?? '');
    $this->assertSame('A tiny transparent pixel.', $response->result->structuredContent['data']['alt']);

    $storage = $this->container->get('entity_type.manager')->getStorage('media');
    $storage->resetCache([(int) $this->media->id()]);
    $reloaded = $storage->load((int) $this->media->id());
    $sourceField = $reloaded->getSource()->getConfiguration()['source_field'];
    $this->assertSame('A tiny transparent pixel.', $reloaded->get($sourceField)->alt);
  }

  /**
   * Without the sampling capability the tool refuses with guidance.
   */
  public function testWithoutSamplingCapabilityRefuses(): void {
    $handler = $this->buildHandler();

    $session = $this->createMock(SessionInterface::class);
    $session->method('get')->willReturnCallback(
      static fn(string $key, mixed $default = NULL) => $key === 'client_capabilities' ? [] : $default,
    );
    $session->method('getId')->willReturn(Uuid::v4());

    $request = (new CallToolRequest('mcp_generate_alt_text', [
      'mid' => (int) $this->media->id(),
    ]))->withId(2);

    $fiber = new \Fiber(static fn() => $handler->handle($request, $session));
    $value = $fiber->start();
    while (!$fiber->isTerminated()) {
      $value = $fiber->resume();
    }
    $response = $fiber->getReturn();

    $this->assertFalse($response->result->structuredContent['success']);
    $this->assertStringContainsString('sampling', $response->result->structuredContent['message']);
  }

}
