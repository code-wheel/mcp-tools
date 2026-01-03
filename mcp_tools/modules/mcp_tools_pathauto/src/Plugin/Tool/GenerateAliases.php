<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_pathauto\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_pathauto\Service\PathautoService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for bulk generating URL aliases.
 *
 * @Tool(
 *   id = "mcp_pathauto_generate",
 *   label = @Translation("Generate URL Aliases"),
 *   description = @Translation("Bulk generate URL aliases for entities using Pathauto patterns. This is a write operation."),
 *   category = "pathauto",
 * )
 */
class GenerateAliases extends ToolPluginBase implements ContainerFactoryPluginInterface {

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
    $entityType = $input['entity_type'] ?? '';
    $bundle = $input['bundle'] ?? NULL;
    $update = $input['update'] ?? FALSE;

    if (empty($entityType)) {
      return ['success' => FALSE, 'error' => 'Entity type is required.'];
    }

    return $this->pathautoService->generateAliases($entityType, $bundle, (bool) $update);
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'entity_type' => [
        'type' => 'string',
        'label' => 'Entity Type',
        'description' => 'The entity type to generate aliases for (e.g., "node", "taxonomy_term", "user").',
        'required' => TRUE,
      ],
      'bundle' => [
        'type' => 'string',
        'label' => 'Bundle',
        'description' => 'Optional bundle (content type, vocabulary) to limit generation.',
        'required' => FALSE,
      ],
      'update' => [
        'type' => 'boolean',
        'label' => 'Update Existing',
        'description' => 'If true, update existing aliases. If false (default), only create missing aliases.',
        'required' => FALSE,
        'default' => FALSE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'entity_type' => ['type' => 'string', 'label' => 'Entity Type'],
      'bundle' => ['type' => 'string', 'label' => 'Bundle'],
      'processed' => ['type' => 'integer', 'label' => 'Entities Processed'],
      'created' => ['type' => 'integer', 'label' => 'Aliases Created'],
      'updated' => ['type' => 'integer', 'label' => 'Aliases Updated'],
      'skipped' => ['type' => 'integer', 'label' => 'Skipped'],
      'message' => ['type' => 'string', 'label' => 'Result Message'],
    ];
  }

}
