#!/usr/bin/env python3

from __future__ import annotations

import argparse
import json
import os
import subprocess
import sys
import time
import urllib.parse
import urllib.error
import urllib.request


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


def _create_api_key(drupal_root: str, label: str, scopes: str) -> str:
    output = _run_drush(
        drupal_root,
        ["mcp-tools:remote-key-create", f"--label={label}", f"--scopes={scopes}"],
    )
    for line in output.splitlines():
        if line.startswith("API Key:"):
            return line.split("API Key:", 1)[1].strip()
    sanitized = "\n".join(
        "[redacted]" if line.startswith("API Key:") else line for line in output.splitlines()
    )
    raise SystemExit(f"Failed to parse API key from drush output:\n{sanitized}")


def _post_jsonrpc(
    url: str,
    payload: dict,
    api_key: str | None,
    session_id: str | None,
    timeout_seconds: float = 10,
) -> tuple[int, dict[str, str], str]:
    body = json.dumps(payload, separators=(",", ":")).encode("utf-8")

    headers = {
        "Content-Type": "application/json",
        "Accept": "application/json, text/event-stream",
    }
    if api_key:
        headers["Authorization"] = f"Bearer {api_key}"
    if session_id:
        headers["Mcp-Session-Id"] = session_id

    req = urllib.request.Request(url=url, data=body, headers=headers, method="POST")

    try:
        with urllib.request.urlopen(req, timeout=timeout_seconds) as resp:
            status = resp.getcode()
            resp_headers = {k: v for (k, v) in resp.headers.items()}
            resp_body = resp.read().decode("utf-8")
            return status, resp_headers, resp_body
    except urllib.error.HTTPError as e:
        status = e.code
        resp_headers = {k: v for (k, v) in e.headers.items()}
        resp_body = e.read().decode("utf-8") if e.fp else ""
        return status, resp_headers, resp_body


def _wait_for_http(url: str, timeout_seconds: float = 10) -> None:
    deadline = time.time() + timeout_seconds
    last_error: Exception | None = None
    while time.time() < deadline:
        try:
            req = urllib.request.Request(url=url, method="GET")
            with urllib.request.urlopen(req, timeout=2):
                return
        except Exception as e:
            last_error = e
            time.sleep(0.2)
    raise SystemExit(f"HTTP server did not become ready: {last_error}")


def _extract_jsonrpc_from_sse(body: str, expected_id: int) -> dict | None:
    data_lines: list[str] = []
    for line in body.splitlines():
        if line == "":
            if not data_lines:
                continue
            payload = "\n".join(data_lines)
            data_lines = []
            try:
                decoded = json.loads(payload)
            except json.JSONDecodeError:
                continue
            if isinstance(decoded, dict) and decoded.get("id") == expected_id:
                return decoded
            continue

        if line.startswith("data:"):
            data_lines.append(line.split("data:", 1)[1].lstrip())

    if data_lines:
        payload = "\n".join(data_lines)
        try:
            decoded = json.loads(payload)
        except json.JSONDecodeError:
            return None
        if isinstance(decoded, dict) and decoded.get("id") == expected_id:
            return decoded
    return None


def _assert_jsonrpc_result(body: str, expected_id: int) -> dict:
    try:
        decoded = json.loads(body)
    except json.JSONDecodeError as e:
        decoded = _extract_jsonrpc_from_sse(body, expected_id)
        if decoded is None:
            raise SystemExit(f"Expected JSON body, got: {body}\nError: {e}") from e

    if not isinstance(decoded, dict):
        raise SystemExit(f"Expected JSON-RPC response object, got: {decoded!r}")
    if decoded.get("id") != expected_id:
        raise SystemExit(f"Expected id={expected_id}, got: {decoded.get('id')}")
    if "result" not in decoded and "error" not in decoded:
        raise SystemExit(f"Expected result/error in response, got: {decoded!r}")

    return decoded


def _run_sequence(base_url: str, api_key: str, expect_write_allowed: bool) -> None:
    url = base_url.rstrip("/") + "/_mcp_tools"

    init_payload = {
        "jsonrpc": "2.0",
        "id": 1,
        "method": "initialize",
        "params": {
            "protocolVersion": "2024-11-05",
            "clientInfo": {"name": "mcp_tools_ci", "version": "0.0.0"},
            "capabilities": {},
        },
    }
    status, headers, body = _post_jsonrpc(url, init_payload, api_key=api_key, session_id=None)
    if status != 200:
        raise SystemExit(f"initialize expected 200, got {status}: {body}")

    session_id = headers.get("Mcp-Session-Id")
    if not session_id:
        raise SystemExit(f"initialize did not return Mcp-Session-Id header. Headers: {headers!r}")

    init_resp = _assert_jsonrpc_result(body, 1)
    server_info = (init_resp.get("result") or {}).get("serverInfo") or {}
    if server_info.get("name") != "Drupal MCP Tools":
        raise SystemExit(f"Unexpected serverInfo.name: {server_info!r}")

    status, _, _ = _post_jsonrpc(
        url,
        {"jsonrpc": "2.0", "method": "notifications/initialized"},
        api_key=api_key,
        session_id=session_id,
    )
    if status not in (200, 202):
        raise SystemExit(f"notifications/initialized expected 200/202, got {status}")

    status, _, body = _post_jsonrpc(
        url,
        {"jsonrpc": "2.0", "id": 2, "method": "tools/list"},
        api_key=api_key,
        session_id=session_id,
    )
    if status != 200:
        raise SystemExit(f"tools/list expected 200, got {status}: {body}")

    tools_resp = _assert_jsonrpc_result(body, 2)
    tools = (tools_resp.get("result") or {}).get("tools") or []
    tool_names = {tool.get("name") for tool in tools if isinstance(tool, dict)}

    if "mcp_tools_get_site_status" not in tool_names:
        raise SystemExit("Expected tool mcp_tools_get_site_status to be registered over HTTP.")
    if "mcp_cache_clear_all" not in tool_names:
        raise SystemExit("Expected tool mcp_cache_clear_all to be registered over HTTP.")

    status, _, body = _post_jsonrpc(
        url,
        {
            "jsonrpc": "2.0",
            "id": 3,
            "method": "tools/call",
            "params": {"name": "mcp_tools_get_site_status", "arguments": {}},
        },
        api_key=api_key,
        session_id=session_id,
        timeout_seconds=30,
    )
    if status != 200:
        raise SystemExit(f"tools/call(get_site_status) expected 200, got {status}: {body}")
    status_resp = _assert_jsonrpc_result(body, 3)
    status_structured = (status_resp.get("result") or {}).get("structuredContent") or {}
    if not status_structured.get("success"):
        raise SystemExit(f"Expected success from mcp_tools_get_site_status, got: {status_structured!r}")

    status, _, body = _post_jsonrpc(
        url,
        {"jsonrpc": "2.0", "id": 4, "method": "tools/call", "params": {"name": "mcp_cache_clear_all", "arguments": {}}},
        api_key=api_key,
        session_id=session_id,
        timeout_seconds=60,
    )
    if status != 200:
        raise SystemExit(f"tools/call(mcp_cache_clear_all) expected 200, got {status}: {body}")
    clear_resp = _assert_jsonrpc_result(body, 4)
    clear_result = clear_resp.get("result") or {}

    if expect_write_allowed:
        if clear_result.get("isError") is True:
            raise SystemExit(f"Expected cache clear to succeed, got isError=true: {clear_result!r}")
        clear_structured = clear_result.get("structuredContent") or {}
        if clear_structured.get("success") is not True:
            raise SystemExit(f"Expected structuredContent.success=true, got: {clear_structured!r}")
    else:
        if clear_result.get("isError") is not True:
            raise SystemExit(f"Expected cache clear to be denied (isError=true), got: {clear_result!r}")


def _run_config_only_sequence(base_url: str, api_key: str) -> None:
    url = base_url.rstrip("/") + "/_mcp_tools"

    init_payload = {
        "jsonrpc": "2.0",
        "id": 101,
        "method": "initialize",
        "params": {
            "protocolVersion": "2024-11-05",
            "clientInfo": {"name": "mcp_tools_ci_config_only", "version": "0.0.0"},
            "capabilities": {},
        },
    }
    status, headers, body = _post_jsonrpc(url, init_payload, api_key=api_key, session_id=None)
    if status != 200:
        raise SystemExit(f"initialize expected 200, got {status}: {body}")

    session_id = headers.get("Mcp-Session-Id")
    if not session_id:
        raise SystemExit(f"initialize did not return Mcp-Session-Id header. Headers: {headers!r}")

    status, _, _ = _post_jsonrpc(
        url,
        {"jsonrpc": "2.0", "method": "notifications/initialized"},
        api_key=api_key,
        session_id=session_id,
    )
    if status not in (200, 202):
        raise SystemExit(f"notifications/initialized expected 200/202, got {status}")

    status, _, body = _post_jsonrpc(
        url,
        {"jsonrpc": "2.0", "id": 102, "method": "tools/list"},
        api_key=api_key,
        session_id=session_id,
    )
    if status != 200:
        raise SystemExit(f"tools/list expected 200, got {status}: {body}")

    tools_resp = _assert_jsonrpc_result(body, 102)
    tools = (tools_resp.get("result") or {}).get("tools") or []
    tool_names = {tool.get("name") for tool in tools if isinstance(tool, dict)}

    if "mcp_structure_create_content_type" not in tool_names:
        raise SystemExit("Expected tool mcp_structure_create_content_type to be registered for config-only checks.")
    if "mcp_cache_clear_all" not in tool_names:
        raise SystemExit("Expected tool mcp_cache_clear_all to be registered for config-only checks.")

    # Config writes should still be allowed in config-only mode.
    status, _, body = _post_jsonrpc(
        url,
        {
            "jsonrpc": "2.0",
            "id": 103,
            "method": "tools/call",
            "params": {
                "name": "mcp_structure_create_content_type",
                "arguments": {
                    "id": "mcp_ci_type",
                    "label": "MCP CI Type",
                    "description": "",
                    "create_body": False,
                },
            },
        },
        api_key=api_key,
        session_id=session_id,
        timeout_seconds=60,
    )
    if status != 200:
        raise SystemExit(f"tools/call(create_content_type) expected 200, got {status}: {body}")
    create_resp = _assert_jsonrpc_result(body, 103)
    create_result = create_resp.get("result") or {}
    if create_result.get("isError") is True:
        raise SystemExit(f"Expected create_content_type to succeed, got isError=true: {create_result!r}")

    # Ops writes should be denied in config-only mode.
    status, _, body = _post_jsonrpc(
        url,
        {"jsonrpc": "2.0", "id": 104, "method": "tools/call", "params": {"name": "mcp_cache_clear_all", "arguments": {}}},
        api_key=api_key,
        session_id=session_id,
        timeout_seconds=60,
    )
    if status != 200:
        raise SystemExit(f"tools/call(mcp_cache_clear_all) expected 200, got {status}: {body}")
    clear_resp = _assert_jsonrpc_result(body, 104)
    clear_result = clear_resp.get("result") or {}
    if clear_result.get("isError") is not True:
        raise SystemExit(f"Expected cache clear to be denied in config-only mode, got: {clear_result!r}")


def main() -> int:
    parser = argparse.ArgumentParser(description="End-to-end HTTP MCP check for mcp_tools_remote.")
    parser.add_argument("--drupal-root", default="drupal", help="Path to Drupal project root")
    parser.add_argument("--base-url", default="http://localhost:8888", help="Base URL where Drupal is served")
    args = parser.parse_args()

    parsed_base_url = urllib.parse.urlparse(args.base_url)
    if not parsed_base_url.hostname:
        raise SystemExit(f"Invalid --base-url (missing hostname): {args.base_url}")
    if parsed_base_url.port is None:
        raise SystemExit(f"Invalid --base-url (missing port): {args.base_url}")

    drupal_root = os.path.abspath(args.drupal_root)
    drush = os.path.join(drupal_root, "vendor", "bin", "drush")
    _require_file(drush)

    _run_drush(drupal_root, ["en", "mcp_tools_remote", "mcp_tools_cache", "mcp_tools_structure", "-y"])
    _run_drush(drupal_root, ["config:set", "mcp_tools_remote.settings", "enabled", "true", "-y"])
    _run_drush(drupal_root, ["cr"])

    read_key = _create_api_key(drupal_root, "CI Read", "read")
    write_key = _create_api_key(drupal_root, "CI Write", "read,write")

    web_root = os.path.join(drupal_root, "web")
    router = os.path.join(web_root, ".ht.router.php")
    _require_file(router)

    server = subprocess.Popen(
        ["php", "-S", f"{parsed_base_url.hostname}:{parsed_base_url.port}", ".ht.router.php"],
        cwd=web_root,
        stdout=subprocess.DEVNULL,
        stderr=subprocess.DEVNULL,
        env={**os.environ, "SYMFONY_DEPRECATIONS_HELPER": "disabled"},
    )

    try:
        _wait_for_http(args.base_url.rstrip("/") + "/", timeout_seconds=15)

        url = args.base_url.rstrip("/") + "/_mcp_tools"
        status, _, _ = _post_jsonrpc(
            url,
            {
                "jsonrpc": "2.0",
                "id": 999,
                "method": "initialize",
                "params": {
                    "protocolVersion": "2024-11-05",
                    "clientInfo": {"name": "mcp_tools_ci", "version": "0.0.0"},
                    "capabilities": {},
                },
            },
            api_key=None,
            session_id=None,
        )
        if status != 401:
            raise SystemExit(f"Expected 401 without API key, got {status}")

        _run_sequence(args.base_url, read_key, expect_write_allowed=False)
        _run_sequence(args.base_url, write_key, expect_write_allowed=True)

        # Config-only mode: allow config writes, deny ops writes.
        _run_drush(drupal_root, ["config:set", "mcp_tools.settings", "access.config_only_mode", "true", "-y"])
        _run_drush(drupal_root, ["cr"])
        _run_config_only_sequence(args.base_url, write_key)
    finally:
        server.terminate()
        try:
            server.wait(timeout=5)
        except subprocess.TimeoutExpired:
            server.kill()

    return 0


if __name__ == "__main__":
    sys.exit(main())
