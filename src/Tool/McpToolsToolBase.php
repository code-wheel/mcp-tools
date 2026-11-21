<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Tool;

use Drupal\Component\Render\FormattableMarkup;
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
        return ExecutableResult::failure(self::resultMessage('Tool execution failed: ' . $e->getMessage()));
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

        // Backfill declared outputs the result did not populate (tools with
        // conditional outputs, e.g. "language" on a full-node delete). Tool
        // API's getOutputValues() throws for declared-but-unset outputs —
        // getOutput() lazily initializes them but getOutputValue() does not —
        // which breaks external consumers such as mcp_server_tool_bridge
        // after the tool has already executed.
        // A declared output the result returns at its top level (e.g.
        // "success" and "message") is taken from there.
        foreach (array_keys($this->getOutputDefinitions()) as $outputName) {
          if (!array_key_exists($outputName, $context)) {
            $context[$outputName] = $legacy[$outputName] ?? NULL;
          }
        }

        return ExecutableResult::success(self::resultMessage($message), $this->inDeclaredTypes($context));
      }

      $error = $legacy['error'] ?? $legacy['message'] ?? 'Tool execution failed.';
      if (!is_string($error) || $error === '') {
        $error = 'Tool execution failed.';
      }

      return ExecutableResult::failure(self::resultMessage($error));
    }
    finally {
      $this->toolCallContext?->leave();
    }
  }

  /**
   * Returns scalar outputs in the type their definition declares.
   *
   * Drupal hands out entity and revision IDs as strings when an entity was
   * loaded and as integers right after it was created. An output declared
   * "integer" has to be an integer either way: Tool API accepts "7", but a
   * client that checks the result against the outputSchema does not, and
   * mcp/sdk from 0.9 rejects such a result after the tool has already run.
   * Only conversions that cannot lose anything are made. Everything else is
   * left as it is, for Tool API's validation to report.
   *
   * @param array<string, mixed> $context
   *   The output values, keyed by output name.
   *
   * @return array<string, mixed>
   *   The same values, scalars converted where their declared type asks.
   */
  private function inDeclaredTypes(array $context): array {
    foreach ($this->getOutputDefinitions() as $name => $definition) {
      $value = $context[$name] ?? NULL;
      if ($value === NULL) {
        continue;
      }
      $type = (string) $definition->getDataType();
      if ($type === 'integer' && is_string($value) && (string) (int) $value === $value) {
        $context[$name] = (int) $value;
      }
      elseif ($type === 'float' && is_string($value) && is_numeric($value)) {
        $context[$name] = (float) $value;
      }
      elseif ($type === 'string' && (is_int($value) || is_float($value) || $value instanceof \Stringable)) {
        $context[$name] = (string) $value;
      }
      elseif ($type === 'boolean' && in_array($value, [0, 1, '0', '1'], TRUE)) {
        // Drupal often stores a yes or no as 0 or 1: core's maintenance mode
        // form does.
        $context[$name] = (bool) $value;
      }
    }
    return $context;
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
      'remote media',
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

  /**
   * Wraps a message built at run time for an ExecutableResult.
   *
   * The message carries IDs and exception text, so it must not be the source
   * string: the locale module would record every message as a string to
   * translate. It is passed as a placeholder instead, as markup so that it is
   * not escaped: the rendered text is the message as built.
   *
   * @param string $message
   *   The message.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The message for the result.
   */
  private static function resultMessage(string $message): TranslatableMarkup {
    return new TranslatableMarkup('@message', ['@message' => new FormattableMarkup($message, [])]);
  }

}
