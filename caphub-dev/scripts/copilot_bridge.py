#!/usr/bin/env python3

import json
import os
import subprocess
import sys
import time
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer


HOST = os.environ.get("COPILOT_BRIDGE_HOST", "0.0.0.0")
PORT = int(os.environ.get("COPILOT_BRIDGE_PORT", "18643"))
API_KEY = (
    os.environ.get("COPILOT_BRIDGE_API_KEY")
    or os.environ.get("GITHUB_MODELS_BRIDGE_API_KEY")
    or os.environ.get("GITHUB_MODELS_API_KEY")
    or ""
).strip()
COPILOT_BIN = os.environ.get("COPILOT_BRIDGE_BIN", os.environ.get("COPILOT_BIN", "copilot")).strip() or "copilot"
COPILOT_HOME = os.environ.get("COPILOT_HOME", os.path.expanduser("~/.copilot"))
EXCLUDED_TOOLS = (
    os.environ.get("COPILOT_BRIDGE_EXCLUDED_TOOLS", "shell,write,read,url,memory").strip()
    or "shell,write,read,url,memory"
)
DEFAULT_TIMEOUT = max(1, int(os.environ.get("COPILOT_BRIDGE_TIMEOUT", "45")))


def json_response(handler: BaseHTTPRequestHandler, status: int, payload: dict) -> None:
    body = json.dumps(payload, ensure_ascii=False).encode("utf-8")
    handler.send_response(status)
    handler.send_header("Content-Type", "application/json; charset=utf-8")
    handler.send_header("Content-Length", str(len(body)))
    handler.end_headers()
    handler.wfile.write(body)


def map_error_status(message: str) -> int:
    lowered = message.lower()

    if "too many requests" in lowered or "rate limit" in lowered:
        return 429

    if "timed out" in lowered or "timeout" in lowered:
        return 504

    return 502


class Handler(BaseHTTPRequestHandler):
    server_version = "CaphubCopilotBridge/1.0"

    def do_GET(self) -> None:
        if self.path == "/health":
            json_response(
                self,
                200,
                {
                    "status": "ok",
                    "copilot_bin": COPILOT_BIN,
                },
            )
            return

        json_response(self, 404, {"message": "Not found."})

    def do_POST(self) -> None:
        if self.path != "/v1/completions":
            json_response(self, 404, {"message": "Not found."})
            return

        if API_KEY == "":
            json_response(self, 500, {"message": "Copilot bridge API key is not configured."})
            return

        auth = self.headers.get("Authorization", "")
        expected = f"Bearer {API_KEY}"

        if auth != expected:
            json_response(self, 401, {"message": "Unauthorized."})
            return

        try:
            content_length = int(self.headers.get("Content-Length", "0"))
        except ValueError:
            json_response(self, 400, {"message": "Invalid content length."})
            return

        raw_body = self.rfile.read(content_length)

        try:
            payload = json.loads(raw_body.decode("utf-8"))
        except Exception:
            json_response(self, 400, {"message": "Invalid JSON body."})
            return

        model = str(payload.get("model", "")).strip()
        prompt = payload.get("prompt")
        timeout = payload.get("timeout", DEFAULT_TIMEOUT)

        try:
            timeout = max(1, int(timeout))
        except Exception:
            json_response(self, 422, {"message": "timeout must be an integer."})
            return

        if model == "":
            json_response(self, 422, {"message": "model is required."})
            return

        if not isinstance(prompt, str) or prompt.strip() == "":
            json_response(self, 422, {"message": "prompt is required."})
            return

        command = [
            COPILOT_BIN,
            "--model",
            model,
            "-p",
            prompt,
            "-s",
            "--allow-all-tools",
            "--disable-builtin-mcps",
            f"--excluded-tools={EXCLUDED_TOOLS}",
        ]
        env = os.environ.copy()
        env["COPILOT_HOME"] = COPILOT_HOME
        started_at = time.time()

        try:
            completed = subprocess.run(
                command,
                capture_output=True,
                text=True,
                timeout=timeout,
                env=env,
            )
        except subprocess.TimeoutExpired:
            json_response(self, 504, {"message": "Copilot CLI request timed out."})
            return

        duration_ms = int(round((time.time() - started_at) * 1000))
        stderr = (completed.stderr or "").strip()
        stdout = (completed.stdout or "").strip()

        if completed.returncode != 0:
            message = stderr or stdout or "Copilot CLI request failed."
            json_response(
                self,
                map_error_status(message),
                {
                    "message": message,
                    "duration_ms": duration_ms,
                    "exit_code": completed.returncode,
                },
            )
            return

        if stdout == "":
            json_response(
                self,
                502,
                {
                    "message": "Copilot CLI completion content is empty.",
                    "duration_ms": duration_ms,
                    "exit_code": completed.returncode,
                },
            )
            return

        json_response(
            self,
            200,
            {
                "content": stdout,
                "duration_ms": duration_ms,
                "exit_code": completed.returncode,
                "model": model,
            },
        )

    def log_message(self, format: str, *args) -> None:
        timestamp = time.strftime("%Y-%m-%d %H:%M:%S")
        message = "%s - - [%s] %s\n" % (self.address_string(), timestamp, format % args)
        sys.stderr.write(message)


def main() -> None:
    server = ThreadingHTTPServer((HOST, PORT), Handler)
    print(f"copilot bridge listening on {HOST}:{PORT}", flush=True)
    server.serve_forever()


if __name__ == "__main__":
    main()
