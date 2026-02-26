<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Plugin\tool\Tool;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp_tools\Tool\McpToolsToolBase;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\Tool\ToolOperation;

/**
 * List available text formats.
 */
#[Tool(
  id: 'mcp_list_text_formats',
  label: new TranslatableMarkup('List Text Formats'),
  description: new TranslatableMarkup('List all available text formats. Use this to find valid format values for body and text_with_summary fields. Common formats: basic_html, restricted_html, full_html, plain_text.'),
  operation: ToolOperation::Read,
  input_definitions: [],
  output_definitions: [
    'formats' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Text Formats'),
      description: new TranslatableMarkup('Array of text formats with id, label, and roles that can use them.'),
    ),
    'total' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total'),
      description: new TranslatableMarkup('Total number of text formats available.'),
    ),
    'default_format' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Default Format'),
      description: new TranslatableMarkup('The default text format ID for anonymous users.'),
    ),
  ],
)]
class ListTextFormats extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'discovery';

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeLegacy(array $input): array {
    $formatStorage = $this->entityTypeManager->getStorage('filter_format');
    $formats = $formatStorage->loadMultiple();

    $result = [];
    $defaultFormat = NULL;

    foreach ($formats as $format) {
      // Skip disabled formats.
      if (!$format->status()) {
        continue;
      }

      $roles = $format->get('roles') ?: [];
      $isDefault = in_array('anonymous', $roles) || in_array('authenticated', $roles);

      if ($isDefault && !$defaultFormat) {
        $defaultFormat = $format->id();
      }

      $result[] = [
        'id' => $format->id(),
        'label' => $format->label(),
        'weight' => $format->get('weight'),
        'roles' => $roles,
        'filters' => $this->getFilterSummary($format),
      ];
    }

    // Sort by weight.
    usort($result, fn($a, $b) => ($a['weight'] ?? 0) <=> ($b['weight'] ?? 0));

    return [
      'success' => TRUE,
      'data' => [
        'formats' => $result,
        'total' => count($result),
        'default_format' => $defaultFormat ?? 'plain_text',
      ],
    ];
  }

  /**
   * Get a summary of enabled filters for a format.
   */
  protected function getFilterSummary($format): array {
    $filters = [];
    $filterCollection = $format->filters();

    foreach ($filterCollection as $filter) {
      if ($filter->status) {
        $filters[] = $filter->getPluginId();
      }
    }

    return $filters;
  }

}
