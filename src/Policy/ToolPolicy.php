<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Policy;

use Drupal\Core\Session\AccountInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\mcp_tools\Service\RateLimiter;
use Drupal\mcp_tools\Tool\McpToolsToolBase;
use Drupal\tool\Tool\Invoker;
use Drupal\tool\Tool\ToolDefinition;
use Drupal\tool\Tool\ToolInvocation;
use Drupal\tool\Tool\ToolManager;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\Tool\ToolRunner;

/**
 * Runs a Tool API tool with the site's rules around it.
 *
 * The rules: what this session may do (its scopes, read-only mode,
 * config-only mode), the write limits, and an audit line. MCP Tools' own
 * tools carry them inside, in their access check and their services. A tool
 * of another module does not know them, so whoever runs it applies them
 * here.
 *
 * This class knows Tool API and nothing of MCP. A definition, values and an
 * invoker go in. Tool API's invocation or a refusal comes out. The MCP
 * server and the Drupal AI bridge both run their tools through it.
 *
 * It holds for the callers that use it. Tool API has no place yet where a
 * rule can refuse a call for every caller: #3583131 adds events for
 * watching a call, and refusing one is left for a follow-up. The three
 * steps (admit, giveBack, record) are the ones that would hang there. The
 * mcp_tools_policy module puts them in front of MCP Server's HTTP endpoint.
 */
final class ToolPolicy {

  public function __construct(
    private readonly ToolRunner $runner,
    private readonly AccessManager $accessManager,
    private readonly RateLimiter $rateLimiter,
    private readonly AuditLogger $auditLogger,
    private readonly AccountInterface $currentUser,
  ) {}

  /**
   * Step one: lets a call in, or refuses it. Counts a write.
   *
   * A caller that runs the tool itself uses run(). One that stands in
   * front of another server calls the three steps: this one before the
   * call, then giveBack() when the call did not run, or record() when it
   * did.
   *
   * @return \Drupal\mcp_tools\Policy\ToolRefusal|null
   *   The refusal, or NULL when the call may go on.
   */
  public function admit(ToolDefinition $definition): ?ToolRefusal {
    if (McpToolsToolBase::owns($definition)) {
      return NULL;
    }
    $operation = $definition->getOperation() ?? ToolOperation::Transform;
    if (!$this->accessManager->allowsForeignToolOperation($operation->value)) {
      return ToolRefusal::access();
    }
    $limit = $this->limitFor($definition);
    if ($limit !== NULL) {
      $check = $this->rateLimiter->checkLimit($limit);
      if (!$check['allowed']) {
        return ToolRefusal::limit($check);
      }
    }
    return NULL;
  }

  /**
   * Step two, for a call that was let in and then did not run.
   *
   * Gives its count back: a refused call must not use the limit up.
   */
  public function giveBack(ToolDefinition $definition): void {
    $limit = McpToolsToolBase::owns($definition) ? NULL : $this->limitFor($definition);
    if ($limit !== NULL) {
      $this->rateLimiter->refund($limit);
    }
  }

  /**
   * Step three, for a call that ran: its audit line.
   *
   * The tool has run by now. An audit log that cannot be written must not
   * turn the answer into "failed": the caller would run the tool again.
   * The site's own logger may be what is broken, so the reason goes to
   * PHP's error log.
   *
   * @param \Drupal\tool\Tool\ToolDefinition $definition
   *   The tool.
   * @param array<string, mixed> $values
   *   The values it ran with.
   * @param bool $success
   *   Whether it reported success.
   */
  public function record(ToolDefinition $definition, array $values, bool $success): void {
    if (McpToolsToolBase::owns($definition)) {
      return;
    }
    try {
      $this->auditLogger->logForeignTool($definition, $values, $success);
    }
    catch (\Throwable $e) {
      error_log(sprintf('MCP Tools: no audit line could be written for the tool %s: %s', $definition->id(), $e->getMessage()));
    }
  }

  /**
   * The limit a call counts against, or NULL when it counts against none.
   *
   * A write counts against the write limits, a trigger against the
   * structure limit, and a write that declares itself destructive against
   * the delete limit. Tool API calls every write that declares nothing
   * destructive, so its isDestructive() is not asked.
   *
   * Tool API refuses a user without the declared permission before
   * anything else. Such a call is not counted, and not told about a limit.
   */
  private function limitFor(ToolDefinition $definition): ?string {
    $limit = match ($definition->getOperation()) {
      ToolOperation::Trigger => 'admin',
      ToolOperation::Write => $definition->get('destructive') === TRUE ? 'delete' : 'write',
      default => NULL,
    };
    if ($limit !== NULL && !ToolManager::checkPermission($definition, $this->currentUser)->isAllowed()) {
      return NULL;
    }
    return $limit;
  }

  /**
   * Runs one tool call: the three steps around Tool API's runner.
   *
   * @param \Drupal\tool\Tool\ToolDefinition $definition
   *   The tool.
   * @param array<string, mixed> $values
   *   The input values.
   * @param \Drupal\tool\Tool\Invoker $invoker
   *   Who is calling, as Tool API passes it to the tool.
   * @param bool $enforce
   *   FALSE runs the tool with Tool API's own checks only. For a caller
   *   that has its own rules, such as another module that builds our call
   *   handler for its own server.
   *
   * @return \Drupal\tool\Tool\ToolInvocation|\Drupal\mcp_tools\Policy\ToolRefusal
   *   Tool API's invocation, also for a call Tool API refused or a tool
   *   that failed. A refusal when one of the rules here said no.
   */
  public function run(ToolDefinition $definition, array $values, Invoker $invoker, bool $enforce = TRUE): ToolInvocation|ToolRefusal {
    if ($enforce && ($refusal = $this->admit($definition)) !== NULL) {
      return $refusal;
    }

    // The call is counted before it runs, because nothing can be refused
    // between Tool API's access check and the tool.
    try {
      $invocation = $this->runner->run((string) $definition->id(), $values, [], $invoker);
    }
    catch (\Throwable $e) {
      if ($enforce) {
        $this->giveBack($definition);
      }
      throw $e;
    }

    if ($enforce) {
      $invocation->hasExecuted()
        ? $this->record($definition, $values, $invocation->getResult()->isSuccess())
        : $this->giveBack($definition);
    }
    return $invocation;
  }

}
