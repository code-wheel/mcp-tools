<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_ai\Form;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configures which Tool API operations are exposed as AI Function Calls.
 */
final class SettingsForm extends ConfigFormBase {

  private const SETTINGS = 'mcp_tools_ai.settings';

  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typed_config_manager,
    private readonly PluginManagerInterface $functionCallManager,
  ) {
    parent::__construct($config_factory, $typed_config_manager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('plugin.manager.ai.function_calls'),
    );
  }

  private const OPERATIONS = [
    'read' => 'Read — query site state (safe, default)',
    'explain' => 'Explain — descriptive/help output (safe, default)',
    'transform' => 'Transform — derive or reshape data',
    'trigger' => 'Trigger — run an action (e.g. cron, cache clear)',
    'write' => 'Write — create or modify content/config (use with care)',
  ];

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'mcp_tools_ai_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [self::SETTINGS];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $exposed = $this->config(self::SETTINGS)->get('exposed_operations') ?? ['read', 'explain'];

    $form['intro'] = [
      '#markup' => $this->t('<p>Choose which MCP Tools operations are exposed to the Drupal AI ecosystem (AI Agents, assistants) as Function Calls. Read-only is the safe default and keeps the tool set focused for reliable agent selection. Enabling write/trigger lets agents take actions — each tool still enforces its own access check.</p>'),
    ];
    $form['exposed_operations'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Exposed operations'),
      '#options' => array_map([$this, 't'], self::OPERATIONS),
      '#default_value' => array_combine($exposed, $exposed),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $selected = array_values(array_filter($form_state->getValue('exposed_operations')));
    $this->config(self::SETTINGS)
      ->set('exposed_operations', $selected)
      ->save();

    // The exposed Function Calls are derived plugins; clear the cache so the
    // new operation set takes effect immediately.
    $this->functionCallManager->clearCachedDefinitions();

    parent::submitForm($form, $form_state);
  }

}
