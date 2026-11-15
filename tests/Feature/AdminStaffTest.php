<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminStaffTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $customerUser;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'support_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'catalog_manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);

        $this->adminUser = User::factory()->create([
            'name' => 'Super Admin Officer',
            'email' => 'superadmin@onestall.test',
            'status' => 'active',
        ]);
        $this->adminUser->assignRole($adminRole);

        $this->customerUser = User::factory()->create([
            'name' => 'Customer Joe',
            'email' => 'customer@onestall.test',
        ]);
    }

    public function test_guest_is_redirected_to_admin_login(): void
    {
        $response = $this->get(route('admin.staff.index'));
        $response->assertRedirect(route('admin.login'));
    }

    public function test_non_admin_customer_is_forbidden(): void
    {
        $response = $this->actingAs($this->customerUser)->get(route('admin.staff.index'));
        $response->assertStatus(403);
    }

    public function test_admin_can_view_staff_index_with_metrics_and_list(): void
    {
        $supportStaff = User::factory()->create([
            'name' => 'Aarav Support',
            'email' => 'aarav.support@onestall.test',
            'status' => 'active',
        ]);
        $supportStaff->assignRole('support_admin');

        $response = $this->actingAs($this->adminUser)->get(route('admin.staff.index'));
        $response->assertStatus(200);
        $response->assertSee('Admin Staff &amp; Role Management', false);
        $response->assertSee('Super Admin Officer');
        $response->assertSee('Aarav Support');
        $response->assertSee('aarav.support@onestall.test');
        $response->assertSee('support admin');
    }

    public function test_admin_can_search_staff(): void
    {
        $target = User::factory()->create([
            'name' => 'Unique Staff Agent',
            'email' => 'uniquestaff@onestall.test',
            'phone' => '+91 9998887776',
            'status' => 'active',
        ]);
        $target->assignRole('admin');

        $other = User::factory()->create([
            'name' => 'Other Staff Agent',
            'email' => 'otherstaff@onestall.test',
            'status' => 'active',
        ]);
        $other->assignRole('admin');

        $response = $this->actingAs($this->adminUser)->get(route('admin.staff.index', ['search' => 'Unique Staff']));
        $response->assertStatus(200);
        $response->assertSee('Unique Staff Agent');
        $response->assertDontSee('Other Staff Agent');
    }

    public function test_admin_can_filter_staff_by_role_and_status(): void
    {
        $supportStaff = User::factory()->create([
            'name' => 'Support Only Person',
            'email' => 'supportonly@onestall.test',
            'status' => 'active',
        ]);
        $supportStaff->assignRole('support_admin');

        $catalogStaff = User::factory()->create([
            'name' => 'Catalog Suspended Person',
            'email' => 'catalog@onestall.test',
            'status' => 'suspended',
        ]);
        $catalogStaff->assignRole('catalog_manager');

        $roleFilter = $this->actingAs($this->adminUser)->get(route('admin.staff.index', ['role' => 'support_admin']));
        $roleFilter->assertStatus(200);
        $roleFilter->assertSee('Support Only Person');
        $roleFilter->assertDontSee('Catalog Suspended Person');

        $statusFilter = $this->actingAs($this->adminUser)->get(route('admin.staff.index', ['status' => 'suspended']));
        $statusFilter->assertStatus(200);
        $statusFilter->assertSee('Catalog Suspended Person');
        $statusFilter->assertDontSee('Support Only Person');
    }

    public function test_admin_can_create_new_staff_member(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('admin.staff.store'), [
            'name' => 'Kavita Patel',
            'email' => 'kavita.patel@onestall.test',
            'phone' => '+91 9876543210',
            'password' => 'SecurePass123!',
            'role' => 'support_admin',
            'status' => 'active',
        ]);

        $response->assertRedirect(route('admin.staff.index'));
        $this->assertDatabaseHas('users', [
            'name' => 'Kavita Patel',
            'email' => 'kavita.patel@onestall.test',
            'status' => 'active',
        ]);

        $kavita = User::where('email', 'kavita.patel@onestall.test')->first();
        $this->assertNotNull($kavita);
        $this->assertTrue($kavita->hasRole('support_admin'));
    }

    public function test_staff_create_validates_unique_email(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('admin.staff.store'), [
            'name' => 'Duplicate Officer',
            'email' => 'superadmin@onestall.test', // matches adminUser
            'password' => 'SecurePass123!',
            'role' => 'admin',
        ]);

        $response->assertSessionHasErrors(['email']);
    }

    public function test_admin_can_update_existing_staff_member(): void
    {
        $staff = User::factory()->create([
            'name' => 'Pre-update Staff',
            'email' => 'preupdate@onestall.test',
            'phone' => '+91 1122334455',
            'status' => 'active',
        ]);
        $staff->assignRole('admin');

        $response = $this->actingAs($this->adminUser)->put(route('admin.staff.update', $staff), [
            'name' => 'Post-update Staff',
            'email' => 'postupdate@onestall.test',
            'phone' => '+91 9988776655',
            'role' => 'support_admin',
            'status' => 'suspended',
        ]);

        $response->assertRedirect(route('admin.staff.index'));

        $fresh = $staff->fresh();
        $this->assertEquals('Post-update Staff', $fresh->name);
        $this->assertEquals('postupdate@onestall.test', $fresh->email);
        $this->assertEquals('+91 9988776655', $fresh->phone);
        $this->assertEquals('suspended', $fresh->status instanceof \App\Enums\UserStatus ? $fresh->status->value : (string) $fresh->status);
        $this->assertTrue($fresh->hasRole('support_admin'));
        $this->assertFalse($fresh->hasRole('admin'));
    }

    public function test_admin_can_update_staff_password(): void
    {
        $staff = User::factory()->create([
            'name' => 'Password Change Staff',
            'email' => 'pwdchange@onestall.test',
            'password' => Hash::make('OldPassword123'),
            'status' => 'active',
        ]);
        $staff->assignRole('admin');

        $response = $this->actingAs($this->adminUser)->put(route('admin.staff.update', $staff), [
            'name' => 'Password Change Staff',
            'email' => 'pwdchange@onestall.test',
            'password' => 'BrandNewPassword456!',
            'role' => 'admin',
            'status' => 'active',
        ]);

        $response->assertRedirect(route('admin.staff.index'));
        $this->assertTrue(Hash::check('BrandNewPassword456!', $staff->fresh()->password));
    }

    public function test_admin_cannot_delete_self(): void
    {
        $response = $this->actingAs($this->adminUser)->delete(route('admin.staff.destroy', $this->adminUser));
        $response->assertRedirect();
        $response->assertSessionHas('error', 'You cannot delete your own admin account.');
        $this->assertDatabaseHas('users', ['id' => $this->adminUser->id]);
    }

    public function test_admin_can_delete_other_staff(): void
    {
        $staff = User::factory()->create([
            'name' => 'To Be Removed',
            'email' => 'removeme@onestall.test',
            'status' => 'active',
        ]);
        $staff->assignRole('admin');

        $response = $this->actingAs($this->adminUser)->delete(route('admin.staff.destroy', $staff));
        $response->assertRedirect(route('admin.staff.index'));
        $this->assertSoftDeleted('users', ['id' => $staff->id]);
    }

    public function test_admin_can_view_permissions_matrix(): void
    {
        Permission::firstOrCreate(['name' => 'orders.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'orders.manage', 'guard_name' => 'web']);

        $response = $this->actingAs($this->adminUser)->get(route('admin.staff.roles'));
        $response->assertStatus(200);
        $response->assertSee('Role &amp; Permission Access Matrix', false);
        $response->assertSee('super admin');
        $response->assertSee('orders.view');
    }

    public function test_admin_can_update_role_permissions(): void
    {
        $perm1 = Permission::firstOrCreate(['name' => 'settlements.view', 'guard_name' => 'web']);
        $perm2 = Permission::firstOrCreate(['name' => 'settlements.process', 'guard_name' => 'web']);

        $role = Role::findByName('support_admin', 'web');

        $response = $this->actingAs($this->adminUser)->put(route('admin.staff.roles.update', $role), [
            'permissions' => [$perm1->name, $perm2->name],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $freshRole = $role->fresh();
        $this->assertTrue($freshRole->hasPermissionTo('settlements.view'));
        $this->assertTrue($freshRole->hasPermissionTo('settlements.process'));
    }
}
