#!/usr/bin/env python3
"""ZMQ bridge for jupyter-php-kernel.

This process owns all Jupyter ZMQ sockets and forwards shell/control traffic
between Jupyter and the PHP worker process via stdio.
"""

from __future__ import annotations

import argparse
import base64
import binascii
import json
import queue
import signal
import subprocess
import sys
import threading
from pathlib import Path
from typing import Any

import zmq


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Jupyter PHP ZMQ bridge")
    parser.add_argument("--connection-file", required=True)
    parser.add_argument("--php-bin", required=True)
    parser.add_argument("--worker-script", required=True)
    return parser.parse_args()


def encode_frames(frames: list[bytes]) -> list[str]:
    return [base64.b64encode(frame).decode("ascii") for frame in frames]


def decode_frames(encoded_frames: Any) -> list[bytes]:
    if not isinstance(encoded_frames, list):
        return []

    decoded: list[bytes] = []
    for frame in encoded_frames:
        if not isinstance(frame, str):
            return []
        try:
            decoded.append(base64.b64decode(frame, validate=True))
        except (ValueError, binascii.Error):
            return []

    return decoded


def worker_reader(stdout, responses: queue.Queue[dict[str, Any]]) -> None:
    for raw_line in stdout:
        line = raw_line.strip()
        if not line:
            continue

        try:
            payload = json.loads(line)
        except json.JSONDecodeError:
            continue

        if not isinstance(payload, dict) or payload.get("event") != "response":
            continue

        channel = payload.get("channel")
        frames = decode_frames(payload.get("frames_b64"))
        if not isinstance(channel, str) or not frames:
            continue

        responses.put({"channel": channel, "frames": frames})


def worker_stderr_forward(stderr) -> None:
    for line in stderr:
        sys.stderr.write(line)
        sys.stderr.flush()


def build_address(connection: dict[str, Any], port_key: str) -> str:
    ip = connection["ip"]
    port = connection[port_key]
    return f"tcp://{ip}:{port}"


def main() -> int:
    args = parse_args()

    connection_file = Path(args.connection_file)
    with connection_file.open("r", encoding="utf-8") as fh:
        connection = json.load(fh)

    worker_cmd = [
        args.php_bin,
        args.worker_script,
        "--worker",
        "--connection_file",
        str(connection_file),
    ]

    worker = subprocess.Popen(
        worker_cmd,
        stdin=subprocess.PIPE,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        text=True,
        encoding="utf-8",
        bufsize=1,
    )

    if worker.stdin is None or worker.stdout is None or worker.stderr is None:
        return 1

    responses: queue.Queue[dict[str, Any]] = queue.Queue()
    threading.Thread(target=worker_reader, args=(worker.stdout, responses), daemon=True).start()
    threading.Thread(target=worker_stderr_forward, args=(worker.stderr,), daemon=True).start()

    context = zmq.Context()
    shell = context.socket(zmq.ROUTER)
    control = context.socket(zmq.ROUTER)
    stdin_socket = context.socket(zmq.ROUTER)
    iopub = context.socket(zmq.PUB)
    hb = context.socket(zmq.REP)

    shell.bind(build_address(connection, "shell_port"))
    control.bind(build_address(connection, "control_port"))
    stdin_socket.bind(build_address(connection, "stdin_port"))
    iopub.bind(build_address(connection, "iopub_port"))
    hb.bind(build_address(connection, "hb_port"))

    poller = zmq.Poller()
    poller.register(shell, zmq.POLLIN)
    poller.register(control, zmq.POLLIN)
    poller.register(stdin_socket, zmq.POLLIN)
    poller.register(hb, zmq.POLLIN)

    running = True

    def stop_bridge(_sig, _frame) -> None:
        nonlocal running
        running = False

    signal.signal(signal.SIGINT, stop_bridge)
    signal.signal(signal.SIGTERM, stop_bridge)

    try:
        while running:
            if worker.poll() is not None:
                break

            events = dict(poller.poll(timeout=100))

            if hb in events:
                hb_message = hb.recv_multipart()
                hb.send_multipart(hb_message)

            if shell in events:
                request_frames = shell.recv_multipart()
                payload = {
                    "event": "request",
                    "channel": "shell",
                    "frames_b64": encode_frames(request_frames),
                }
                worker.stdin.write(json.dumps(payload, separators=(",", ":")) + "\n")
                worker.stdin.flush()

            if control in events:
                request_frames = control.recv_multipart()
                payload = {
                    "event": "request",
                    "channel": "control",
                    "frames_b64": encode_frames(request_frames),
                }
                worker.stdin.write(json.dumps(payload, separators=(",", ":")) + "\n")
                worker.stdin.flush()

            if stdin_socket in events:
                # Drain unsupported stdin requests to avoid queue buildup.
                stdin_socket.recv_multipart()

            while True:
                try:
                    response = responses.get_nowait()
                except queue.Empty:
                    break

                channel = response["channel"]
                frames = response["frames"]

                if channel == "iopub":
                    iopub.send_multipart(frames)
                elif channel == "control":
                    control.send_multipart(frames)
                elif channel == "shell":
                    shell.send_multipart(frames)

    finally:
        try:
            worker.terminate()
        except Exception:
            pass

        shell.close(0)
        control.close(0)
        stdin_socket.close(0)
        iopub.close(0)
        hb.close(0)
        context.term()

    return int(worker.returncode or 0)


if __name__ == "__main__":
    raise SystemExit(main())
