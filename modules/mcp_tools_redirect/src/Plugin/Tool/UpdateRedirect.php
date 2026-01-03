<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_redirect\Plugin\Tool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mcp_tools_redirect\Service\RedirectService;
use Drupal\tool\ToolPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool for updating an existing redirect.
 *
 * @Tool(
 *   id = "mcp_redirect_update",
 *   label = @Translation("Update Redirect"),
 *   description = @Translation("Update an existing URL redirect. This is a write operation."),
 *   category = "redirect",
 * )
 */
class UpdateRedirect extends ToolPluginBase implements ContainerFactoryPluginInterface {

  protected RedirectService $redirectService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->redirectService = $container->get('mcp_tools_redirect.redirect');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $input = []): array {
    $id = $input['id'] ?? 0;

    if (empty($id)) {
      return ['success' => FALSE, 'error' => 'Redirect ID is required.'];
    }

    $values = [];

    if (isset($input['source'])) {
      $values['source'] = $input['source'];
    }
    if (isset($input['destination'])) {
      $values['destination'] = $input['destination'];
    }
    if (isset($input['status_code'])) {
      $values['status_code'] = (int) $input['status_code'];
    }
    if (isset($input['language'])) {
      $values['language'] = $input['language'];
    }

    if (empty($values)) {
      return ['success' => FALSE, 'error' => 'At least one field to update is required (source, destination, status_code, or language).'];
    }

    return $this->redirectService->updateRedirect((int) $id, $values);
  }

  /**
   * {@inheritdoc}
   */
  public function getInputDefinition(): array {
    return [
      'id' => [
        'type' => 'integer',
        'label' => 'Redirect ID',
        'description' => 'The redirect ID to update.',
        'required' => TRUE,
      ],
      'source' => [
        'type' => 'string',
        'label' => 'Source Path',
        'description' => 'New source path to redirect from.',
        'required' => FALSE,
      ],
      'destination' => [
        'type' => 'string',
        'label' => 'Destination',
        'description' => 'New destination path or URL to redirect to.',
        'required' => FALSE,
      ],
      'status_code' => [
        'type' => 'integer',
        'label' => 'Status Code',
        'description' => 'New HTTP redirect status code (301, 302, 303, or 307).',
        'required' => FALSE,
      ],
      'language' => [
        'type' => 'string',
        'label' => 'Language',
        'description' => 'New language code for the redirect.',
        'required' => FALSE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputDefinition(): array {
    return [
      'redirect' => ['type' => 'object', 'label' => 'Updated Redirect'],
      'updated_fields' => ['type' => 'array', 'label' => 'Updated Fields'],
      'message' => ['type' => 'string', 'label' => 'Result Message'],
    ];
  }

}
