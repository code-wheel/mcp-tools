<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Tool;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\McpToolCallContext;
use Drupal\tool\ExecutableResult;
use Drupal\tool\Tool\ToolBase;
use Drupal\tool\Tool\ToolDefinition;
use Drupal\tool\Tool\ToolOperation;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for MCP Tools Tool API plugins.
 *
 * Wraps the existing MCP Tools array-based tool result format into Tool API
 * ExecutableResult objects and enforces category permissions + MCP scopes.
 */
abstract class McpToolsToolBase extends ToolBase {

  /**
   * The MCP Tools access manager.
   */
  protected AccessManager $accessManager;

  /**
   * Tool-call execution context.
   */
  protected ?McpToolCallContext $toolCallContext = NULL;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var static $instance */
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->accessManager = $container->get('mcp_tools.access_manager');
    $instance->toolCallContext = $container->get('mcp_tools.tool_call_context');
    return $instance;
  }

  /**
   * Execute the legacy MCP Tools implementation.
   *
   * @param array $input
   *   The input values.
   *
   * @return array
   *   A legacy response array with a boolean `success` key and optional `data`,
   *   `message`, and/or `error` keys.
   */
  abstract protected function executeLegacy(array $input): array;

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    $this->toolCallContext?->enter();
    try {
      try {
        $legacy = $this->executeLegacy($values);
      }
      catch (\Throwable $e) {
        return ExecutableResult::failure(new TranslatableMarkup('Tool execution failed: @message', [
          '@message' => $e->getMessage(),
        ]));
      }

      $success = (bool) ($legacy['success'] ?? FALSE);
      if ($success) {
        $message = $legacy['message'] ?? 'Success.';
        if (!is_string($message) || $message === '') {
          $message = 'Success.';
        }

        $context = [];
        if (array_key_exists('data', $legacy)) {
          $context = is_array($legacy['data']) ? $legacy['data'] : ['data' => $legacy['data']];
        }
        else {
          $context = $legacy;
          unset($context['success'], $context['message']);
        }

        // phpcs:ignore Drupal.Semantics.FunctionT.NotLiteralString
        return ExecutableResult::success(new TranslatableMarkup($message), $context);
      }

      $error = $legacy['error'] ?? $legacy['message'] ?? 'Tool execution failed.';
      if (!is_string($error) || $error === '') {
        $error = 'Tool execution failed.';
      }

      // phpcs:ignore Drupal.Semantics.FunctionT.NotLiteralString
      return ExecutableResult::failure(new TranslatableMarkup($error));
    }
    finally {
      $this->toolCallContext?->leave();
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(array $values, AccountInterface $account, bool $return_as_object = FALSE): bool|AccessResultInterface {
    $permission = 'mcp_tools use ' . static::getMcpCategory();
    $permissionAccess = AccessResult::allowedIfHasPermission($account, $permission);

    $definition = $this->getPluginDefinition();
    $operation = $definition instanceof ToolDefinition ? $definition->getOperation() : ToolOperation::Transform;

    $scopeAllowed = match ($operation) {
      ToolOperation::Trigger => $this->accessManager->hasScope(AccessManager::SCOPE_ADMIN) && !$this->accessManager->isReadOnlyMode(),
      ToolOperation::Write => $this->accessManager->hasScope(AccessManager::SCOPE_WRITE) && !$this->accessManager->isReadOnlyMode(),
      default => $this->accessManager->hasScope(AccessManager::SCOPE_READ),
    };

    $scopeAccess = $scopeAllowed ? AccessResult::allowed() : AccessResult::forbidden();

    $policyAccess = AccessResult::allowed();
    if ($operation === ToolOperation::Write || $operation === ToolOperation::Trigger) {
      $writeKind = static::getMcpWriteKind();
      $policyAccess = $this->accessManager->isWriteKindAllowed($writeKind)
        ? AccessResult::allowed()
        : AccessResult::forbidden();
    }

    $access = $permissionAccess->andIf($scopeAccess)->andIf($policyAccess);
    return $return_as_object ? $access : $access->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function access(?AccountInterface $account = NULL, $return_as_object = FALSE): bool|AccessResultInterface {
    $account = $account ?? $this->currentUser;
    return $this->checkAccess([], $account, (bool) $return_as_object);
  }

  /**
   * Returns the MCP category for this tool.
   */
  protected static function getMcpCategory(): string {
    $const = static::class . '::MCP_CATEGORY';
    if (defined($const)) {
      $value = constant($const);
      if (is_string($value) && $value !== '') {
        return $value;
      }
    }
    return 'discovery';
  }

  /**
   * Returns the write kind for this tool (config/content/ops).
   *
   * Used to enforce "config-only mode" without requiring every tool to
   * implement its own checks.
   */
  protected static function getMcpWriteKind(): string {
    $const = static::class . '::MCP_WRITE_KIND';
    if (defined($const)) {
      $value = constant($const);
      if (is_string($value) && in_array($value, AccessManager::ALL_WRITE_KINDS, TRUE)) {
        return $value;
      }
    }

    $category = static::getMcpCategory();

    return match ($category) {
      // Content/entity mutations (nodes, media, users, etc.).
      'content',
      'users',
      'media',
      'batch',
      'migration',
      'moderation',
      'scheduler',
      'redirect',
      'entity_clone',
      // Default menus to content because menu links are content entities.
      'menus',
        => AccessManager::WRITE_KIND_CONTENT,

      // Operational actions (runtime state, indexing, regeneration, etc.).
      'cache',
      'cron',
      'ultimate_cron',
      'search_api',
        => AccessManager::WRITE_KIND_OPS,

      // Everything else is treated as configuration changes.
      default => AccessManager::WRITE_KIND_CONFIG,
    };
  }

}
