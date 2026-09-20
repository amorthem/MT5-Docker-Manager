<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Users/Index', [
            'users' => User::query()
                ->select(['id', 'name', 'email', 'role', 'created_at'])
                ->latest()
                ->paginate(25)
                ->withQueryString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateUser($request, true);
        $role = UserRole::from($validated['role']);

        abort_unless($request->user()->canManageRole($role), 403);

        User::create([
            ...$validated,
            'password' => Hash::make($validated['password']),
            'role' => $role,
        ]);

        return to_route('users.index')->with('success', 'User created.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $this->validateUser($request, false, $user);

        abort_unless($request->user()->canManageRole($user->role), 403);

        if (isset($validated['role'])) {
            abort_unless($request->user()->canManageRole(UserRole::from($validated['role'])), 403);
        }

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $user->update($validated);

        return to_route('users.index')->with('success', 'User updated.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if($request->user()->is($user), 422, 'You cannot delete your own account.');
        abort_unless($request->user()->canManageRole($user->role), 403);

        $user->delete();

        return to_route('users.index')->with('success', 'User deleted.');
    }

    private function validateUser(Request $request, bool $creating, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.($user?->id ?? 'NULL')],
            'password' => [$creating ? 'required' : 'nullable', 'string', 'min:8'],
            'role' => ['required', 'string', 'in:user,support,admin,dev'],
        ]);
    }
}
