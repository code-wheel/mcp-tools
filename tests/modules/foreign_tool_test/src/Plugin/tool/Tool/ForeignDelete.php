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
 * A write tool that says of itself that it destroys something.
 */
#[Tool(
  id: 'foreign_test_delete',
  label: new TranslatableMarkup('Foreign delete'),
  description: new TranslatableMarkup('Clears a State flag.'),
  operation: ToolOperation::Write,
  destructive: TRUE,
)]
final class ForeignDelete extends ToolBase {

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    \Drupal::state()->delete('foreign_tool_test.wrote');
    return ExecutableResult::success(new TranslatableMarkup('Deleted.'));
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(array $values, AccountInterface $account, bool $return_as_object = FALSE): bool|AccessResultInterface {
    return $return_as_object ? AccessResult::allowed() : TRUE;
  }

}
