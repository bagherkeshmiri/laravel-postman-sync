<?php

namespace BagherKeshmiri\PostmanSync\Tests;

use BagherKeshmiri\PostmanSync\RouteMap;
use BagherKeshmiri\PostmanSync\Tests\Fixtures\UserController;
use Illuminate\Routing\Router;
use PHPUnit\Framework\Attributes\Test;

class RouteMapTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        /** @var Router $router */
        $router->get('api/users', [UserController::class, 'index']);
        $router->post('api/users', [UserController::class, 'store']);
        $router->get('api/users/{user}', [UserController::class, 'show']);
        $router->get('web/dashboard', fn() => 'not part of the api');
    }

    #[Test]
    public function only_routes_under_the_configured_prefix_are_documented(): void
    {
        $endpoints = $this->app->make(RouteMap::class)->endpoints();

        $this->assertSame([
            'GET users' => 'api/users',
            'POST users' => 'api/users',
            'GET users/{param}' => 'api/users/{user}',
        ], $endpoints);
    }

    #[Test]
    public function fields_come_from_the_form_request_on_the_action(): void
    {
        $rules = $this->app->make(RouteMap::class)->rules();

        $this->assertSame([
            'required' => true,
            'rule' => 'required|email',
        ], $rules['POST users']['email']);

        $this->assertSame('nullable|image', $rules['POST users']['avatar']['rule']);
        $this->assertFalse($rules['POST users']['active']['required']);
    }

    #[Test]
    public function an_inline_validate_call_is_read_when_there_is_no_form_request(): void
    {
        $rules = $this->app->make(RouteMap::class)->rules();

        $this->assertSame(['search', 'per_page'], array_keys($rules['GET users']));
        $this->assertTrue($rules['GET users']['per_page']['required']);
    }

    #[Test]
    public function an_action_that_validates_nothing_contributes_no_rules(): void
    {
        $this->assertArrayNotHasKey('GET users/{param}', $this->app->make(RouteMap::class)->rules());
    }
}
