<?php

declare(strict_types=1);

namespace Drupal\foreign_tool_test\Plugin\tool\Tool;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\ExecutableResult;
use Drupal\tool\Tool\ToolBase;
use Drupal\tool\Tool\ToolOperation;

/**
 * A tool that suspends its Fiber the way Drupal core and the MCP SDK do.
 */
#[Tool(
  id: 'foreign_test_suspends',
  label: new TranslatableMarkup('Foreign suspends'),
  description: new TranslatableMarkup('Suspends the Fiber three times and returns the last answer.'),
  operation: ToolOperation::Read,
)]
final class ForeignSuspends extends ToolBase {

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    // What Drupal core does to let a caller batch work.
    \Fiber::suspend();
    \Fiber::suspend('resume me');
    // What the SDK's client gateway does to reach the client.
    $answer = \Fiber::suspend(['type' => 'notification', 'notification' => 'ping']);
    return ExecutableResult::success(new TranslatableMarkup('OK'), ['answer' => $answer]);
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(array $values, AccountInterface $account, bool $return_as_object = FALSE): bool|AccessResultInterface {
    return $return_as_object ? AccessResult::allowed() : TRUE;
  }

}
