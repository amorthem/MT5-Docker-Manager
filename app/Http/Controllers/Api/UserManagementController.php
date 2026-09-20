<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserManagementController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => User::query()->select(['id', 'name', 'email', 'role'])->paginate(25),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12'],
            'role' => ['required', 'string', 'in:user,support,admin,dev'],
        ]);

        $role = UserRole::from($validated['role']);
        abort_unless($request->user()->canManageRole($role), 403);

        $user = User::create([
            ...$validated,
            'password' => Hash::make($validated['password']),
            'role' => $role,
        ]);

        return response()->json([
            'data' => $user->only(['id', 'name', 'email', 'role']),
        ], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'password' => ['sometimes', 'string', 'min:12'],
            'role' => ['sometimes', 'string', 'in:user,support,admin,dev'],
        ]);

        abort_unless($request->user()->canManageRole($user->role), 403);

        if (isset($validated['role'])) {
            abort_unless($request->user()->canManageRole(UserRole::from($validated['role'])), 403);
        }

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $user->update($validated);

        return response()->json([
            'data' => $user->only(['id', 'name', 'email', 'role']),
        ]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        abort_if($request->user()->is($user), 422, 'You cannot delete your own account.');
        abort_unless($request->user()->canManageRole($user->role), 403);

        $user->delete();

        return response()->json(['message' => 'User deleted.']);
    }
}