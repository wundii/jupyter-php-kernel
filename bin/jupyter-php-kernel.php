#!/usr/bin/env php
<?php

if (file_exists(__DIR__.'/../../../autoload.php')) {
    require __DIR__.'/../../../autoload.php';
} else {
    require __DIR__.'/../vendor/autoload.php';
}

$opts = getopt('irc:w', ['install', 'run', 'connection_file:', 'worker']);

if (isset($opts['install']) || isset($opts['i'])) {
    Wundii\JupyterPhpKernel\Installer\Installer::install();
    exit(0);
}

$connection_file_path = $opts['connection_file'] ?? $opts['c'] ?? null;
if (!is_string($connection_file_path) || $connection_file_path === '') {
    fwrite(STDERR, "Missing --connection_file\n");
    exit(1);
}

if (isset($opts['worker']) || isset($opts['w'])) {
    $connection_details = (new Wundii\JupyterPhpKernel\ConnectionDetails($connection_file_path))->read();
    $kernel = new Wundii\JupyterPhpKernel\Kernel($connection_details);
    $kernel->run();
    exit(0);
}

if (isset($opts['run']) || isset($opts['r'])) {
    $python = null;
    $path = trim((string) shell_exec('command -v ' . escapeshellarg('python3')));
    if ($path !== '') {
        $python = $path;
    }

    if ($python === null) {
        fwrite(STDERR, "Could not find python3 executable\n");
        exit(1);
    }

    $bridgeScript = realpath(__DIR__ . '/../bridge/jupyter_php_zmq_bridge.py');
    $workerScript = realpath(__FILE__);

    if ($bridgeScript === false || $workerScript === false) {
        fwrite(STDERR, "Bridge scripts not found\n");
        exit(1);
    }

    $command = [
        escapeshellarg($python),
        escapeshellarg($bridgeScript),
        '--connection-file',
        escapeshellarg($connection_file_path),
        '--php-bin',
        escapeshellarg(PHP_BINARY),
        '--worker-script',
        escapeshellarg($workerScript),
    ];

    $exitCode = 0;
    passthru(implode(' ', $command), $exitCode);
    exit($exitCode);
}
