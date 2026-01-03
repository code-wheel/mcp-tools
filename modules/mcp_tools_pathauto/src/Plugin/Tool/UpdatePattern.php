<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_pathauto\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_pathauto\Service\PathautoService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for updating URL alias patterns.
 *
 * @Tool(
 *   id = "mcp_pathauto_update",
 *   label = @Translation("Update Pathauto Pattern"),
 *   description = @Translation("Update an existing URL alias pattern. This is a write operation."),
 *   category = "pathauto",
 * )
 */
class UpdatePattern extends ToolPluginBase implements ContainerFactoryPluginInterface {

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

    if (empty($id)) {
      return ['success' => FALSE, 'error' => 'Pattern ID is required.'];
    }

    // Build values array from provided inputs.
    $values = [];

    if (isset($input['label']) && $input['label'] !== '') {
      $values['label'] = $input['label'];
    }

    if (isset($input['pattern']) && $input['pattern'] !== '') {
      $values['pattern'] = $input['pattern'];
    }

    if (isset($input['weight'])) {
      $values['weight'] = (int) $input['weight'];
    }

    if (isset($input['status'])) {
      $values['status'] = (bool) $input['status'];
    }

    if (array_key_exists('bundle', $input)) {
      $values['bundle'] = $input['bundle'];
    }

    if (empty($values)) {
      return ['success' => FALSE, 'error' => 'At least one field to update is required (label, pattern, weight, status, or bundle).'];
    }

    return $this->pathautoService->updatePattern($id, $values);
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'id' => [
        'type' => 'string',
        'label' => 'Pattern ID',
        'description' => 'The machine name of the pattern to update.',
        'required' => TRUE,
      ],
      'label' => [
        'type' => 'string',
        'label' => 'Label',
        'description' => 'New human-readable name for the pattern.',
        'required' => FALSE,
      ],
      'pattern' => [
        'type' => 'string',
        'label' => 'URL Pattern',
        'description' => 'New URL alias pattern using tokens.',
        'required' => FALSE,
      ],
      'weight' => [
        'type' => 'integer',
        'label' => 'Weight',
        'description' => 'Pattern weight (lower weights are processed first).',
        'required' => FALSE,
      ],
      'status' => [
        'type' => 'boolean',
        'label' => 'Enabled',
        'description' => 'Whether the pattern is enabled (true/false).',
        'required' => FALSE,
      ],
      'bundle' => [
        'type' => 'string',
        'label' => 'Bundle',
        'description' => 'Bundle to restrict the pattern to (null to remove restriction).',
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
      'updated_fields' => ['type' => 'array', 'label' => 'Updated Fields'],
      'message' => ['type' => 'string', 'label' => 'Result Message'],
    ];
  }

}
