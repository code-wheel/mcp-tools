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
    \Drupal\field\Entity\FieldStorageConfig::create(['field_name' => 'field_reviewer', 'entity_type' => 'node', 'type' => 'entity_reference', 'settings' => ['target_type' => 'user']])->save();
    \Drupal\field\Entity\FieldConfig::create(['field_name' => 'field_reviewer', 'entity_type' => 'node', 'bundle' => 'page'])->save();
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

      // A name where an id goes is said as such, before Drupal looks it up
      // (on PostgreSQL that lookup is a database error, and the save went
      // on into the same error).
      // (The owner field cannot show it: Node::preSave makes an owner it
      // cannot load the anonymous user, before any presave hook.)
      $node->setTitle('Changed');
      $node->set('field_reviewer', ['target_id' => 'ann']);
      $this->assertStringContainsString('Give its id, not its name', (string) $refused(fn() => $node->save()), 'A user name where a user id goes was not said as such.');
      $node->set('field_reviewer', ['target_id' => (int) $user->id()]);

      // What validates is saved.
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
