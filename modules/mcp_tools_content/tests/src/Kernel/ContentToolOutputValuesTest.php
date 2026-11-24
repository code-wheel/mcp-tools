<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_content\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\filter\Entity\FilterFormat;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\mcp_tools\Traits\DeclaredOutputTypesTrait;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Executes content tool plugins and reads their typed output values.
 *
 * Regression coverage for conditional outputs: Tool API's getOutputValues()
 * throws for declared-but-unset outputs, which broke external consumers
 * (e.g. mcp_server_tool_bridge) after the tool had already executed.
 * McpToolsToolBase backfills unset declared outputs with NULL.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_content')]
class ContentToolOutputValuesTest extends KernelTestBase {

  use DeclaredOutputTypesTrait;

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'text',
    'node',
    'dblog',
    'update',
    'tool',
    'mcp_tools',
    'mcp_tools_content',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('user', ['users_data']);
    $this->installSchema('dblog', ['watchdog']);
    $this->installConfig(['system', 'field', 'filter', 'node', 'mcp_tools']);

    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();

    // An editor who may create and edit content (tools follow entity
    // access).
    // Publishing is for 'administer nodes', as on the node form.
    $this->setUpCurrentUser([], ['bypass node access', 'administer nodes']);
    $this->config('mcp_tools.settings')
      ->set('access.allowed_scopes', ['read', 'write'])
      ->set('access.read_only_mode', FALSE)
      ->save();
    $this->container->get('mcp_tools.access_manager')->setScopes(['read', 'write']);
  }

  /**
   * A confirmed full delete exposes all declared outputs without throwing.
   */
  public function testDeleteContentPluginOutputValues(): void {
    $node = Node::create(['type' => 'article', 'title' => 'Doomed', 'langcode' => 'en']);
    $node->save();

    $tool = $this->container->get('plugin.manager.tool')->createInstance('mcp_delete_content');
    $tool->setInputValue('nid', (int) $node->id());
    $tool->setInputValue('confirm_delete_all', TRUE);
    $tool->execute();

    $result = $tool->getResult();
    $this->assertTrue($result->isSuccess(), (string) $result->getMessage());
    $this->assertOutputsHaveDeclaredTypes($tool, (string) $tool->getPluginId());

    $values = $tool->getOutputValues();
    $this->assertNull($values['language'], 'The single-translation output is NULL on a full delete.');
    $this->assertSame(['en'], $values['languages_deleted']);
    $this->assertSame([], $values['remaining_languages']);
  }

  /**
   * A new item says whether it is published.
   *
   * The status input said "Defaults to true". The code saves a draft when
   * no status is given, and the result did not carry the status at all. A
   * model that was asked for a published item left the input out, read
   * "created successfully" and reported a published item.
   */
  public function testCreateContentSaysWhetherTheItemIsPublished(): void {
    $manager = $this->container->get('plugin.manager.tool');

    $tool = $manager->createInstance('mcp_create_content');
    $tool->setInputValue('type', 'article');
    $tool->setInputValue('title', 'No status given');
    $tool->execute();
    $this->assertTrue($tool->getResult()->isSuccess(), (string) $tool->getResult()->getMessage());
    $this->assertOutputsHaveDeclaredTypes($tool, (string) $tool->getPluginId());
    $values = $tool->getOutputValues();
    $this->assertFalse(Node::load($values['nid'])->isPublished());
    $this->assertSame('draft', $values['status']);
    $this->assertStringContainsString('unpublished draft', $values['message']);
    $this->assertStringContainsString('mcp_publish_content', $values['message']);

    $tool = $manager->createInstance('mcp_create_content');
    $tool->setInputValue('type', 'article');
    $tool->setInputValue('title', 'Published');
    $tool->setInputValue('status', TRUE);
    $tool->execute();
    $values = $tool->getOutputValues();
    $this->assertTrue(Node::load($values['nid'])->isPublished());
    $this->assertSame('published', $values['status']);
    $this->assertStringContainsString('published', $values['message']);
    $this->assertStringNotContainsString('draft', $values['message']);

    // What the input says about its default is what the code does.
    $description = (string) $tool->getPluginDefinition()->getInputDefinitions()['status']->getDescription();
    $this->assertStringNotContainsString('Defaults to true', $description);
    $this->assertStringContainsString('draft', $description);
  }

  /**
   * An item made with a title and nothing else says so.
   *
   * Asked for an item with a body text, a model sent the title and no
   * field values, read "created and published" and reported the body as
   * set (fast-agent with gpt-oss:20b, 2026-11-21, three of seven tries).
   * The result now says that only the title was set and names the fields
   * the content type has, so the model can add them and not create the
   * item a second time.
   */
  public function testCreateContentSaysWhenOnlyTheTitleWasSet(): void {
    $this->addArticleFields();
    $manager = $this->container->get('plugin.manager.tool');

    $tool = $manager->createInstance('mcp_create_content');
    $tool->setInputValue('type', 'article');
    $tool->setInputValue('title', 'Title only');
    $tool->setInputValue('status', TRUE);
    $tool->execute();
    $message = $tool->getOutputValues()['message'];
    $this->assertStringContainsString('created and published', $message);
    $this->assertStringContainsString('Only the title was set', $message);
    $this->assertStringContainsString('body, field_subtitle', $message);
    $this->assertStringContainsString('mcp_update_content', $message);

    // The example in the input's description is one that works: a text
    // field takes a plain string. And an item with a field value does not
    // get the note, whatever else is left empty.
    $description = (string) $tool->getPluginDefinition()->getInputDefinitions()['fields']->getDescription();
    $this->assertStringContainsString('{"body": "The text"}', $description);
    $tool = $manager->createInstance('mcp_create_content');
    $tool->setInputValue('type', 'article');
    $tool->setInputValue('title', 'With a body');
    $tool->setInputValue('fields', ['body' => 'The text']);
    $tool->execute();
    $values = $tool->getOutputValues();
    $this->assertSame('The text', Node::load($values['nid'])->get('body')->value);
    $this->assertStringNotContainsString('Only the title was set', $values['message']);
  }

  /**
   * A text is saved with a format the site has.
   *
   * A plain string for a text field was saved with the format "basic_html",
   * whether the site has it or not. This test site has only the fallback
   * format, like a site of the minimal profile. Drupal renders a text
   * whose format is missing as empty, so the item was there and its body
   * showed nothing. The same for a format a model names because our own
   * description shows it.
   */
  public function testTextIsSavedInAFormatTheSiteHas(): void {
    $this->addArticleFields();
    $this->assertNull(FilterFormat::load('basic_html'), 'The test site has no basic_html.');
    $manager = $this->container->get('plugin.manager.tool');

    $bodies = [
      'a plain string' => 'The text',
      'a format the site does not have' => ['value' => 'The text', 'format' => 'basic_html'],
    ];
    foreach ($bodies as $case => $body) {
      $tool = $manager->createInstance('mcp_create_content');
      $tool->setInputValue('type', 'article');
      $tool->setInputValue('title', $case);
      $tool->setInputValue('fields', ['body' => $body]);
      $tool->execute();
      $this->assertTrue($tool->getResult()->isSuccess(), $case . ': ' . $tool->getResult()->getMessage());
      $item = Node::load($tool->getOutputValues()['nid'])->get('body');
      $this->assertSame('The text', $item->value, $case);
      $this->assertNotNull(FilterFormat::load((string) $item->format), $case . ': saved with the format "' . $item->format . '", which this site does not have.');
      // What a visitor gets: the text, not nothing.
      $this->assertStringContainsString('The text', (string) $item->processed, $case);
    }

    // The same through an update.
    $tool = $manager->createInstance('mcp_update_content');
    $tool->setInputValue('nid', (int) Node::load(1)->id());
    $tool->setInputValue('updates', ['body' => 'Another text']);
    $tool->execute();
    $this->assertTrue($tool->getResult()->isSuccess(), (string) $tool->getResult()->getMessage());
    $this->container->get('entity_type.manager')->getStorage('node')->resetCache();
    $item = Node::load(1)->get('body');
    $this->assertSame('Another text', $item->value);
    $this->assertNotNull(FilterFormat::load((string) $item->format));

    // A site that has basic_html, and a user who may not use it, gets the
    // fallback format: Drupal would refuse basic_html for that user.
    FilterFormat::create(['format' => 'basic_html', 'name' => 'Basic HTML'])->save();
    $tool = $manager->createInstance('mcp_create_content');
    $tool->setInputValue('type', 'article');
    $tool->setInputValue('title', 'Not for me');
    $tool->setInputValue('fields', ['body' => 'The text']);
    $tool->execute();
    $this->assertTrue($tool->getResult()->isSuccess(), (string) $tool->getResult()->getMessage());
    $this->assertSame('plain_text', Node::load($tool->getOutputValues()['nid'])->get('body')->format);

    // A user who may use it gets it, as before.
    $this->setUpCurrentUser([], ['bypass node access', 'administer nodes', 'use text format basic_html']);
    $tool = $manager->createInstance('mcp_create_content');
    $tool->setInputValue('type', 'article');
    $tool->setInputValue('title', 'With basic_html');
    $tool->setInputValue('fields', ['body' => 'The text']);
    $tool->execute();
    $this->assertSame('basic_html', Node::load($tool->getOutputValues()['nid'])->get('body')->format);
  }

  /**
   * Gives the article a body and a subtitle.
   */
  private function addArticleFields(): void {
    foreach (['body' => 'text_long', 'field_subtitle' => 'string'] as $name => $type) {
      // On Drupal 10 the node module comes with the storage of the body.
      if (!FieldStorageConfig::loadByName('node', $name)) {
        FieldStorageConfig::create(['entity_type' => 'node', 'field_name' => $name, 'type' => $type])->save();
      }
      FieldConfig::create(['entity_type' => 'node', 'field_name' => $name, 'bundle' => 'article', 'label' => $name])->save();
    }
  }

  /**
   * A source-language update exposes all declared outputs without throwing.
   */
  public function testUpdateContentPluginOutputValues(): void {
    $node = Node::create(['type' => 'article', 'title' => 'Before', 'langcode' => 'en']);
    $node->save();

    $tool = $this->container->get('plugin.manager.tool')->createInstance('mcp_update_content');
    $tool->setInputValue('nid', (int) $node->id());
    $tool->setInputValue('updates', ['title' => 'After']);
    $tool->execute();

    $result = $tool->getResult();
    $this->assertTrue($result->isSuccess(), (string) $result->getMessage());
    $this->assertOutputsHaveDeclaredTypes($tool, (string) $tool->getPluginId());

    $values = $tool->getOutputValues();
    $this->assertSame('After', $values['title']);
    $this->assertSame('en', $values['language']);
    $this->assertNull($values['paragraph_fields_updated'], 'Translate-only output is NULL on a source-language update.');
    $this->assertNull($values['untouched_translatable_fields'], 'Translate-only output is NULL on a source-language update.');
  }

}
