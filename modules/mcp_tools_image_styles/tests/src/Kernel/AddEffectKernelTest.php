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

  /**
   * A setting the effect does not have is refused, and nothing is added.
   *
   * It was stored as given: a scale with "widht" had no width, did
   * nothing, and the tool said it was added.
   */
  public function testASettingTheEffectDoesNotHaveIsRefused(): void {
    $this->installSchema('dblog', ['watchdog']);
    $this->installConfig(['system', 'image', 'mcp_tools']);
    $this->container->get('mcp_tools.access_manager')->setScopes(['read', 'write']);
    ImageStyle::create(['name' => 'probe', 'label' => 'Probe'])->save();

    $result = $this->container->get('mcp_tools_image_styles.image_style_service')->addImageEffect('probe', 'image_scale', ['widht' => 200]);
    $this->assertFalse($result['success'], 'A setting the effect does not have was stored with success.');
    $this->assertStringContainsString('has no setting widht. Its settings: width, height, upscale', $result['error']);
    $this->assertCount(0, ImageStyle::load('probe')->getEffects());
  }

  /**
   * The create tool gives back the new style's id.
   *
   * It declared success and message only, so the id the service returned
   * never reached the model.
   */
  public function testTheCreateToolGivesTheId(): void {
    $this->installSchema('dblog', ['watchdog']);
    $this->installConfig(['system', 'image', 'mcp_tools']);
    $this->container->get('mcp_tools.access_manager')->setScopes(['read', 'write']);
    $tool = $this->container->get('plugin.manager.tool')->createInstance('mcp_image_styles_create');
    $tool->setInputValue('id', 'hero');
    $tool->setInputValue('label', 'Hero');
    $tool->execute();
    $this->assertTrue($tool->getResult()->isSuccess(), (string) $tool->getResult()->getMessage());
    $this->assertSame('hero', $tool->getOutputValues()['id'] ?? NULL);
  }

}
