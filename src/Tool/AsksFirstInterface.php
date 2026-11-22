<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Tool;

/**
 * A tool of MCP Tools that wants a person's yes before some of its calls.
 *
 * Most calls need none. A few are strong enough that the client's user
 * should see them first: granting a permission for trusted roles only, or
 * applying a recipe that an agent wrote and no person has read. The MCP
 * server asks the user where the client can ask (MCP elicitation). For a
 * client that cannot ask, the site decides: run it and write it to the
 * audit log, or refuse it (access.confirm_destructive_tools).
 */
interface AsksFirstInterface {

  /**
   * Why this call needs a yes from a person, if it does.
   *
   * Asked only for a session that may run the tool. It is not asked for a
   * call that would be refused anyway.
   *
   * @param array<string, mixed> $arguments
   *   The arguments of the call.
   *
   * @return string|null
   *   One or two plain sentences that say what the call would do, or NULL
   *   when it needs no yes.
   */
  public function reasonToAsk(array $arguments): ?string;

}
