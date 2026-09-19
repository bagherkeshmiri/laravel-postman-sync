<?php

namespace BagherKeshmiri\PostmanSync\Tests\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class UserController extends Controller
{
    public function index(Request $request): array
    {
        $request->validate([
            'search' => 'nullable|string',
            'per_page' => 'required|integer',
        ]);

        return [];
    }

    public function store(StoreUserRequest $request): array
    {
        return [];
    }

    public function show(int $user): array
    {
        return [];
    }
}
