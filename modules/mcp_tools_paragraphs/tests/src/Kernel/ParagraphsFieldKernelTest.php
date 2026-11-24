<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_paragraphs\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * A field that holds paragraphs, added by the field tools, works.
 *
 * No tool could add one: a model could make paragraph types but not put
 * them on a content type or in another paragraph type, and the README
 * said to use a plain entity_reference, which Paragraphs does not support.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_paragraphs')]
final class ParagraphsFieldKernelTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system', 'user', 'field', 'text', 'filter', 'file', 'node', 'dblog', 'update',
    'entity_reference_revisions', 'paragraphs',
    'tool', 'mcp_tools', 'mcp_tools_structure', 'mcp_tools_paragraphs', 'mcp_tools_content', 'mcp_tools_batch',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('paragraph');
    $this->installSchema('dblog', ['watchdog']);
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system', 'field', 'filter', 'node', 'mcp_tools']);
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
    ParagraphsType::create(['id' => 'text', 'label' => 'Text'])->save();
    ParagraphsType::create(['id' => 'accordion', 'label' => 'Accordion'])->save();
    // An administrator: the content tools follow Drupal's access.
    $this->setUpCurrentUser([], [], TRUE);
    $this->container->get('mcp_tools.access_manager')->setScopes(['read', 'write']);
  }

  /**
   * On a content type: the right field, and a node holds a paragraph in it.
   */
  public function testOnAContentType(): void {
    $fields = $this->container->get('mcp_tools_structure.field');
    $this->assertContains('paragraphs', array_column($fields->getFieldTypes()['types'], 'id'));

    // A target_type given is not taken: the field holds paragraphs.
    $result = $fields->addField('node', 'page', 'sections', 'paragraphs', 'Sections', ['target_bundles' => ['text'], 'target_type' => 'node']);
    $this->assertTrue($result['success'], (string) ($result['error'] ?? ''));

    $storage = FieldStorageConfig::load('node.field_sections');
    $this->assertSame('entity_reference_revisions', $storage->getType());
    $this->assertSame('paragraph', $storage->getSetting('target_type'));
    $this->assertSame(-1, $storage->getCardinality());
    $field = FieldConfig::load('node.page.field_sections');
    $this->assertSame('default:paragraph', $field->getSetting('handler'));
    $this->assertSame(['text' => 'text'], $field->getSetting('handler_settings')['target_bundles']);
    $display = $this->container->get('entity_display.repository');
    $this->assertSame('paragraphs', $display->getFormDisplay('node', 'page')->getComponent('field_sections')['type'] ?? NULL);
    $this->assertSame('entity_reference_revisions_entity_view', $display->getViewDisplay('node', 'page')->getComponent('field_sections')['type'] ?? NULL);

    $paragraph = Paragraph::create(['type' => 'text']);
    $node = Node::create(['type' => 'page', 'title' => 'Home', 'field_sections' => [$paragraph]]);
    $node->save();
    $node = Node::load($node->id());
    $held = $node->get('field_sections')->referencedEntities();
    $this->assertCount(1, $held);
    $this->assertSame('text', $held[0]->bundle());
    // Paragraphs keeps track of what holds each paragraph.
    $this->assertSame($node->id(), $held[0]->getParentEntity()->id());
  }

  /**
   * In a paragraph type: paragraphs in paragraphs.
   */
  public function testInAParagraphType(): void {
    $result = $this->container->get('mcp_tools_paragraphs.paragraphs')->addField('accordion', 'items', 'paragraphs', ['target_bundles' => ['text']]);
    $this->assertTrue($result['success'], (string) ($result['error'] ?? ''));
    $this->assertSame('entity_reference_revisions', FieldStorageConfig::load('paragraph.field_items')->getType());

    $outer = Paragraph::create(['type' => 'accordion', 'field_items' => [Paragraph::create(['type' => 'text'])]]);
    $outer->save();
    $this->assertSame('text', Paragraph::load($outer->id())->get('field_items')->entity->bundle());
  }

  /**
   * A paragraph type that does not exist is refused, with the ones there are.
   */
  public function testAnUnknownParagraphTypeIsRefused(): void {
    $result = $this->container->get('mcp_tools_structure.field')->addField('node', 'page', 'sections', 'paragraphs', 'Sections', ['target_bundles' => ['hero']]);
    $this->assertFalse($result['success']);
    $this->assertStringContainsString('no bundle hero', $result['error']);
    $this->assertStringContainsString('accordion', $result['error']);
    $this->assertStringContainsString('text', $result['error']);
    $this->assertNull(FieldStorageConfig::load('node.field_sections'));
  }

  /**
   * The batch tool fills a paragraphs field as the content tool does.
   *
   * Only the content tool had the code: given paragraphs inline, the batch
   * tool passed the items on as they were.
   */
  public function testTheBatchToolFillsAParagraphsField(): void {
    $fields = $this->container->get('mcp_tools_structure.field');
    $this->assertTrue($fields->addField('node', 'page', 'sections', 'paragraphs', 'Sections')['success']);

    $result = $this->container->get('mcp_tools_batch.batch')->createMultipleContent('page', [
      ['title' => 'Batch', 'fields' => ['field_sections' => [['type' => 'text'], ['type' => 'accordion']]]],
    ]);
    $this->assertTrue($result['success'], json_encode($result));
    $node = Node::load($result['data']['created'][0]['nid']);
    $this->assertSame(['text', 'accordion'], array_map(fn($p) => $p->bundle(), $node->get('field_sections')->referencedEntities()));
  }

  /**
   * A field a paragraph's type does not have is refused, and nothing made.
   */
  public function testAnUnknownFieldInAParagraphIsRefused(): void {
    $this->container->get('mcp_tools_structure.field')->addField('node', 'page', 'sections', 'paragraphs', 'Sections');
    $result = $this->container->get('mcp_tools_content.content')->createContent('page', 'Typo', ['field_sections' => [['type' => 'text', 'field_titel' => 'x']]]);
    $this->assertFalse($result['success'], 'A field the paragraph does not have was dropped and the call said success.');
    $this->assertStringContainsString("has no field field_titel", $result['error']);
    $this->assertSame([], Node::loadMultiple());
  }

}
