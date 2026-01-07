<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_theme\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;

/**
 * Service for theme management operations.
 */
class ThemeService {

  public function __construct(
    protected ThemeHandlerInterface $themeHandler,
    protected ThemeManagerInterface $themeManager,
    protected ConfigFactoryInterface $configFactory,
    protected ThemeExtensionList $themeExtensionList,
    protected AccessManager $accessManager,
    protected AuditLogger $auditLogger,
  ) {}

  /**
   * Get the current active theme information.
   *
   * @return array
   *   Result with active theme data.
   */
  public function getActiveTheme(): array {
    try {
      $activeTheme = $this->themeManager->getActiveTheme();
      $defaultTheme = $this->configFactory->get('system.theme')->get('default');
      $adminTheme = $this->configFactory->get('system.theme')->get('admin');

      return [
        'success' => TRUE,
        'data' => [
          'active_theme' => $activeTheme->getName(),
          'default_theme' => $defaultTheme,
          'admin_theme' => $adminTheme ?: $defaultTheme,
          'active_theme_info' => [
            'name' => $activeTheme->getName(),
            'path' => $activeTheme->getPath(),
            'engine' => $activeTheme->getEngine(),
            'base_themes' => array_keys($activeTheme->getBaseThemeExtensions()),
            'regions' => $activeTheme->getRegions(),
          ],
        ],
      ];
    }
    catch (\Exception $e) {
      return ['success' => FALSE, 'error' => 'Failed to get active theme: ' . $e->getMessage()];
    }
  }

  /**
   * List all installed themes.
   *
   * @param bool $includeUninstalled
   *   Whether to include uninstalled themes.
   *
   * @return array
   *   Result with themes list.
   */
  public function listThemes(bool $includeUninstalled = FALSE): array {
    try {
      $defaultTheme = $this->configFactory->get('system.theme')->get('default');
      $adminTheme = $this->configFactory->get('system.theme')->get('admin');
      $installedThemes = $this->themeHandler->listInfo();
      $themes = [];

      if ($includeUninstalled) {
        // Get all available themes.
        $allThemes = $this->themeExtensionList->getList();
        foreach ($allThemes as $name => $extension) {
          $info = $extension->info;
          $isInstalled = isset($installedThemes[$name]);
          $themes[] = [
            'name' => $name,
            'label' => $info['name'] ?? $name,
            'description' => $info['description'] ?? '',
            'version' => $info['version'] ?? 'N/A',
            'installed' => $isInstalled,
            'is_default' => $name === $defaultTheme,
            'is_admin' => $name === $adminTheme,
            'base_theme' => $info['base theme'] ?? NULL,
            'screenshot' => isset($info['screenshot']) ? $extension->getPath() . '/' . $info['screenshot'] : NULL,
          ];
        }
      }
      else {
        // Only installed themes.
        foreach ($installedThemes as $name => $theme) {
          $info = $theme->info;
          $themes[] = [
            'name' => $name,
            'label' => $info['name'] ?? $name,
            'description' => $info['description'] ?? '',
            'version' => $info['version'] ?? 'N/A',
            'installed' => TRUE,
            'is_default' => $name === $defaultTheme,
            'is_admin' => $name === $adminTheme,
            'base_theme' => $info['base theme'] ?? NULL,
            'screenshot' => isset($info['screenshot']) ? $theme->getPath() . '/' . $info['screenshot'] : NULL,
          ];
        }
      }

      return [
        'success' => TRUE,
        'data' => [
          'themes' => $themes,
          'total' => count($themes),
          'default_theme' => $defaultTheme,
          'admin_theme' => $adminTheme,
        ],
      ];
    }
    catch (\Exception $e) {
      return ['success' => FALSE, 'error' => 'Failed to list themes: ' . $e->getMessage()];
    }
  }

  /**
   * Set the default theme.
   *
   * @param string $theme
   *   Theme machine name.
   *
   * @return array
   *   Result with success status.
   */
  public function setDefaultTheme(string $theme): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    // Validate theme exists and is installed.
    $validationResult = $this->validateThemeInstalled($theme);
    if (!$validationResult['valid']) {
      return ['success' => FALSE, 'error' => $validationResult['error']];
    }

    try {
      $currentDefault = $this->configFactory->get('system.theme')->get('default');

      if ($currentDefault === $theme) {
        return [
          'success' => TRUE,
          'data' => [
            'theme' => $theme,
            'message' => "Theme '$theme' is already the default theme.",
            'changed' => FALSE,
          ],
        ];
      }

      $this->configFactory->getEditable('system.theme')
        ->set('default', $theme)
        ->save();

      $this->auditLogger->logSuccess('set_default_theme', 'theme', $theme, [
        'previous_default' => $currentDefault,
      ]);

      return [
        'success' => TRUE,
        'data' => [
          'theme' => $theme,
          'previous_default' => $currentDefault,
          'message' => "Default theme changed from '$currentDefault' to '$theme'.",
          'changed' => TRUE,
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('set_default_theme', 'theme', $theme, [
        'error' => $e->getMessage(),
      ]);
      return ['success' => FALSE, 'error' => 'Failed to set default theme: ' . $e->getMessage()];
    }
  }

  /**
   * Set the admin theme.
   *
   * @param string $theme
   *   Theme machine name.
   *
   * @return array
   *   Result with success status.
   */
  public function setAdminTheme(string $theme): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    // Validate theme exists and is installed.
    $validationResult = $this->validateThemeInstalled($theme);
    if (!$validationResult['valid']) {
      return ['success' => FALSE, 'error' => $validationResult['error']];
    }

    try {
      $currentAdmin = $this->configFactory->get('system.theme')->get('admin');

      if ($currentAdmin === $theme) {
        return [
          'success' => TRUE,
          'data' => [
            'theme' => $theme,
            'message' => "Theme '$theme' is already the admin theme.",
            'changed' => FALSE,
          ],
        ];
      }

      $this->configFactory->getEditable('system.theme')
        ->set('admin', $theme)
        ->save();

      $this->auditLogger->logSuccess('set_admin_theme', 'theme', $theme, [
        'previous_admin' => $currentAdmin,
      ]);

      return [
        'success' => TRUE,
        'data' => [
          'theme' => $theme,
          'previous_admin' => $currentAdmin ?: '(none)',
          'message' => "Admin theme changed to '$theme'.",
          'changed' => TRUE,
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('set_admin_theme', 'theme', $theme, [
        'error' => $e->getMessage(),
      ]);
      return ['success' => FALSE, 'error' => 'Failed to set admin theme: ' . $e->getMessage()];
    }
  }

  /**
   * Get theme settings.
   *
   * @param string $theme
   *   Theme machine name.
   *
   * @return array
   *   Result with theme settings.
   */
  public function getThemeSettings(string $theme): array {
    // Validate theme exists.
    $validationResult = $this->validateThemeExists($theme);
    if (!$validationResult['valid']) {
      return ['success' => FALSE, 'error' => $validationResult['error']];
    }

    try {
      $settings = $this->configFactory->get("$theme.settings")->getRawData();

      // Also get global theme settings.
      $globalSettings = $this->configFactory->get('system.theme.global')->getRawData();

      return [
        'success' => TRUE,
        'data' => [
          'theme' => $theme,
          'settings' => $settings ?: [],
          'global_settings' => $globalSettings ?: [],
          'logo' => [
            'use_default' => $settings['logo']['use_default'] ?? $globalSettings['logo']['use_default'] ?? TRUE,
            'path' => $settings['logo']['path'] ?? $globalSettings['logo']['path'] ?? NULL,
          ],
          'favicon' => [
            'use_default' => $settings['favicon']['use_default'] ?? $globalSettings['favicon']['use_default'] ?? TRUE,
            'path' => $settings['favicon']['path'] ?? $globalSettings['favicon']['path'] ?? NULL,
          ],
        ],
      ];
    }
    catch (\Exception $e) {
      return ['success' => FALSE, 'error' => 'Failed to get theme settings: ' . $e->getMessage()];
    }
  }

  /**
   * Update theme settings.
   *
   * @param string $theme
   *   Theme machine name.
   * @param array $settings
   *   Settings to update (logo, favicon, colors, features, etc.).
   *
   * @return array
   *   Result with success status.
   */
  public function updateThemeSettings(string $theme, array $settings): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    // Validate theme exists.
    $validationResult = $this->validateThemeExists($theme);
    if (!$validationResult['valid']) {
      return ['success' => FALSE, 'error' => $validationResult['error']];
    }

    try {
      $config = $this->configFactory->getEditable("$theme.settings");
      $updatedKeys = [];

      foreach ($settings as $key => $value) {
        // Handle nested settings like logo.path, favicon.use_default.
        if (is_array($value)) {
          foreach ($value as $subKey => $subValue) {
            $config->set("$key.$subKey", $subValue);
            $updatedKeys[] = "$key.$subKey";
          }
        }
        else {
          $config->set($key, $value);
          $updatedKeys[] = $key;
        }
      }

      $config->save();

      $this->auditLogger->logSuccess('update_theme_settings', 'theme', $theme, [
        'updated_keys' => $updatedKeys,
      ]);

      return [
        'success' => TRUE,
        'data' => [
          'theme' => $theme,
          'updated_keys' => $updatedKeys,
          'message' => "Theme settings for '$theme' updated successfully.",
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('update_theme_settings', 'theme', $theme, [
        'error' => $e->getMessage(),
      ]);
      return ['success' => FALSE, 'error' => 'Failed to update theme settings: ' . $e->getMessage()];
    }
  }

  /**
   * Enable/install a theme.
   *
   * @param string $theme
   *   Theme machine name.
   *
   * @return array
   *   Result with success status.
   */
  public function enableTheme(string $theme): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    // Check if theme exists.
    $validationResult = $this->validateThemeExists($theme);
    if (!$validationResult['valid']) {
      return ['success' => FALSE, 'error' => $validationResult['error']];
    }

    // Check if already installed.
    $installedThemes = $this->themeHandler->listInfo();
    if (isset($installedThemes[$theme])) {
      return [
        'success' => TRUE,
        'data' => [
          'theme' => $theme,
          'message' => "Theme '$theme' is already installed.",
          'changed' => FALSE,
        ],
      ];
    }

    try {
      $this->themeHandler->install([$theme]);

      $this->auditLogger->logSuccess('enable_theme', 'theme', $theme, []);

      // Get theme info for the response.
      $themeInfo = $this->themeExtensionList->getExtensionInfo($theme);

      return [
        'success' => TRUE,
        'data' => [
          'theme' => $theme,
          'label' => $themeInfo['name'] ?? $theme,
          'message' => "Theme '$theme' installed successfully.",
          'changed' => TRUE,
          'admin_path' => "/admin/appearance",
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('enable_theme', 'theme', $theme, [
        'error' => $e->getMessage(),
      ]);
      return ['success' => FALSE, 'error' => 'Failed to enable theme: ' . $e->getMessage()];
    }
  }

  /**
   * Disable/uninstall a theme.
   *
   * @param string $theme
   *   Theme machine name.
   *
   * @return array
   *   Result with success status.
   */
  public function disableTheme(string $theme): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    // Check if theme is installed.
    $installedThemes = $this->themeHandler->listInfo();
    if (!isset($installedThemes[$theme])) {
      return [
        'success' => TRUE,
        'data' => [
          'theme' => $theme,
          'message' => "Theme '$theme' is not installed.",
          'changed' => FALSE,
        ],
      ];
    }

    // Safety check: Cannot disable the default theme.
    $defaultTheme = $this->configFactory->get('system.theme')->get('default');
    if ($theme === $defaultTheme) {
      return [
        'success' => FALSE,
        'error' => "Cannot disable theme '$theme' because it is the current default theme. Set a different default theme first.",
      ];
    }

    // Safety check: Cannot disable the admin theme while it's set.
    $adminTheme = $this->configFactory->get('system.theme')->get('admin');
    if ($theme === $adminTheme) {
      return [
        'success' => FALSE,
        'error' => "Cannot disable theme '$theme' because it is the current admin theme. Set a different admin theme first.",
      ];
    }

    // Safety check: Cannot disable if it's a base theme for installed themes.
    foreach ($installedThemes as $installedName => $installedTheme) {
      $info = $installedTheme->info;
      if (isset($info['base theme']) && $info['base theme'] === $theme) {
        return [
          'success' => FALSE,
          'error' => "Cannot disable theme '$theme' because it is a base theme for '$installedName'. Disable '$installedName' first.",
        ];
      }
    }

    try {
      $themeLabel = $installedThemes[$theme]->info['name'] ?? $theme;
      $this->themeHandler->uninstall([$theme]);

      $this->auditLogger->logSuccess('disable_theme', 'theme', $theme, []);

      return [
        'success' => TRUE,
        'data' => [
          'theme' => $theme,
          'label' => $themeLabel,
          'message' => "Theme '$themeLabel' ($theme) uninstalled successfully.",
          'changed' => TRUE,
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('disable_theme', 'theme', $theme, [
        'error' => $e->getMessage(),
      ]);
      return ['success' => FALSE, 'error' => 'Failed to disable theme: ' . $e->getMessage()];
    }
  }

  /**
   * Validate that a theme exists (installed or not).
   *
   * @param string $theme
   *   Theme machine name.
   *
   * @return array
   *   ['valid' => bool, 'error' => string|null].
   */
  protected function validateThemeExists(string $theme): array {
    try {
      $allThemes = $this->themeExtensionList->getList();
      if (!isset($allThemes[$theme])) {
        return [
          'valid' => FALSE,
          'error' => "Theme '$theme' does not exist.",
        ];
      }
      return ['valid' => TRUE, 'error' => NULL];
    }
    catch (\Exception $e) {
      return [
        'valid' => FALSE,
        'error' => "Failed to validate theme: " . $e->getMessage(),
      ];
    }
  }

  /**
   * Validate that a theme is installed.
   *
   * @param string $theme
   *   Theme machine name.
   *
   * @return array
   *   ['valid' => bool, 'error' => string|null].
   */
  protected function validateThemeInstalled(string $theme): array {
    $installedThemes = $this->themeHandler->listInfo();
    if (!isset($installedThemes[$theme])) {
      // Check if it exists but isn't installed.
      $existsResult = $this->validateThemeExists($theme);
      if (!$existsResult['valid']) {
        return $existsResult;
      }
      return [
        'valid' => FALSE,
        'error' => "Theme '$theme' exists but is not installed. Enable it first.",
      ];
    }
    return ['valid' => TRUE, 'error' => NULL];
  }

}
