<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools\Kernel;

use Drupal\filter\Entity\FilterFormat;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mcp_tools\Service\TextFormat;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * A text is saved with a format the site has.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mcp_tools\Service\TextFormat::class)]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools')]
final class TextFormatKernelTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'filter', 'dblog', 'update', 'tool', 'mcp_tools'];

  /**
   * A site without "basic_html" gets its fallback format, others as before.
   */
  public function testAFormatTheSiteHas(): void {
    $this->installConfig(['filter']);
    $manager = $this->container->get('entity_type.manager');
    $fallback = (string) $this->config('filter.settings')->get('fallback_format');
    $this->assertNotNull(FilterFormat::load($fallback));
    $this->assertNull(FilterFormat::load('basic_html'));

    // No "basic_html" here: nothing may be saved with it.
    $this->assertSame($fallback, TextFormat::resolve($manager));
    $this->assertSame($fallback, TextFormat::resolve($manager, 'basic_html'));
    $this->assertSame(['value' => 'Hello', 'format' => $fallback], TextFormat::fieldValue($manager, 'Hello'));
    $this->assertSame(['value' => 'Hello', 'format' => $fallback], TextFormat::fieldValue($manager, ['value' => 'Hello', 'format' => 'basic_html']));
    // What has no format, or is not one value, is left as it is.
    $this->assertSame(['value' => 'Hello'], TextFormat::fieldValue($manager, ['value' => 'Hello']));
    $this->assertSame([['value' => 'One'], ['value' => 'Two']], TextFormat::fieldValue($manager, [['value' => 'One'], ['value' => 'Two']]));

    // A site that has it keeps what it had, and a format that was asked
    // for and exists is taken.
    FilterFormat::create(['format' => 'basic_html', 'name' => 'Basic HTML'])->save();
    FilterFormat::create(['format' => 'rich', 'name' => 'Rich'])->save();
    $this->assertSame('basic_html', TextFormat::resolve($manager));
    $this->assertSame('rich', TextFormat::resolve($manager, 'rich'));
    $this->assertSame('basic_html', TextFormat::resolve($manager, 'gone'));

    // A format that is switched off is not one the site has.
    FilterFormat::load('rich')->disable()->save();
    $this->assertSame('basic_html', TextFormat::resolve($manager, 'rich'));
  }

  /**
   * The list of text formats says who may use each, and the real default.
   *
   * The tool read the roles from a property of the format that Drupal
   * leaves empty once a format is saved. So every format had no roles,
   * and the default was "plain_text" on every site.
   */
  public function testListTextFormatsSaysWhoMayUseAFormat(): void {
    $this->installEntitySchema('user');
    $this->installConfig(['filter', 'user', 'mcp_tools']);
    $this->setUpCurrentUser([], ['mcp_tools use discovery']);
    FilterFormat::create(['format' => 'basic_html', 'name' => 'Basic HTML', 'weight' => -5])->save();
    user_role_grant_permissions('authenticated', ['use text format basic_html']);

    $list = function (): array {
      $tool = $this->container->get('plugin.manager.tool')->createInstance('mcp_list_text_formats');
      $tool->execute();
      $this->assertTrue($tool->getResult()->isSuccess(), (string) $tool->getResult()->getMessage());
      return $tool->getOutputValues();
    };

    $values = $list();
    $formats = array_column($values['formats'], NULL, 'id');
    $this->assertSame(['authenticated'], $formats['basic_html']['roles']);
    // Anonymous users have the fallback format only.
    $this->assertSame($this->config('filter.settings')->get('fallback_format'), $values['default_format']);

    // Once they may use another one that comes first, that is their default.
    user_role_grant_permissions('anonymous', ['use text format basic_html']);
    $this->assertSame('basic_html', $list()['default_format']);
  }

}
