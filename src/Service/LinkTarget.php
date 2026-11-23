<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Service;

use Drupal\Core\Path\PathValidatorInterface;

/**
 * Whether something answers at a link's target on this site.
 *
 * A model asked to point something at a content item it knows by title
 * makes the path up ("/news/hello-world"), and a tool that stores a path
 * says success. The redirect and the menu link tools ask this, and say in
 * their answer when nothing is there: they still store the link, because a
 * page can come later.
 */
final class LinkTarget {

  public function __construct(
    private readonly PathValidatorInterface $pathValidator,
  ) {}

  /**
   * Whether something answers at a URI, as far as this site knows.
   *
   * Only a path of this site is looked at (internal:/... or /...). Another
   * site, and entity:, route: and base: addresses, count as answering.
   */
  public function answers(string $uri): bool {
    if (str_starts_with($uri, 'internal:')) {
      $uri = substr($uri, strlen('internal:'));
    }
    elseif (preg_match('#^[a-z][a-z0-9+.-]*:#i', $uri)) {
      return TRUE;
    }
    $path = (string) preg_replace('/[?#].*$/', '', '/' . ltrim($uri, '/'));
    if ($path === '/' || $path === '/<front>' || $path === '/<nolink>' || $path === '/<none>') {
      return TRUE;
    }
    // A route with what it needs (a node that exists), or a path alias.
    if ($this->pathValidator->getUrlIfValidWithoutAccessCheck($path) !== FALSE) {
      return TRUE;
    }
    // A file under the web root.
    return is_file(DRUPAL_ROOT . $path);
  }

  /**
   * The warning for a target where nothing answers, or NULL.
   *
   * @param string $uri
   *   The target as it was given, or as stored.
   * @param string $fixWith
   *   The tool that sets the target right, for the hint.
   */
  public function warning(string $uri, string $fixWith): ?string {
    if ($this->answers($uri)) {
      return NULL;
    }
    $shown = str_starts_with($uri, 'internal:') ? substr($uri, strlen('internal:')) : $uri;
    return "Nothing answers at '$shown' on this site now: no page, no path alias and no file."
      . " If a content item was meant, find it with mcp_tools_search_content and give its path, like /node/12, to $fixWith.";
  }

}
