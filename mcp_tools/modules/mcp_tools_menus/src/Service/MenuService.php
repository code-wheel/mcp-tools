<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_menus\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\system\Entity\Menu;

/**
 * Service for menu management operations.
 */
class MenuService {

  /**
   * System menus that cannot be deleted.
   */
  protected const PROTECTED_MENUS = ['admin', 'tools', 'account', 'main', 'footer'];

  /**
   * Allowed URI schemes for menu links.
   *
   * SECURITY: Restricting schemes prevents XSS via javascript: URIs,
   * data exfiltration via data: URIs, and other protocol-based attacks.
   */
  protected const ALLOWED_URI_SCHEMES = [
    'internal',  // Drupal internal paths
    'entity',    // Entity references (entity:node/1)
    'http',      // External HTTP links
    'https',     // External HTTPS links
    'base',      // Base path references
    'route',     // Drupal route references
  ];

  /**
   * Blocked URI patterns (security).
   */
  protected const BLOCKED_URI_PATTERNS = [
    '/^javascript:/i',  // XSS vector
    '/^data:/i',        // Data URI (potential XSS)
    '/^vbscript:/i',    // VBScript (IE XSS)
    '/^file:/i',        // Local file access
    '/^ftp:/i',         // FTP (usually not needed)
    '/^mailto:/i',      // Email links (use dedicated field types)
    '/^tel:/i',         // Phone links (use dedicated field types)
  ];

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected MenuLinkManagerInterface $menuLinkManager,
    protected AccessManager $accessManager,
    protected AuditLogger $auditLogger,
  ) {}

  /**
   * Create a new menu.
   *
   * @param string $id
   *   Machine name.
   * @param string $label
   *   Human-readable name.
   * @param string $description
   *   Optional description.
   *
   * @return array
   *   Result with success status.
   */
  public function createMenu(string $id, string $label, string $description = ''): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    // Validate machine name.
    if (!preg_match('/^[a-z][a-z0-9_-]*$/', $id)) {
      return [
        'success' => FALSE,
        'error' => 'Invalid machine name. Use lowercase letters, numbers, underscores, and hyphens.',
      ];
    }

    if (strlen($id) > 32) {
      return [
        'success' => FALSE,
        'error' => 'Machine name must be 32 characters or less.',
      ];
    }

    // Check if menu exists.
    $existing = $this->entityTypeManager->getStorage('menu')->load($id);
    if ($existing) {
      return [
        'success' => FALSE,
        'error' => "Menu '$id' already exists.",
      ];
    }

    try {
      $menu = Menu::create([
        'id' => $id,
        'label' => $label,
        'description' => $description,
      ]);
      $menu->save();

      $this->auditLogger->logSuccess('create_menu', 'menu', $id, [
        'label' => $label,
      ]);

      return [
        'success' => TRUE,
        'data' => [
          'id' => $id,
          'label' => $label,
          'message' => "Menu '$label' ($id) created successfully.",
          'admin_path' => "/admin/structure/menu/manage/$id",
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('create_menu', 'menu', $id, ['error' => $e->getMessage()]);
      return [
        'success' => FALSE,
        'error' => 'Failed to create menu: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Delete a menu.
   *
   * @param string $id
   *   Menu machine name.
   *
   * @return array
   *   Result with success status.
   */
  public function deleteMenu(string $id): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    // Protect system menus.
    if (in_array($id, self::PROTECTED_MENUS)) {
      return [
        'success' => FALSE,
        'error' => "Cannot delete system menu '$id'. Protected menus: " . implode(', ', self::PROTECTED_MENUS),
      ];
    }

    $menu = $this->entityTypeManager->getStorage('menu')->load($id);

    if (!$menu) {
      return [
        'success' => FALSE,
        'error' => "Menu '$id' not found.",
      ];
    }

    try {
      $label = $menu->label();
      $menu->delete();

      $this->auditLogger->logSuccess('delete_menu', 'menu', $id, [
        'label' => $label,
      ]);

      return [
        'success' => TRUE,
        'data' => [
          'id' => $id,
          'message' => "Menu '$label' ($id) deleted successfully.",
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('delete_menu', 'menu', $id, ['error' => $e->getMessage()]);
      return [
        'success' => FALSE,
        'error' => 'Failed to delete menu: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Add a link to a menu.
   *
   * @param string $menuName
   *   Menu machine name.
   * @param string $title
   *   Link title.
   * @param string $uri
   *   Link URI (internal path like /node/1 or external URL).
   * @param array $options
   *   Optional: weight, parent, expanded, description.
   *
   * @return array
   *   Result with success status.
   */
  public function addMenuLink(string $menuName, string $title, string $uri, array $options = []): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    // Verify menu exists.
    $menu = $this->entityTypeManager->getStorage('menu')->load($menuName);
    if (!$menu) {
      return [
        'success' => FALSE,
        'error' => "Menu '$menuName' not found.",
      ];
    }

    // SECURITY: Validate and normalize URI.
    $uriValidation = $this->validateMenuUri($uri);
    if (!$uriValidation['valid']) {
      return [
        'success' => FALSE,
        'error' => 'Invalid URI: ' . $uriValidation['reason'],
      ];
    }
    $uri = $uriValidation['uri'];

    try {
      $linkData = [
        'title' => $title,
        'link' => ['uri' => $uri],
        'menu_name' => $menuName,
        'weight' => $options['weight'] ?? 0,
        'expanded' => $options['expanded'] ?? FALSE,
        'description' => $options['description'] ?? '',
      ];

      // Handle parent link.
      if (!empty($options['parent'])) {
        $linkData['parent'] = $options['parent'];
      }

      $menuLink = MenuLinkContent::create($linkData);
      $menuLink->save();

      $this->auditLogger->logSuccess('add_menu_link', 'menu_link_content', (string) $menuLink->id(), [
        'title' => $title,
        'menu' => $menuName,
        'uri' => $uri,
      ]);

      return [
        'success' => TRUE,
        'data' => [
          'id' => $menuLink->id(),
          'uuid' => $menuLink->uuid(),
          'title' => $title,
          'menu' => $menuName,
          'uri' => $uri,
          'plugin_id' => $menuLink->getPluginId(),
          'message' => "Menu link '$title' added to '$menuName'.",
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('add_menu_link', 'menu_link_content', 'new', ['error' => $e->getMessage()]);
      return [
        'success' => FALSE,
        'error' => 'Failed to add menu link: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Update a menu link.
   *
   * @param int $linkId
   *   Menu link content entity ID.
   * @param array $updates
   *   Fields to update: title, uri, weight, expanded, description.
   *
   * @return array
   *   Result with success status.
   */
  public function updateMenuLink(int $linkId, array $updates): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    $menuLink = $this->entityTypeManager->getStorage('menu_link_content')->load($linkId);

    if (!$menuLink) {
      return [
        'success' => FALSE,
        'error' => "Menu link with ID $linkId not found.",
      ];
    }

    try {
      if (isset($updates['title'])) {
        $menuLink->set('title', $updates['title']);
      }
      if (isset($updates['uri'])) {
        // SECURITY: Validate URI before updating.
        $uriValidation = $this->validateMenuUri($updates['uri']);
        if (!$uriValidation['valid']) {
          return [
            'success' => FALSE,
            'error' => 'Invalid URI: ' . $uriValidation['reason'],
          ];
        }
        $menuLink->set('link', ['uri' => $uriValidation['uri']]);
      }
      if (isset($updates['weight'])) {
        $menuLink->set('weight', $updates['weight']);
      }
      if (isset($updates['expanded'])) {
        $menuLink->set('expanded', $updates['expanded']);
      }
      if (isset($updates['description'])) {
        $menuLink->set('description', $updates['description']);
      }

      $menuLink->save();

      $this->auditLogger->logSuccess('update_menu_link', 'menu_link_content', (string) $linkId, [
        'updates' => array_keys($updates),
      ]);

      return [
        'success' => TRUE,
        'data' => [
          'id' => $linkId,
          'title' => $menuLink->getTitle(),
          'message' => "Menu link updated successfully.",
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('update_menu_link', 'menu_link_content', (string) $linkId, ['error' => $e->getMessage()]);
      return [
        'success' => FALSE,
        'error' => 'Failed to update menu link: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Delete a menu link.
   *
   * @param int $linkId
   *   Menu link content entity ID.
   *
   * @return array
   *   Result with success status.
   */
  public function deleteMenuLink(int $linkId): array {
    if (!$this->accessManager->canWrite()) {
      return $this->accessManager->getWriteAccessDenied();
    }

    $menuLink = $this->entityTypeManager->getStorage('menu_link_content')->load($linkId);

    if (!$menuLink) {
      return [
        'success' => FALSE,
        'error' => "Menu link with ID $linkId not found.",
      ];
    }

    try {
      $title = $menuLink->getTitle();
      $menuName = $menuLink->getMenuName();
      $menuLink->delete();

      $this->auditLogger->logSuccess('delete_menu_link', 'menu_link_content', (string) $linkId, [
        'title' => $title,
        'menu' => $menuName,
      ]);

      return [
        'success' => TRUE,
        'data' => [
          'id' => $linkId,
          'message' => "Menu link '$title' deleted successfully.",
        ],
      ];
    }
    catch (\Exception $e) {
      $this->auditLogger->logFailure('delete_menu_link', 'menu_link_content', (string) $linkId, ['error' => $e->getMessage()]);
      return [
        'success' => FALSE,
        'error' => 'Failed to delete menu link: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Validate and normalize a menu URI.
   *
   * SECURITY: This method prevents XSS attacks via javascript: URIs,
   * data exfiltration via data: URIs, and other protocol-based attacks.
   *
   * @param string $uri
   *   The URI to validate.
   *
   * @return array
   *   ['valid' => bool, 'uri' => string|null, 'reason' => string|null]
   */
  protected function validateMenuUri(string $uri): array {
    $uri = trim($uri);

    if (empty($uri)) {
      return ['valid' => FALSE, 'uri' => NULL, 'reason' => 'URI cannot be empty'];
    }

    // Check against blocked patterns first (most dangerous).
    foreach (self::BLOCKED_URI_PATTERNS as $pattern) {
      if (preg_match($pattern, $uri)) {
        return ['valid' => FALSE, 'uri' => NULL, 'reason' => 'URI scheme is not allowed for security reasons'];
      }
    }

    // Check if URI already has a valid scheme.
    $hasScheme = FALSE;
    foreach (self::ALLOWED_URI_SCHEMES as $scheme) {
      if (str_starts_with(strtolower($uri), $scheme . ':')) {
        $hasScheme = TRUE;
        break;
      }
    }

    // If no recognized scheme, treat as internal path.
    if (!$hasScheme) {
      // Must start with / for internal paths.
      if (!str_starts_with($uri, '/')) {
        $uri = '/' . $uri;
      }
      $uri = 'internal:' . $uri;
    }

    return ['valid' => TRUE, 'uri' => $uri, 'reason' => NULL];
  }

}
