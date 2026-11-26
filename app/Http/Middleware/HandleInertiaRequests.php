<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $this->sharedUser($request->user()),
            ],
        ];
    }

    /**
     * Hanya field yang dibutuhkan UI; email, password hash, dsb. tidak dikirim ke browser.
     *
     * @return array{id: int, name: string, username: string, role: string, department: array{id: int, name: string}|null}|null
     */
    private function sharedUser(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        $department = $user->department;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'role' => $user->role->value,
            'department' => $department === null ? null : ['id' => $department->id, 'name' => $department->name],
        ];
    }
}
