<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_content\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\filter\Entity\FilterFormat;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * The answer says what a text format does not show of a text.
 *
 * A table, a heading or a whole format was lost and the answer was plain
 * success: ten of fourteen kinds of text in Basic HTML.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_content')]
final class TextLossKernelTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'field',
    'text',
    'filter',
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
    $this->installSchema('dblog', ['watchdog']);
    $this->installConfig(['system', 'filter', 'node', 'mcp_tools']);
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
    FieldStorageConfig::create(['field_name' => 'field_text', 'entity_type' => 'node', 'type' => 'text_long'])->save();
    FieldConfig::create(['field_name' => 'field_text', 'entity_type' => 'node', 'bundle' => 'page', 'label' => 'Text'])->save();
    FilterFormat::create([
      'format' => 'basic_html',
      'name' => 'Basic',
      'filters' => ['filter_html' => ['status' => TRUE, 'settings' => ['allowed_html' => '<p> <a href hreflang> <h2 id> <strong>', 'filter_html_help' => FALSE, 'filter_html_nofollow' => FALSE]]],
    ])->save();
    FilterFormat::create(['format' => 'full_html', 'name' => 'Full'])->save();
    $this->createUser();
    $this->setUpCurrentUser([], ['access content', 'create page content', 'edit any page content', 'use text format basic_html']);
    $this->config('mcp_tools.settings')->set('access.allowed_scopes', ['read', 'write'])->set('access.read_only_mode', FALSE)->save();
    $this->container->get('mcp_tools.access_manager')->setScopes(['read', 'write']);
  }

  /**
   * Create and update say it; what is let through is not mentioned.
   */
  public function testTheAnswerSaysWhatIsLost(): void {
    $content = $this->container->get('mcp_tools_content.content');
    $message = static fn(array $result): string => (string) ($result['data']['message'] ?? $result['error']);

    $said = $message($content->createContent('page', 'A', ['text' => '<h1>Top</h1><table><tr><td>x</td></tr></table><p class="lead" style="color:red">y</p>']));
    foreach (["'basic_html'", '<h1>', '<table>', '<td>', 'class on <p>', 'style on <p>', 'field_text'] as $part) {
      $this->assertStringContainsString($part, $said);
    }
    $this->assertStringNotContainsString('<tbody>', $said);

    $fine = $content->createContent('page', 'B', ['text' => '<h2 id="a">Head</h2><p>1 < 2 <a href="/x">in</a> <strong>b</strong></p>']);
    $this->assertStringNotContainsString('text format', $message($fine));

    $said = $message($content->updateContent((int) $fine['data']['nid'], ['field_text' => ['value' => '<iframe src="/x"></iframe>', 'format' => 'full_html']]));
    $this->assertStringContainsString("'full_html' cannot be used here", $said);
    $this->assertStringContainsString('<iframe>', $said);

    // A title change says nothing about a text it did not give.
    $this->assertStringNotContainsString('text format', $message($content->updateContent((int) $fine['data']['nid'], ['title' => 'C'])));
  }

  /**
   * A format without a list of tags loses nothing.
   */
  public function testFormatThatLetsAllThroughSaysNothing(): void {
    $this->setUpCurrentUser([], ['access content', 'create page content', 'use text format full_html']);
    $result = $this->container->get('mcp_tools_content.content')->createContent('page', 'D', ['text' => ['value' => '<table><tr><td>x</td></tr></table>', 'format' => 'full_html']]);
    $this->assertTrue($result['success'], (string) ($result['error'] ?? ''));
    $this->assertStringNotContainsString('text format', $result['data']['message']);
  }

}
