<?php

namespace TopMenu\PostmanSync;

use RuntimeException;

class CollectionFile
{
    private const ENCODING = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    public static function load(string $path): array
    {
        if (!is_file($path)) {
            throw new RuntimeException("Collection not found: $path");
        }

        $collection = json_decode((string) file_get_contents($path), true);

        if (!is_array($collection) || !isset($collection['info'], $collection['item'])) {
            throw new RuntimeException("Not a Postman collection: $path");
        }

        return $collection;
    }

    public static function save(string $path, array $collection): void
    {
        file_put_contents($path, self::encode($collection));
    }

    public static function encode(array $collection): string
    {
        return (string) json_encode($collection, self::ENCODING);
    }

    /** Every request in the tree, ignoring the folders. */
    public static function requests(array $items): array
    {
        $out = [];

        foreach ($items as $item) {
            if (isset($item['item'])) {
                $out = array_merge($out, self::requests($item['item']));
                continue;
            }

            $out[] = $item;
        }

        return $out;
    }
}
