<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_pathauto\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_pathauto\Service\PathautoService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for deleting URL alias patterns.
 *
 * @Tool(
 *   id = "mcp_pathauto_delete",
 *   label = @Translation("Delete Pathauto Pattern"),
 *   description = @Translation("Delete a URL alias pattern. This is a destructive write operation."),
 *   category = "pathauto",
 * )
 */
class DeletePattern extends ToolPluginBase implements ContainerFactoryPluginInterface {

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

    return $this->pathautoService->deletePattern($id);
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'id' => [
        'type' => 'string',
        'label' => 'Pattern ID',
        'description' => 'The machine name of the pattern to delete.',
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
      'message' => ['type' => 'string', 'label' => 'Result Message'],
    ];
  }

}
