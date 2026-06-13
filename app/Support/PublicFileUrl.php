<?php

namespace App\Support;

class PublicFileUrl
{
    public static function tripPhoto(?string $value): ?string
    {
        $path = self::storagePath($value);

        if ($path === null) {
            return null;
        }

        return rtrim((string) config('app.url'), '/').'/storage/'.$path;
    }

    public static function storagePath(?string $value): ?string
    {
        $raw = trim((string) $value);

        if ($raw === '') {
            return null;
        }

        $raw = str_replace('\\', '/', $raw);
        $parsedPath = parse_url($raw, PHP_URL_PATH);
        if (is_string($parsedPath) && $parsedPath !== '') {
            $raw = $parsedPath;
        }

        $raw = ltrim($raw, '/');
        foreach ([
            'storage/app/public/',
            'app/public/',
            'public/',
            'storage/',
        ] as $prefix) {
            if (str_starts_with($raw, $prefix)) {
                $raw = substr($raw, strlen($prefix));
                break;
            }
        }

        if (! str_starts_with($raw, 'trip_photos/')) {
            $raw = 'trip_photos/'.basename($raw);
        }

        return $raw;
    }
}
