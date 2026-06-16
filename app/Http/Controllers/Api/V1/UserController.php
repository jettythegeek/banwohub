<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Concerns\ResolvesOrganization;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    use ResolvesOrganization;

    /** @var list<string> */
    private const ASSIGNABLE_ROLES = [
        'Firm Admin',
        'Partner',
        'Lawyer',
        'Paralegal',
        'Secretary',
        'Client',
        'Consultant',
    ];

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', User::class);

        $organization = $this->organizationFor($request->user());

        $users = User::query()
            ->with('roles')
            ->where('organization_id', $organization->id)
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = '%'.$request->string('search').'%';
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', $search)
                        ->orWhere('email', 'like', $search);
                });
            })
            ->when($request->filled('active'), fn ($q) => $q->where('is_active', $request->boolean('active')))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return UserResource::collection($users);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $organization = $this->organizationFor($request->user());

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', 'string', Rule::in(self::ASSIGNABLE_ROLES)],
            'password' => ['nullable', 'string', PasswordRule::defaults()],
            'phone' => ['nullable', 'string', 'max:50'],
            'job_title' => ['nullable', 'string', 'max:255'],
        ]);

        $tempPassword = $data['password'] ?? Str::password(12);

        $user = User::query()->create([
            'organization_id' => $organization->id,
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'job_title' => $data['job_title'] ?? null,
            'password' => $tempPassword,
            'is_active' => true,
        ]);

        $user->assignRole($data['role']);

        return (new UserResource($user->load('roles')))
            ->additional(array_filter([
                'temporary_password' => ! isset($data['password']) ? $tempPassword : null,
                'message' => 'User created. Share the temporary password securely (invite email coming later).',
            ]))
            ->response()
            ->setStatusCode(201);
    }

    public function show(User $user): UserResource
    {
        $this->authorize('view', $user);

        return new UserResource($user->load('roles'));
    }

    public function update(Request $request, User $user): UserResource
    {
        $this->authorize('update', $user);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['sometimes', 'string', Rule::in(self::ASSIGNABLE_ROLES)],
            'phone' => ['nullable', 'string', 'max:50'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'password' => ['nullable', 'string', PasswordRule::defaults()],
        ]);

        $role = $data['role'] ?? null;
        unset($data['role']);

        $user->update($data);

        if ($role) {
            $user->syncRoles([$role]);
        }

        return new UserResource($user->fresh()->load('roles'));
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'You cannot deactivate your own account.'], 422);
        }

        $user->update(['is_active' => false]);
        $user->tokens()->delete();

        return response()->json(['message' => 'User deactivated successfully.']);
    }

    public function roles(): JsonResponse
    {
        return response()->json([
            'roles' => Role::query()
                ->whereIn('name', self::ASSIGNABLE_ROLES)
                ->orderBy('name')
                ->pluck('name'),
        ]);
    }
}
