<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_batch\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_batch\Service\BatchService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @Tool(
 *   id = "mcp_batch_create_terms",
 *   label = @Translation("Batch Create Taxonomy Terms"),
 *   description = @Translation("Create multiple taxonomy terms at once. Maximum 50 terms per operation."),
 *   category = "batch",
 * )
 */
class CreateMultipleTerms extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected BatchService $batchService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->batchService = $container->get('mcp_tools_batch.batch');
    return $instance;
  }

  public function execute(array $input = []): array {
    $vocabulary = $input['vocabulary'] ?? '';
    $terms = $input['terms'] ?? [];

    if (empty($vocabulary)) {
      return ['success' => FALSE, 'error' => 'Vocabulary is required.'];
    }

    if (empty($terms)) {
      return ['success' => FALSE, 'error' => 'Terms array is required.'];
    }

    return $this->batchService->createMultipleTerms($vocabulary, $terms);
  }

  public function getInputDefinition(): array {
    return [
      'vocabulary' => [
        'type' => 'string',
        'label' => 'Vocabulary',
        'description' => 'The machine name of the vocabulary (e.g., "tags", "categories").',
        'required' => TRUE,
      ],
      'terms' => [
        'type' => 'array',
        'label' => 'Terms',
        'description' => 'Array of terms to create. Each can be a string (term name) or object with "name", "description", "parent" (term ID), "weight".',
        'required' => TRUE,
      ],
    ];
  }

  public function getOutputDefinition(): array {
    return [
      'vocabulary' => ['type' => 'string', 'label' => 'Vocabulary'],
      'total_requested' => ['type' => 'integer', 'label' => 'Total Requested'],
      'created_count' => ['type' => 'integer', 'label' => 'Created Count'],
      'skipped_count' => ['type' => 'integer', 'label' => 'Skipped Count'],
      'error_count' => ['type' => 'integer', 'label' => 'Error Count'],
      'created' => ['type' => 'array', 'label' => 'Created Terms'],
      'skipped' => ['type' => 'array', 'label' => 'Skipped Terms'],
      'errors' => ['type' => 'array', 'label' => 'Errors'],
      'message' => ['type' => 'string', 'label' => 'Result Message'],
    ];
  }

}
