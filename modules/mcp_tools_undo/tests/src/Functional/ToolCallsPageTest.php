<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_undo\Functional;

use Drupal\node\Entity\Node;
use Drupal\Tests\BrowserTestBase;

/**
 * A person sees what tool calls changed, and undoes one from Reports.
 */
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_undo')]
final class ToolCallsPageTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'tool', 'mcp_tools', 'mcp_tools_undo'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The page lists the call, and its Undo puts the content back.
   */
  public function testAPersonUndoesACall(): void {
    $this->drupalCreateContentType(['type' => 'page']);
    $node = Node::create(['type' => 'page', 'title' => 'Before']);
    $node->save();
    $context = $this->container->get('mcp_tools.tool_call_context');
    $context->enter();
    $node->setTitle('Changed by a tool');
    $node->setNewRevision(TRUE);
    $node->save();
    $context->leave();

    $this->drupalLogin($this->drupalCreateUser(['view mcp tool calls']));
    $this->drupalGet('admin/reports/mcp-tool-calls');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('changed node ' . $node->id());
    $this->assertSession()->linkNotExists('Undo', 'Seeing is not undoing.');

    $this->drupalLogin($this->drupalCreateUser(['undo mcp tool calls', 'access content']));
    $this->drupalGet('admin/reports/mcp-tool-calls');
    $this->clickLink('Undo');
    $this->submitForm([], 'Undo');
    $this->assertSession()->pageTextContains('Undone: 1 changes');
    $this->container->get('entity_type.manager')->getStorage('node')->resetCache();
    $this->assertSame('Before', Node::load($node->id())->label());

    $this->drupalLogin($this->drupalCreateUser([]));
    $this->drupalGet('admin/reports/mcp-tool-calls');
    $this->assertSession()->statusCodeEquals(403);
  }

}
