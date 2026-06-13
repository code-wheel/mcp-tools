<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_ai\Plugin\AiFunctionCall\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\tool\Tool\ToolDefinition;
use Drupal\tool\Tool\ToolOperation;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Derives a curated set of drupal/ai Function Calls from Tool API tools.
 *
 * Every tool MCP Tools registers via the Tool API is structurally a named,
 * typed, executable function — the same shape drupal/ai's FunctionCall plugin
 * expects. This deriver surfaces them to the AI ecosystem (AI Agents,
 * assistants) without hand-writing a plugin per tool.
 *
 * Exposure is curated by operation (config: mcp_tools_ai.settings
 * exposed_operations), defaulting to read + explain. That keeps the surface
 * small enough for reliable agent tool-selection and safe by default; writes
 * and triggers are opt-in. Execution is delegated back to the Tool API plugin
 * by ToolApiFunctionCall::execute(), which still enforces each tool's own
 * access check.
 */
final class ToolApiFunctionCallDeriver extends DeriverBase implements ContainerDeriverInterface {

  public function __construct(
    private readonly PluginManagerInterface $toolManager,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id): self {
    return new self(
      $container->get('plugin.manager.tool'),
      $container->get('config.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition): array {
    // Re-derive from current config every call (no memo): exposure is
    // config-driven, so a settings change + cache clear must take effect.
    $this->derivatives = [];

    $exposed = $this->configFactory
      ->get('mcp_tools_ai.settings')
      ->get('exposed_operations') ?? ['read', 'explain'];
    $exposed = array_map('strtolower', (array) $exposed);

    foreach ($this->toolManager->getDefinitions() as $tool_id => $tool_definition) {
      if (!$tool_definition instanceof ToolDefinition) {
        continue;
      }
      try {
        $operation = $tool_definition->getOperation() ?? ToolOperation::Transform;
        if (!in_array($operation->value, $exposed, TRUE)) {
          continue;
        }

        $definition = $base_plugin_definition;
        $definition['name'] = (string) $tool_definition->getLabel();
        $definition['description'] = (string) ($tool_definition->getDescription() ?: $tool_definition->getLabel());
        $definition['group'] = 'mcp_tools';
        // Function names must be plain identifiers (no colons/dots).
        $definition['function_name'] = 'mcp_tools__' . preg_replace('/[^a-zA-Z0-9_]+/', '_', (string) $tool_id);
        // Stash the real Tool API id so execute() can delegate back.
        $definition['tool_api_id'] = (string) $tool_id;
        $definition['context_definitions'] = $this->buildContextDefinitions($tool_definition);

        $this->derivatives[$tool_id] = $definition;
      }
      catch (\Throwable $e) {
        // A single malformed tool must never break discovery of the rest.
        continue;
      }
    }

    return $this->derivatives;
  }

  /**
   * Maps a tool's input definitions to drupal/ai context definitions.
   *
   * @return array<string, \Drupal\Core\Plugin\Context\ContextDefinition>
   *   Context definitions keyed by input name.
   */
  private function buildContextDefinitions(ToolDefinition $tool_definition): array {
    $context_definitions = [];
    foreach ($tool_definition->getInputDefinitions() as $name => $input) {
      $context_definitions[$name] = new ContextDefinition(
        data_type: $input->getDataType(),
        label: $name,
        required: $input->isRequired(),
        description: (string) ($input->getDescription() ?? ''),
      );
    }
    return $context_definitions;
  }

}
