<?php

namespace App\Services\Migrations;

use RuntimeException;

class WordPressLegacySnapshotService
{
    public const REQUIRED_FILES = [
        'users.jsonl',
        'usermeta.jsonl',
        'business_posts.jsonl',
        'business_postmeta.jsonl',
    ];

    public const OPTIONAL_FILES = [];

    public function audit(string $directory): array
    {
        $directory = rtrim($directory, DIRECTORY_SEPARATOR);

        if (!is_dir($directory)) {
            throw new RuntimeException(
                "Snapshot directory not found: {$directory}"
            );
        }

        $files = [];
        $missing = [];
        $totalBytes = 0;

        foreach (self::REQUIRED_FILES as $name) {
            $path = $directory.DIRECTORY_SEPARATOR.$name;

            if (!is_file($path)) {
                $missing[] = $name;
                continue;
            }

            $info = $this->inspectJsonLines($path);

            $files[$name] = $info;
            $totalBytes += $info['bytes'];
        }

        foreach (self::OPTIONAL_FILES as $name) {
            $path = $directory.DIRECTORY_SEPARATOR.$name;

            if (!is_file($path)) {
                continue;
            }

            $info = $this->inspectJsonLines($path);

            $files[$name] = $info;
            $totalBytes += $info['bytes'];
        }

        return [
            'directory' => $directory,
            'ready' => $missing === [],
            'missing_required_files' => $missing,
            'files' => $files,
            'total_bytes' => $totalBytes,
        ];
    }

    private function inspectJsonLines(string $path): array
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException(
                "Unable to open snapshot file: {$path}"
            );
        }

        $rows = 0;
        $blankLines = 0;
        $invalidRows = 0;
        $firstError = null;

        try {
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);

                if ($line === '') {
                    $blankLines++;
                    continue;
                }

                $rows++;

                json_decode($line, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    $invalidRows++;

                    if ($firstError === null) {
                        $firstError = [
                            'row' => $rows,
                            'error' => json_last_error_msg(),
                        ];
                    }
                }
            }
        } finally {
            fclose($handle);
        }

        return [
            'bytes' => filesize($path) ?: 0,
            'rows' => $rows,
            'blank_lines' => $blankLines,
            'invalid_json_rows' => $invalidRows,
            'first_error' => $firstError,
            'sha256' => hash_file('sha256', $path),
            'valid' => $invalidRows === 0,
        ];
    }
}
