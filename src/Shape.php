<?php

namespace TopMenu\PostmanSync;

/**
 * Both sides of the sync have to be compared on the same footing: a Laravel uri
 * ("api/v2/providers/{provider}/orders") and a Postman url ("{{base_url}}/providers/
 * {{provider_id}}/orders") describe one endpoint. Collapsing every parameter to
 * {param} and dropping the prefix makes them equal strings.
 */
class Shape
{
    public function __construct(private readonly string $prefix = '')
    {
    }

    /** "GET providers/{param}/orders" */
    public function key(string $method, string $uri): string
    {
        return strtoupper($method) . ' ' . $this->of($uri);
    }

    public function of(string $uri): string
    {
        $uri = (string) strtok($uri, '?');
        $uri = (string) preg_replace('~^\{\{[^}]+}}/?~', '', trim($uri, '/'));       // {{base_url}}
        $uri = (string) preg_replace('~^https?://[^/]+~', '', $uri);                 // a literal host
        $uri = trim($uri, '/');

        if ($this->prefix !== '') {
            $uri = (string) preg_replace('~^' . preg_quote($this->prefix, '~') . '/~', '', $uri);
        }

        return implode('/', array_map(
            fn(string $segment) => $this->isParameter($segment) ? '{param}' : $segment,
            explode('/', $uri)
        ));
    }

    /** The key of a Postman request item. */
    public function ofItem(array $item): string
    {
        $url = $item['request']['url'] ?? [];
        $raw = is_array($url) ? ($url['raw'] ?? '') : (string) $url;

        return $this->key($item['request']['method'] ?? 'GET', (string) $raw);
    }

    private function isParameter(string $segment): bool
    {
        return (bool) preg_match('~^(\{\{.+}}|\d+|\{[^}]+})$~', $segment);
    }
}
