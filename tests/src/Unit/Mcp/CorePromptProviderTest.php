<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Unit\Mcp;

use Drupal\mcp_tools\Mcp\Prompt\CorePromptProvider;
use Drupal\mcp_tools\Service\SiteHealthService;
use Drupal\mcp_tools\Service\SystemStatusService;
use Mcp\Schema\Content\PromptMessage;
use Mcp\Schema\Enum\Role;
use PHPUnit\Framework\TestCase;

/**
 * Tests for CorePromptProvider.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools\Mcp\Prompt\CorePromptProvider::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
final class CorePromptProviderTest extends TestCase {

  private SiteHealthService $siteHealthService;
  private SystemStatusService $systemStatusService;
  private CorePromptProvider $provider;

  protected function setUp(): void {
    parent::setUp();

    $this->siteHealthService = $this->createMock(SiteHealthService::class);
    $this->systemStatusService = $this->createMock(SystemStatusService::class);

    $this->provider = new CorePromptProvider(
      $this->siteHealthService,
      $this->systemStatusService,
    );
  }

  public function testGetPromptsReturnsPromptDefinitions(): void {
    $prompts = $this->provider->getPrompts();

    $this->assertIsArray($prompts);
    $this->assertCount(1, $prompts);

    $prompt = $prompts[0];
    $this->assertSame('mcp_tools/site-brief', $prompt['name']);
    $this->assertStringContainsString('health', $prompt['description']);
    $this->assertArrayHasKey('handler', $prompt);
    $this->assertIsCallable($prompt['handler']);
  }

  public function testSiteBriefPromptReturnsMessages(): void {
    $this->siteHealthService->method('getSiteStatus')->willReturn([
      'site_name' => 'Test Site',
      'drupal_version' => '10.3.0',
      'php_version' => '8.3.0',
    ]);

    $messages = $this->provider->siteBriefPrompt();

    $this->assertIsArray($messages);
    $this->assertCount(1, $messages);
    $this->assertInstanceOf(PromptMessage::class, $messages[0]);
    $this->assertSame(Role::User, $messages[0]->role);
  }

  public function testSiteBriefPromptWithFocus(): void {
    $this->siteHealthService->method('getSiteStatus')->willReturn([
      'site_name' => 'Test Site',
    ]);

    $messages = $this->provider->siteBriefPrompt('security');

    $this->assertCount(1, $messages);
    $content = $messages[0]->content;
    $this->assertStringContainsString('Focus: security', $content->text);
  }

  public function testSiteBriefPromptWithRequirements(): void {
    $this->siteHealthService->method('getSiteStatus')->willReturn([
      'site_name' => 'Test Site',
    ]);
    $this->systemStatusService->method('getRequirements')->willReturn([
      'summary' => ['errors' => 0, 'warnings' => 2],
      'has_errors' => FALSE,
    ]);

    $messages = $this->provider->siteBriefPrompt(NULL, TRUE);

    $this->assertCount(1, $messages);
    $content = $messages[0]->content;
    $this->assertStringContainsString('requirements', $content->text);
  }

  public function testSiteBriefPromptHandlerIsCallable(): void {
    $this->siteHealthService->method('getSiteStatus')->willReturn([]);

    $prompts = $this->provider->getPrompts();
    $handler = $prompts[0]['handler'];

    $result = $handler();

    $this->assertIsArray($result);
    $this->assertCount(1, $result);
    $this->assertInstanceOf(PromptMessage::class, $result[0]);
  }

}
