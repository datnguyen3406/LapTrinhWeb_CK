<?php
declare(strict_types=1);

namespace App\Database;

final class Environment
{
    /** @return array<string, string> */
    public static function load(string $path): array
    {
        $values = [];
        if (is_file($path)) {
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                    continue;
                }
                [$key, $value] = explode('=', $line, 2);
                $values[trim($key)] = trim(trim($value), "\"'");
            }
        }

        foreach (getenv() ?: [] as $key => $value) {
            $values[$key] = $value;
        }
        return $values;
    }
}
