<?php

namespace App\Support;

class TextEncoding
{
    /**
     * Normalize legacy encoded text to UTF-8 for safe UI rendering.
     */
    public static function normalizeString(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        foreach (['CP1258', 'Windows-1258', 'Windows-1252', 'ISO-8859-1'] as $source) {
            try {
                $converted = @mb_convert_encoding($value, 'UTF-8', $source);
                if (is_string($converted) && $converted !== '' && mb_check_encoding($converted, 'UTF-8')) {
                    return $converted;
                }
            } catch (\Throwable) {
                // Try the next fallback encoding.
            }
        }

        return $value;
    }

    public static function normalizeValue(mixed $value): mixed
    {
        if (is_string($value)) {
            return self::normalizeString($value);
        }

        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = self::normalizeValue($item);
            }
            return $value;
        }

        if (is_object($value)) {
            foreach (get_object_vars($value) as $property => $item) {
                $value->{$property} = self::normalizeValue($item);
            }
            return $value;
        }

        return $value;
    }
}