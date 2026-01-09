<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_analysis\Service;

/**
 * Facade service for site health and content analysis.
 *
 * This service delegates to specialized analyzer services for each domain.
 *
 * SECURITY NOTE: Analysis services use accessCheck(FALSE) in entity queries.
 * This is intentional for analysis operations because:
 * 1. Analysis tools are read-only - they don't modify data
 * 2. Site analysis requires visibility into ALL content to be accurate
 * 3. MCP access is already controlled via AccessManager scopes
 * 4. Partial analysis results could lead to missed issues
 *
 * Access control is enforced at the MCP layer, not the query layer.
 */
class AnalysisService {

  public function __construct(
    protected LinkAnalyzer $linkAnalyzer,
    protected ContentAuditor $contentAuditor,
    protected SeoAnalyzer $seoAnalyzer,
    protected SecurityAuditor $securityAuditor,
    protected FieldAnalyzer $fieldAnalyzer,
    protected PerformanceAnalyzer $performanceAnalyzer,
    protected AccessibilityAnalyzer $accessibilityAnalyzer,
    protected DuplicateDetector $duplicateDetector,
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
    return $this->linkAnalyzer->findBrokenLinks($limit, $baseUrlOverride);
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
    return $this->contentAuditor->contentAudit($options);
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
    return $this->seoAnalyzer->analyzeSeo($entityType, $entityId);
  }

  /**
   * Perform security audit.
   *
   * @return array
   *   Security audit results.
   */
  public function securityAudit(): array {
    return $this->securityAuditor->securityAudit();
  }

  /**
   * Find unused fields across all entities.
   *
   * @return array
   *   Results with unused fields.
   */
  public function findUnusedFields(): array {
    return $this->fieldAnalyzer->findUnusedFields();
  }

  /**
   * Analyze site performance metrics.
   *
   * @return array
   *   Performance analysis results.
   */
  public function analyzePerformance(): array {
    return $this->performanceAnalyzer->analyzePerformance();
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
    return $this->accessibilityAnalyzer->checkAccessibility($entityType, $entityId);
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
    return $this->duplicateDetector->findDuplicateContent($contentType, $field, $threshold);
  }

}
