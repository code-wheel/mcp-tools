<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Builds a lightweight site blueprint summary for MCP context snapshots.
 */
final class SiteBlueprintService {

  private const MAX_FIELDS_PER_TYPE = 20;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityFieldManagerInterface $entityFieldManager,
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Returns a site blueprint summary.
   *
   * @return array<string, mixed>
   *   Blueprint data.
   */
  public function getBlueprint(): array {
    return [
      'content_types' => $this->getContentTypes(),
      'vocabularies' => $this->moduleHandler->moduleExists('taxonomy')
        ? $this->getVocabularies()
        : ['total' => 0, 'items' => [], 'note' => 'Taxonomy module is disabled.'],
      'roles' => $this->getRoles(),
      'views' => $this->moduleHandler->moduleExists('views')
        ? $this->getViews()
        : ['total' => 0, 'items' => [], 'note' => 'Views module is disabled.'],
      'menus' => $this->getMenus(),
      'themes' => $this->getThemes(),
    ];
  }

  /**
   * Summarize content types and their fields.
   */
  private function getContentTypes(): array {
    try {
      $types = $this->entityTypeManager->getStorage('node_type')->loadMultiple();
    }
    catch (\Throwable $e) {
      return ['error' => 'Unable to load content types: ' . $e->getMessage()];
    }

    $items = [];
    foreach ($types as $type) {
      $fields = [];
      $fieldDefinitions = $this->entityFieldManager->getFieldDefinitions('node', $type->id());
      foreach ($fieldDefinitions as $fieldName => $fieldDefinition) {
        if (interface_exists(\Drupal\field\FieldConfigInterface::class)
          && !$fieldDefinition instanceof \Drupal\field\FieldConfigInterface) {
          continue;
        }

        $fields[] = [
          'name' => (string) $fieldName,
          'type' => method_exists($fieldDefinition, 'getType') ? (string) $fieldDefinition->getType() : 'unknown',
          'required' => method_exists($fieldDefinition, 'isRequired') ? (bool) $fieldDefinition->isRequired() : FALSE,
        ];
      }

      $fieldsSummary = $this->limitList($fields, self::MAX_FIELDS_PER_TYPE);

      $items[] = [
        'id' => $type->id(),
        'label' => $type->label(),
        'description' => $type->getDescription() ?: '',
        'field_count' => $fieldsSummary['total'],
        'fields' => $fieldsSummary['items'],
        'fields_truncated' => $fieldsSummary['truncated'],
      ];
    }

    usort($items, static fn(array $a, array $b): int => strcasecmp($a['label'], $b['label']));

    return [
      'total' => count($items),
      'items' => $items,
    ];
  }

  /**
   * Summarize taxonomy vocabularies.
   */
  private function getVocabularies(): array {
    try {
      $vocabularies = $this->entityTypeManager->getStorage('taxonomy_vocabulary')->loadMultiple();
    }
    catch (\Throwable $e) {
      return ['error' => 'Unable to load vocabularies: ' . $e->getMessage()];
    }

    $items = [];
    foreach ($vocabularies as $vocabulary) {
      $items[] = [
        'id' => $vocabulary->id(),
        'label' => $vocabulary->label(),
        'description' => $vocabulary->getDescription() ?: '',
      ];
    }

    usort($items, static fn(array $a, array $b): int => strcasecmp($a['label'], $b['label']));

    return [
      'total' => count($items),
      'items' => $items,
    ];
  }

  /**
   * Summarize user roles.
   */
  private function getRoles(): array {
    try {
      $roles = $this->entityTypeManager->getStorage('user_role')->loadMultiple();
    }
    catch (\Throwable $e) {
      return ['error' => 'Unable to load roles: ' . $e->getMessage()];
    }

    $items = [];
    foreach ($roles as $role) {
      $permissions = method_exists($role, 'getPermissions') ? $role->getPermissions() : [];
      $items[] = [
        'id' => $role->id(),
        'label' => $role->label(),
        'permission_count' => is_array($permissions) ? count($permissions) : 0,
      ];
    }

    usort($items, static fn(array $a, array $b): int => strcasecmp($a['label'], $b['label']));

    return [
      'total' => count($items),
      'items' => $items,
    ];
  }

  /**
   * Summarize views.
   */
  private function getViews(): array {
    try {
      $views = $this->entityTypeManager->getStorage('view')->loadMultiple();
    }
    catch (\Throwable $e) {
      return ['error' => 'Unable to load views: ' . $e->getMessage()];
    }

    $items = [];
    foreach ($views as $view) {
      $status = method_exists($view, 'status') ? (bool) $view->status() : (bool) ($view->get('status') ?? FALSE);
      $baseTable = method_exists($view, 'get') ? $view->get('base_table') : NULL;
      $items[] = [
        'id' => $view->id(),
        'label' => $view->label(),
        'status' => $status ? 'enabled' : 'disabled',
        'base_table' => $baseTable ? (string) $baseTable : '',
      ];
    }

    usort($items, static fn(array $a, array $b): int => strcasecmp($a['label'], $b['label']));

    return [
      'total' => count($items),
      'items' => $items,
    ];
  }

  /**
   * Summarize menus.
   */
  private function getMenus(): array {
    try {
      $menus = $this->entityTypeManager->getStorage('menu')->loadMultiple();
    }
    catch (\Throwable $e) {
      return ['error' => 'Unable to load menus: ' . $e->getMessage()];
    }

    $items = [];
    foreach ($menus as $menu) {
      $items[] = [
        'id' => $menu->id(),
        'label' => $menu->label(),
      ];
    }

    usort($items, static fn(array $a, array $b): int => strcasecmp($a['label'], $b['label']));

    return [
      'total' => count($items),
      'items' => $items,
    ];
  }

  /**
   * Summarize themes from configuration.
   */
  private function getThemes(): array {
    $systemTheme = $this->configFactory->get('system.theme');
    $coreExtensions = $this->configFactory->get('core.extension');
    $enabled = $coreExtensions->get('theme') ?? [];

    return [
      'default' => (string) ($systemTheme->get('default') ?? ''),
      'admin' => (string) ($systemTheme->get('admin') ?? ''),
      'enabled' => array_values(array_keys((array) $enabled)),
      'total_enabled' => count((array) $enabled),
    ];
  }

  /**
   * Limits a list to a maximum size.
   *
   * @param array<int, array<string, mixed>> $items
   * @param int $limit
   *
   * @return array{items: array<int, array<string, mixed>>, total: int, truncated: bool}
   */
  private function limitList(array $items, int $limit): array {
    $total = count($items);
    if ($total > $limit) {
      return [
        'items' => array_slice($items, 0, $limit),
        'total' => $total,
        'truncated' => TRUE,
      ];
    }

    return [
      'items' => $items,
      'total' => $total,
      'truncated' => FALSE,
    ];
  }

}
