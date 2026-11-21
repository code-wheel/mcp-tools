<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_ai\Plugin\AiFunctionCall;

use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\ai\Service\FunctionCalling\FunctionCallInterface;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\mcp_tools\Policy\ToolPolicy;
use Drupal\mcp_tools\Policy\ToolRefusal;
use Drupal\tool\FailureCategory;
use Drupal\tool\Tool\Invoker;
use Drupal\tool\Tool\ToolDefinition;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Bridges every MCP Tools (Tool API) tool into a drupal/ai FunctionCall.
 *
 * The concrete per-tool definitions come from
 * \Drupal\mcp_tools_ai\Plugin\AiFunctionCall\Derivative\ToolApiFunctionCallDeriver;
 * this class delegates execution to the underlying Tool API plugin.
 */
#[FunctionCall(
  id: 'mcp_tools_tool',
  function_name: 'mcp_tools_tool',
  name: 'MCP Tools tool',
  description: 'Executes a Drupal Tool API tool provided by MCP Tools.',
  group: 'mcp_tools',
  deriver: '\Drupal\mcp_tools_ai\Plugin\AiFunctionCall\Derivative\ToolApiFunctionCallDeriver',
)]
final class ToolApiFunctionCall extends FunctionCallBase implements ExecutableFunctionCallInterface {

  /**
   * The Tool API plugin manager.
   */
  protected PluginManagerInterface $toolManager;

  /**
   * What runs a tool: Tool API's runner, with the site's rules around it.
   */
  protected ToolPolicy $toolPolicy;

  /**
   * Captured readable output from the last execution.
   */
  protected string $output = '';

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): FunctionCallInterface|static {
    $instance = new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ai.context_definition_normalizer'),
    );
    $instance->toolManager = $container->get('plugin.manager.tool');
    $instance->toolPolicy = $container->get('mcp_tools.tool_policy');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    $tool_id = $this->getPluginDefinition()['tool_api_id'] ?? NULL;
    if (!$tool_id) {
      $this->output = 'Error: no Tool API id bound to this function call.';
      return;
    }

    try {
      $definition = $this->toolManager->getDefinition($tool_id);
      assert($definition instanceof ToolDefinition);

      // Tool API tools read inputs from their own typed values, not from
      // execute() arguments: hand over each provided context value.
      $values = [];
      foreach (array_keys($this->getContextDefinitions()) as $name) {
        $value = $this->getContextValue($name);
        if ($value !== NULL) {
          $values[$name] = $value;
        }
      }

      // Drupal AI does not check access before it executes a function call.
      // Tool API's runner does: the declared permission, the inputs, the
      // tool's own access check. Around it are the site's rules for a tool
      // of another provider: scopes, read-only mode, the write limits and
      // the audit line, the same as when the MCP server runs it.
      $outcome = $this->toolPolicy->run($definition, $values, new Invoker('mcp_tools_ai'));

      if ($outcome instanceof ToolRefusal) {
        $payload = ['success' => FALSE, 'message' => $outcome->message, 'data' => []];
        if ($outcome->reason === ToolRefusal::LIMIT) {
          $payload['retry_after'] = $outcome->retryAfter;
        }
      }
      else {
        $result = $outcome->getResult();
        $denied = !$outcome->hasExecuted() && $result->getFailureCategory() === FailureCategory::Access;
        $payload = [
          'success' => $result->isSuccess(),
          // A denial says no more than that. Tool API's own text names
          // the permission that is missing.
          'message' => $denied ? 'Access denied.' : (string) $result->getMessage(),
          'data' => $result->getContextValues(),
        ];
      }
      $this->output = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '';
    }
    catch (\Throwable $e) {
      $this->output = 'Tool execution failed: ' . $e->getMessage();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getReadableOutput(): string {
    return $this->output;
  }

}
