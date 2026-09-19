<?php

namespace BagherKeshmiri\PostmanSync\Tests;

use BagherKeshmiri\PostmanSync\Shape;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

class ShapeTest extends PHPUnitTestCase
{
    #[Test]
    public function a_laravel_uri_and_a_postman_url_collapse_to_the_same_key(): void
    {
        $shape = new Shape('api');

        $this->assertSame(
            $shape->key('GET', 'api/providers/{provider}/orders'),
            $shape->key('get', '{{base_url}}/providers/{{provider_id}}/orders'),
        );
    }

    #[Test]
    public function it_strips_the_prefix_the_query_string_and_a_literal_host(): void
    {
        $shape = new Shape('api');

        $this->assertSame('users', $shape->of('api/users'));
        $this->assertSame('users', $shape->of('/api/users/'));
        $this->assertSame('users', $shape->of('https://example.test/api/users?page=2'));
    }

    #[Test]
    public function a_numeric_segment_counts_as_a_parameter(): void
    {
        $shape = new Shape('api');

        $this->assertSame('users/{param}', $shape->of('{{base_url}}/users/17'));
    }

    #[Test]
    public function it_keys_a_postman_item_by_method_and_path(): void
    {
        $shape = new Shape('api');

        $item = [
            'request' => [
                'method' => 'PATCH',
                'url' => ['raw' => '{{base_url}}/users/{{user_id}}', 'path' => ['users', '{{user_id}}']],
            ],
        ];

        $this->assertSame('PATCH users/{param}', $shape->ofItem($item));
    }

    #[Test]
    public function without_a_prefix_nothing_is_stripped(): void
    {
        $this->assertSame('api/users', (new Shape)->of('api/users'));
    }
}
