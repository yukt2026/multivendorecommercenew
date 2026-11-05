<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class StaffController extends Controller
{
    /**
     * Display list of admin staff members.
     */
    public function index(Request $request): View
    {
        $adminRoles = ['super_admin', 'admin', 'support_admin', 'catalog_manager', 'finance_admin'];
        $query = User::role($adminRoles)->with('roles')->latest('id');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($role = $request->query('role')) {
            if ($role !== 'all') {
                $query->role($role);
            }
        }

        $staff = $query->paginate(15)->withQueryString();
        $roles = Role::whereIn('name', $adminRoles)->orWhere('guard_name', 'web')->get();

        $metrics = [
            'total_staff' => User::role($adminRoles)->count(),
            'super_admins' => User::role('super_admin')->count(),
            'admins' => User::role('admin')->count(),
            'support_admins' => User::role('support_admin')->count(),
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
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
            'status' => 'active',
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
            'status' => ['required', 'in:active,blocked'],
        ]);

        $staff->name = $validated['name'];
        $staff->email = $validated['email'];
        $staff->phone = $validated['phone'] ?? null;
        $staff->status = $validated['status'];

        if (!empty($validated['password'])) {
            $staff->password = Hash::make($validated['password']);
        }
        $staff->save();

        $staff->syncRoles([$validated['role']]);

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
        $adminRoles = ['super_admin', 'admin', 'support_admin', 'finance_admin', 'catalog_manager'];
        $roles = Role::whereIn('name', $adminRoles)->orWhere('guard_name', 'web')->with('permissions')->get();
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
