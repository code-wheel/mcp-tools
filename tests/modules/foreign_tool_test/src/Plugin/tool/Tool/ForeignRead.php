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
 * A read tool that knows nothing of MCP Tools scopes or modes.
 */
#[Tool(
  id: 'foreign_test_read',
  label: new TranslatableMarkup('Foreign read'),
  description: new TranslatableMarkup('Reads a State flag.'),
  operation: ToolOperation::Read,
)]
final class ForeignRead extends ToolBase {

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    \Drupal::state()->set('foreign_tool_test.read', TRUE);
    return ExecutableResult::success(new TranslatableMarkup('Read.'));
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(array $values, AccountInterface $account, bool $return_as_object = FALSE): bool|AccessResultInterface {
    return $return_as_object ? AccessResult::allowed() : TRUE;
  }

}
