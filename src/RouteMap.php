<?php

namespace BagherKeshmiri\PostmanSync;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use ReflectionMethod;
use ReflectionNamedType;
use Throwable;

/**
 * What the application says its API is: which endpoints exist, and which fields
 * each one validates.
 */
class RouteMap
{
    public function __construct(
        private readonly Shape $shape,
        private readonly string $prefix,
    ) {
    }

    /** @return array<string, string> shape key => route uri */
    public function endpoints(): array
    {
        $out = [];

        foreach ($this->routes() as $route) {
            foreach ($this->methods($route) as $method) {
                $out[$this->shape->key($method, $route->uri())] = $route->uri();
            }
        }

        return $out;
    }

    /**
     * The fields each endpoint validates, from the action's FormRequest or, failing
     * that, from an inline $request->validate([...]) in the method body.
     *
     * @return array<string, array<string, array{required: bool, rule: string}>>
     */
    public function rules(): array
    {
        $out = [];

        foreach ($this->routes() as $route) {
            $action = $route->getActionName();

            if (!str_contains($action, '@')) {
                continue;
            }

            [$class, $methodName] = explode('@', $action);

            try {
                $method = new ReflectionMethod($class, $methodName);
            } catch (Throwable) {
                continue;
            }

            $rules = $this->fromFormRequest($method) ?: $this->fromMethodBody($method);

            if ($rules === []) {
                continue;
            }

            foreach ($this->methods($route) as $httpMethod) {
                $out[$this->shape->key($httpMethod, $route->uri())] = $rules;
            }
        }

        return $out;
    }

    /** @return list<Route> */
    private function routes(): array
    {
        $prefix = $this->prefix === '' ? '' : $this->prefix . '/';

        return array_values(array_filter(
            iterator_to_array(RouteFacade::getRoutes()),
            fn(Route $route) => $prefix === '' || str_starts_with($route->uri(), $prefix)
        ));
    }

    /** @return list<string> */
    private function methods(Route $route): array
    {
        return array_values(array_diff($route->methods(), ['HEAD', 'OPTIONS']));
    }

    private function fromFormRequest(ReflectionMethod $method): array
    {
        foreach ($method->getParameters() as $parameter) {
            $type = $parameter->getType();

            if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
                continue;
            }

            $name = $type->getName();

            if (!class_exists($name) || !is_subclass_of($name, FormRequest::class)) {
                continue;
            }

            try {
                return $this->normalise((new $name)->rules());
            } catch (Throwable) {
                return [];                                 // rules() that needs a live request
            }
        }

        return [];
    }

    private function fromMethodBody(ReflectionMethod $method): array
    {
        $file = $method->getFileName();

        if ($file === false) {
            return [];
        }

        $lines = file($file) ?: [];
        $body = implode('', array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));

        if (!preg_match('~->validate\(\s*\[(.*?)]\s*\)~s', $body, $match)) {
            return [];
        }

        preg_match_all("~'([a-zA-Z0-9_.*]+)'\s*=>\s*(\[[^]]*]|'[^']*')~s", $match[1], $found, PREG_SET_ORDER);

        $rules = [];
        foreach ($found as [, $field, $rule]) {
            $rules[$field] = $rule;
        }

        return $this->normalise($rules);
    }

    private function normalise(array $rules): array
    {
        $out = [];

        foreach ($rules as $field => $rule) {
            $text = is_array($rule)
                ? implode('|', array_map(fn($r) => is_object($r) ? class_basename($r) : (string) $r, $rule))
                : (string) $rule;

            $out[$field] = ['required' => str_contains($text, 'required'), 'rule' => $text];
        }

        return $out;
    }
}
