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
use Drupal\tool\TypedData\InputDefinition;

/**
 * A tool that returns the values it was given, in the types it got them.
 */
#[Tool(
  id: 'foreign_test_echo',
  label: new TranslatableMarkup('Foreign echo'),
  description: new TranslatableMarkup('Returns its input values.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'count' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Count'),
      description: new TranslatableMarkup('A number.'),
      required: TRUE,
    ),
    'dry_run' => new InputDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Dry run'),
      description: new TranslatableMarkup('A yes or no.'),
      required: FALSE,
    ),
  ],
)]
final class ForeignEcho extends ToolBase {

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    return ExecutableResult::success(new TranslatableMarkup('OK'), ['values' => $values]);
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(array $values, AccountInterface $account, bool $return_as_object = FALSE): bool|AccessResultInterface {
    return $return_as_object ? AccessResult::allowed() : TRUE;
  }

}
