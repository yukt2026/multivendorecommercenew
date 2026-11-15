<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Enum;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class StaffController extends Controller
{
    /**
     * Admin staff role identifiers.
     */
    protected array $adminRoles = [
        'super_admin',
        'admin',
        'support_admin',
        'catalog_manager',
        'finance_admin',
        'support_staff',
    ];

    /**
     * Display list of admin staff members.
     */
    public function index(Request $request): View
    {
        $existingAdminRoles = Role::whereIn('name', $this->adminRoles)->pluck('name')->toArray();

        $query = !empty($existingAdminRoles)
            ? User::role($existingAdminRoles)->with('roles')->latest('id')
            : User::whereRaw('1 = 0');

        if ($search = $request->query('search')) {
            $search = trim((string) $search);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($role = $request->query('role')) {
            if ($role !== 'all' && in_array($role, $existingAdminRoles, true)) {
                $query->role($role);
            }
        }

        if ($status = $request->query('status')) {
            if ($status !== 'all') {
                $query->where('status', $status);
            }
        }

        $staff = $query->paginate(15)->withQueryString();
        $roles = Role::whereIn('name', $this->adminRoles)->get();

        $metrics = [
            'total_staff' => !empty($existingAdminRoles) ? User::role($existingAdminRoles)->count() : 0,
            'super_admins' => in_array('super_admin', $existingAdminRoles, true) ? User::role('super_admin')->count() : 0,
            'admins' => in_array('admin', $existingAdminRoles, true) ? User::role('admin')->count() : 0,
            'support_admins' => in_array('support_admin', $existingAdminRoles, true) ? User::role('support_admin')->count() : 0,
        ];

        return view('admin.staff.index', compact('staff', 'roles', 'metrics'));
    }

    /**
     * Store new admin staff user.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'string', 'exists:roles,name'],
            'status' => ['nullable', 'string', new Enum(UserStatus::class)],
        ]);

        $status = !empty($validated['status']) ? UserStatus::from($validated['status']) : UserStatus::ACTIVE;

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
            'status' => $status,
        ]);

        $user->assignRole($validated['role']);

        if (function_exists('activity')) {
            activity('staff')
                ->performedOn($user)
                ->causedBy(auth()->user())
                ->withProperties(['role' => $validated['role']])
                ->log("Staff user {$user->name} created with role {$validated['role']}");
        }

        return redirect()->route('admin.staff.index')->with('success', "Staff member '{$user->name}' created successfully.");
    }

    /**
     * Update existing staff user.
     */
    public function update(Request $request, User $staff): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $staff->id],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['required', 'string', 'exists:roles,name'],
            'status' => ['nullable', 'string', new Enum(UserStatus::class)],
        ]);

        $staff->name = $validated['name'];
        $staff->email = $validated['email'];
        $staff->phone = $validated['phone'] ?? null;
        if (!empty($validated['status'])) {
            $staff->status = UserStatus::from($validated['status']);
        }

        if (!empty($validated['password'])) {
            $staff->password = Hash::make($validated['password']);
        }
        $staff->save();

        $staff->syncRoles([$validated['role']]);

        if (function_exists('activity')) {
            activity('staff')
                ->performedOn($staff)
                ->causedBy(auth()->user())
                ->withProperties(['role' => $validated['role']])
                ->log("Staff user {$staff->name} updated");
        }

        return redirect()->route('admin.staff.index')->with('success', "Staff member '{$staff->name}' updated successfully.");
    }

    /**
     * Delete a staff user (cannot delete self).
     */
    public function destroy(User $staff): RedirectResponse
    {
        if ($staff->id === auth()->id()) {
            return back()->with('error', 'You cannot delete your own admin account.');
        }

        $name = $staff->name;
        $staff->delete();

        if (function_exists('activity')) {
            activity('staff')
                ->causedBy(auth()->user())
                ->log("Staff user {$name} deleted");
        }

        return redirect()->route('admin.staff.index')->with('success', "Staff member '{$name}' deleted.");
    }

    /**
     * Granular Permission Matrix UI.
     */
    public function roles(): View
    {
        $roles = Role::whereIn('name', $this->adminRoles)->with('permissions')->get();
        $permissions = Permission::all()->groupBy(function ($p) {
            return explode('.', $p->name)[0] ?? 'general';
        });

        return view('admin.staff.roles', compact('roles', 'permissions'));
    }

    /**
     * Update permissions for a specific role.
     */
    public function updateRolePermissions(Request $request, Role $role): RedirectResponse
    {
        $validated = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['exists:permissions,name'],
        ]);

        $permissions = $validated['permissions'] ?? [];
        $role->syncPermissions($permissions);

        if (function_exists('activity')) {
            activity('role')
                ->performedOn($role)
                ->causedBy(auth()->user())
                ->log("Permissions updated for role {$role->name}");
        }

        return back()->with('success', "Permissions updated for role '{$role->name}'.");
    }
}
