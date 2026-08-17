<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserAdminController extends Controller
{
    public function index(Request $request): Response
    {
        $q = trim((string) $request->string('q'));
        $role = (string) $request->string('role', 'all');
        $allowedRoles = ['admin', 'receiving', 'analyst', 'head_analysis'];

        if (! in_array($role, ['all', ...$allowedRoles], true)) {
            $role = 'all';
        }

        $users = User::query()
            ->with('roles')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%");
                });
            })
            ->when($role !== 'all', fn ($query) => $query->role($role))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames()->values(),
            ]);

        $roleNames = Role::query()->orderBy('name')->pluck('name');
        $counts = ['all' => User::query()->count()];
        foreach ($roleNames as $roleName) {
            $counts[$roleName] = User::role($roleName)->count();
        }

        return Inertia::render('admin/users', [
            'users' => $users,
            'roles' => $roleNames,
            'filters' => [
                'q' => $q,
                'role' => $role,
            ],
            'counts' => $counts,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::defaults()],
            'role' => ['required', 'string', Rule::in(['admin', 'receiving', 'analyst', 'head_analysis'])],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'email_verified_at' => now(),
        ]);
        $user->syncRoles([$data['role']]);

        return back()->with('success', 'User created.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', Password::defaults()],
            'role' => ['required', 'string', Rule::in(['admin', 'receiving', 'analyst', 'head_analysis'])],
        ]);

        $user->name = $data['name'];
        $user->email = $data['email'];
        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }
        $user->save();
        $user->syncRoles([$data['role']]);

        return back()->with('success', 'User updated.');
    }
}
