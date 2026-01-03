<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_recipes\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\File\FileSystemInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;
use Symfony\Component\Yaml\Yaml;

/**
 * Service for Drupal Recipes integration.
 *
 * Provides tools for listing, viewing, validating, and applying Drupal Recipes.
 * Drupal Recipes are YAML-based site building configurations introduced in
 * Drupal 10.3 that can be applied to configure a site.
 */
class RecipesService {

  /**
   * Minimum Drupal version for recipes support.
   */
  protected const MIN_DRUPAL_VERSION = '10.3.0';

  /**
   * State key for tracking applied recipes.
   */
  protected const APPLIED_RECIPES_STATE_KEY = 'mcp_tools_recipes.applied';

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected ModuleExtensionList $moduleExtensionList,
    protected FileSystemInterface $fileSystem,
    protected string $appRoot,
    protected AccessManager $accessManager,
    protected AuditLogger $auditLogger,
  ) {}

  /**
   * Check if Drupal Recipes are supported in this Drupal version.
   *
   * @return bool
   *   TRUE if recipes are supported.
   */
  public function isRecipesSupported(): bool {
    return version_compare(\Drupal::VERSION, self::MIN_DRUPAL_VERSION, '>=');
  }

  /**
   * Get an error message for unsupported Drupal versions.
   *
   * @return array
   *   Error response array.
   */
  protected function getUnsupportedVersionError(): array {
    return [
      'success' => FALSE,
      'error' => sprintf(
        'Drupal Recipes require Drupal %s or later. Current version: %s',
        self::MIN_DRUPAL_VERSION,
        \Drupal::VERSION
      ),
      'code' => 'UNSUPPORTED_VERSION',
    ];
  }

  /**
   * List available recipes from all known recipe directories.
   *
   * @return array
   *   Result array with list of recipes.
   */
  public function listRecipes(): array {
    if (!$this->isRecipesSupported()) {
      return $this->getUnsupportedVersionError();
    }

    $recipes = [];
    $directories = $this->getRecipeDirectories();

    foreach ($directories as $source => $directory) {
      if (!is_dir($directory)) {
        continue;
      }

      $items = scandir($directory);
      foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
          continue;
        }

        $recipePath = $directory . '/' . $item;
        $recipeYml = $recipePath . '/recipe.yml';

        if (is_dir($recipePath) && file_exists($recipeYml)) {
          try {
            $recipeData = Yaml::parseFile($recipeYml);
            $recipes[] = [
              'name' => $item,
              'label' => $recipeData['name'] ?? $item,
              'description' => $recipeData['description'] ?? '',
              'type' => $recipeData['type'] ?? 'Site',
              'source' => $source,
              'path' => $recipePath,
            ];
          }
          catch (\Exception $e) {
            // Skip malformed recipes but log the issue.
            \Drupal::logger('mcp_tools_recipes')->warning(
              'Failed to parse recipe at @path: @error',
              ['@path' => $recipeYml, '@error' => $e->getMessage()]
            );
          }
        }
      }
    }

    return [
      'success' => TRUE,
      'data' => [
        'recipes' => $recipes,
        'count' => count($recipes),
        'sources' => array_keys($directories),
      ],
    ];
  }

  /**
   * Get details of a specific recipe.
   *
   * @param string $recipeName
   *   The recipe name/directory.
   *
   * @return array
   *   Result array with recipe details.
   */
  public function getRecipe(string $recipeName): array {
    if (!$this->isRecipesSupported()) {
      return $this->getUnsupportedVersionError();
    }

    $recipePath = $this->findRecipe($recipeName);
    if (!$recipePath) {
      return [
        'success' => FALSE,
        'error' => sprintf('Recipe "%s" not found.', $recipeName),
        'code' => 'RECIPE_NOT_FOUND',
      ];
    }

    $recipeYml = $recipePath . '/recipe.yml';

    try {
      $recipeData = Yaml::parseFile($recipeYml);

      // Build comprehensive recipe information.
      $recipe = [
        'name' => $recipeName,
        'label' => $recipeData['name'] ?? $recipeName,
        'description' => $recipeData['description'] ?? '',
        'type' => $recipeData['type'] ?? 'Site',
        'path' => $recipePath,
        'install' => $recipeData['install'] ?? [],
        'config' => [],
        'recipes' => $recipeData['recipes'] ?? [],
      ];

      // Parse config imports if present.
      if (isset($recipeData['config'])) {
        $recipe['config'] = [
          'import' => $recipeData['config']['import'] ?? [],
          'actions' => $recipeData['config']['actions'] ?? [],
        ];
      }

      // Check for additional recipe files.
      $recipe['files'] = $this->getRecipeFiles($recipePath);

      return [
        'success' => TRUE,
        'data' => $recipe,
      ];
    }
    catch (\Exception $e) {
      return [
        'success' => FALSE,
        'error' => sprintf('Failed to read recipe "%s": %s', $recipeName, $e->getMessage()),
        'code' => 'RECIPE_READ_ERROR',
      ];
    }
  }

  /**
   * Validate a recipe before applying.
   *
   * @param string $recipeName
   *   The recipe name/directory.
   *
   * @return array
   *   Result array with validation results.
   */
  public function validateRecipe(string $recipeName): array {
    if (!$this->isRecipesSupported()) {
      return $this->getUnsupportedVersionError();
    }

    $recipePath = $this->findRecipe($recipeName);
    if (!$recipePath) {
      return [
        'success' => FALSE,
        'error' => sprintf('Recipe "%s" not found.', $recipeName),
        'code' => 'RECIPE_NOT_FOUND',
      ];
    }

    $recipeYml = $recipePath . '/recipe.yml';
    $errors = [];
    $warnings = [];

    try {
      $recipeData = Yaml::parseFile($recipeYml);

      // Check required fields.
      if (empty($recipeData['name'])) {
        $errors[] = 'Recipe is missing required "name" field.';
      }

      // Validate module dependencies.
      if (!empty($recipeData['install'])) {
        foreach ($recipeData['install'] as $module) {
          if (!$this->moduleExtensionList->exists($module)) {
            $errors[] = sprintf('Required module "%s" is not available.', $module);
          }
          elseif (!$this->moduleExtensionList->get($module)->status) {
            $warnings[] = sprintf('Module "%s" is not currently enabled but will be installed.', $module);
          }
        }
      }

      // Validate recipe dependencies.
      if (!empty($recipeData['recipes'])) {
        foreach ($recipeData['recipes'] as $dependency) {
          if (!$this->findRecipe($dependency)) {
            $errors[] = sprintf('Dependent recipe "%s" not found.', $dependency);
          }
        }
      }

      // Validate config directory.
      $configDir = $recipePath . '/config';
      if (is_dir($configDir)) {
        $configFiles = glob($configDir . '/*.yml');
        foreach ($configFiles as $configFile) {
          try {
            Yaml::parseFile($configFile);
          }
          catch (\Exception $e) {
            $errors[] = sprintf(
              'Invalid YAML in config file "%s": %s',
              basename($configFile),
              $e->getMessage()
            );
          }
        }
      }

      // Check if RecipeRunner class exists.
      if (!class_exists('Drupal\Core\Recipe\RecipeRunner')) {
        $warnings[] = 'RecipeRunner class not found. Recipe may need to be applied via Drush.';
      }

      $isValid = empty($errors);

      return [
        'success' => TRUE,
        'data' => [
          'recipe' => $recipeName,
          'valid' => $isValid,
          'errors' => $errors,
          'warnings' => $warnings,
          'message' => $isValid
            ? 'Recipe validation passed.'
            : 'Recipe validation failed with errors.',
        ],
      ];
    }
    catch (\Exception $e) {
      return [
        'success' => FALSE,
        'error' => sprintf('Failed to validate recipe "%s": %s', $recipeName, $e->getMessage()),
        'code' => 'VALIDATION_ERROR',
      ];
    }
  }

  /**
   * Apply a recipe to the site.
   *
   * @param string $recipeName
   *   The recipe name/directory.
   *
   * @return array
   *   Result array indicating success or failure.
   */
  public function applyRecipe(string $recipeName): array {
    if (!$this->isRecipesSupported()) {
      return $this->getUnsupportedVersionError();
    }

    // Require admin scope for applying recipes.
    if (!$this->accessManager->canAdmin()) {
      return [
        'success' => FALSE,
        'error' => 'Applying recipes requires admin scope. This operation can make significant changes to your site.',
        'code' => 'INSUFFICIENT_SCOPE',
      ];
    }

    // Validate first.
    $validation = $this->validateRecipe($recipeName);
    if (!$validation['success']) {
      return $validation;
    }

    if (!$validation['data']['valid']) {
      return [
        'success' => FALSE,
        'error' => 'Recipe validation failed. Fix errors before applying.',
        'errors' => $validation['data']['errors'],
        'code' => 'VALIDATION_FAILED',
      ];
    }

    $recipePath = $this->findRecipe($recipeName);

    // Check if RecipeRunner is available.
    if (!class_exists('Drupal\Core\Recipe\RecipeRunner')) {
      $this->auditLogger->logFailure('apply_recipe', 'recipe', $recipeName, [
        'error' => 'RecipeRunner class not available',
      ]);

      return [
        'success' => FALSE,
        'error' => 'RecipeRunner class not available. Please apply this recipe using Drush: drush recipe ' . $recipePath,
        'code' => 'RUNNER_NOT_AVAILABLE',
        'drush_command' => 'drush recipe ' . $recipePath,
      ];
    }

    try {
      // Load and apply the recipe using Drupal's RecipeRunner.
      $recipe = \Drupal\Core\Recipe\Recipe::createFromDirectory($recipePath);
      \Drupal\Core\Recipe\RecipeRunner::processRecipe($recipe);

      // Track applied recipe.
      $this->trackAppliedRecipe($recipeName, $recipePath);

      $this->auditLogger->logSuccess('apply_recipe', 'recipe', $recipeName, [
        'path' => $recipePath,
      ]);

      return [
        'success' => TRUE,
        'data' => [
          'recipe' => $recipeName,
          'path' => $recipePath,
          'message' => sprintf('Recipe "%s" applied successfully.', $recipeName),
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('apply_recipe', 'recipe', $recipeName, [
        'error' => $e->getMessage(),
      ]);

      return [
        'success' => FALSE,
        'error' => sprintf('Failed to apply recipe "%s": %s', $recipeName, $e->getMessage()),
        'code' => 'APPLY_ERROR',
      ];
    }
  }

  /**
   * Get list of recipes that have been applied.
   *
   * @return array
   *   Result array with list of applied recipes.
   */
  public function getAppliedRecipes(): array {
    if (!$this->isRecipesSupported()) {
      return $this->getUnsupportedVersionError();
    }

    $appliedRecipes = \Drupal::state()->get(self::APPLIED_RECIPES_STATE_KEY, []);

    return [
      'success' => TRUE,
      'data' => [
        'recipes' => $appliedRecipes,
        'count' => count($appliedRecipes),
      ],
    ];
  }

  /**
   * Create a new recipe file.
   *
   * @param string $name
   *   The recipe machine name (directory name).
   * @param string $description
   *   Human-readable description.
   * @param array $config
   *   Recipe configuration including modules, config, etc.
   *
   * @return array
   *   Result array indicating success or failure.
   */
  public function createRecipe(string $name, string $description, array $config = []): array {
    if (!$this->isRecipesSupported()) {
      return $this->getUnsupportedVersionError();
    }

    // Require admin scope for creating recipes.
    if (!$this->accessManager->canAdmin()) {
      return [
        'success' => FALSE,
        'error' => 'Creating recipes requires admin scope.',
        'code' => 'INSUFFICIENT_SCOPE',
      ];
    }

    // Validate recipe name (machine name format).
    if (!preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
      return [
        'success' => FALSE,
        'error' => 'Recipe name must be a valid machine name (lowercase letters, numbers, underscores, starting with a letter).',
        'code' => 'INVALID_NAME',
      ];
    }

    // Create in the site's recipes directory.
    $recipesDir = $this->appRoot . '/recipes';
    $recipePath = $recipesDir . '/' . $name;

    // Check if recipe already exists.
    if (is_dir($recipePath)) {
      return [
        'success' => FALSE,
        'error' => sprintf('Recipe "%s" already exists at %s', $name, $recipePath),
        'code' => 'RECIPE_EXISTS',
      ];
    }

    try {
      // Create recipe directory.
      if (!is_dir($recipesDir)) {
        $this->fileSystem->mkdir($recipesDir, 0755, TRUE);
      }
      $this->fileSystem->mkdir($recipePath, 0755, TRUE);

      // Build recipe.yml content.
      $recipeData = [
        'name' => $config['label'] ?? ucwords(str_replace('_', ' ', $name)),
        'description' => $description,
        'type' => $config['type'] ?? 'Site',
      ];

      // Add optional sections.
      if (!empty($config['recipes'])) {
        $recipeData['recipes'] = $config['recipes'];
      }

      if (!empty($config['install'])) {
        $recipeData['install'] = $config['install'];
      }

      if (!empty($config['config'])) {
        $recipeData['config'] = $config['config'];
      }

      // Write recipe.yml.
      $yamlContent = Yaml::dump($recipeData, 4, 2);
      file_put_contents($recipePath . '/recipe.yml', $yamlContent);

      // Create config directory if config files are needed.
      if (!empty($config['config_files'])) {
        $configDir = $recipePath . '/config';
        $this->fileSystem->mkdir($configDir, 0755, TRUE);

        foreach ($config['config_files'] as $filename => $content) {
          // Validate filename to prevent path traversal.
          if (!preg_match('/^[a-zA-Z0-9_\-\.]+\.yml$/', $filename) || str_contains($filename, '..')) {
            throw new \InvalidArgumentException("Invalid config filename: {$filename}");
          }
          $configContent = is_array($content) ? Yaml::dump($content, 4, 2) : $content;
          file_put_contents($configDir . '/' . $filename, $configContent);
        }
      }

      $this->auditLogger->logSuccess('create_recipe', 'recipe', $name, [
        'path' => $recipePath,
        'description' => $description,
      ]);

      return [
        'success' => TRUE,
        'data' => [
          'name' => $name,
          'path' => $recipePath,
          'message' => sprintf('Recipe "%s" created successfully.', $name),
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('create_recipe', 'recipe', $name, [
        'error' => $e->getMessage(),
      ]);

      return [
        'success' => FALSE,
        'error' => sprintf('Failed to create recipe "%s": %s', $name, $e->getMessage()),
        'code' => 'CREATE_ERROR',
      ];
    }
  }

  /**
   * Get all directories where recipes may be located.
   *
   * @return array
   *   Associative array of source => path.
   */
  protected function getRecipeDirectories(): array {
    $directories = [
      'site' => $this->appRoot . '/recipes',
      'core' => $this->appRoot . '/core/recipes',
    ];

    // Add contrib module recipe directories.
    foreach ($this->moduleExtensionList->getAllInstalledInfo() as $name => $info) {
      $modulePath = $this->moduleExtensionList->getPath($name);
      $recipeDir = $this->appRoot . '/' . $modulePath . '/recipes';
      if (is_dir($recipeDir)) {
        $directories['module:' . $name] = $recipeDir;
      }
    }

    return $directories;
  }

  /**
   * Find a recipe by name in all known locations.
   *
   * @param string $recipeName
   *   The recipe name to find.
   *
   * @return string|null
   *   The recipe path or NULL if not found.
   */
  protected function findRecipe(string $recipeName): ?string {
    // First check if it's an absolute path.
    if (str_starts_with($recipeName, '/') && is_dir($recipeName) && file_exists($recipeName . '/recipe.yml')) {
      return $recipeName;
    }

    // Search in known directories.
    foreach ($this->getRecipeDirectories() as $directory) {
      $recipePath = $directory . '/' . $recipeName;
      if (is_dir($recipePath) && file_exists($recipePath . '/recipe.yml')) {
        return $recipePath;
      }
    }

    return NULL;
  }

  /**
   * Get list of files in a recipe directory.
   *
   * @param string $recipePath
   *   Path to the recipe directory.
   *
   * @return array
   *   List of files with their types.
   */
  protected function getRecipeFiles(string $recipePath): array {
    $files = [];

    // recipe.yml is always present.
    $files[] = ['name' => 'recipe.yml', 'type' => 'main'];

    // Check for config directory.
    $configDir = $recipePath . '/config';
    if (is_dir($configDir)) {
      $configFiles = glob($configDir . '/*.yml');
      foreach ($configFiles as $configFile) {
        $files[] = [
          'name' => 'config/' . basename($configFile),
          'type' => 'config',
        ];
      }
    }

    // Check for content directory.
    $contentDir = $recipePath . '/content';
    if (is_dir($contentDir)) {
      $contentFiles = scandir($contentDir);
      foreach ($contentFiles as $file) {
        if ($file !== '.' && $file !== '..') {
          $files[] = [
            'name' => 'content/' . $file,
            'type' => 'content',
          ];
        }
      }
    }

    return $files;
  }

  /**
   * Track an applied recipe in state.
   *
   * @param string $recipeName
   *   The recipe name.
   * @param string $recipePath
   *   The recipe path.
   */
  protected function trackAppliedRecipe(string $recipeName, string $recipePath): void {
    $appliedRecipes = \Drupal::state()->get(self::APPLIED_RECIPES_STATE_KEY, []);

    $appliedRecipes[$recipeName] = [
      'name' => $recipeName,
      'path' => $recipePath,
      'applied_at' => date('c'),
      'applied_by' => \Drupal::currentUser()->getAccountName(),
    ];

    \Drupal::state()->set(self::APPLIED_RECIPES_STATE_KEY, $appliedRecipes);
  }

}
