<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Mcp\Prompt;

use Drupal\Core\Session\AccountInterface;
use Drupal\mcp_tools\Mcp\FiberIsolation;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\McpToolCallContext;
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
    private readonly ?AccessManager $accessManager = NULL,
    private readonly ?AccountInterface $currentUser = NULL,
    private readonly ?McpToolCallContext $toolCallContext = NULL,
  ) {}

  /**
   * Runs a read the way a tool call runs: starting like a new request.
   *
   * See CoreResourceProvider::fresh().
   */
  private function fresh(callable $read): mixed {
    $this->toolCallContext?->enter();
    try {
      return $read();
    }
    finally {
      $this->toolCallContext?->leave();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getPrompts(): array {
    return [
      [
        'name' => 'mcp_tools/site-brief',
        'description' => 'Summarize site health and notable risks for a Drupal site.',
        'handler' => fn(?string $focus = NULL, bool $include_requirements = FALSE) => FiberIsolation::run(fn() => $this->fresh(fn() => $this->siteBriefPrompt($focus, $include_requirements))),
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
    // The prompt carries site status, so it answers to the same rule as the
    // site health tools: the read scope and their permission.
    $allowed = $this->accessManager !== NULL
      && $this->currentUser !== NULL
      && $this->accessManager->canRead()
      && $this->currentUser->hasPermission('mcp_tools use site_health');
    if (!$allowed) {
      return [
        new PromptMessage(Role::User, new TextContent('Access denied. The current user may not read this site\'s status, so no site brief can be made.')),
      ];
    }

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
