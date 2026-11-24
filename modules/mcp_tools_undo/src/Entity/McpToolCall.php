<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_undo\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp_tools_undo\Form\UndoToolCallForm;
use Drupal\mcp_tools_undo\McpToolCallAccessControlHandler;
use Drupal\mcp_tools_undo\McpToolCallListBuilder;

/**
 * One MCP tool call that changed something, and what it changed.
 *
 * It is the record undo works from, and what a person sees under Reports.
 * A call does not change after it ran; only its state does (applied,
 * undone), so it has no revisions.
 *
 * @ContentEntityType(
 *   id = "mcp_tool_call",
 *   label = @Translation("MCP tool call"),
 *   label_collection = @Translation("MCP tool calls"),
 *   handlers = {
 *     "list_builder" = "Drupal\mcp_tools_undo\McpToolCallListBuilder",
 *     "access" = "Drupal\mcp_tools_undo\McpToolCallAccessControlHandler",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "undo" = "Drupal\mcp_tools_undo\Form\UndoToolCallForm",
 *     },
 *   },
 *   base_table = "mcp_tool_call",
 *   admin_permission = "undo mcp tool calls",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "label" = "summary",
 *   },
 *   links = {
 *     "collection" = "/admin/reports/mcp-tool-calls",
 *     "undo-form" = "/admin/reports/mcp-tool-calls/{mcp_tool_call}/undo",
 *   },
 * )
 *
 * The annotation is for Drupal 10, which has no entity type attribute; the
 * attribute is for Drupal 11, which reads it first.
 */
#[ContentEntityType(
  id: 'mcp_tool_call',
  label: new TranslatableMarkup('MCP tool call'),
  label_collection: new TranslatableMarkup('MCP tool calls'),
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
    'label' => 'summary',
  ],
  handlers: [
    'list_builder' => McpToolCallListBuilder::class,
    'access' => McpToolCallAccessControlHandler::class,
    'views_data' => 'Drupal\views\EntityViewsData',
    'form' => [
      'undo' => UndoToolCallForm::class,
    ],
  ],
  links: [
    'collection' => '/admin/reports/mcp-tool-calls',
    'undo-form' => '/admin/reports/mcp-tool-calls/{mcp_tool_call}/undo',
  ],
  admin_permission: 'undo mcp tool calls',
  base_table: 'mcp_tool_call',
)]
class McpToolCall extends ContentEntityBase {

  /**
   * The call has been applied, and can be undone.
   */
  public const APPLIED = 'applied';

  /**
   * The call has been undone.
   */
  public const UNDONE = 'undone';

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields['call_id'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Call ID'))
      ->setDescription(new TranslatableMarkup("The tool call's correlation id."))
      ->setRequired(TRUE)
      ->setSetting('max_length', 64)
      ->addConstraint('UniqueField');
    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('User'))
      ->setDescription(new TranslatableMarkup('Whose call it was.'))
      ->setSetting('target_type', 'user');
    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(new TranslatableMarkup('Time'));
    $fields['state'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('State'))
      ->setDescription(new TranslatableMarkup('applied or undone.'))
      ->setSetting('max_length', 16)
      ->setDefaultValue(self::APPLIED);
    $fields['undone_by'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Undone by'))
      ->setDescription(new TranslatableMarkup('The call, or "a person", that undid it.'))
      ->setSetting('max_length', 64);
    $fields['summary'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Summary'))
      ->setDescription(new TranslatableMarkup('What the call changed, in a few words.'))
      ->setSetting('max_length', 255);
    $fields['changes'] = BaseFieldDefinition::create('map')
      ->setLabel(new TranslatableMarkup('Changes'))
      ->setDescription(new TranslatableMarkup('Each change, with what undo needs to put it back.'));
    return $fields;
  }

  /**
   * The changes, oldest first.
   */
  public function getChanges(): array {
    // The list is kept under one key: a map item reads a list given to it
    // straight as its own properties.
    return array_values((array) ($this->get('changes')->first()?->getValue()['list'] ?? []));
  }

  /**
   * Sets the changes, and the summary from them.
   */
  public function setChanges(array $changes): static {
    $this->set('changes', [['list' => array_values($changes)]]);
    $words = [];
    foreach ($changes as $change) {
      $words[] = $change['op'] . ' ' . ($change['kind'] === 'config' ? $change['name'] : "{$change['entity_type']} {$change['id']}" . (($change['label'] ?? '') !== '' ? " ({$change['label']})" : ''));
    }
    $summary = implode(', ', $words);
    $this->set('summary', mb_strlen($summary) > 250 ? mb_substr($summary, 0, 249) . '…' : $summary);
    return $this;
  }

}
