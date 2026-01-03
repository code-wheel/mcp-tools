<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_pathauto\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_pathauto\Service\PathautoService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for creating URL alias patterns.
 *
 * @Tool(
 *   id = "mcp_pathauto_create",
 *   label = @Translation("Create Pathauto Pattern"),
 *   description = @Translation("Create a new URL alias pattern. This is a write operation."),
 *   category = "pathauto",
 * )
 */
class CreatePattern extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected PathautoService $pathautoService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->pathautoService = $container->get('mcp_tools_pathauto.pathauto');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $input = []): array {
    $id = $input['id'] ?? '';
    $label = $input['label'] ?? '';
    $pattern = $input['pattern'] ?? '';
    $entityType = $input['entity_type'] ?? '';
    $bundle = $input['bundle'] ?? NULL;

    if (empty($id)) {
      return ['success' => FALSE, 'error' => 'Pattern ID (machine name) is required.'];
    }

    if (empty($label)) {
      return ['success' => FALSE, 'error' => 'Pattern label is required.'];
    }

    if (empty($pattern)) {
      return ['success' => FALSE, 'error' => 'URL pattern is required.'];
    }

    if (empty($entityType)) {
      return ['success' => FALSE, 'error' => 'Entity type is required.'];
    }

    // Validate machine name format.
    if (!preg_match('/^[a-z0-9_]+$/', $id)) {
      return ['success' => FALSE, 'error' => 'Pattern ID must contain only lowercase letters, numbers, and underscores.'];
    }

    return $this->pathautoService->createPattern($id, $label, $pattern, $entityType, $bundle);
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'id' => [
        'type' => 'string',
        'label' => 'Pattern ID',
        'description' => 'Machine name for the pattern (lowercase, underscores allowed).',
        'required' => TRUE,
      ],
      'label' => [
        'type' => 'string',
        'label' => 'Label',
        'description' => 'Human-readable name for the pattern.',
        'required' => TRUE,
      ],
      'pattern' => [
        'type' => 'string',
        'label' => 'URL Pattern',
        'description' => 'The URL alias pattern using tokens (e.g., "blog/[node:title]", "[term:vocabulary]/[term:name]").',
        'required' => TRUE,
      ],
      'entity_type' => [
        'type' => 'string',
        'label' => 'Entity Type',
        'description' => 'The entity type this pattern applies to (e.g., "node", "taxonomy_term", "user").',
        'required' => TRUE,
      ],
      'bundle' => [
        'type' => 'string',
        'label' => 'Bundle',
        'description' => 'Optional bundle (content type, vocabulary) to restrict the pattern to.',
        'required' => FALSE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'id' => ['type' => 'string', 'label' => 'Pattern ID'],
      'label' => ['type' => 'string', 'label' => 'Label'],
      'pattern' => ['type' => 'string', 'label' => 'URL Pattern'],
      'entity_type' => ['type' => 'string', 'label' => 'Entity Type'],
      'bundle' => ['type' => 'string', 'label' => 'Bundle'],
      'message' => ['type' => 'string', 'label' => 'Result Message'],
    ];
  }

}
