<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_analysis\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use GuzzleHttp\ClientInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Service for site health and content analysis.
 *
 * SECURITY NOTE: This service uses accessCheck(FALSE) in entity queries.
 * This is intentional for analysis operations because:
 * 1. Analysis tools are read-only - they don't modify data
 * 2. Site analysis requires visibility into ALL content to be accurate
 * 3. MCP access is already controlled via AccessManager scopes
 * 4. Partial analysis results could lead to missed issues
 *
 * Access control is enforced at the MCP layer, not the query layer.
 */
class AnalysisService {

  /**
   * Max bytes allowed for serialized metatag field data.
   *
   * Prevents memory exhaustion on crafted field payloads.
   */
  private const MAX_SERIALIZED_METATAG_BYTES = 65536;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected Connection $database,
    protected ClientInterface $httpClient,
    protected ConfigFactoryInterface $configFactory,
    protected ModuleHandlerInterface $moduleHandler,
    protected RequestStack $requestStack,
  ) {}

  /**
   * Find broken internal links in content.
   *
   * @param int $limit
   *   Maximum number of links to check.
   * @param string|null $baseUrlOverride
   *   Optional base URL override (e.g., https://example.com). Useful for
   *   STDIO/CLI contexts where there is no active HTTP request.
   *
   * @return array
   *   Analysis results with broken links found.
   */
  public function findBrokenLinks(int $limit = 100, ?string $baseUrlOverride = NULL): array {
    $brokenLinks = [];
    $checkedCount = 0;
    $allowedHosts = $this->getAllowedHosts();
    if (empty($allowedHosts)) {
      return [
        'success' => FALSE,
        'error' => 'URL fetching is disabled. Configure allowed hosts in MCP Tools settings (allowed_hosts).',
        'code' => 'URL_FETCH_DISABLED',
      ];
    }

    $baseUrl = $this->resolveBaseUrl($baseUrlOverride);
    if ($baseUrl === '') {
      return [
        'success' => FALSE,
        'error' => 'Unable to determine a base URL for link checking. Provide base_url to the tool or run this tool over HTTP.',
        'code' => 'MISSING_BASE_URL',
      ];
    }

    $baseHost = (string) (parse_url($baseUrl, PHP_URL_HOST) ?? '');
    if ($baseHost === '') {
      return [
        'success' => FALSE,
        'error' => "Invalid base URL '$baseUrl'.",
        'code' => 'INVALID_BASE_URL',
      ];
    }

    if (!$this->isHostAllowed($baseHost, $allowedHosts)) {
      return [
        'success' => FALSE,
        'error' => "Host '$baseHost' is not allowed for URL fetching. Update MCP Tools settings (allowed_hosts).",
        'code' => 'HOST_NOT_ALLOWED',
        'host' => $baseHost,
      ];
    }

    try {
      // Get published nodes with body or text fields.
      $nodeStorage = $this->entityTypeManager->getStorage('node');
      $query = $nodeStorage->getQuery()
        ->condition('status', 1)
        ->accessCheck(FALSE)
        ->range(0, $limit);
      $nids = $query->execute();

      $nodes = $nodeStorage->loadMultiple($nids);
      $linksToCheck = [];

      foreach ($nodes as $node) {
        // Extract links from all text fields.
        foreach ($node->getFields() as $fieldName => $field) {
          $fieldType = $field->getFieldDefinition()->getType();
          if (in_array($fieldType, ['text', 'text_long', 'text_with_summary'])) {
            foreach ($field as $item) {
              $value = $item->value ?? '';
              // Extract href attributes.
              preg_match_all('/href=["\']([^"\']+)["\']/', $value, $matches);
              foreach ($matches[1] as $url) {
                // Only check internal links.
                $isInternal = str_starts_with($url, '/') || ($baseUrl !== '' && str_starts_with($url, $baseUrl));
                if ($isInternal) {
                  $fullUrl = str_starts_with($url, '/') ? $baseUrl . $url : $url;
                  $linksToCheck[] = [
                    'url' => $fullUrl,
                    'original' => $url,
                    'source_nid' => $node->id(),
                    'source_title' => $node->getTitle(),
                    'field' => $fieldName,
                  ];
                }
              }
            }
          }
        }
      }

      // Check each link (limit to avoid timeout).
      $checkLimit = min(count($linksToCheck), $limit);
      for ($i = 0; $i < $checkLimit; $i++) {
        $link = $linksToCheck[$i];
        $checkedCount++;

        $targetHost = (string) (parse_url($link['url'], PHP_URL_HOST) ?? '');
        if ($targetHost === '' || !$this->isHostAllowed($targetHost, $allowedHosts)) {
          $brokenLinks[] = [
            'url' => $link['original'],
            'status' => 'blocked',
            'error' => "Blocked outbound request to host '$targetHost'.",
            'source_nid' => $link['source_nid'],
            'source_title' => $link['source_title'],
            'field' => $link['field'],
          ];
          continue;
        }

        try {
          $response = $this->httpClient->request('HEAD', $link['url'], [
            'timeout' => 5,
            // SECURITY: prevent redirects to hosts outside the allowlist.
            'allow_redirects' => [
              'max' => 3,
              'strict' => TRUE,
              'on_redirect' => function ($request, $response, $uri) use ($allowedHosts): void {
                $redirectHost = (string) (parse_url((string) $uri, PHP_URL_HOST) ?? '');
                if ($redirectHost === '' || !$this->isHostAllowed($redirectHost, $allowedHosts)) {
                  throw new \RuntimeException("Redirect to blocked host '$redirectHost'.");
                }
              },
            ],
            'http_errors' => FALSE,
          ]);

          $statusCode = $response->getStatusCode();
          if ($statusCode === 404) {
            $brokenLinks[] = [
              'url' => $link['original'],
              'status' => 404,
              'source_nid' => $link['source_nid'],
              'source_title' => $link['source_title'],
              'field' => $link['field'],
            ];
          }
          elseif ($statusCode >= 400) {
            $brokenLinks[] = [
              'url' => $link['original'],
              'status' => $statusCode,
              'source_nid' => $link['source_nid'],
              'source_title' => $link['source_title'],
              'field' => $link['field'],
            ];
          }
        }
        catch (\Throwable $e) {
          $brokenLinks[] = [
            'url' => $link['original'],
            'status' => 'error',
            'error' => $e->getMessage(),
            'source_nid' => $link['source_nid'],
            'source_title' => $link['source_title'],
            'field' => $link['field'],
          ];
        }
      }

      $suggestions = [];
      if (!empty($brokenLinks)) {
        $suggestions[] = 'Review and fix or remove the broken links listed above.';
        $suggestions[] = 'Consider setting up URL redirects for moved content.';
        $suggestions[] = 'Use the mcp_redirect module to create redirects for 404 URLs.';
      }

      return [
        'success' => TRUE,
        'data' => [
          'broken_links' => $brokenLinks,
          'total_checked' => $checkedCount,
          'broken_count' => count($brokenLinks),
          'suggestions' => $suggestions,
        ],
      ];
    }
    catch (\Exception $e) {
      return ['success' => FALSE, 'error' => 'Failed to scan for broken links: ' . $e->getMessage()];
    }
  }

  /**
   * Resolve a base URL for outbound HTTP checks.
   */
  private function resolveBaseUrl(?string $baseUrlOverride): string {
    if ($baseUrlOverride !== NULL && trim($baseUrlOverride) !== '') {
      return rtrim(trim($baseUrlOverride), '/');
    }

    $request = $this->requestStack->getCurrentRequest();
    $baseUrl = $request ? $request->getSchemeAndHttpHost() : '';
    return $baseUrl !== '' ? rtrim($baseUrl, '/') : '';
  }

  /**
   * Returns the configured allowlist for outbound URL fetching.
   *
   * @return string[]
   *   Host patterns (supports wildcards like *.example.com).
   */
  private function getAllowedHosts(): array {
    $config = $this->configFactory->get('mcp_tools.settings');
    $hosts = $config->get('allowed_hosts') ?? [];
    $hosts = array_values(array_filter(array_map('strval', (array) $hosts), static fn(string $value): bool => trim($value) !== ''));
    return $hosts;
  }

  /**
   * Checks a host against the configured allowlist patterns.
   */
  private function isHostAllowed(string $host, array $allowedHosts): bool {
    $host = strtolower(trim($host));
    if ($host === '') {
      return FALSE;
    }

    foreach ($allowedHosts as $allowedHost) {
      $allowedHost = strtolower(trim((string) $allowedHost));
      if ($allowedHost === '') {
        continue;
      }

      $quoted = preg_quote($allowedHost, '/');
      $pattern = str_replace('\\*', '.*', $quoted);
      if (preg_match('/^' . $pattern . '$/i', $host)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Audit content for stale, orphaned, or draft content.
   *
   * @param array $options
   *   Options including:
   *   - stale_days: Days since last update to consider stale (default: 365).
   *   - include_drafts: Include draft content (default: true).
   *   - content_types: Array of content types to audit (default: all).
   *
   * @return array
   *   Audit results.
   */
  public function contentAudit(array $options = []): array {
    $staleDays = $options['stale_days'] ?? 365;
    $includeDrafts = $options['include_drafts'] ?? TRUE;
    $contentTypes = $options['content_types'] ?? [];

    try {
      $nodeStorage = $this->entityTypeManager->getStorage('node');
      $results = [
        'stale_content' => [],
        'orphaned_content' => [],
        'drafts' => [],
      ];

      // Find stale content (not updated in X days).
      $staleTimestamp = strtotime("-{$staleDays} days");
      $query = $nodeStorage->getQuery()
        ->condition('changed', $staleTimestamp, '<')
        ->condition('status', 1)
        ->accessCheck(FALSE)
        ->range(0, 50);

      if (!empty($contentTypes)) {
        $query->condition('type', $contentTypes, 'IN');
      }

      $staleNids = $query->execute();
      $staleNodes = $nodeStorage->loadMultiple($staleNids);

      foreach ($staleNodes as $node) {
        $results['stale_content'][] = [
          'nid' => $node->id(),
          'title' => $node->getTitle(),
          'type' => $node->bundle(),
          'last_updated' => date('Y-m-d', $node->getChangedTime()),
          'days_since_update' => floor((time() - $node->getChangedTime()) / 86400),
        ];
      }

      // Find orphaned content (unpublished with no recent views).
      $query = $nodeStorage->getQuery()
        ->condition('status', 0)
        ->accessCheck(FALSE)
        ->range(0, 50);

      if (!empty($contentTypes)) {
        $query->condition('type', $contentTypes, 'IN');
      }

      $orphanedNids = $query->execute();
      $orphanedNodes = $nodeStorage->loadMultiple($orphanedNids);

      foreach ($orphanedNodes as $node) {
        $results['orphaned_content'][] = [
          'nid' => $node->id(),
          'title' => $node->getTitle(),
          'type' => $node->bundle(),
          'created' => date('Y-m-d', $node->getCreatedTime()),
          'last_updated' => date('Y-m-d', $node->getChangedTime()),
        ];
      }

      // Find drafts if requested.
      if ($includeDrafts) {
        // Check for content moderation drafts if module exists.
        if ($this->entityTypeManager->hasDefinition('content_moderation_state')) {
          $query = $this->database->select('content_moderation_state_field_data', 'cms')
            ->fields('cms', ['content_entity_id', 'moderation_state'])
            ->condition('cms.content_entity_type_id', 'node')
            ->condition('cms.moderation_state', 'draft')
            ->range(0, 50);
          $draftResults = $query->execute()->fetchAll();

          $draftNids = array_column($draftResults, 'content_entity_id');
          if (!empty($draftNids)) {
            $draftNodes = $nodeStorage->loadMultiple($draftNids);
            foreach ($draftNodes as $node) {
              $results['drafts'][] = [
                'nid' => $node->id(),
                'title' => $node->getTitle(),
                'type' => $node->bundle(),
                'author' => $node->getOwner()->getDisplayName(),
                'created' => date('Y-m-d', $node->getCreatedTime()),
              ];
            }
          }
        }
        else {
          // Fallback: unpublished content as "drafts".
          $results['drafts'] = $results['orphaned_content'];
        }
      }

      $suggestions = [];
      if (!empty($results['stale_content'])) {
        $suggestions[] = 'Consider reviewing and updating stale content to keep it relevant.';
        $suggestions[] = 'Archive or unpublish content that is no longer needed.';
      }
      if (!empty($results['orphaned_content'])) {
        $suggestions[] = 'Review orphaned content - consider deleting or republishing.';
      }
      if (!empty($results['drafts'])) {
        $suggestions[] = 'Review draft content and either publish or discard.';
      }

      return [
        'success' => TRUE,
        'data' => [
          'stale_content' => $results['stale_content'],
          'stale_count' => count($results['stale_content']),
          'orphaned_content' => $results['orphaned_content'],
          'orphaned_count' => count($results['orphaned_content']),
          'drafts' => $results['drafts'],
          'draft_count' => count($results['drafts']),
          'audit_options' => [
            'stale_days' => $staleDays,
            'content_types' => $contentTypes ?: 'all',
          ],
          'suggestions' => $suggestions,
        ],
      ];
    }
    catch (\Exception $e) {
      return ['success' => FALSE, 'error' => 'Failed to perform content audit: ' . $e->getMessage()];
    }
  }

  /**
   * Analyze SEO for a specific entity.
   *
   * @param string $entityType
   *   Entity type (e.g., 'node', 'taxonomy_term').
   * @param int $entityId
   *   Entity ID.
   *
   * @return array
   *   SEO analysis results.
   */
  public function analyzeSeo(string $entityType, int $entityId): array {
    try {
      $storage = $this->entityTypeManager->getStorage($entityType);
      $entity = $storage->load($entityId);

      if (!$entity) {
        return ['success' => FALSE, 'error' => "Entity {$entityType}/{$entityId} not found."];
      }

      $issues = [];
      $score = 100;
      $title = method_exists($entity, 'getTitle') ? $entity->getTitle() : ($entity->label() ?? '');

      // Check title length.
      $titleLength = strlen($title);
      if ($titleLength < 30) {
        $issues[] = [
          'type' => 'title_short',
          'severity' => 'warning',
          'message' => "Title is too short ({$titleLength} chars). Recommended: 30-60 characters.",
        ];
        $score -= 10;
      }
      elseif ($titleLength > 60) {
        $issues[] = [
          'type' => 'title_long',
          'severity' => 'warning',
          'message' => "Title is too long ({$titleLength} chars). May be truncated in search results.",
        ];
        $score -= 5;
      }

      // Check for meta tags if metatag module is enabled.
      $hasMetaDescription = FALSE;
      if ($this->moduleHandler->moduleExists('metatag') && $entity->hasField('field_metatag')) {
        $metatag = $entity->get('field_metatag')->value;
        if (!empty($metatag)) {
          if (is_string($metatag) && strlen($metatag) <= self::MAX_SERIALIZED_METATAG_BYTES) {
            $metatagData = @unserialize($metatag, ['allowed_classes' => FALSE]);
            if (is_array($metatagData) && !empty($metatagData['description'])) {
              $hasMetaDescription = TRUE;
              $descLength = strlen((string) $metatagData['description']);
              if ($descLength < 120 || $descLength > 160) {
                $issues[] = [
                  'type' => 'meta_description_length',
                  'severity' => 'info',
                  'message' => "Meta description length ({$descLength}) not optimal. Recommended: 120-160 characters.",
                ];
                $score -= 5;
              }
            }
          }
        }
      }

      if (!$hasMetaDescription) {
        $issues[] = [
          'type' => 'missing_meta_description',
          'severity' => 'warning',
          'message' => 'No meta description found. Add one for better search visibility.',
        ];
        $score -= 15;
      }

      // Analyze body content for headings and images.
      $bodyContent = '';
      foreach ($entity->getFields() as $field) {
        $fieldType = $field->getFieldDefinition()->getType();
        if (in_array($fieldType, ['text', 'text_long', 'text_with_summary'])) {
          foreach ($field as $item) {
            $bodyContent .= $item->value ?? '';
          }
        }
      }

      if (!empty($bodyContent)) {
        // Check heading structure.
        preg_match_all('/<h([1-6])[^>]*>/i', $bodyContent, $headings);
        if (empty($headings[1])) {
          $issues[] = [
            'type' => 'no_headings',
            'severity' => 'warning',
            'message' => 'No headings found in content. Use H2-H6 to structure content.',
          ];
          $score -= 10;
        }
        else {
          // Check heading hierarchy.
          $headingLevels = array_map('intval', $headings[1]);
          if (min($headingLevels) === 1) {
            $issues[] = [
              'type' => 'h1_in_content',
              'severity' => 'warning',
              'message' => 'H1 found in body content. H1 should be reserved for page title.',
            ];
            $score -= 5;
          }
        }

        // Check images for alt text.
        preg_match_all('/<img[^>]*>/i', $bodyContent, $images);
        if (!empty($images[0])) {
          $missingAlt = 0;
          foreach ($images[0] as $img) {
            if (!preg_match('/alt\s*=\s*["\'][^"\']+["\']/', $img)) {
              $missingAlt++;
            }
          }
          if ($missingAlt > 0) {
            $issues[] = [
              'type' => 'missing_alt_text',
              'severity' => 'error',
              'message' => "{$missingAlt} image(s) missing alt text. Required for SEO and accessibility.",
            ];
            $score -= ($missingAlt * 10);
          }
        }

        // Check content length.
        $wordCount = str_word_count(strip_tags($bodyContent));
        if ($wordCount < 300) {
          $issues[] = [
            'type' => 'thin_content',
            'severity' => 'info',
            'message' => "Content is thin ({$wordCount} words). Consider adding more content (300+ words recommended).",
          ];
          $score -= 5;
        }
      }
      else {
        $issues[] = [
          'type' => 'no_content',
          'severity' => 'error',
          'message' => 'No body content found.',
        ];
        $score -= 20;
      }

      $score = max(0, $score);

      $suggestions = [];
      if ($score < 70) {
        $suggestions[] = 'Focus on addressing error-level issues first.';
      }
      if (!$hasMetaDescription) {
        $suggestions[] = 'Install and configure the Metatag module for better SEO control.';
      }

      return [
        'success' => TRUE,
        'data' => [
          'entity_type' => $entityType,
          'entity_id' => $entityId,
          'title' => $title,
          'seo_score' => $score,
          'score_rating' => $score >= 80 ? 'good' : ($score >= 60 ? 'needs_improvement' : 'poor'),
          'issues' => $issues,
          'issue_count' => count($issues),
          'suggestions' => $suggestions,
        ],
      ];
    }
    catch (\Exception $e) {
      return ['success' => FALSE, 'error' => 'Failed to analyze SEO: ' . $e->getMessage()];
    }
  }

  /**
   * Perform security audit.
   *
   * @return array
   *   Security audit results.
   */
  public function securityAudit(): array {
    $issues = [];
    $warnings = [];

    try {
      // Check anonymous user permissions.
      $roleStorage = $this->entityTypeManager->getStorage('user_role');
      $anonymousRole = $roleStorage->load('anonymous');
      if ($anonymousRole) {
        $anonPermissions = $anonymousRole->getPermissions();
        $dangerousPerms = [
          'administer nodes',
          'administer users',
          'administer site configuration',
          'administer modules',
          'administer permissions',
          'bypass node access',
          'administer content types',
        ];

        $exposedPerms = array_intersect($anonPermissions, $dangerousPerms);
        if (!empty($exposedPerms)) {
          $issues[] = [
            'type' => 'dangerous_anonymous_permissions',
            'severity' => 'critical',
            'message' => 'Anonymous users have dangerous permissions: ' . implode(', ', $exposedPerms),
          ];
        }
      }

      // Check authenticated user permissions.
      $authenticatedRole = $roleStorage->load('authenticated');
      if ($authenticatedRole) {
        $authPermissions = $authenticatedRole->getPermissions();
        $sensitivePerms = [
          'administer users',
          'administer permissions',
          'administer site configuration',
        ];

        $exposedPerms = array_intersect($authPermissions, $sensitivePerms);
        if (!empty($exposedPerms)) {
          $warnings[] = [
            'type' => 'sensitive_authenticated_permissions',
            'severity' => 'warning',
            'message' => 'All authenticated users have sensitive permissions: ' . implode(', ', $exposedPerms),
          ];
        }
      }

      // Check for overly permissive roles.
      $roles = $roleStorage->loadMultiple();
      $overlyPermissiveRoles = [];
      foreach ($roles as $role) {
        if (in_array($role->id(), ['anonymous', 'authenticated', 'administrator'])) {
          continue;
        }
        $permissions = $role->getPermissions();
        if (in_array('bypass node access', $permissions) || in_array('administer permissions', $permissions)) {
          $overlyPermissiveRoles[] = $role->label();
        }
      }

      if (!empty($overlyPermissiveRoles)) {
        $warnings[] = [
          'type' => 'overly_permissive_roles',
          'severity' => 'warning',
          'message' => 'Roles with elevated permissions: ' . implode(', ', $overlyPermissiveRoles),
        ];
      }

      // Check user registration settings.
      $userSettings = $this->configFactory->get('user.settings');
      $registerMode = $userSettings->get('register');
      if ($registerMode === 'visitors') {
        $warnings[] = [
          'type' => 'open_registration',
          'severity' => 'warning',
          'message' => 'User registration is open to visitors without admin approval.',
        ];
      }

      // Check for users with admin role.
      $userStorage = $this->entityTypeManager->getStorage('user');
      $adminUsers = $userStorage->getQuery()
        ->condition('roles', 'administrator')
        ->condition('status', 1)
        ->accessCheck(FALSE)
        ->execute();

      if (count($adminUsers) > 5) {
        $warnings[] = [
          'type' => 'many_admins',
          'severity' => 'info',
          'message' => 'There are ' . count($adminUsers) . ' active administrator accounts. Review if all need admin access.',
        ];
      }

      // Check for blocked users with admin role.
      $blockedAdmins = $userStorage->getQuery()
        ->condition('roles', 'administrator')
        ->condition('status', 0)
        ->accessCheck(FALSE)
        ->execute();

      if (!empty($blockedAdmins)) {
        $warnings[] = [
          'type' => 'blocked_admins',
          'severity' => 'info',
          'message' => count($blockedAdmins) . ' blocked user(s) still have administrator role assigned.',
        ];
      }

      // Check PHP input format availability.
      if ($this->moduleHandler->moduleExists('php')) {
        $issues[] = [
          'type' => 'php_module_enabled',
          'severity' => 'critical',
          'message' => 'PHP Filter module is enabled. This is a serious security risk.',
        ];
      }

      $suggestions = [];
      if (!empty($issues)) {
        $suggestions[] = 'Address critical security issues immediately.';
      }
      if (!empty($warnings)) {
        $suggestions[] = 'Review permission assignments and apply principle of least privilege.';
      }
      $suggestions[] = 'Regularly audit user accounts and remove unused ones.';
      $suggestions[] = 'Enable two-factor authentication for admin accounts if available.';

      return [
        'success' => TRUE,
        'data' => [
          'critical_issues' => $issues,
          'warnings' => $warnings,
          'critical_count' => count($issues),
          'warning_count' => count($warnings),
          'admin_user_count' => count($adminUsers),
          'registration_mode' => $registerMode,
          'suggestions' => $suggestions,
        ],
      ];
    }
    catch (\Exception $e) {
      return ['success' => FALSE, 'error' => 'Failed to perform security audit: ' . $e->getMessage()];
    }
  }

  /**
   * Find unused fields across all entities.
   *
   * @return array
   *   Results with unused fields.
   */
  public function findUnusedFields(): array {
    try {
      $unusedFields = [];
      $fieldConfigStorage = $this->entityTypeManager->getStorage('field_config');
      $fieldConfigs = $fieldConfigStorage->loadMultiple();

      foreach ($fieldConfigs as $fieldConfig) {
        $entityType = $fieldConfig->getTargetEntityTypeId();
        $bundle = $fieldConfig->getTargetBundle();
        $fieldName = $fieldConfig->getName();

        // Skip base fields.
        if (!str_starts_with($fieldName, 'field_')) {
          continue;
        }

        try {
          $storage = $this->entityTypeManager->getStorage($entityType);
          $query = $storage->getQuery()
            ->condition($fieldName, NULL, 'IS NOT NULL')
            ->accessCheck(FALSE)
            ->range(0, 1);

          if ($entityType === 'node' || $entityType === 'taxonomy_term' || $entityType === 'media') {
            $query->condition('type', $bundle);
          }
          elseif ($entityType === 'paragraph') {
            $query->condition('type', $bundle);
          }

          $count = $query->count()->execute();

          if ($count === 0) {
            $unusedFields[] = [
              'field_name' => $fieldName,
              'entity_type' => $entityType,
              'bundle' => $bundle,
              'field_type' => $fieldConfig->getType(),
              'label' => $fieldConfig->getLabel(),
            ];
          }
        }
        catch (\Exception $e) {
          // Skip fields that can't be queried.
          continue;
        }
      }

      $suggestions = [];
      if (!empty($unusedFields)) {
        $suggestions[] = 'Review unused fields and consider removing them to simplify content editing.';
        $suggestions[] = 'Before deleting, verify the field is not used in views or templates.';
        $suggestions[] = 'Use drush field:delete to remove fields safely.';
      }
      else {
        $suggestions[] = 'All configured fields are in use.';
      }

      return [
        'success' => TRUE,
        'data' => [
          'unused_fields' => $unusedFields,
          'unused_count' => count($unusedFields),
          'suggestions' => $suggestions,
        ],
      ];
    }
    catch (\Exception $e) {
      return ['success' => FALSE, 'error' => 'Failed to find unused fields: ' . $e->getMessage()];
    }
  }

  /**
   * Analyze site performance metrics.
   *
   * @return array
   *   Performance analysis results.
   */
  public function analyzePerformance(): array {
    try {
      $results = [
        'cache_status' => [],
        'slow_queries' => [],
        'watchdog_errors' => [],
        'render_times' => [],
      ];

      // Get cache settings.
      $systemPerformance = $this->configFactory->get('system.performance');
      $results['cache_status'] = [
        'page_cache_max_age' => $systemPerformance->get('cache.page.max_age'),
        'css_aggregation' => $systemPerformance->get('css.preprocess'),
        'js_aggregation' => $systemPerformance->get('js.preprocess'),
      ];

      // Analyze watchdog for performance issues (if dblog enabled).
      if ($this->moduleHandler->moduleExists('dblog')) {
        // Get recent PHP errors.
        $query = $this->database->select('watchdog', 'w')
          ->fields('w', ['message', 'variables', 'timestamp', 'type'])
          ->condition('type', ['php', 'system'], 'IN')
          ->condition('severity', [0, 1, 2, 3], 'IN')
          ->orderBy('timestamp', 'DESC')
          ->range(0, 20);
        $errorLogs = $query->execute()->fetchAll();

        foreach ($errorLogs as $log) {
          $results['watchdog_errors'][] = [
            'type' => $log->type,
            'message' => substr($log->message, 0, 200),
            'timestamp' => date('Y-m-d H:i:s', $log->timestamp),
          ];
        }

        // Look for slow page warnings.
        $slowQuery = $this->database->select('watchdog', 'w')
          ->fields('w', ['message', 'variables', 'timestamp'])
          ->condition('message', '%slow%', 'LIKE')
          ->orderBy('timestamp', 'DESC')
          ->range(0, 10);
        $slowLogs = $slowQuery->execute()->fetchAll();

        foreach ($slowLogs as $log) {
          $results['slow_queries'][] = [
            'message' => substr($log->message, 0, 200),
            'timestamp' => date('Y-m-d H:i:s', $log->timestamp),
          ];
        }
      }

      // Check database size.
      $dbSizeQuery = $this->database->query("
        SELECT table_name,
               ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
        ORDER BY (data_length + index_length) DESC
        LIMIT 10
      ");
      $largestTables = $dbSizeQuery->fetchAll();

      $results['database'] = [
        'largest_tables' => array_map(function ($row) {
          return [
            'table' => $row->table_name,
            'size_mb' => $row->size_mb,
          ];
        }, $largestTables),
      ];

      // Generate suggestions.
      $suggestions = [];

      if ($results['cache_status']['page_cache_max_age'] === 0) {
        $suggestions[] = 'Enable page caching for better performance (set max_age > 0).';
      }
      if (!$results['cache_status']['css_aggregation']) {
        $suggestions[] = 'Enable CSS aggregation to reduce HTTP requests.';
      }
      if (!$results['cache_status']['js_aggregation']) {
        $suggestions[] = 'Enable JavaScript aggregation to reduce HTTP requests.';
      }
      if (!empty($results['watchdog_errors'])) {
        $suggestions[] = 'Review and fix PHP errors in the watchdog log.';
      }
      if (!empty($results['slow_queries'])) {
        $suggestions[] = 'Investigate slow queries and consider adding database indexes.';
      }

      $suggestions[] = 'Consider using Redis or Memcache for cache backend.';
      $suggestions[] = 'Review Views queries and enable Views caching where appropriate.';

      return [
        'success' => TRUE,
        'data' => [
          'cache_status' => $results['cache_status'],
          'watchdog_errors' => $results['watchdog_errors'],
          'error_count' => count($results['watchdog_errors']),
          'slow_queries' => $results['slow_queries'],
          'database' => $results['database'],
          'suggestions' => $suggestions,
        ],
      ];
    }
    catch (\Exception $e) {
      return ['success' => FALSE, 'error' => 'Failed to analyze performance: ' . $e->getMessage()];
    }
  }

  /**
   * Check accessibility for a specific entity.
   *
   * @param string $entityType
   *   Entity type.
   * @param int $entityId
   *   Entity ID.
   *
   * @return array
   *   Accessibility check results.
   */
  public function checkAccessibility(string $entityType, int $entityId): array {
    try {
      $storage = $this->entityTypeManager->getStorage($entityType);
      $entity = $storage->load($entityId);

      if (!$entity) {
        return ['success' => FALSE, 'error' => "Entity {$entityType}/{$entityId} not found."];
      }

      $issues = [];
      $title = method_exists($entity, 'getTitle') ? $entity->getTitle() : ($entity->label() ?? '');

      // Collect all text content.
      $bodyContent = '';
      foreach ($entity->getFields() as $field) {
        $fieldType = $field->getFieldDefinition()->getType();
        if (in_array($fieldType, ['text', 'text_long', 'text_with_summary'])) {
          foreach ($field as $item) {
            $bodyContent .= $item->value ?? '';
          }
        }
      }

      if (!empty($bodyContent)) {
        // Check images for alt text.
        preg_match_all('/<img[^>]*>/i', $bodyContent, $images);
        foreach ($images[0] as $img) {
          if (!preg_match('/alt\s*=/', $img)) {
            $issues[] = [
              'type' => 'missing_alt',
              'severity' => 'error',
              'wcag' => '1.1.1',
              'message' => 'Image missing alt attribute.',
              'element' => substr($img, 0, 100),
            ];
          }
          elseif (preg_match('/alt\s*=\s*["\']["\']/', $img)) {
            // Empty alt - check if decorative.
            if (!preg_match('/role\s*=\s*["\']presentation["\']/', $img)) {
              $issues[] = [
                'type' => 'empty_alt',
                'severity' => 'warning',
                'wcag' => '1.1.1',
                'message' => 'Image has empty alt. If decorative, add role="presentation".',
                'element' => substr($img, 0, 100),
              ];
            }
          }
        }

        // Check heading hierarchy.
        preg_match_all('/<h([1-6])[^>]*>(.*?)<\/h\1>/is', $bodyContent, $headings, PREG_SET_ORDER);
        if (!empty($headings)) {
          $lastLevel = 0;
          foreach ($headings as $heading) {
            $level = (int) $heading[1];
            if ($lastLevel > 0 && $level > $lastLevel + 1) {
              $issues[] = [
                'type' => 'heading_skip',
                'severity' => 'warning',
                'wcag' => '1.3.1',
                'message' => "Heading level skipped from H{$lastLevel} to H{$level}.",
                'element' => substr($heading[0], 0, 50),
              ];
            }
            $lastLevel = $level;
          }
        }

        // Check for empty links.
        preg_match_all('/<a[^>]*>(.*?)<\/a>/is', $bodyContent, $links, PREG_SET_ORDER);
        foreach ($links as $link) {
          $linkText = strip_tags($link[1]);
          $linkText = trim($linkText);
          if (empty($linkText)) {
            // Check for aria-label.
            if (!preg_match('/aria-label\s*=/', $link[0])) {
              $issues[] = [
                'type' => 'empty_link',
                'severity' => 'error',
                'wcag' => '2.4.4',
                'message' => 'Link has no accessible text.',
                'element' => substr($link[0], 0, 100),
              ];
            }
          }
          elseif (in_array(strtolower($linkText), ['click here', 'read more', 'here', 'more', 'link'])) {
            $issues[] = [
              'type' => 'generic_link_text',
              'severity' => 'warning',
              'wcag' => '2.4.4',
              'message' => "Link text '{$linkText}' is not descriptive.",
              'element' => substr($link[0], 0, 100),
            ];
          }
        }

        // Check for tables without headers.
        preg_match_all('/<table[^>]*>.*?<\/table>/is', $bodyContent, $tables);
        foreach ($tables[0] as $table) {
          if (!preg_match('/<th[^>]*>/i', $table)) {
            $issues[] = [
              'type' => 'table_no_headers',
              'severity' => 'error',
              'wcag' => '1.3.1',
              'message' => 'Table has no header cells (th).',
            ];
          }
        }

        // Check for color contrast indicators (text mentioning colors).
        if (preg_match('/\b(red|green|blue|click the colored)\b/i', strip_tags($bodyContent))) {
          $issues[] = [
            'type' => 'color_reference',
            'severity' => 'info',
            'wcag' => '1.4.1',
            'message' => 'Content may reference color. Ensure color is not the only means of conveying information.',
          ];
        }
      }

      $errorCount = count(array_filter($issues, fn($i) => $i['severity'] === 'error'));
      $warningCount = count(array_filter($issues, fn($i) => $i['severity'] === 'warning'));

      $suggestions = [];
      if ($errorCount > 0) {
        $suggestions[] = 'Fix error-level accessibility issues first.';
      }
      if (count(array_filter($issues, fn($i) => $i['type'] === 'missing_alt')) > 0) {
        $suggestions[] = 'Add descriptive alt text to all informative images.';
      }
      if (count(array_filter($issues, fn($i) => $i['type'] === 'generic_link_text')) > 0) {
        $suggestions[] = 'Use descriptive link text that makes sense out of context.';
      }
      $suggestions[] = 'Consider running a full accessibility audit with tools like WAVE or axe.';

      return [
        'success' => TRUE,
        'data' => [
          'entity_type' => $entityType,
          'entity_id' => $entityId,
          'title' => $title,
          'issues' => $issues,
          'error_count' => $errorCount,
          'warning_count' => $warningCount,
          'info_count' => count($issues) - $errorCount - $warningCount,
          'suggestions' => $suggestions,
        ],
      ];
    }
    catch (\Exception $e) {
      return ['success' => FALSE, 'error' => 'Failed to check accessibility: ' . $e->getMessage()];
    }
  }

  /**
   * Find duplicate content based on field similarity.
   *
   * @param string $contentType
   *   Content type machine name.
   * @param string $field
   *   Field to compare (default: 'title').
   * @param float $threshold
   *   Similarity threshold 0-1 (default: 0.8).
   *
   * @return array
   *   Results with potential duplicates.
   */
  public function findDuplicateContent(string $contentType, string $field = 'title', float $threshold = 0.8): array {
    try {
      $nodeStorage = $this->entityTypeManager->getStorage('node');

      // Verify content type exists.
      $nodeTypeStorage = $this->entityTypeManager->getStorage('node_type');
      if (!$nodeTypeStorage->load($contentType)) {
        return ['success' => FALSE, 'error' => "Content type '{$contentType}' not found."];
      }

      // Load all nodes of this type.
      $query = $nodeStorage->getQuery()
        ->condition('type', $contentType)
        ->accessCheck(FALSE)
        ->range(0, 500);
      $nids = $query->execute();
      $nodes = $nodeStorage->loadMultiple($nids);

      // Extract values for comparison.
      $items = [];
      foreach ($nodes as $node) {
        $value = '';
        if ($field === 'title') {
          $value = $node->getTitle();
        }
        elseif ($node->hasField($field)) {
          $fieldValue = $node->get($field)->getValue();
          if (!empty($fieldValue[0]['value'])) {
            $value = strip_tags($fieldValue[0]['value']);
          }
        }
        elseif ($node->hasField('field_' . $field)) {
          $fieldValue = $node->get('field_' . $field)->getValue();
          if (!empty($fieldValue[0]['value'])) {
            $value = strip_tags($fieldValue[0]['value']);
          }
        }

        if (!empty($value)) {
          $items[] = [
            'nid' => $node->id(),
            'title' => $node->getTitle(),
            'value' => $value,
            'status' => $node->isPublished() ? 'published' : 'unpublished',
            'created' => $node->getCreatedTime(),
          ];
        }
      }

      // Find duplicates using similarity comparison.
      $duplicates = [];
      $compared = [];

      for ($i = 0; $i < count($items); $i++) {
        for ($j = $i + 1; $j < count($items); $j++) {
          $key = $items[$i]['nid'] . '-' . $items[$j]['nid'];
          if (isset($compared[$key])) {
            continue;
          }
          $compared[$key] = TRUE;

          // Calculate similarity.
          $similarity = $this->calculateSimilarity($items[$i]['value'], $items[$j]['value']);

          if ($similarity >= $threshold) {
            $duplicates[] = [
              'item1' => [
                'nid' => $items[$i]['nid'],
                'title' => $items[$i]['title'],
                'status' => $items[$i]['status'],
                'created' => date('Y-m-d', $items[$i]['created']),
              ],
              'item2' => [
                'nid' => $items[$j]['nid'],
                'title' => $items[$j]['title'],
                'status' => $items[$j]['status'],
                'created' => date('Y-m-d', $items[$j]['created']),
              ],
              'similarity' => round($similarity * 100, 1) . '%',
              'field_compared' => $field,
            ];
          }
        }
      }

      // Sort by similarity descending.
      usort($duplicates, fn($a, $b) => floatval($b['similarity']) <=> floatval($a['similarity']));

      $suggestions = [];
      if (!empty($duplicates)) {
        $suggestions[] = 'Review potential duplicates and merge or delete as appropriate.';
        $suggestions[] = 'Keep the older/more complete version and redirect the duplicate.';
        $suggestions[] = 'Consider using the Entity Clone module to track intentional copies.';
      }
      else {
        $suggestions[] = 'No duplicates found at the current threshold. Try lowering the threshold to find more similar content.';
      }

      return [
        'success' => TRUE,
        'data' => [
          'content_type' => $contentType,
          'field_compared' => $field,
          'threshold' => $threshold,
          'items_analyzed' => count($items),
          'duplicates' => $duplicates,
          'duplicate_count' => count($duplicates),
          'suggestions' => $suggestions,
        ],
      ];
    }
    catch (\Exception $e) {
      return ['success' => FALSE, 'error' => 'Failed to find duplicates: ' . $e->getMessage()];
    }
  }

  /**
   * Calculate similarity between two strings.
   *
   * @param string $str1
   *   First string.
   * @param string $str2
   *   Second string.
   *
   * @return float
   *   Similarity score between 0 and 1.
   */
  protected function calculateSimilarity(string $str1, string $str2): float {
    // Normalize strings.
    $str1 = strtolower(trim($str1));
    $str2 = strtolower(trim($str2));

    if ($str1 === $str2) {
      return 1.0;
    }

    if (empty($str1) || empty($str2)) {
      return 0.0;
    }

    // Use similar_text for percentage.
    $similarity = 0;
    similar_text($str1, $str2, $similarity);

    return $similarity / 100;
  }

}
