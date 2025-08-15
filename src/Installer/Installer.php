<?php

declare(strict_types=1);

namespace Wundii\JupyterPhpKernel\Installer;

class Installer
{
    public static function install(): void
    {
        $kernel_path = self::getInstallPath();

        if (!is_dir($kernel_path)) {
            mkdir($kernel_path, 0755, true);
        }

        file_put_contents($kernel_path . '/kernel.json', json_encode(self::getKernelJSON()));
        copy(__FILE__ . '/logo-32x32.png', $kernel_path . '/logo-32x32.png');
        copy(__FILE__ . '/logo-64x64.png', $kernel_path . '/logo-64x64.png');
        copy(__FILE__ . '/logo-64x64.png', $kernel_path . '/logo-svg.svg');
    }

    protected static function getInstallPath(): string
    {
        $data_dir = shell_exec('jupyter --data-dir');
        $data_dir = trim($data_dir);

        if ($data_dir === '') {
            echo 'ERROR: Could not find jupyter data directory. Please ensure jupyter is installed';
        }

        return $data_dir . '/kernels/PHP';
    }

    protected static function getKernelJSON(): array
    {
        $kernel_path = self::getInstallPath();
        return [
            'argv' => ['jupyter-php-kernel', '-r',  '-c', '{connection_file}'],
            'display_name' => 'PHP ' . PHP_VERSION,
            'language' => 'php',
            'metadata' => [
                'debugger' => true,
                'file1' => __FILE__ . '/logo-32x32.png',
                'file2' => $kernel_path . '/logo-32x32.png',
            ],
        ];
    }
}
