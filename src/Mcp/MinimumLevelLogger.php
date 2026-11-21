<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Mcp;

use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Passes on only the log records at or above a minimum level.
 *
 * The MCP SDK logs every tool, resource and prompt it registers at debug
 * level, and each step of a request at info level: about 56 records per
 * request to the remote endpoint, all written to the site's logs. Warnings
 * and errors still get through.
 */
final class MinimumLevelLogger extends AbstractLogger {

  /**
   * The PSR-3 levels, from the most verbose to the most severe.
   */
  private const LEVELS = [
    LogLevel::DEBUG,
    LogLevel::INFO,
    LogLevel::NOTICE,
    LogLevel::WARNING,
    LogLevel::ERROR,
    LogLevel::CRITICAL,
    LogLevel::ALERT,
    LogLevel::EMERGENCY,
  ];

  /**
   * Constructs the logger.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger that receives the records that pass.
   * @param string $minimumLevel
   *   The least severe PSR-3 level that passes.
   */
  public function __construct(
    private readonly LoggerInterface $logger,
    private readonly string $minimumLevel = LogLevel::WARNING,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function log($level, string|\Stringable $message, array $context = []): void {
    $rank = array_search($level, self::LEVELS, TRUE);
    // A level PSR-3 does not define is passed on rather than lost.
    if ($rank === FALSE || $rank >= array_search($this->minimumLevel, self::LEVELS, TRUE)) {
      $this->logger->log($level, $message, $context);
    }
  }

}
