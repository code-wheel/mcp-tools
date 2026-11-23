<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_image_styles\Kernel;

use Drupal\image\Entity\ImageStyle;
use Drupal\KernelTests\KernelTestBase;

/**
 * An effect is added the way Drupal's image style form adds one.
 *
 * The tool gave the effect no weight, which Drupal's validation refuses (so
 * in a tool call nothing was saved), and put the effect's id in its data.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_image_styles')]
final class AddEffectKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'image', 'file', 'dblog', 'update', 'tool', 'mcp_tools', 'mcp_tools_image_styles'];

  /**
   * Two effects in a tool call: both saved, in order.
   */
  public function testEffectsAreAddedInOrder(): void {
    $this->installSchema('dblog', ['watchdog']);
    $this->installConfig(['system', 'image', 'mcp_tools']);
    $this->container->get('mcp_tools.access_manager')->setScopes(['read', 'write']);
    ImageStyle::create(['name' => 'probe', 'label' => 'Probe'])->save();
    $service = $this->container->get('mcp_tools_image_styles.image_style_service');

    $context = $this->container->get('mcp_tools.tool_call_context');
    $context->enter();
    try {
      $first = $service->addImageEffect('probe', 'image_scale', ['width' => 100, 'height' => NULL, 'upscale' => FALSE]);
      $second = $service->addImageEffect('probe', 'image_desaturate', []);
    }
    finally {
      $context->leave();
    }
    $this->assertTrue($first['success'] ?? FALSE, json_encode($first));
    $this->assertTrue($second['success'] ?? FALSE, json_encode($second));

    $effects = ImageStyle::load('probe')->getEffects();
    $this->assertCount(2, $effects);
    $scale = $effects->get($first['effect_uuid'])->getConfiguration();
    $this->assertSame(['width' => 100, 'height' => NULL, 'upscale' => FALSE], $scale['data'], 'The effect id was stored in its data.');
    $this->assertLessThan($effects->get($second['effect_uuid'])->getWeight(), $effects->get($first['effect_uuid'])->getWeight());
  }

}
