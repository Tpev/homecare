<?php

namespace App\Services\Screening;

use RuntimeException;

class ScreeningPrivateFiles
{
    public function directory(string $path): void
    {
        $path = $this->resolveStorageBoundary($path);
        $this->assertSafePath($path);
        $directories = [$path];
        foreach (array_filter([config('screening.private_directory'), config('screening.audit_directory')]) as $root) {
            $root = rtrim($this->resolveStorageBoundary($root), '/\\');
            if ($this->within($path, $root)) {
                for ($parent = dirname($path); $this->within($parent, $root); $parent = dirname($parent)) {
                    $directories[] = $parent;
                    if ($this->samePath($parent, $root)) {
                        break;
                    }
                }
            }
        }
        foreach (array_reverse(array_unique($directories)) as $directory) {
            $this->ensureDirectory($directory);
        }
    }

    /** Open only a regular private file, creating it without world permissions. */
    public function open(string $path, string $mode = 'xb')
    {
        if (! in_array($mode, ['xb', 'c+b'], true)) {
            throw new RuntimeException('Unsupported private screening file mode.');
        }
        $path = $this->resolveStorageBoundary($path);
        $this->directory(dirname($path));
        $this->assertSafePath($path);
        $existed = file_exists($path);
        if ($existed && ($mode === 'xb' || ! is_file($path))) {
            throw new RuntimeException('Cannot create or open the private screening file.');
        }
        if ($existed) {
            $this->permissions($path, 0660);
        }
        $previousMask = umask(0007);
        try {
            $file = @fopen($path, $mode);
        } finally {
            umask($previousMask);
        }
        if ($file === false) {
            throw new RuntimeException('Cannot create or open the private screening file.');
        }
        try {
            $this->assertSafePath($path);
            $opened = fstat($file);
            $named = lstat($path);
            if (! $opened || ! $named || ($opened['mode'] & 0170000) !== 0100000
                || $opened['ino'] !== $named['ino'] || $opened['dev'] !== $named['dev']) {
                throw new RuntimeException('The private screening file changed while opening.');
            }
            $this->permissions($path, 0660);
        } catch (\Throwable $error) {
            fclose($file);
            if (! $existed && ! is_link($path) && is_file($path)) {
                unlink($path);
            }
            throw $error;
        }

        return $file;
    }

    public function copy(string $source, string $destination): void
    {
        $input = @fopen($source, 'rb');
        if ($input === false) {
            throw new RuntimeException('Cannot read the local screening archive.');
        }
        try {
            $output = $this->open($destination);
            try {
                $size = filesize($source);
                if (stream_copy_to_stream($input, $output, $size + 1) !== $size || ! fflush($output)) {
                    throw new RuntimeException('Cannot copy the complete local screening archive.');
                }
            } finally {
                fclose($output);
            }
        } finally {
            fclose($input);
        }
    }

    public function writeLocked(string $path, string $contents): void
    {
        $file = $this->open($path, 'c+b');
        try {
            if (! flock($file, LOCK_EX) || ! rewind($file) || fwrite($file, $contents) !== strlen($contents)
                || ! ftruncate($file, strlen($contents)) || ! fflush($file)) {
                throw new RuntimeException('Cannot write the private screening recovery file.');
            }
        } finally {
            flock($file, LOCK_UN);
            fclose($file);
        }
    }

    private function ensureDirectory(string $path): void
    {
        $this->assertSafePath($path);
        if (! is_dir($path)) {
            if (file_exists($path)) {
                throw new RuntimeException('A private screening directory path is occupied by a file.');
            }
            $parent = dirname($path);
            if (! is_dir($parent)) {
                $this->ensureDirectory($parent);
            }
            if (! @mkdir($path, 02770) && ! is_dir($path)) {
                throw new RuntimeException('Cannot create the private screening directory.');
            }
        }
        $this->permissions($path, 02770);
    }

    private function permissions(string $path, int $mode): void
    {
        clearstatcache(true, $path);
        // The other service user may own an already-correct file and cannot chmod it.
        if (PHP_OS_FAMILY !== 'Windows' && (fileperms($path) & 07777) === $mode) {
            return;
        }
        if (! @chmod($path, $mode)) {
            throw new RuntimeException('Cannot set private screening file permissions.');
        }
        clearstatcache(true, $path);
        if (PHP_OS_FAMILY !== 'Windows' && (fileperms($path) & 07777) !== $mode) {
            throw new RuntimeException('Private screening file permissions were not applied.');
        }
    }

    private function assertSafePath(string $path): void
    {
        $this->assertPathSyntax($path);
        for ($current = $path; ; $current = dirname($current)) {
            clearstatcache(true, $current);
            if (is_link($current)) {
                throw new RuntimeException('Symlinks are not permitted below the trusted screening storage boundary.');
            }
            if (dirname($current) === $current || $current === '.') {
                break;
            }
        }
    }

    private function resolveStorageBoundary(string $path): string
    {
        $this->assertPathSyntax($path);
        $storage = rtrim(str_replace('\\', '/', storage_path()), '/');
        $logical = str_replace('\\', '/', $path);
        if ($this->within($logical, $storage)) {
            // Release/current and storage itself may be deployment-managed symlinks.
            // Resolve them at runtime; cached configuration must retain its logical path.
            $canonical = realpath($storage);
            if ($canonical === false) {
                throw new RuntimeException('The trusted application storage directory is unavailable.');
            }

            return rtrim($canonical, '/\\').substr($logical, strlen($storage));
        }

        return $path;
    }

    private function assertPathSyntax(string $path): void
    {
        if ($path === '' || str_contains($path, "\0") || str_contains($path, '://')
            || preg_match('~(^|[\\\\/])\.\.?([\\\\/]|$)~', $path)) {
            throw new RuntimeException('Invalid private screening path.');
        }
    }

    private function within(string $path, string $root): bool
    {
        return $this->samePath($path, $root) || str_starts_with($this->normalized($path).'/', $this->normalized($root).'/');
    }

    private function samePath(string $first, string $second): bool
    {
        return $this->normalized($first) === $this->normalized($second);
    }

    private function normalized(string $path): string
    {
        $path = rtrim(str_replace('\\', '/', $path), '/');

        return PHP_OS_FAMILY === 'Windows' ? strtolower($path) : $path;
    }
}
