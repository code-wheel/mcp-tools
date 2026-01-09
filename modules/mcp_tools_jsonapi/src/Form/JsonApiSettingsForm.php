<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_jsonapi\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configuration form for MCP Tools JSON:API settings.
 */
class JsonApiSettingsForm extends ConfigFormBase {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ResourceTypeRepositoryInterface $resourceTypeRepository,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('jsonapi.resource_type.repository'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'mcp_tools_jsonapi_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['mcp_tools_jsonapi.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('mcp_tools_jsonapi.settings');

    $form['description'] = [
      '#type' => 'markup',
      '#markup' => '<p>' . $this->t('Configure which entity types are exposed via the JSON:API MCP tools. These tools provide generic CRUD operations for any Drupal entity type.') . '</p>',
    ];

    // Get all available entity types from JSON:API.
    $entityTypeOptions = $this->getEntityTypeOptions();

    $form['access_control'] = [
      '#type' => 'details',
      '#title' => $this->t('Entity Type Access'),
      '#open' => TRUE,
    ];

    $form['access_control']['allowed_entity_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Allowed entity types'),
      '#description' => $this->t('Select which entity types to expose. Leave all unchecked to allow all content entity types (except blocked ones). Blocked types always take precedence.'),
      '#options' => $entityTypeOptions,
      '#default_value' => $config->get('allowed_entity_types') ?? [],
    ];

    $form['access_control']['blocked_entity_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Blocked entity types'),
      '#description' => $this->t('These entity types are always blocked regardless of the allowed list. User, shortcut, and shortcut_set are always blocked for security.'),
      '#options' => $entityTypeOptions,
      '#default_value' => $config->get('blocked_entity_types') ?? [],
    ];

    $form['access_control']['always_blocked_note'] = [
      '#type' => 'markup',
      '#markup' => '<p class="description"><strong>' . $this->t('Always blocked (hardcoded):') . '</strong> user, shortcut, shortcut_set</p>',
    ];

    $form['operations'] = [
      '#type' => 'details',
      '#title' => $this->t('Operations'),
      '#open' => TRUE,
    ];

    $form['operations']['allow_write_operations'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow write operations'),
      '#description' => $this->t('When disabled, only read operations (list, get) are permitted. Create, update, and delete will be blocked.'),
      '#default_value' => (bool) $config->get('allow_write_operations'),
    ];

    $form['response'] = [
      '#type' => 'details',
      '#title' => $this->t('Response Settings'),
      '#open' => TRUE,
    ];

    $form['response']['max_items_per_page'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum items per page'),
      '#description' => $this->t('Maximum number of entities returned in list operations.'),
      '#default_value' => (int) ($config->get('max_items_per_page') ?? 50),
      '#min' => 1,
      '#max' => 100,
    ];

    $form['response']['include_relationships'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include relationships'),
      '#description' => $this->t('Include entity reference fields in responses. This increases response size but provides more complete data.'),
      '#default_value' => (bool) $config->get('include_relationships'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Filter out unchecked values from checkboxes.
    $allowedTypes = array_values(array_filter($form_state->getValue('allowed_entity_types') ?? []));
    $blockedTypes = array_values(array_filter($form_state->getValue('blocked_entity_types') ?? []));

    $this->config('mcp_tools_jsonapi.settings')
      ->set('allowed_entity_types', $allowedTypes)
      ->set('blocked_entity_types', $blockedTypes)
      ->set('allow_write_operations', (bool) $form_state->getValue('allow_write_operations'))
      ->set('max_items_per_page', (int) $form_state->getValue('max_items_per_page'))
      ->set('include_relationships', (bool) $form_state->getValue('include_relationships'))
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Get available entity type options from JSON:API.
   *
   * @return array
   *   Entity type options keyed by entity type ID.
   */
  protected function getEntityTypeOptions(): array {
    $options = [];
    $resourceTypes = $this->resourceTypeRepository->all();

    foreach ($resourceTypes as $resourceType) {
      $entityTypeId = $resourceType->getEntityTypeId();

      // Skip if we already have this entity type.
      if (isset($options[$entityTypeId])) {
        continue;
      }

      // Skip internal types.
      if ($resourceType->isInternal()) {
        continue;
      }

      try {
        $entityTypeDef = $this->entityTypeManager->getDefinition($entityTypeId);
        $options[$entityTypeId] = $entityTypeDef->getLabel() . ' (' . $entityTypeId . ')';
      }
      catch (\Exception $e) {
        // Skip if entity type doesn't exist.
      }
    }

    asort($options);
    return $options;
  }

}
