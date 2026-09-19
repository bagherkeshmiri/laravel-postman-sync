<?php

namespace BagherKeshmiri\PostmanSync;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PostmanApi
{
    private const ENDPOINT = 'https://api.getpostman.com/collections';

    public function __construct(
        private readonly ?string $key,
        private readonly ?string $uid,
        private readonly int $timeout = 120,
    ) {
    }

    public function configured(): bool
    {
        return filled($this->key) && filled($this->uid);
    }

    /** The collections the key can see, as "name => uid", to help find the uid to configure. */
    public function collections(): array
    {
        $response = $this->request()->get(self::ENDPOINT);

        $this->guard($response->failed(), $response->status(), $response->body());

        return array_column($response->json('collections') ?? [], 'uid', 'name');
    }

    public function fetch(): array
    {
        $response = $this->request()->get(self::ENDPOINT . '/' . $this->uid);

        $this->guard($response->failed(), $response->status(), $response->body());

        return $response->json('collection') ?? [];
    }

    /** Postman has no merge endpoint: a PUT replaces the whole collection. */
    public function replace(array $collection): string
    {
        $response = $this->request()->put(self::ENDPOINT . '/' . $this->uid, ['collection' => $collection]);

        $this->guard($response->failed(), $response->status(), $response->body());

        return $response->json('collection.name') ?? 'collection';
    }

    private function request(): PendingRequest
    {
        if (!$this->configured()) {
            throw new RuntimeException('Set POSTMAN_API_KEY and POSTMAN_COLLECTION_UID first.');
        }

        return Http::withHeaders(['X-Api-Key' => $this->key])->timeout($this->timeout);
    }

    private function guard(bool $failed, int $status, string $body): void
    {
        if ($failed) {
            throw new RuntimeException("Postman returned $status: $body");
        }
    }
}
