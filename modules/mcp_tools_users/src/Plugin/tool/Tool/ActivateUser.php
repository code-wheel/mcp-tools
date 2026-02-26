<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_users\Plugin\tool\Tool;

use Drupal\mcp_tools_users\Service\UserService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp_tools\Tool\McpToolsToolBase;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinition;

/**
 * Tool plugin implementation.
 */
#[Tool(
  id: 'mcp_activate_user',
  label: new TranslatableMarkup('Activate User'),
  description: new TranslatableMarkup('Activate a blocked user account.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'uid' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('User ID'),
      description: new TranslatableMarkup('User ID to activate. User will be able to log in after activation.'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'uid' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('User ID'),
      description: new TranslatableMarkup('The activated user ID.'),
    ),
    'username' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Username'),
      description: new TranslatableMarkup('Username of the activated user.'),
    ),
    'status' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Status'),
      description: new TranslatableMarkup('New status (will be "active").'),
    ),
    'changed' => new ContextDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Changed'),
      description: new TranslatableMarkup('True if user was activated, false if already active.'),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup('Confirmation message.'),
    ),
  ],
)]
class ActivateUser extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'users';


  /**
   * The user service.
   *
   * @var \Drupal\mcp_tools_users\Service\UserService
   */
  protected UserService $userService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->userService = $container->get('mcp_tools_users.user');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeLegacy(array $input): array {
    $uid = $input['uid'] ?? 0;

    if (empty($uid)) {
      return ['success' => FALSE, 'error' => 'User ID (uid) is required.'];
    }

    return $this->userService->activateUser((int) $uid);
  }

}
