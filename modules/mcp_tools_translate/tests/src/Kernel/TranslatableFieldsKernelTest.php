<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_translate\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * What the translation tools take for the text of a node.
 *
 * Found on a site with three languages, through the real server:
 * - Only fields named "field_..." and the title were offered for
 *   translation. The body field of core, on nearly every site, was left
 *   out and not asked for, so a translation kept the source body.
 * - A text given with a format the site does not have was saved with it,
 *   and Drupal shows such a text as nothing.
 * - A content type that is not enabled for translation was translated all
 *   the same.
 * - A string longer than its field allows was cut without a word.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_translate')]
final class TranslatableFieldsKernelTest extends KernelTestBase {

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
    'language',
    'content_translation',
    'dblog',
    'update',
    'tool',
    'mcp_tools',
    'mcp_tools_content',
    'mcp_tools_translate',
  ];

  /**
   * The translation service.
   *
   * @var \Drupal\mcp_tools_translate\Service\ContentTranslationService
   */
  private $service;

  /**
   * Whether the site has the field type text_with_summary.
   *
   * Drupal 12 moved it out of core, to the module of that name. Without it
   * the body is a long formatted text, and the summary parts are skipped.
   */
  private bool $summary;

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
    $this->installConfig(['system', 'field', 'filter', 'node', 'language', 'mcp_tools']);

    ConfigurableLanguage::createFromLangcode('fr')->save();
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
    // Translation is on for articles, and not for pages.
    $this->container->get('content_translation.manager')->setEnabled('node', 'article', TRUE);

    // The body field of core, and a field whose name has another prefix.
    // Drupal 10 brings the storage of the body field with the node module.
    $this->summary = $this->container->get('plugin.manager.field.field_type')->hasDefinition('text_with_summary');
    if (FieldStorageConfig::loadByName('node', 'body') === NULL) {
      FieldStorageConfig::create(['field_name' => 'body', 'entity_type' => 'node', 'type' => $this->summary ? 'text_with_summary' : 'text_long'])->save();
    }
    FieldStorageConfig::create(['field_name' => 'extra_note', 'entity_type' => 'node', 'type' => 'string'])->save();
    FieldConfig::create(['field_name' => 'body', 'entity_type' => 'node', 'bundle' => 'article', 'translatable' => TRUE])->save();
    FieldConfig::create(['field_name' => 'body', 'entity_type' => 'node', 'bundle' => 'page', 'translatable' => TRUE])->save();
    FieldConfig::create(['field_name' => 'extra_note', 'entity_type' => 'node', 'bundle' => 'article', 'translatable' => TRUE])->save();

    // The first user is uid 1 and passes every permission check.
    $this->createUser();
    // A translator as Drupal's forms want one (tools follow entity access).
    $this->setUpCurrentUser([], ['translate article node', 'translate any entity', 'create content translations', 'update content translations']);
    $this->config('mcp_tools.settings')
      ->set('access.allowed_scopes', ['read', 'write'])
      ->set('access.read_only_mode', FALSE)
      ->save();
    $this->container->get('mcp_tools.access_manager')->setScopes(['read', 'write']);
    $this->service = $this->container->get('mcp_tools_translate.translation');
  }

  /**
   * An English article with a body, a summary and a note.
   */
  private function article(): NodeInterface {
    $node = Node::create([
      'type' => 'article',
      'title' => 'EN title',
      'langcode' => 'en',
      'body' => ['value' => 'EN body', 'format' => 'plain_text'] + ($this->summary ? ['summary' => 'EN summary'] : []),
      'extra_note' => 'EN note',
    ]);
    $node->save();
    return $node;
  }

  /**
   * The node as stored.
   */
  private function reload(int $nid): NodeInterface {
    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $storage->resetCache([$nid]);
    return $storage->load($nid);
  }

  /**
   * The body and a field with another prefix are offered and asked for.
   */
  public function testTheBodyIsPartOfTheTranslation(): void {
    $nid = (int) $this->article()->id();

    $content = $this->service->getTranslatableContent($nid);
    $this->assertTrue($content['success'], json_encode($content));
    $fields = $content['data']['fields'];
    $this->assertArrayHasKey('body', $fields, 'The body is not offered for translation: ' . implode(', ', array_keys($fields)));
    $this->assertSame('EN body', $fields['body']['value']);
    if ($this->summary) {
      $this->assertSame('EN summary', $fields['body']['summary']);
    }
    $this->assertArrayHasKey('extra_note', $fields);
    $this->assertContains('body', $content['data']['reading_order']);

    // A translation without the body is not complete.
    $partial = $this->service->translateContent($nid, 'fr', ['title' => 'Titre', 'extra_note' => 'Note']);
    $this->assertFalse($partial['success'], 'A translation without the body was taken.');
    $this->assertContains('body', $partial['missing']['node_fields']);
    $this->assertFalse($this->reload($nid)->hasTranslation('fr'));

    // With all of it, each language has its own text.
    $full = $this->service->translateContent($nid, 'fr', [
      'title' => 'Titre',
      'body' => ['value' => 'Corps'] + ($this->summary ? ['summary' => 'Resume'] : []),
      'extra_note' => 'Note',
    ]);
    $this->assertTrue($full['success'], json_encode($full));
    $node = $this->reload($nid);
    $this->assertSame('Corps', $node->getTranslation('fr')->get('body')->value);
    $this->assertSame('Note', $node->getTranslation('fr')->get('extra_note')->value);
    $this->assertSame('EN body', $node->getTranslation('en')->get('body')->value);
    if ($this->summary) {
      $this->assertSame('Resume', $node->getTranslation('fr')->get('body')->summary);
      $this->assertSame('EN summary', $node->getTranslation('en')->get('body')->summary);
    }
  }

  /**
   * A summary that is not translated does not stay in the source language.
   */
  public function testASummaryIsNotLeftInTheSourceLanguage(): void {
    if (!$this->summary) {
      $this->markTestSkipped('This site has no text_with_summary field type (Drupal 12 without the module of that name).');
    }
    $nid = (int) $this->article()->id();
    $result = $this->service->translateContent($nid, 'fr', ['title' => 'Titre', 'body' => 'Corps', 'extra_note' => 'Note']);
    $this->assertTrue($result['success'], json_encode($result));
    $french = $this->reload($nid)->getTranslation('fr');
    $this->assertSame('Corps', $french->get('body')->value);
    $this->assertNotSame('EN summary', $french->get('body')->summary, 'The French version carries the English summary.');
    $this->assertStringContainsString('summary', $result['data']['message']);
  }

  /**
   * A translation is saved with a text format the site has.
   */
  public function testATranslationGetsAFormatTheSiteHas(): void {
    $nid = (int) $this->article()->id();
    $result = $this->service->translateContent($nid, 'fr', [
      'title' => 'Titre',
      'body' => ['value' => 'Corps', 'format' => 'does_not_exist'],
      'extra_note' => 'Note',
    ]);
    $this->assertTrue($result['success'], json_encode($result));
    $this->assertSame('plain_text', $this->reload($nid)->getTranslation('fr')->get('body')->format);

    // The same for an update of a translation.
    $updated = $this->service->updateTranslation($nid, 'fr', ['body' => ['value' => 'Corps 2', 'format' => 'nope']]);
    $this->assertTrue($updated['success'], json_encode($updated));
    $french = $this->reload($nid)->getTranslation('fr');
    $this->assertSame('Corps 2', $french->get('body')->value);
    $this->assertSame('plain_text', $french->get('body')->format);
  }

  /**
   * A content type that is not enabled for translation is not translated.
   */
  public function testATypeThatIsNotTranslatableIsRefused(): void {
    $page = Node::create(['type' => 'page', 'title' => 'A page', 'langcode' => 'en', 'body' => ['value' => 'Page body', 'format' => 'plain_text']]);
    $page->save();
    $nid = (int) $page->id();

    $result = $this->service->translateContent($nid, 'fr', ['title' => 'Une page', 'body' => 'Corps']);
    $this->assertFalse($result['success'], 'A page was translated, and pages are not enabled for translation.');
    $this->assertStringContainsString('not enabled for translation', $result['error']);
    $this->assertFalse($this->reload($nid)->hasTranslation('fr'));

    $content = $this->service->getTranslatableContent($nid);
    $this->assertFalse($content['success']);
    $this->assertStringContainsString('not enabled for translation', $content['error']);
  }

  /**
   * A string that does not fit its field is refused, not cut.
   */
  public function testATextThatDoesNotFitIsRefused(): void {
    $nid = (int) $this->article()->id();
    $long = str_repeat('Lang ', 60);
    $this->assertGreaterThan(255, mb_strlen($long));

    $result = $this->service->translateContent($nid, 'fr', ['title' => $long, 'body' => 'Corps', 'extra_note' => 'Note']);
    $this->assertFalse($result['success'], 'A title of 300 characters was taken and cut to 255.');
    $this->assertStringContainsString('title', $result['error']);
    $this->assertStringContainsString('255', $result['error']);
    $this->assertFalse($this->reload($nid)->hasTranslation('fr'));

    // An update of a translation is held to the same.
    $this->assertTrue($this->service->translateContent($nid, 'fr', ['title' => 'Titre', 'body' => 'Corps', 'extra_note' => 'Note'])['success']);
    $updated = $this->service->updateTranslation($nid, 'fr', ['title' => $long]);
    $this->assertFalse($updated['success']);
    $this->assertSame('Titre', $this->reload($nid)->getTranslation('fr')->getTitle());
  }

}
