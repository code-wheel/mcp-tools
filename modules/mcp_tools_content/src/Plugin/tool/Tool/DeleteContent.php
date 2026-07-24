<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_content\Plugin\tool\Tool;

use Drupal\mcp_tools_content\Service\ContentService;
use Drupal\mcp_tools_translate\Service\ContentTranslationAccess;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp_tools\Tool\McpToolsToolBase;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinition;

/**
 * Tool plugin implementation.
 */
#[Tool(
  id: 'mcp_delete_content',
  label: new TranslatableMarkup('Delete Content'),
  description: new TranslatableMarkup('Delete a node, or a single language version (translation) of a node. Deleting the whole node requires the explicit confirm_delete_all flag and removes ALL translations at once (soft-deleted to the trash when the Trash module is enabled). Passing language deletes ONLY that translation and keeps every other language intact.'),
  operation: ToolOperation::Write,
  destructive: TRUE,
  input_definitions: [
    'nid' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Node ID'),
      description: new TranslatableMarkup('The node ID to delete (or to delete a translation from).'),
      required: TRUE,
    ),
    'language' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Language'),
      description: new TranslatableMarkup('Language code of the single translation to delete (e.g. "it"). All other language versions of the node are kept. The source language of a node cannot be deleted while translations exist. Mutually exclusive with confirm_delete_all.'),
      required: FALSE,
    ),
    'confirm_delete_all' => new InputDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Confirm full delete'),
      description: new TranslatableMarkup("Required to delete the ENTIRE node including every translation. Without this flag (and without language), the call is rejected and lists the node's language versions."),
      required: FALSE,
    ),
  ],
  output_definitions: [
    'nid' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Node ID'),
      description: new TranslatableMarkup('The affected node ID.'),
    ),
    'title' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Title'),
      description: new TranslatableMarkup('The title of the affected node.'),
    ),
    'language' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Deleted language'),
      description: new TranslatableMarkup('Language code of the deleted translation (single-translation delete only).'),
    ),
    'languages_deleted' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Deleted languages'),
      description: new TranslatableMarkup('All language codes removed by this call.'),
    ),
    'remaining_languages' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Remaining languages'),
      description: new TranslatableMarkup('Language codes still present on the node after the call.'),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Result Message'),
      description: new TranslatableMarkup('Confirmation message naming the affected language version(s).'),
    ),
  ],
)]
class DeleteContent extends McpToolsToolBase {

  protected const MCP_CATEGORY = 'content';


  /**
   * The content service.
   *
   * @var \Drupal\mcp_tools_content\Service\ContentService
   */
  protected ContentService $contentService;

  /**
   * Translation access checker, when mcp_tools_translate is installed.
   *
   * Optional soft dependency: single-translation deletes are gated by the
   * same per-bundle translate permission that created the translation. The
   * nullable type is safe without the submodule — the class is only
   * autoloaded when the container actually provides the service.
   *
   * @var \Drupal\mcp_tools_translate\Service\ContentTranslationAccess|null
   */
  protected ?ContentTranslationAccess $translationAccess = NULL;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->contentService = $container->get('mcp_tools_content.content');
    if ($container->has('mcp_tools_translate.access')) {
      $instance->translationAccess = $container->get('mcp_tools_translate.access');
    }
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeLegacy(array $input): array {
    $nid = $input['nid'] ?? 0;
    $language = trim((string) ($input['language'] ?? ''));
    $confirmDeleteAll = (bool) ($input['confirm_delete_all'] ?? FALSE);

    if (empty($nid)) {
      return ['success' => FALSE, 'error' => 'Node ID (nid) is required.'];
    }

    if ($language !== '' && $this->translationAccess !== NULL) {
      $node = $this->translationAccess->loadNode((int) $nid);
      if ($node !== NULL) {
        $denied = $this->translationAccess->checkTranslateAccess($node);
        if ($denied !== NULL) {
          return $denied;
        }
      }
    }

    return $this->contentService->deleteContent(
      (int) $nid,
      $language === '' ? NULL : $language,
      $confirmDeleteAll,
    );
  }

}
