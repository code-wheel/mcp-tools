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
   * Told of the keys a tool returned and did not declare, when it exists.
   *
   * Only declared outputs reach the client, so such a key was lost: the id
   * a create tool returned, for one. A test module defines the service, so
   * the tool sweep sees every one; a site has none.
   */
  protected ?object $droppedOutputs = NULL;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var static $instance */
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->accessManager = $container->get('mcp_tools.access_manager');
    $instance->toolCallContext = $container->get('mcp_tools.tool_call_context');
    $instance->droppedOutputs = $container->has('mcp_tools.dropped_outputs') ? $container->get('mcp_tools.dropped_outputs') : NULL;
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
      $refusal = $this->unconfirmedFor($values);
      if ($refusal !== NULL) {
        return ExecutableResult::failure(self::resultMessage($refusal));
      }
      try {
        $legacy = $this->executeLegacy($values);
      }
      catch (\Throwable $e) {
        return ExecutableResult::failure(self::resultMessage('Tool execution failed: ' . $e->getMessage()));
      }

      $success = (bool) ($legacy['success'] ?? FALSE);
      if ($success) {
        // A service that says what it found in data.message, not message,
        // was heard as "Success.": "the entity has no metatag field" read as
        // no tags. Its words are the answer's.
        $message = $legacy['message'] ?? (is_array($legacy['data'] ?? NULL) ? ($legacy['data']['message'] ?? NULL) : NULL) ?? 'Success.';
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

        $declared = array_keys($this->getOutputDefinitions());
        // A message given in data is the answer's message now, so it is not
        // lost when the tool does not declare it.
        if (!in_array('message', $declared, TRUE) && ($context['message'] ?? NULL) === $message) {
          unset($context['message']);
        }
        if ($this->droppedOutputs !== NULL && $declared !== [] && ($dropped = array_values(array_diff(array_keys($context), $declared))) !== []) {
          $this->droppedOutputs->dropped($this->getPluginId(), $dropped);
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
   * Invokers where a person runs the tool, or the site's own code does.
   *
   * No invoker is a form such as Tool Explorer's, or code calling the tool.
   * "php" is Tool API's runner without an invoker, "drush" its command.
   */
  private const PERSON_INVOKERS = ['php', 'drush'];

  /**
   * Why a strong call is not run for this caller, or NULL when it may run.
   *
   * A new site runs a strong call (a tool that declares itself destructive,
   * or one of ours that says so for these values) only after a person said
   * yes. Our server and ToolPolicy hold to that. A tool of ours can also be
   * called on ways in that are not ours: MCP Server's tool bridge without
   * mcp_tools_policy, Tool API's own AI connector, another module's agent.
   * Tool API tells the tool who called it (the invoker). Where that is not
   * a person and nobody said yes, the tool refuses, so the rule holds on
   * every way in to our tools, whatever the caller.
   *
   * @param array<string, mixed> $values
   *   The values of the call.
   */
  private function unconfirmedFor(array $values): ?string {
    if ($this->accessManager === NULL || !$this->accessManager->destructiveToolsNeedConfirmation() || $this->toolCallContext?->wasConfirmedByUser()) {
      return NULL;
    }
    $invoker = $this->getInvoker()?->id;
    if ($invoker === NULL || in_array($invoker, self::PERSON_INVOKERS, TRUE)) {
      return NULL;
    }
    $reason = $this instanceof AsksFirstInterface ? $this->reasonToAsk($values) : NULL;
    $destructive = $this->getPluginDefinition() instanceof ToolDefinition && $this->getPluginDefinition()->get('destructive') === TRUE;
    if ($reason === NULL && !$destructive) {
      return NULL;
    }
    $what = $reason ?? sprintf("The tool '%s' says it is destructive.", (string) $this->getPluginId());
    return $what . sprintf(' This site runs such a call only after a person confirmed it, and this caller (%s) did not ask anyone, so nothing was run.', $invoker)
      . ' No argument changes that: do not call it again. Tell the user what you wanted to run, so a person can do it or allow it.';
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
    $definition = $this->getPluginDefinition();
    $operation = $definition instanceof ToolDefinition ? $definition->getOperation() : ToolOperation::Transform;

    $access = static::accessFor($operation, $account, $this->accessManager);
    return $return_as_object ? $access : $access->isAllowed();
  }

  /**
   * Whether a tool is one of MCP Tools' own.
   *
   * By what the tool is, not by what its module is called: it extends this
   * class, so it checks scopes and modes in its access check and limits and
   * audits from inside its service. A plain Tool API tool does none of
   * that, also when its module is named mcp_tools_something, and whoever
   * runs it applies those rules from outside (ToolPolicy).
   */
  public static function owns(ToolDefinition $definition): bool {
    $class = $definition->getClass();
    return is_string($class) && is_a($class, self::class, TRUE);
  }

  /**
   * The access rule of a tool of this class, asked without making the tool.
   *
   * The category's permission, the scope the operation needs, read-only mode
   * and config-only mode. The tool's own access check is this rule, and the
   * tool list asks it too, so the list never shows what the door refuses.
   *
   * @param \Drupal\tool\Tool\ToolOperation $operation
   *   The operation the tool declares.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user.
   * @param \Drupal\mcp_tools\Service\AccessManager $accessManager
   *   The access manager, for the scopes and modes of this session.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public static function accessFor(ToolOperation $operation, AccountInterface $account, AccessManager $accessManager): AccessResultInterface {
    $permission = 'mcp_tools use ' . static::getMcpCategory();
    $permissionAccess = AccessResult::allowedIfHasPermission($account, $permission);

    $scopeAllowed = match ($operation) {
      ToolOperation::Trigger => $accessManager->hasScope(AccessManager::SCOPE_ADMIN) && !$accessManager->isReadOnlyMode(),
      ToolOperation::Write => $accessManager->hasScope(AccessManager::SCOPE_WRITE) && !$accessManager->isReadOnlyMode(),
      default => $accessManager->hasScope(AccessManager::SCOPE_READ),
    };

    $scopeAccess = $scopeAllowed ? AccessResult::allowed() : AccessResult::forbidden();

    $policyAccess = AccessResult::allowed();
    if ($operation === ToolOperation::Write || $operation === ToolOperation::Trigger) {
      $writeKind = static::getMcpWriteKind();
      $policyAccess = $accessManager->isWriteKindAllowed($writeKind)
        ? AccessResult::allowed()
        : AccessResult::forbidden();
    }

    return $permissionAccess->andIf($scopeAccess)->andIf($policyAccess);
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
      'jsonapi',
      'metatag',
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

      // Configuration.
      'blocks',
      'config',
      'image_styles',
      'layout_builder',
      'paragraphs',
      'pathauto',
      'recipes',
      'sitemap',
      'structure',
      'templates',
      'theme',
      'translation',
      'views',
      'webform',
      // The undo service checks the kind of each change it would undo.
      'undo',
        => AccessManager::WRITE_KIND_CONFIG,

      // A category that nobody put on a list is not let through as config.
      default => AccessManager::WRITE_KIND_CONTENT,
    };
  }

  /**
   * Wraps a message built at run time for an ExecutableResult.
   *
   * The message carries IDs and exception text, so it must not be the source
   * string: the locale module would record every message as a string to
   * translate. It is passed as a placeholder instead, as plain text, so that
   * a page that shows it escapes it: it may hold a title or an ID that
   * somebody typed. A client that reads text asks plainMessage().
   *
   * @param string $message
   *   The message.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The message for the result.
   */
  private static function resultMessage(string $message): TranslatableMarkup {
    return new TranslatableMarkup('@message', ['@message' => $message]);
  }

  /**
   * A result's message as text, for a client that does not render HTML.
   *
   * @param \Stringable|string $message
   *   The message of a result.
   *
   * @return string
   *   The message as built. It is not safe to print as HTML.
   */
  public static function plainMessage(\Stringable|string $message): string {
    if ($message instanceof TranslatableMarkup && $message->getUntranslatedString() === '@message') {
      $text = $message->getArguments()['@message'] ?? NULL;
      if (is_string($text)) {
        return $text;
      }
    }
    return (string) $message;
  }

}
