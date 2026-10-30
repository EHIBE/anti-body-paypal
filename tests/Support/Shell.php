<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Facades\Process;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;

final class Shell
{
    private static ?string $resolved = null;

    public static function sh(): string
    {
        return self::$resolved ??= self::locate();
    }

    private static function locate(): string
    {
        $found = (new ExecutableFinder)->find('sh');

        if ($found !== null) {
            return str_replace('\\', '/', $found);
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $result = Process::run(['git', '--exec-path']);

            if ($result->successful()) {
                $execPath = str_replace('\\', '/', trim($result->output()));
                $candidate = dirname($execPath, 3).'/bin/sh.exe';

                if (is_file($candidate)) {
                    return $candidate;
                }
            }
        }

        throw new RuntimeException('No sh binary found. Put sh on PATH or install Git for Windows so that its bin/sh.exe is next to git.');
    }

    public static function toolDirectory(): string
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return '';
        }

        $candidate = dirname(self::sh(), 2).'/usr/bin';

        return is_dir($candidate) ? $candidate : '';
    }
}
