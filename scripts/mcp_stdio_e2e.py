#!/usr/bin/env python3

from __future__ import annotations

import argparse
import json
import os
import selectors
import subprocess
import sys
import time


def _require_file(path: str) -> None:
    if not os.path.isfile(path):
        raise SystemExit(f"Missing required file: {path}")


def _read_stdout_json_for_id(
    proc: subprocess.Popen[str],
    sel: selectors.BaseSelector,
    request_id: int,
    timeout_seconds: float,
) -> dict:
    deadline = time.time() + timeout_seconds
    stderr_lines: list[str] = []

    while time.time() < deadline:
        if proc.poll() is not None:
            remaining_err = proc.stderr.read() if proc.stderr else ""
            if remaining_err:
                stderr_lines.append(remaining_err.rstrip("\n"))
            raise SystemExit(
                f"STDIO server exited early with code {proc.returncode}. STDERR:\n"
                + "\n".join(stderr_lines)
            )

        for key, _ in sel.select(timeout=0.1):
            stream = key.fileobj
            channel = key.data

            line = stream.readline()
            if not line:
                continue
            line = line.strip()
            if not line:
                continue

            if channel == "stderr":
                stderr_lines.append(line)
                continue

            try:
                msg = json.loads(line)
            except json.JSONDecodeError as e:
                raise SystemExit(f"Non-JSON output on STDOUT: {line}\nError: {e}") from e

            if isinstance(msg, dict) and msg.get("id") == request_id:
                return msg

    raise SystemExit(f"Timed out waiting for response id={request_id}")


def _send(proc: subprocess.Popen[str], payload: dict) -> None:
    proc.stdin.write(json.dumps(payload, separators=(",", ":")) + "\n")
    proc.stdin.flush()


def main() -> int:
    parser = argparse.ArgumentParser(description="End-to-end STDIO MCP check for mcp_tools_stdio.")
    parser.add_argument("--drupal-root", default="drupal", help="Path to Drupal project root")
    args = parser.parse_args()

    drupal_root = os.path.abspath(args.drupal_root)
    drush = os.path.join(drupal_root, "vendor", "bin", "drush")
    _require_file(drush)

    cmd = [
        drush,
        "mcp-tools:serve",
        "--uid=1",
        "--scope=read",
        "--quiet",
    ]

    proc = subprocess.Popen(
        cmd,
        cwd=drupal_root,
        stdin=subprocess.PIPE,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        text=True,
        bufsize=1,
        env={**os.environ, "SYMFONY_DEPRECATIONS_HELPER": "disabled"},
    )
    assert proc.stdin and proc.stdout and proc.stderr

    os.set_blocking(proc.stdout.fileno(), False)
    os.set_blocking(proc.stderr.fileno(), False)

    sel = selectors.DefaultSelector()
    sel.register(proc.stdout, selectors.EVENT_READ, "stdout")
    sel.register(proc.stderr, selectors.EVENT_READ, "stderr")

    try:
        _send(
            proc,
            {
                "jsonrpc": "2.0",
                "id": 1,
                "method": "initialize",
                "params": {
                    "protocolVersion": "2024-11-05",
                    "clientInfo": {"name": "mcp_tools_ci", "version": "0.0.0"},
                    "capabilities": {},
                },
            },
        )
        init = _read_stdout_json_for_id(proc, sel, 1, 15)

        server_info = (init.get("result") or {}).get("serverInfo") or {}
        if server_info.get("name") != "Drupal MCP Tools":
            raise SystemExit(f"Unexpected serverInfo.name: {server_info!r}")

        _send(proc, {"jsonrpc": "2.0", "method": "notifications/initialized"})

        _send(proc, {"jsonrpc": "2.0", "id": 2, "method": "tools/list"})
        tools_list = _read_stdout_json_for_id(proc, sel, 2, 15)
        tools = (tools_list.get("result") or {}).get("tools") or []
        tool_names = {tool.get("name") for tool in tools if isinstance(tool, dict)}

        if "mcp_tools_get_site_status" not in tool_names:
            raise SystemExit("Expected tool mcp_tools_get_site_status to be registered over STDIO.")

        _send(
            proc,
            {
                "jsonrpc": "2.0",
                "id": 3,
                "method": "tools/call",
                "params": {"name": "mcp_tools_get_site_status", "arguments": {}},
            },
        )
        call = _read_stdout_json_for_id(proc, sel, 3, 30)
        result = call.get("result") or {}
        structured = result.get("structuredContent") or {}
        if not structured.get("success"):
            raise SystemExit(f"Expected success from mcp_tools_get_site_status, got: {structured!r}")

        data = structured.get("data") or {}
        if "drupal_version" not in data:
            raise SystemExit(f"Expected drupal_version in result data, got keys: {sorted(data.keys())}")
    finally:
        try:
            if proc.stdin:
                proc.stdin.close()
        except Exception:
            pass

        try:
            proc.wait(timeout=5)
        except subprocess.TimeoutExpired:
            proc.terminate()
            try:
                proc.wait(timeout=5)
            except subprocess.TimeoutExpired:
                proc.kill()

    return 0


if __name__ == "__main__":
    sys.exit(main())

