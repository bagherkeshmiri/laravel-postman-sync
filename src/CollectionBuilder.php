<?php

namespace BagherKeshmiri\PostmanSync;

/**
 * Rewrites a collection so it matches the application: the routes decide which
 * requests exist and which fields they carry, everything else in the file is
 * left exactly as it was found.
 */
class CollectionBuilder
{
    public function __construct(
        private readonly Shape $shape,
        private readonly string $baseUrlVariable,
        private readonly string $parameterSuffix,
        private readonly array $parameterVariables,
        private readonly string $rootFolder,
        private readonly string $routePrefix,
    ) {
    }

    /**
     * @param  array<string, string>  $endpoints  shape key => route uri
     * @param  array<string, array>  $rules  shape key => validated fields
     */
    public function build(array $collection, array $endpoints, array $rules, BuildReport $report): array
    {
        $collection['item'] = $this->prune($collection['item'], $endpoints, $report);

        $this->addMissing($collection, array_diff_key($endpoints, $report->kept), $endpoints, $report);

        $collection['item'] = $this->fill($collection['item'], $rules, $report);

        $this->declareVariables($collection, $report);

        return $collection;
    }

    /** Drop requests whose route is gone, and remember which folder the survivors are in. */
    private function prune(array $items, array $endpoints, BuildReport $report, string $folder = ''): array
    {
        $out = [];

        foreach ($items as $item) {
            if (isset($item['item'])) {
                $path = $folder === '' ? ($item['name'] ?? '') : $folder . ' / ' . ($item['name'] ?? '');
                $item['item'] = $this->prune($item['item'], $endpoints, $report, $path);

                if ($item['item'] !== []) {
                    $out[] = $item;
                }

                continue;
            }

            $key = $this->shape->ofItem($item);

            if (isset($endpoints[$key])) {
                $report->kept[$key] = $folder;
                $out[] = $item;

                continue;
            }

            $report->removed[] = $key;
        }

        return $out;
    }

    private function addMissing(array &$collection, array $missing, array $endpoints, BuildReport $report): void
    {
        foreach ($missing as $key => $uri) {
            [$method, $shape] = explode(' ', $key, 2);

            $folder = $this->bestFolder($shape, $report->kept) ?? $this->fallbackFolder($shape);
            $target = &$this->folderAt($collection['item'], array_filter(explode(' / ', $folder)));
            $target[] = $this->makeRequest($method, $uri, $shape, $endpoints);
            unset($target);

            $report->added[] = "$key  [$folder]";
        }
    }

    /** The folder holding the request that shares the longest path prefix with this one. */
    private function bestFolder(string $shape, array $kept): ?string
    {
        $segments = explode('/', $shape);
        $best = null;
        $bestScore = 0;

        foreach ($kept as $key => $folder) {
            $candidate = explode('/', substr($key, (int) strpos($key, ' ') + 1));
            $score = 0;

            foreach ($segments as $i => $segment) {
                if (($candidate[$i] ?? null) !== $segment) {
                    break;
                }
                $score++;
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $folder;
            }
        }

        return $bestScore >= 2 ? $best : null;
    }

    private function fallbackFolder(string $shape): string
    {
        $static = array_values(array_filter(explode('/', $shape), fn(string $s) => $s !== '{param}'));
        $named = implode(' / ', array_map('ucfirst', array_slice($static, 0, 2)));

        return trim($this->rootFolder === '' ? $named : $this->rootFolder . ' / ' . $named, ' /');
    }

    /** Walk to a folder by its " / " path, creating the folders it needs. */
    private function &folderAt(array &$items, array $names): array
    {
        if ($names === []) {
            return $items;
        }

        $name = array_shift($names);

        foreach ($items as &$item) {
            if (($item['name'] ?? null) === $name && isset($item['item'])) {
                return $this->folderAt($item['item'], $names);
            }
        }
        unset($item);

        $items[] = ['name' => $name, 'item' => []];
        $fresh = &$items[count($items) - 1]['item'];

        return $this->folderAt($fresh, $names);
    }

    private function makeRequest(string $method, string $uri, string $shape, array $endpoints): array
    {
        $uri = trim($uri, '/');

        if ($this->routePrefix !== '') {
            $uri = (string) preg_replace('~^' . preg_quote($this->routePrefix, '~') . '/~', '', $uri);
        }

        $segments = explode('/', $uri);

        $path = array_map(
            fn(string $segment) => preg_match('~^\{(.+)\}$~', $segment, $m) ? '{{' . $this->variableFor($m[1]) . '}}' : $segment,
            $segments
        );

        $base = '{{' . $this->baseUrlVariable . '}}';

        $item = [
            'name' => $this->requestName($method, $segments, $shape, $endpoints),
            'request' => [
                'method' => $method,
                'header' => [],
                'url' => [
                    'raw' => $base . '/' . implode('/', $path),
                    'host' => [$base],
                    'path' => $path,
                ],
            ],
            'response' => [],
        ];

        if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $item['request']['body'] = ['mode' => 'raw', 'raw' => "{\n}", 'options' => ['raw' => ['language' => 'json']]];
        }

        return $item;
    }

    /** "index" / "show" / "store" / "update" / "destroy", or the action segment itself. */
    private function requestName(string $method, array $segments, string $shape, array $endpoints): string
    {
        $last = (string) end($segments);

        if (!preg_match('~^\{.+\}$~', $last)) {
            // a sibling "GET <shape>/{param}" means this path is a resource collection
            $isResource = isset($endpoints["GET $shape/{param}"]);

            return match (true) {
                $isResource && $method === 'GET' => 'index',
                $isResource && $method === 'POST' => 'store',
                default => $last,
            };
        }

        return match ($method) {
            'GET' => 'show',
            'PUT', 'PATCH' => 'update',
            'DELETE' => 'destroy',
            default => 'store',
        };
    }

    private function variableFor(string $parameter): string
    {
        $parameter = (string) strtok($parameter, ':');                // {user:username}

        return $this->parameterVariables[$parameter] ?? $parameter . $this->parameterSuffix;
    }

    private function fill(array $items, array $rules, BuildReport $report): array
    {
        foreach ($items as &$item) {
            if (isset($item['item'])) {
                $item['item'] = $this->fill($item['item'], $rules, $report);

                continue;
            }

            $fields = $rules[$this->shape->ofItem($item)] ?? null;

            if ($fields === null) {
                continue;
            }

            if (strtoupper($item['request']['method'] ?? 'GET') === 'GET') {
                $this->fillQuery($item, $fields, $report);

                continue;
            }

            $this->fillBody($item, $fields, $report);
        }

        return $items;
    }

    private function fillQuery(array &$item, array $fields, BuildReport $report): void
    {
        $existing = [];
        foreach ($item['request']['url']['query'] ?? [] as $parameter) {
            $existing[$parameter['key']] = $parameter;
        }

        $query = [];
        foreach ($fields as $field => $meta) {
            if (str_contains($field, '*')) {
                continue;                                    // "date.*" is covered by "date"
            }

            $key = str_contains($field, '.') ? str_replace('.', '[', $field) . ']' : $field;

            $query[] = $existing[$key] ?? [
                'key' => $key,
                'value' => (string) (is_scalar($value = $this->sampleValue($field, $meta['rule'])) ? $value : ''),
                'description' => $meta['rule'],
                'disabled' => !$meta['required'],
            ];
        }

        if ($query === [] || $query === ($item['request']['url']['query'] ?? null)) {
            return;
        }

        $enabled = array_filter($query, fn(array $parameter) => empty($parameter['disabled']));
        $string = implode('&', array_map(fn(array $parameter) => $parameter['key'] . '=' . $parameter['value'], $enabled));

        $item['request']['url']['query'] = $query;
        $item['request']['url']['raw'] = (string) strtok((string) ($item['request']['url']['raw'] ?? ''), '?') . ($string !== '' ? '?' . $string : '');
        $report->queries++;
    }

    private function fillBody(array &$item, array $fields, BuildReport $report): void
    {
        $body = $this->buildBody($fields);

        if ($body === []) {
            return;
        }

        $current = trim($item['request']['body']['raw'] ?? '');
        $existing = $current === '' ? [] : json_decode($current, true);

        if (!is_array($existing)) {
            return;                                          // hand-written and not JSON, leave it alone
        }

        $raw = (string) json_encode($this->merge($body, $existing), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($raw === $current) {
            return;
        }

        $item['request']['body'] = ['mode' => 'raw', 'raw' => $raw, 'options' => ['raw' => ['language' => 'json']]];
        $report->bodies++;
    }

    /**
     * The rules decide which fields exist; whatever was typed into them in Postman
     * stays, so a new field appears without wiping the ones next to it.
     */
    private function merge(array $generated, array $existing): array
    {
        foreach ($generated as $key => $value) {
            if (!array_key_exists($key, $existing)) {
                continue;
            }

            $generated[$key] = is_array($value) && is_array($existing[$key])
                ? $this->merge($value, $existing[$key])
                : $existing[$key];
        }

        return $generated;
    }

    /** Turn dotted and starred rule keys such as "items.*.price" into a nested skeleton. */
    private function buildBody(array $fields): array
    {
        $body = [];

        foreach ($fields as $field => $meta) {
            if (preg_match('~\b(image|file|mimes)\b~', $meta['rule'])) {
                continue;                                    // not representable in a JSON body
            }

            $parts = explode('.', $field);
            $ref = &$body;

            foreach ($parts as $i => $part) {
                $last = $i === count($parts) - 1;

                if ($part === '*') {
                    if ($last) {
                        $ref = [$this->sampleValue($parts[$i - 1] ?? '', $meta['rule'])];
                        break;
                    }

                    if (!isset($ref[0]) || !is_array($ref[0])) {
                        $ref = [[]];
                    }

                    $ref = &$ref[0];

                    continue;
                }

                if ($last) {
                    $ref[$part] ??= $this->sampleValue($part, $meta['rule']);
                    break;
                }

                if (!isset($ref[$part]) || !is_array($ref[$part])) {
                    $ref[$part] = [];
                }

                $ref = &$ref[$part];
            }
            unset($ref);
        }

        return $body;
    }

    private function sampleValue(string $field, string $rule): mixed
    {
        return match (true) {
            str_contains($rule, 'boolean') => false,
            str_contains($rule, 'array') => [],
            str_contains($rule, 'date') => date('Y-m-d'),
            str_contains($rule, 'email') => 'user@example.com',
            str_contains($rule, 'integer') || str_contains($rule, 'numeric') => str_ends_with($field, $this->parameterSuffix) ? 1 : 0,
            default => '',
        };
    }

    /** Every {{variable}} the collection mentions has to be declared, or Postman shows it unresolved. */
    private function declareVariables(array &$collection, BuildReport $report): void
    {
        preg_match_all('~\{\{([a-z0-9_]+)\}\}~i', CollectionFile::encode($collection), $matches);

        $report->variables = array_values(array_diff(
            array_unique($matches[1]),
            array_column($collection['variable'] ?? [], 'key')
        ));

        foreach ($report->variables as $name) {
            $collection['variable'][] = ['key' => $name, 'value' => ''];
        }
    }
}
