<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_tools_recipes\Unit\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\State\StateInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\mcp_tools_recipes\Service\RecipesService;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;

/**
 * @coversDefaultClass \Drupal\mcp_tools_recipes\Service\RecipesService
 * @group mcp_tools_recipes
 */
final class RecipesServiceTest extends UnitTestCase {

  private string $appRoot;

  protected function tearDown(): void {
    if (isset($this->appRoot) && is_dir($this->appRoot)) {
      $this->deleteDirectory($this->appRoot);
    }
    parent::tearDown();
  }

  private function createService(array $overrides = []): RecipesService {
    $this->appRoot = $overrides['app_root'] ?? sys_get_temp_dir() . '/mcp_tools_recipes_' . bin2hex(random_bytes(8));

    return new RecipesService(
      $overrides['config_factory'] ?? $this->createMock(ConfigFactoryInterface::class),
      $overrides['module_extension_list'] ?? $this->createMock(ModuleExtensionList::class),
      $overrides['file_system'] ?? $this->createTestFileSystem(),
      $this->appRoot,
      $overrides['access_manager'] ?? $this->createMock(AccessManager::class),
      $overrides['audit_logger'] ?? $this->createMock(AuditLogger::class),
      $overrides['state'] ?? $this->createMock(StateInterface::class),
      $overrides['current_user'] ?? $this->createMock(AccountProxyInterface::class),
      $overrides['logger'] ?? $this->createMock(LoggerInterface::class),
    );
  }

  private function createTestFileSystem(): FileSystemInterface {
    $fileSystem = $this->createMock(FileSystemInterface::class);
    $fileSystem->method('mkdir')->willReturnCallback(static function (...$args): bool {
      $directory = (string) ($args[0] ?? '');
      $mode = (int) ($args[1] ?? 0755);
      $recursive = (bool) ($args[2] ?? FALSE);
      if ($directory === '') {
        return FALSE;
      }
      if (!is_dir($directory)) {
        return mkdir($directory, $mode, $recursive);
      }
      return TRUE;
    });
    return $fileSystem;
  }

  /**
   * @covers ::listRecipes
   */
  public function testListRecipesFindsSiteAndCoreRecipes(): void {
    $moduleList = $this->createMock(ModuleExtensionList::class);
    $moduleList->method('getAllInstalledInfo')->willReturn([]);

    $service = $this->createService([
      'module_extension_list' => $moduleList,
    ]);

    $this->writeRecipe($this->appRoot . '/recipes/my_recipe', [
      'name' => 'My Recipe',
      'description' => 'Example',
      'type' => 'Site',
    ]);
    $this->writeRecipe($this->appRoot . '/core/recipes/core_recipe', [
      'name' => 'Core Recipe',
      'description' => 'Core example',
      'type' => 'Site',
    ]);

    $result = $service->listRecipes();
    $this->assertTrue($result['success']);
    $this->assertSame(2, $result['data']['count']);

    $names = array_column($result['data']['recipes'], 'name');
    $this->assertContains('my_recipe', $names);
    $this->assertContains('core_recipe', $names);
  }

  /**
   * @covers ::getRecipe
   */
  public function testGetRecipeReturnsNotFound(): void {
    $moduleList = $this->createMock(ModuleExtensionList::class);
    $moduleList->method('getAllInstalledInfo')->willReturn([]);

    $service = $this->createService([
      'module_extension_list' => $moduleList,
    ]);

    $result = $service->getRecipe('missing_recipe');
    $this->assertFalse($result['success']);
    $this->assertSame('RECIPE_NOT_FOUND', $result['code']);
  }

  /**
   * @covers ::getRecipe
   */
  public function testGetRecipeIncludesFiles(): void {
    $moduleList = $this->createMock(ModuleExtensionList::class);
    $moduleList->method('getAllInstalledInfo')->willReturn([]);

    $service = $this->createService([
      'module_extension_list' => $moduleList,
    ]);

    $recipePath = $this->appRoot . '/recipes/my_recipe';
    $this->writeRecipe($recipePath, [
      'name' => 'My Recipe',
      'description' => 'Example',
      'type' => 'Site',
    ]);

    mkdir($recipePath . '/config', 0755, TRUE);
    file_put_contents($recipePath . '/config/system.site.yml', "name: 'Site'\n");
    mkdir($recipePath . '/content', 0755, TRUE);
    file_put_contents($recipePath . '/content/readme.txt', 'Hello');

    $result = $service->getRecipe('my_recipe');
    $this->assertTrue($result['success']);

    $files = $result['data']['files'] ?? [];
    $fileNames = array_column($files, 'name');
    $this->assertContains('recipe.yml', $fileNames);
    $this->assertContains('config/system.site.yml', $fileNames);
    $this->assertContains('content/readme.txt', $fileNames);
  }

  /**
   * @covers ::validateRecipe
   */
  public function testValidateRecipeReportsYamlAndDependencyErrors(): void {
    $moduleList = $this->createMock(ModuleExtensionList::class);
    $moduleList->method('getAllInstalledInfo')->willReturn([]);
    $moduleList->method('exists')->willReturnMap([
      ['missing_module', FALSE],
      ['disabled_module', TRUE],
    ]);
    $moduleList->method('get')->willReturnCallback(static function (string $name): object {
      $ext = new \stdClass();
      $ext->status = $name === 'disabled_module' ? 0 : 1;
      return $ext;
    });

    $service = $this->createService([
      'module_extension_list' => $moduleList,
    ]);

    $recipePath = $this->appRoot . '/recipes/bad_recipe';
    mkdir($recipePath . '/config', 0755, TRUE);
    file_put_contents($recipePath . '/recipe.yml', <<<YAML
description: "Bad recipe"
install:
  - missing_module
  - disabled_module
recipes:
  - missing_dependency
YAML);
    file_put_contents($recipePath . '/config/broken.yml', ":\n");

    $result = $service->validateRecipe('bad_recipe');
    $this->assertTrue($result['success']);
    $this->assertFalse($result['data']['valid']);

    $errors = $result['data']['errors'] ?? [];
    $this->assertNotEmpty($errors);
    $this->assertTrue((bool) array_filter($errors, static fn(string $e): bool => str_contains($e, 'missing required "name"')));
    $this->assertTrue((bool) array_filter($errors, static fn(string $e): bool => str_contains($e, 'Required module "missing_module"')));
    $this->assertTrue((bool) array_filter($errors, static fn(string $e): bool => str_contains($e, 'Dependent recipe "missing_dependency"')));

    $warnings = $result['data']['warnings'] ?? [];
    $this->assertTrue((bool) array_filter($warnings, static fn(string $w): bool => str_contains($w, 'disabled_module')));
  }

  /**
   * @covers ::createRecipe
   */
  public function testCreateRecipeRejectsInvalidMachineName(): void {
    $accessManager = $this->createMock(AccessManager::class);
    $accessManager->method('canAdmin')->willReturn(TRUE);

    $service = $this->createService([
      'access_manager' => $accessManager,
    ]);

    $result = $service->createRecipe('Bad Name', 'Example');
    $this->assertFalse($result['success']);
    $this->assertSame('INVALID_NAME', $result['code']);
  }

  /**
   * @covers ::createRecipe
   */
  public function testCreateRecipeWritesRecipeAndConfigFiles(): void {
    $accessManager = $this->createMock(AccessManager::class);
    $accessManager->method('canAdmin')->willReturn(TRUE);

    $service = $this->createService([
      'access_manager' => $accessManager,
    ]);

    $result = $service->createRecipe('my_new_recipe', 'Example', [
      'label' => 'My New Recipe',
      'type' => 'Site',
      'config_files' => [
        'system.site.yml' => ['name' => 'Test'],
      ],
    ]);

    $this->assertTrue($result['success']);
    $this->assertFileExists($this->appRoot . '/recipes/my_new_recipe/recipe.yml');
    $this->assertFileExists($this->appRoot . '/recipes/my_new_recipe/config/system.site.yml');
  }

  private function writeRecipe(string $path, array $recipeData): void {
    mkdir($path, 0755, TRUE);
    $yaml = '';
    foreach ($recipeData as $key => $value) {
      $yaml .= $key . ': ' . json_encode($value, JSON_UNESCAPED_SLASHES) . "\n";
    }
    file_put_contents($path . '/recipe.yml', $yaml);
  }

  private function deleteDirectory(string $directory): void {
    $iterator = new \RecursiveIteratorIterator(
      new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
      \RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $file) {
      if ($file->isDir()) {
        rmdir($file->getPathname());
        continue;
      }
      unlink($file->getPathname());
    }

    rmdir($directory);
  }

}
