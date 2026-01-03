<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_webform\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_webform\Service\WebformService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for deleting a webform submission.
 *
 * @Tool(
 *   id = "mcp_delete_webform_submission",
 *   label = @Translation("Delete Webform Submission"),
 *   description = @Translation("Permanently delete a webform submission."),
 *   category = "webform",
 * )
 */
class DeleteSubmission extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected WebformService $webformService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->webformService = $container->get('mcp_tools_webform.webform');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $input = []): array {
    $sid = $input['sid'] ?? 0;

    if (empty($sid)) {
      return ['success' => FALSE, 'error' => 'Submission ID (sid) is required.'];
    }

    return $this->webformService->deleteSubmission((int) $sid);
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'sid' => ['type' => 'integer', 'label' => 'Submission ID', 'required' => TRUE],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'sid' => ['type' => 'integer', 'label' => 'Deleted Submission ID'],
      'webform_id' => ['type' => 'string', 'label' => 'Webform ID'],
      'webform_title' => ['type' => 'string', 'label' => 'Webform Title'],
      'message' => ['type' => 'string', 'label' => 'Result Message'],
    ];
  }

}
