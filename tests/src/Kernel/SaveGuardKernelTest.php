<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\image\Entity\ImageStyle;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\User;

/**
 * In a tool call, an entity is saved only if it passes Drupal's validation.
 *
 * The tools saved without it: an email "nope" was stored for a user, an
 * image effect with no width for a style, a title longer than its field.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
final class SaveGuardKernelTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'field', 'text', 'filter', 'node', 'image', 'file', 'dblog', 'update', 'tool', 'mcp_tools'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('file');
    $this->installSchema('dblog', ['watchdog']);
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system', 'filter', 'node', 'image', 'mcp_tools']);
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
    $this->setUpCurrentUser([], [], TRUE);
  }

  /**
   * Saves inside a tool call, and one outside.
   */
  public function testAToolCallSavesOnlyWhatValidates(): void {
    $context = $this->container->get('mcp_tools.tool_call_context');
    $user = User::create(['name' => 'ann', 'mail' => 'ann@example.com', 'status' => 1]);
    $user->save();
    $node = Node::create(['type' => 'page', 'title' => 'Fine']);
    $node->save();

    $context->enter();
    try {
      $refused = function (callable $save): ?string {
        try {
          $save();
          return NULL;
        }
        catch (\Exception $e) {
          // Entity storage wraps what a presave hook throws.
          $refusal = $e instanceof \Drupal\mcp_tools\Service\SaveRefusedException ? $e : $e->getPrevious();
          if (!$refusal instanceof \Drupal\mcp_tools\Service\SaveRefusedException) {
            throw $e;
          }
          return $refusal->getMessage();
        }
      };
      $user->setEmail('nope');
      $this->assertStringContainsString('mail', (string) $refused(fn() => $user->save()), 'A user with the email "nope" was saved in a tool call.');

      $style = ImageStyle::create(['name' => 'probe', 'label' => 'Probe']);
      $style->addImageEffect(['id' => 'image_scale', 'data' => ['width' => 'abc', 'height' => NULL, 'upscale' => FALSE]]);
      $this->assertNotNull($refused(fn() => $style->save()), 'An image effect with width "abc" was saved.');

      $node->setTitle(str_repeat('x', 300));
      $this->assertNotNull($refused(fn() => $node->save()), 'A 300 character title was saved.');

      // What validates is saved.
      $node->setTitle('Changed');
      $this->assertNull($refused(fn() => $node->save()));
    }
    finally {
      $context->leave();
    }

    // Outside a tool call, Drupal's own way: no check here.
    $user = User::load($user->id());
    $user->setEmail('nope-outside');
    $user->save();
    $this->assertSame('nope-outside', User::load($user->id())->getEmail());
  }

}
