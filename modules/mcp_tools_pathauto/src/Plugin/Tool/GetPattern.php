<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_pathauto\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_pathauto\Service\PathautoService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for getting URL alias pattern details.
 *
 * @Tool(
 *   id = "mcp_pathauto_get_pattern",
 *   label = @Translation("Get Pathauto Pattern"),
 *   description = @Translation("Get details of a specific URL alias pattern."),
 *   category = "pathauto",
 * )
 */
class GetPattern extends ToolPluginBase implements ContainerFactoryPluginInterface {

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
    $id = $input['id'] ?? '';

    if (empty($id)) {
      return ['success' => FALSE, 'error' => 'Pattern ID is required.'];
    }

    return $this->pathautoService->getPattern($id);
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'id' => [
        'type' => 'string',
        'label' => 'Pattern ID',
        'description' => 'The machine name of the pattern to retrieve.',
        'required' => TRUE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'id' => ['type' => 'string', 'label' => 'Pattern ID'],
      'label' => ['type' => 'string', 'label' => 'Label'],
      'pattern' => ['type' => 'string', 'label' => 'URL Pattern'],
      'entity_type' => ['type' => 'string', 'label' => 'Entity Type'],
      'bundles' => ['type' => 'array', 'label' => 'Bundles'],
      'weight' => ['type' => 'integer', 'label' => 'Weight'],
      'status' => ['type' => 'boolean', 'label' => 'Enabled'],
    ];
  }

}
