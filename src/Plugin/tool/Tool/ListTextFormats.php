<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Plugin\tool\Tool;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\tool\TypedData\OutputDefinition;
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
    'formats' => new OutputDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Text Formats'),
      description: new TranslatableMarkup('Array of text formats with id, label, and roles that can use them.'),
    ),
    'total' => new OutputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Total'),
      description: new TranslatableMarkup('Total number of text formats available.'),
    ),
    'default_format' => new OutputDefinition(
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
    $allRoles = $this->entityTypeManager->getStorage('user_role')->loadMultiple();

    foreach ($formats as $format) {
      // Skip disabled formats.
      if (!$format->status()) {
        continue;
      }

      // Who may use a format is a permission, and everybody may use the
      // fallback format. The "roles" property of a format is empty once
      // the format is saved.
      $permission = $format->getPermissionName();
      $roles = [];
      foreach ($allRoles as $roleId => $role) {
        if ($permission === FALSE || $role->hasPermission($permission)) {
          $roles[] = (string) $roleId;
        }
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

    // The first format an anonymous user may use: what Drupal itself
    // preselects for them.
    $defaultFormat = '';
    foreach ($result as $each) {
      if (in_array('anonymous', $each['roles'], TRUE)) {
        $defaultFormat = (string) $each['id'];
        break;
      }
    }

    return [
      'success' => TRUE,
      'data' => [
        'formats' => $result,
        'total' => count($result),
        'default_format' => $defaultFormat,
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
