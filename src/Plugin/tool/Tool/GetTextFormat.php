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
use Drupal\tool\TypedData\InputDefinition;

/**
 * Get details about a text format.
 */
#[Tool(
  id: 'mcp_get_text_format',
  label: new TranslatableMarkup('Get Text Format'),
  description: new TranslatableMarkup('Get details about a specific text format including enabled filters and allowed HTML tags. Useful for understanding what markup is allowed in a format.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Format ID'),
      description: new TranslatableMarkup('Machine name of the text format (e.g., "basic_html", "full_html", "plain_text"). Use mcp_list_text_formats to see available formats.'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'id' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Format ID'),
      description: new TranslatableMarkup('Machine name of the text format.'),
    ),
    'label' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Label'),
      description: new TranslatableMarkup('Human-readable name.'),
    ),
    'roles' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Roles'),
      description: new TranslatableMarkup('Roles that can use this format.'),
    ),
    'filters' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Filters'),
      description: new TranslatableMarkup('Enabled filters with their settings.'),
    ),
    'allowed_html' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Allowed HTML'),
      description: new TranslatableMarkup('HTML tags allowed by the filter_html filter, if enabled.'),
    ),
    'admin_path' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Admin Path'),
      description: new TranslatableMarkup('Path to configure this format in admin UI.'),
    ),
  ],
)]
class GetTextFormat extends McpToolsToolBase {

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
    $id = $input['id'] ?? '';

    if (empty($id)) {
      return ['success' => FALSE, 'error' => 'Format ID is required.'];
    }

    $format = $this->entityTypeManager->getStorage('filter_format')->load($id);
    if (!$format) {
      return [
        'success' => FALSE,
        'error' => "Text format '$id' not found. Use mcp_list_text_formats to see available formats.",
      ];
    }

    $filters = [];
    $allowedHtml = NULL;
    $filterCollection = $format->filters();

    foreach ($filterCollection as $filter) {
      if (!$filter->status) {
        continue;
      }

      $filterData = [
        'id' => $filter->getPluginId(),
        'label' => $filter->getLabel(),
        'weight' => $filter->weight,
      ];

      // Get settings for the filter.
      $settings = $filter->settings;
      if (!empty($settings)) {
        $filterData['settings'] = $settings;
      }

      // Extract allowed HTML if this is the filter_html filter.
      if ($filter->getPluginId() === 'filter_html' && isset($settings['allowed_html'])) {
        $allowedHtml = $settings['allowed_html'];
      }

      $filters[] = $filterData;
    }

    // Sort filters by weight.
    usort($filters, fn($a, $b) => ($a['weight'] ?? 0) <=> ($b['weight'] ?? 0));

    return [
      'success' => TRUE,
      'data' => [
        'id' => $format->id(),
        'label' => $format->label(),
        'roles' => $format->get('roles') ?: [],
        'filters' => $filters,
        'allowed_html' => $allowedHtml,
        'admin_path' => "/admin/config/content/formats/manage/$id",
      ],
    ];
  }

}
