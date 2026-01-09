<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_analysis\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use GuzzleHttp\ClientInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Service for analyzing broken links in content.
 */
class LinkAnalyzer {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ClientInterface $httpClient,
    protected ConfigFactoryInterface $configFactory,
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
  public function resolveBaseUrl(?string $baseUrlOverride): string {
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
  public function getAllowedHosts(): array {
    $config = $this->configFactory->get('mcp_tools.settings');
    $hosts = $config->get('allowed_hosts') ?? [];
    $hosts = array_values(array_filter(array_map('strval', (array) $hosts), static fn(string $value): bool => trim($value) !== ''));
    return $hosts;
  }

  /**
   * Checks a host against the configured allowlist patterns.
   */
  public function isHostAllowed(string $host, array $allowedHosts): bool {
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

}
