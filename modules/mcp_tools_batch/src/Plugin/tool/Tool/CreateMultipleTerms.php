<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_batch\Plugin\tool\Tool;

use Drupal\mcp_tools_batch\Service\BatchService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp_tools\Tool\McpToolsToolBase;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinition;

/**
 * Tool plugin implementation.
 */
#[Tool(
  id: 'mcp_batch_create_terms',
  label: new TranslatableMarkup('Batch Create Taxonomy Terms'),
  description: new TranslatableMarkup('Create multiple taxonomy terms at once. Maximum 50 terms per operation.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'vocabulary' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Vocabulary'),
      description: new TranslatableMarkup('The machine name of the vocabulary (e.g., "tags", "categories").'),
      required: TRUE,
    ),
    'terms' => new InputDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Terms'),
      description: new TranslatableMarkup('Array of terms to create. Each can be a string (term name) or object with "name", "description", "parent" (term ID), "weight".'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'vocabulary' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Vocabulary'),
      description: new TranslatableMarkup('Vocabulary machine name where terms were created.'),
    ),
    'total_requested' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total Requested'),
      description: new TranslatableMarkup('Number of terms requested for creation.'),
    ),
    'created_count' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Created Count'),
      description: new TranslatableMarkup('Number of terms successfully created.'),
    ),
    'skipped_count' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Skipped Count'),
      description: new TranslatableMarkup('Number of terms skipped (already exist with same name).'),
    ),
    'error_count' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Error Count'),
      description: new TranslatableMarkup('Number of terms that failed to create.'),
    ),
    'created' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Created Terms'),
      description: new TranslatableMarkup('Array of created terms with tid, name, and path.'),
    ),
    'skipped' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Skipped Terms'),
      description: new TranslatableMarkup('Array of skipped terms with name and reason (duplicate).'),
    ),
    'errors' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Errors'),
      description: new TranslatableMarkup('Array of errors with name and error message for each failure.'),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup('Summary of the batch term creation operation.'),
    ),
  ],
)]
class CreateMultipleTerms extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'batch';


  protected BatchService $batchService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->batchService = $container->get('mcp_tools_batch.batch');
    return $instance;
  }

  protected function executeLegacy(array $input): array {
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


}
