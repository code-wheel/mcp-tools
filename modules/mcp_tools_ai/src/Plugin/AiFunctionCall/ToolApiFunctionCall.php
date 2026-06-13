<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_ai\Plugin\AiFunctionCall;

use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\ai\Service\FunctionCalling\FunctionCallInterface;
use Drupal\Component\Plugin\PluginManagerInterface;
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
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    $definition = $this->getPluginDefinition();
    $tool_id = $definition['tool_api_id'] ?? NULL;
    if (!$tool_id) {
      $this->output = 'Error: no Tool API id bound to this function call.';
      return;
    }

    try {
      $tool = $this->toolManager->createInstance($tool_id);

      // Tool API tools read inputs from their own typed values, not from
      // execute() arguments — transfer each provided context value across.
      foreach (array_keys($this->getContextDefinitions()) as $name) {
        $value = $this->getContextValue($name);
        if ($value !== NULL) {
          $tool->setInputValue($name, $value);
        }
      }

      // execute() returns the plugin; the result lives in getResult().
      $tool->execute();
      $result = $tool->getResult();

      $payload = [
        'success' => $result->isSuccess(),
        'message' => (string) $result->getMessage(),
        'data' => $result->getContextValues(),
      ];
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
