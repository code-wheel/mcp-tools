<?php

declare(strict_types=1);

namespace Drupal\mcp_tools\Service;

/**
 * Request-scoped bridge from tool execution to the connected MCP client.
 *
 * The MCP call handler arms this service with the SDK ClientGateway for the
 * duration of a tool call; services can then report progress (and later,
 * request sampling) without knowing anything about MCP. Outside an MCP
 * request — cron, drush, other Tool API runtimes — every method is a
 * silent no-op.
 */
final class McpClientBridge {

  /**
   * The SDK client gateway for the active MCP request, when armed.
   *
   * Deliberately untyped: mcp/sdk 0.2.x has no ClientGateway class.
   */
  private ?object $gateway = NULL;

  /**
   * The client's advertised capabilities for the active request.
   *
   * @var array<string, mixed>
   */
  private array $clientCapabilities = [];

  /**
   * Arms or disarms the bridge for the current MCP request.
   *
   * @param object|null $gateway
   *   The SDK ClientGateway, or NULL to disarm.
   * @param array<string, mixed> $clientCapabilities
   *   The client's advertised capabilities.
   */
  public function setGateway(?object $gateway, array $clientCapabilities = []): void {
    $this->gateway = $gateway;
    $this->clientCapabilities = $gateway === NULL ? [] : $clientCapabilities;
  }

  /**
   * Whether the connected client supports LLM sampling.
   */
  public function supportsSampling(): bool {
    return $this->gateway !== NULL
      && \Fiber::getCurrent() !== NULL
      && array_key_exists('sampling', $this->clientCapabilities)
      && method_exists($this->gateway, 'sample');
  }

  /**
   * Asks the client's model to generate text.
   *
   * @param object|string $message
   *   A prompt string or an SDK Content object (e.g. ImageContent).
   * @param int $maxTokens
   *   Token budget for the response.
   * @param int $timeout
   *   Timeout in seconds.
   *
   * @return string|null
   *   The generated text, or NULL when sampling is unavailable or failed.
   */
  public function sample(object|string $message, int $maxTokens = 1000, int $timeout = 120): ?string {
    if (!$this->supportsSampling()) {
      return NULL;
    }
    try {
      $result = $this->gateway->sample($message, $maxTokens, $timeout);
    }
    catch (\Throwable) {
      return NULL;
    }
    $content = $result->content ?? NULL;
    $text = $content->text ?? NULL;
    return is_string($text) && trim($text) !== '' ? trim($text) : NULL;
  }

  /**
   * Reports tool progress to the client.
   *
   * The SDK only sends a notification when the client attached a
   * progressToken to the request, so callers can report unconditionally.
   *
   * @param float $progress
   *   Work completed so far.
   * @param float|null $total
   *   Total work expected, when known.
   * @param string|null $message
   *   Human-readable progress message.
   */
  public function progress(float $progress, ?float $total = NULL, ?string $message = NULL): void {
    if ($this->gateway === NULL || \Fiber::getCurrent() === NULL || !method_exists($this->gateway, 'progress')) {
      return;
    }
    try {
      $this->gateway->progress($progress, $total, $message);
    }
    catch (\Throwable) {
      // Progress is best-effort; never let it break the tool call.
    }
  }

}
