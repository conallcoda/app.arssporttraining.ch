<?php

namespace Coda\FormKit\Support;

class Str
{
    public static function snake(string $value): string
    {
        $value = preg_replace('/\s+/u', '', ucwords($value));

        return strtolower(preg_replace('/(.)(?=[A-Z])/u', '$1_', $value));
    }

    public static function kebab(string $value): string
    {
        return str_replace('_', '-', static::snake($value));
    }

    public static function headline(string $value): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $value));
    }

    public static function dataGet(array $data, string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);

        foreach ($keys as $segment) {
            if (! is_array($data) || ! array_key_exists($segment, $data)) {
                return $default;
            }

            $data = $data[$segment];
        }

        return $data;
    }
}
