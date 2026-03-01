# jupyter-php-kernel

This repo is a derivative of [jupyter-php-kernel](https://github.com/Rabrennie/jupyter-php-kernel).

## Runtime architecture (PHP 8.5 compatible)
`php-zmq`/`react/zmq` are not used in PHP.

- The PHP kernel runs as a worker process over `STDIN/STDOUT`.
- A small Python bridge (`bridge/jupyter_php_zmq_bridge.py`) owns the Jupyter ZMQ sockets.
- Messages are forwarded between bridge and PHP worker as JSON lines with base64-encoded frames.

## Requirements
- PHP >= 8.1
- Python 3
- `pyzmq` (`pip install pyzmq`)

## Start Python ZMQ
```SHELL
python3 -m py_compile ./bridge/jupyter_php_zmq_bridge.py
```
