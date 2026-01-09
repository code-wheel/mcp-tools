<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Mcp\Prompt;

use Drupal\mcp_tools\Service\SiteHealthService;
use Drupal\mcp_tools\Service\SystemStatusService;
use Mcp\Schema\Content\PromptMessage;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Enum\Role;

/**
 * Core MCP prompt provider for site context.
 */
class CorePromptProvider implements PromptProviderInterface {

  public function __construct(
    private readonly SiteHealthService $siteHealthService,
    private readonly SystemStatusService $systemStatusService,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getPrompts(): array {
    return [
      [
        'name' => 'mcp_tools/site-brief',
        'description' => 'Summarize site health and notable risks for a Drupal site.',
        'handler' => fn(?string $focus = NULL, bool $include_requirements = FALSE) => $this->siteBriefPrompt($focus, $include_requirements),
      ],
    ];
  }

  /**
   * Builds a site brief prompt for assistants.
   *
   * @param string|null $focus
   *   Optional focus area (performance, security, content, etc.).
   * @param bool $include_requirements
   *   Whether to include runtime requirements details.
   *
   * @return \Mcp\Schema\Content\PromptMessage[]
   *   Prompt messages.
   */
  public function siteBriefPrompt(?string $focus = NULL, bool $include_requirements = FALSE): array {
    $status = $this->siteHealthService->getSiteStatus();

    $context = [
      'site_status' => $status,
    ];

    if ($include_requirements) {
      $context['requirements'] = $this->systemStatusService->getRequirements(TRUE);
    }

    $prompt = "Create a concise Drupal site brief for an AI assistant.\n";
    if ($focus) {
      $prompt .= "Focus: {$focus}.\n";
    }
    $prompt .= "Use the following context:\n";
    $prompt .= json_encode($context, JSON_PRETTY_PRINT);

    return [
      new PromptMessage(Role::User, new TextContent($prompt)),
    ];
  }

}
