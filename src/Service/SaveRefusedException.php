<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Service;

/**
 * A save refused by SaveGuard: its message says what is wrong.
 */
final class SaveRefusedException extends \InvalidArgumentException {
}
