<?php

namespace BagherKeshmiri\PostmanSync\Tests;

use BagherKeshmiri\PostmanSync\BuildReport;
use BagherKeshmiri\PostmanSync\CollectionBuilder;
use BagherKeshmiri\PostmanSync\CollectionFile;
use BagherKeshmiri\PostmanSync\Shape;
use BagherKeshmiri\PostmanSync\Tests\Fixtures\Collection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

class CollectionBuilderTest extends PHPUnitTestCase
{
    private function builder(string $rootFolder = ''): CollectionBuilder
    {
        return new CollectionBuilder(new Shape('api'), 'base_url', '_id', [], $rootFolder, 'api');
    }

    private function endpoints(): array
    {
        return [
            'GET users' => 'api/users',
            'POST users' => 'api/users',
            'GET users/{param}' => 'api/users/{user}',
            'POST projects/{param}/tasks' => 'api/projects/{project}/tasks',
        ];
    }

    private function rules(): array
    {
        return [
            'GET users' => [
                'search' => ['required' => false, 'rule' => 'nullable|string'],
                'per_page' => ['required' => true, 'rule' => 'required|integer'],
            ],
            'POST users' => [
                'name' => ['required' => true, 'rule' => 'required|string'],
                'email' => ['required' => true, 'rule' => 'required|email'],
                'active' => ['required' => false, 'rule' => 'boolean'],
                'avatar' => ['required' => false, 'rule' => 'nullable|image'],
                'items.*.price' => ['required' => true, 'rule' => 'required|numeric'],
            ],
        ];
    }

    private function build(?array $collection = null, ?BuildReport $report = null): array
    {
        return $this->builder()->build(
            $collection ?? Collection::make(),
            $this->endpoints(),
            $this->rules(),
            $report ?? new BuildReport,
        );
    }

    /** @return array<string, array> shape key => request item */
    private function requests(array $collection): array
    {
        $shape = new Shape('api');
        $out = [];

        foreach (CollectionFile::requests($collection['item']) as $item) {
            $out[$shape->ofItem($item)] = $item;
        }

        return $out;
    }

    private function folderNames(array $items, string $prefix = ''): array
    {
        $out = [];

        foreach ($items as $item) {
            if (!isset($item['item'])) {
                continue;
            }

            $name = $prefix === '' ? $item['name'] : $prefix . ' / ' . $item['name'];
            $out[] = $name;
            $out = array_merge($out, $this->folderNames($item['item'], $name));
        }

        return $out;
    }

    #[Test]
    public function a_request_whose_route_is_gone_is_removed_with_the_folder_it_emptied(): void
    {
        $report = new BuildReport;
        $collection = $this->build(report: $report);

        $this->assertSame(['DELETE users/legacy'], $report->removed);
        $this->assertArrayNotHasKey('DELETE users/legacy', $this->requests($collection));
        $this->assertNotContains('Legacy', $this->folderNames($collection['item']));
    }

    #[Test]
    public function a_new_route_joins_the_folder_of_the_requests_it_shares_a_path_with(): void
    {
        $collection = $this->build();
        $users = $collection['item'][0];

        $this->assertSame('Users', $users['name']);
        $this->assertContains('show', array_column($users['item'], 'name'));
    }

    #[Test]
    public function a_new_route_with_no_relatives_gets_folders_named_after_its_path(): void
    {
        $collection = $this->build();

        $this->assertContains('Projects', $this->folderNames($collection['item']));
        $this->assertContains('Projects / Tasks', $this->folderNames($collection['item']));
        $this->assertArrayHasKey('POST projects/{param}/tasks', $this->requests($collection));
    }

    #[Test]
    public function the_root_folder_setting_wraps_the_fallback(): void
    {
        $collection = $this->builder('API')->build(Collection::make(), $this->endpoints(), [], new BuildReport);

        $this->assertContains('API / Projects / Tasks', $this->folderNames($collection['item']));
    }

    #[Test]
    public function a_route_parameter_becomes_a_collection_variable_in_the_url(): void
    {
        $request = $this->requests($this->build())['GET users/{param}']['request'];

        $this->assertSame('{{base_url}}/users/{{user_id}}', $request['url']['raw']);
        $this->assertSame(['users', '{{user_id}}'], $request['url']['path']);
    }

    #[Test]
    public function saved_responses_and_headers_survive_a_build(): void
    {
        $index = $this->requests($this->build())['GET users'];

        $this->assertSame([['name' => '200 ok', 'body' => '{"data":[]}']], $index['response']);
        $this->assertSame([['key' => 'Accept', 'value' => 'application/json']], $index['request']['header']);
    }

    #[Test]
    public function the_json_body_follows_the_rules_and_keeps_what_was_typed_in(): void
    {
        $body = json_decode($this->requests($this->build())['POST users']['request']['body']['raw'], true);

        $this->assertSame('Elnaz', $body['name']);
        $this->assertSame('user@example.com', $body['email']);
        $this->assertFalse($body['active']);
        $this->assertSame([['price' => 0]], $body['items']);
        $this->assertArrayNotHasKey('avatar', $body, 'an upload cannot be expressed in a JSON body');
    }

    #[Test]
    public function a_body_that_is_not_json_is_assumed_hand_written_and_left_alone(): void
    {
        $collection = Collection::make();
        $collection['item'][0]['item'][1]['request']['body']['raw'] = '<user><name>Elnaz</name></user>';

        $built = $this->build($collection);

        $this->assertSame('<user><name>Elnaz</name></user>', $this->requests($built)['POST users']['request']['body']['raw']);
    }

    #[Test]
    public function a_get_takes_its_rules_as_query_parameters_and_optional_ones_start_disabled(): void
    {
        $url = $this->requests($this->build())['GET users']['request']['url'];

        $this->assertSame(
            [
                ['key' => 'search', 'value' => '', 'description' => 'nullable|string', 'disabled' => true],
                ['key' => 'per_page', 'value' => '0', 'description' => 'required|integer', 'disabled' => false],
            ],
            $url['query'],
        );

        $this->assertSame('{{base_url}}/users?per_page=0', $url['raw']);
    }

    #[Test]
    public function every_variable_the_collection_mentions_is_declared_once(): void
    {
        $report = new BuildReport;
        $collection = $this->build(report: $report);
        $variables = array_column($collection['variable'], 'value', 'key');

        $this->assertSame(['user_id', 'project_id'], $report->variables);
        $this->assertSame('http://localhost/api', $variables['base_url'], 'an existing value is not reset');
        $this->assertSame('', $variables['user_id']);
    }

    #[Test]
    public function an_empty_starter_file_is_enough_to_begin_with(): void
    {
        $starter = [
            'info' => ['name' => 'My API', 'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json'],
            'item' => [],
        ];

        $built = $this->build($starter);

        $this->assertSame(array_keys($this->endpoints()), array_keys($this->requests($built)));
        $this->assertSame(['base_url', 'user_id', 'project_id'], array_column($built['variable'], 'key'));
    }

    #[Test]
    public function a_second_build_over_its_own_output_changes_nothing(): void
    {
        $second = new BuildReport;
        $this->builder()->build($this->build(), $this->endpoints(), $this->rules(), $second);

        $this->assertFalse($second->changed());
    }
}
