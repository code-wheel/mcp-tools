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
use Drupal\tool\TypedData\OutputDefinition;

/**
 * A read tool that says who called it, as Tool API told it.
 */
#[Tool(
  id: 'foreign_test_whoami',
  label: new TranslatableMarkup('Foreign who am I'),
  description: new TranslatableMarkup('Names the invoker of this call.'),
  operation: ToolOperation::Read,
  output_definitions: [
    'caller' => new OutputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Caller'),
      description: new TranslatableMarkup('The invoker ID, or "nobody".'),
    ),
  ],
)]
final class ForeignWhoami extends ToolBase {

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    $caller = $this->getInvoker()?->id ?? 'nobody';
    return ExecutableResult::success(new TranslatableMarkup('Called by @caller.', ['@caller' => $caller]), ['caller' => $caller]);
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(array $values, AccountInterface $account, bool $return_as_object = FALSE): bool|AccessResultInterface {
    return $return_as_object ? AccessResult::allowed() : TRUE;
  }

}
