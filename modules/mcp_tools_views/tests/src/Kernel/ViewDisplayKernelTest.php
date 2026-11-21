<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_views\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\views\Entity\View;

/**
 * Adding a display to a view, on real services.
 *
 * The tool called addDisplay() on the view executable, which has no such
 * method, so it failed every time. The unit test only covered the refusals.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\Group('mcp_tools_views')]
final class ViewDisplayKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'views',
    'dblog',
    'update',
    'tool',
    'mcp_tools',
    'mcp_tools_views',
  ];

  /**
   * A new view gets a second display, and the display is saved.
   */
  public function testAddDisplay(): void {
    $this->installSchema('dblog', ['watchdog']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installConfig(['system', 'mcp_tools']);
    $this->container->get('mcp_tools.access_manager')->setScopes(['read', 'write']);
    $service = $this->container->get('mcp_tools_views.views');

    $result = $service->createView('sweep_view', 'Sweep view');
    $this->assertTrue($result['success'], (string) ($result['error'] ?? ''));

    $result = $service->addDisplay('sweep_view', 'block', ['title' => 'Sweep block', 'items_per_page' => 3]);
    $this->assertTrue($result['success'], (string) ($result['error'] ?? ''));

    $view = View::load('sweep_view');
    $this->assertNotNull($view);
    $display = $view->getDisplay($result['data']['display_id']);
    $this->assertSame('block', $display['display_plugin']);

    // What is stored is not enough: a display takes its title and pager
    // from the default display unless it says it has its own. Ask Views
    // what the display will really use.
    $executable = $view->getExecutable();
    $executable->setDisplay($result['data']['display_id']);
    $this->assertSame('Sweep block', $executable->getTitle());
    $executable->initPager();
    $this->assertSame(3, $executable->getItemsPerPage());

    // The default display keeps its own title.
    $executable = View::load('sweep_view')->getExecutable();
    $executable->setDisplay('default');
    $this->assertNotSame('Sweep block', $executable->getTitle());
  }

}
