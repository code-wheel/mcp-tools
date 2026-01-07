<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_structure\Plugin\tool\Tool;

use Drupal\mcp_tools_structure\Service\TaxonomyService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp_tools\Tool\McpToolsToolBase;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinition;

/**
 * Create a vocabulary with terms in one operation.
 */
#[Tool(
  id: 'mcp_structure_setup_taxonomy',
  label: new TranslatableMarkup('Setup Taxonomy'),
  description: new TranslatableMarkup('Create a vocabulary with initial terms in a single operation. Reduces round-trips compared to calling CreateVocabulary + CreateTerms separately. Supports hierarchical terms.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Vocabulary ID'),
      description: new TranslatableMarkup('Machine name for the vocabulary (e.g., "categories", "tags"). Lowercase letters, numbers, underscores only.'),
      required: TRUE,
    ),
    'label' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Label'),
      description: new TranslatableMarkup('Human-readable name (e.g., "Categories", "Tags").'),
      required: TRUE,
    ),
    'description' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Description'),
      description: new TranslatableMarkup('Description of the vocabulary.'),
      required: FALSE,
    ),
    'terms' => new InputDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Terms'),
      description: new TranslatableMarkup('Array of terms to create. Can be simple strings ["Term1", "Term2"] or objects with hierarchy [{"name": "Parent"}, {"name": "Child", "parent": "Parent"}]. For hierarchical terms, use "parent" to specify the parent term name.'),
      required: FALSE,
    ),
  ],
  output_definitions: [
    'vocabulary' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Vocabulary'),
      description: new TranslatableMarkup('Machine name of the created vocabulary.'),
    ),
    'label' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Label'),
      description: new TranslatableMarkup('Human-readable name of the vocabulary.'),
    ),
    'terms_created' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Terms Created'),
      description: new TranslatableMarkup('List of terms that were created with their IDs.'),
    ),
    'term_count' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Term Count'),
      description: new TranslatableMarkup('Number of terms created.'),
    ),
    'admin_path' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Admin Path'),
      description: new TranslatableMarkup('Path to manage the vocabulary in admin UI.'),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup('Summary of what was created.'),
    ),
  ],
)]
class SetupTaxonomy extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'structure';

  protected TaxonomyService $taxonomyService;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->taxonomyService = $container->get('mcp_tools_structure.taxonomy');
    return $instance;
  }

  protected function executeLegacy(array $input): array {
    $id = $input['id'] ?? '';
    $label = $input['label'] ?? '';
    $description = $input['description'] ?? '';
    $terms = $input['terms'] ?? [];

    if (empty($id)) {
      return ['success' => FALSE, 'error' => 'Vocabulary ID is required.'];
    }

    if (empty($label)) {
      return ['success' => FALSE, 'error' => 'Label is required.'];
    }

    // Step 1: Create the vocabulary.
    $vocabResult = $this->taxonomyService->createVocabulary($id, $label, $description);
    if (!($vocabResult['success'] ?? FALSE)) {
      return $vocabResult;
    }

    $termsCreated = [];
    $termNameToId = [];

    if (!empty($terms)) {
      // Step 2: Create terms (handle hierarchy).
      // First pass: create all terms without hierarchy.
      foreach ($terms as $term) {
        $termName = is_string($term) ? $term : ($term['name'] ?? '');
        if (empty($termName)) {
          continue;
        }

        $termResult = $this->taxonomyService->createTerm($id, $termName);
        if ($termResult['success'] ?? FALSE) {
          $tid = $termResult['data']['tid'] ?? $termResult['tid'] ?? NULL;
          $termsCreated[] = ['name' => $termName, 'tid' => $tid];
          $termNameToId[$termName] = $tid;
        }
      }

      // Second pass: update hierarchy for terms with parents.
      foreach ($terms as $term) {
        if (!is_array($term) || empty($term['parent'])) {
          continue;
        }

        $termName = $term['name'] ?? '';
        $parentName = $term['parent'];

        if (isset($termNameToId[$termName]) && isset($termNameToId[$parentName])) {
          // Update the term's parent.
          $this->updateTermParent($termNameToId[$termName], $termNameToId[$parentName]);
        }
      }
    }

    $termCount = count($termsCreated);
    $message = "Created vocabulary '$label' ($id)";
    if ($termCount > 0) {
      $message .= " with $termCount terms.";
    }
    else {
      $message .= ".";
    }

    return [
      'success' => TRUE,
      'data' => [
        'vocabulary' => $id,
        'label' => $label,
        'terms_created' => $termsCreated,
        'term_count' => $termCount,
        'admin_path' => "/admin/structure/taxonomy/manage/$id/overview",
        'message' => $message,
      ],
    ];
  }

  /**
   * Update a term's parent.
   */
  protected function updateTermParent(int $tid, int $parentTid): void {
    try {
      $term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($tid);
      if ($term) {
        $term->set('parent', $parentTid);
        $term->save();
      }
    }
    catch (\Exception $e) {
      // Silently ignore hierarchy errors.
    }
  }

}
