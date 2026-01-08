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


def _run_drush(drupal_root: str, args: list[str]) -> str:
    cmd = [os.path.join(drupal_root, "vendor", "bin", "drush"), *args]
    completed = subprocess.run(
        cmd,
        cwd=drupal_root,
        check=True,
        stdout=subprocess.PIPE,
        stderr=subprocess.STDOUT,
        text=True,
        env={**os.environ, "SYMFONY_DEPRECATIONS_HELPER": "disabled"},
    )
    return completed.stdout


def _set_config(drupal_root: str, config_name: str, key: str, value: object) -> None:
    encoded = json.dumps(value, separators=(",", ":"))
    php = (
        f'$config = \\Drupal::configFactory()->getEditable("{config_name}"); '
        f'$config->set("{key}", json_decode(\'{encoded}\', true)); '
        "$config->save();"
    )
    _run_drush(drupal_root, ["eval", php])


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


def _start_stdio_server(drupal_root: str, scope: str) -> tuple[subprocess.Popen[str], selectors.BaseSelector]:
    drush = os.path.join(drupal_root, "vendor", "bin", "drush")
    cmd = [
        drush,
        "mcp-tools:serve",
        "--uid=1",
        f"--scope={scope}",
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

    return proc, sel


def _stop_stdio_server(proc: subprocess.Popen[str]) -> None:
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


def _initialize_and_list_tools(proc: subprocess.Popen[str], sel: selectors.BaseSelector, request_id: int) -> set[str]:
    _send(
        proc,
        {
            "jsonrpc": "2.0",
            "id": request_id,
            "method": "initialize",
            "params": {
                "protocolVersion": "2025-06-18",
                "clientInfo": {"name": "mcp_tools_ci", "version": "0.0.0"},
                "capabilities": {},
            },
        },
    )
    init = _read_stdout_json_for_id(proc, sel, request_id, 15)

    server_info = (init.get("result") or {}).get("serverInfo") or {}
    if server_info.get("name") != "Drupal MCP Tools":
        raise SystemExit(f"Unexpected serverInfo.name: {server_info!r}")

    _send(proc, {"jsonrpc": "2.0", "method": "notifications/initialized"})

    list_id = request_id + 1
    _send(proc, {"jsonrpc": "2.0", "id": list_id, "method": "tools/list"})
    tools_list = _read_stdout_json_for_id(proc, sel, list_id, 15)
    tools = (tools_list.get("result") or {}).get("tools") or []
    return {tool.get("name") for tool in tools if isinstance(tool, dict)}


def main() -> int:
    parser = argparse.ArgumentParser(description="End-to-end STDIO MCP check for mcp_tools_stdio.")
    parser.add_argument("--drupal-root", default="drupal", help="Path to Drupal project root")
    args = parser.parse_args()

    drupal_root = os.path.abspath(args.drupal_root)
    drush = os.path.join(drupal_root, "vendor", "bin", "drush")
    _require_file(drush)

    # Ensure required submodules are enabled for the representative tool calls.
    _run_drush(drupal_root, ["en", "mcp_tools_stdio", "mcp_tools_cache", "mcp_tools_structure", "-y"])
    _run_drush(drupal_root, ["cr"])

    # Ensure a known baseline so this check is deterministic on local reruns.
    _set_config(drupal_root, "mcp_tools.settings", "access.read_only_mode", False)
    _set_config(drupal_root, "mcp_tools.settings", "access.config_only_mode", False)

    try:
        # 1) Read scope: read tools allowed, write tools denied.
        proc, sel = _start_stdio_server(drupal_root, scope="read")
        try:
            tool_names = _initialize_and_list_tools(proc, sel, request_id=1)

            if "mcp_tools_get_site_status" not in tool_names:
                raise SystemExit("Expected tool mcp_tools_get_site_status to be registered over STDIO.")
            if "mcp_tools_list_content_types" not in tool_names:
                raise SystemExit("Expected tool mcp_tools_list_content_types to be registered over STDIO.")
            if "mcp_cache_clear_all" not in tool_names:
                raise SystemExit("Expected tool mcp_cache_clear_all to be registered over STDIO.")

            _send(
                proc,
                {
                    "jsonrpc": "2.0",
                    "id": 10,
                    "method": "tools/call",
                    "params": {"name": "mcp_tools_get_site_status", "arguments": {}},
                },
            )
            call = _read_stdout_json_for_id(proc, sel, 10, 30)
            result = call.get("result") or {}
            structured = result.get("structuredContent") or {}
            if not structured.get("success"):
                raise SystemExit(f"Expected success from mcp_tools_get_site_status, got: {structured!r}")

            data = structured.get("data") or {}
            if "drupal_version" not in data:
                raise SystemExit(f"Expected drupal_version in result data, got keys: {sorted(data.keys())}")

            _send(
                proc,
                {
                    "jsonrpc": "2.0",
                    "id": 11,
                    "method": "tools/call",
                    "params": {"name": "mcp_tools_list_content_types", "arguments": {}},
                },
            )
            call = _read_stdout_json_for_id(proc, sel, 11, 30)
            result = call.get("result") or {}
            structured = result.get("structuredContent") or {}
            if not structured.get("success"):
                raise SystemExit(f"Expected success from mcp_tools_list_content_types, got: {structured!r}")

            # Ops write should be denied under read scope.
            _send(
                proc,
                {
                    "jsonrpc": "2.0",
                    "id": 12,
                    "method": "tools/call",
                    "params": {"name": "mcp_cache_clear_all", "arguments": {}},
                },
            )
            call = _read_stdout_json_for_id(proc, sel, 12, 60)
            result = call.get("result") or {}
            if result.get("isError") is not True:
                raise SystemExit(f"Expected cache clear to be denied under read scope, got: {result!r}")
        finally:
            _stop_stdio_server(proc)

        # 2) Read+write scope: representative config + ops writes allowed.
        proc, sel = _start_stdio_server(drupal_root, scope="read,write")
        try:
            tool_names = _initialize_and_list_tools(proc, sel, request_id=101)

            if "mcp_structure_create_content_type" not in tool_names:
                raise SystemExit("Expected tool mcp_structure_create_content_type to be registered over STDIO.")
            if "mcp_cache_clear_all" not in tool_names:
                raise SystemExit("Expected tool mcp_cache_clear_all to be registered over STDIO.")

            _send(
                proc,
                {
                    "jsonrpc": "2.0",
                    "id": 110,
                    "method": "tools/call",
                    "params": {
                        "name": "mcp_structure_create_content_type",
                        "arguments": {
                            "id": "mcp_stdio_type",
                            "label": "MCP STDIO Type",
                            "description": "",
                            "create_body": False,
                        },
                    },
                },
            )
            call = _read_stdout_json_for_id(proc, sel, 110, 60)
            result = call.get("result") or {}
            if result.get("isError") is True:
                structured = result.get("structuredContent") or {}
                error_message = (structured.get("message") or structured.get("error") or "").lower()
                if "already exists" not in error_message:
                    raise SystemExit(f"Expected create_content_type to succeed, got: {result!r}")

            _send(
                proc,
                {
                    "jsonrpc": "2.0",
                    "id": 111,
                    "method": "tools/call",
                    "params": {"name": "mcp_cache_clear_all", "arguments": {}},
                },
            )
            call = _read_stdout_json_for_id(proc, sel, 111, 60)
            result = call.get("result") or {}
            if result.get("isError") is True:
                raise SystemExit(f"Expected cache clear to succeed under write scope, got: {result!r}")
        finally:
            _stop_stdio_server(proc)
    finally:
        pass

    return 0


if __name__ == "__main__":
    sys.exit(main())
