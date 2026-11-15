<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\SupportTicket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminSupportTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $customerUser;
    protected User $staffUser;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $supportRole = Role::firstOrCreate(['name' => 'support_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);

        $this->adminUser = User::factory()->create(['name' => 'Admin User', 'email' => 'admin@onestall.test']);
        $this->adminUser->assignRole($adminRole);

        $this->staffUser = User::factory()->create(['name' => 'Support Agent', 'email' => 'agent@onestall.test']);
        $this->staffUser->assignRole($supportRole);

        $this->customerUser = User::factory()->create(['name' => 'Customer Joe', 'email' => 'joe@customer.test']);
    }

    public function test_guest_is_redirected_to_admin_login(): void
    {
        $response = $this->get(route('admin.support.index'));
        $response->assertRedirect(route('admin.login'));
    }

    public function test_non_admin_customer_is_forbidden(): void
    {
        $response = $this->actingAs($this->customerUser)->get(route('admin.support.index'));
        $response->assertStatus(403);
    }

    public function test_admin_can_view_support_index_with_metrics_and_tickets(): void
    {
        SupportTicket::factory()->create([
            'ticket_number' => 'TCK-1001',
            'subject' => 'Payment issue on checkout',
            'status' => TicketStatus::OPEN,
            'priority' => TicketPriority::HIGH,
            'user_id' => $this->customerUser->id,
        ]);

        SupportTicket::factory()->create([
            'ticket_number' => 'TCK-1002',
            'subject' => 'Delivery delay complaint',
            'status' => TicketStatus::IN_PROGRESS,
            'priority' => TicketPriority::MEDIUM,
            'user_id' => $this->customerUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('admin.support.index'));
        $response->assertStatus(200);
        $response->assertSee('Customer &amp; Seller Support Tickets', false);
        $response->assertSee('TCK-1001');
        $response->assertSee('Payment issue on checkout');
        $response->assertSee('TCK-1002');
    }

    public function test_admin_can_filter_tickets_by_status(): void
    {
        SupportTicket::factory()->create([
            'ticket_number' => 'TCK-OPEN-1',
            'status' => TicketStatus::OPEN,
        ]);

        SupportTicket::factory()->create([
            'ticket_number' => 'TCK-WAIT-1',
            'status' => TicketStatus::WAITING_CUSTOMER,
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('admin.support.index', ['status' => 'waiting_customer']));
        $response->assertStatus(200);
        $response->assertSee('TCK-WAIT-1');
        $response->assertDontSee('TCK-OPEN-1');
    }

    public function test_admin_can_filter_tickets_by_priority(): void
    {
        SupportTicket::factory()->create([
            'ticket_number' => 'TCK-URGENT',
            'priority' => TicketPriority::URGENT,
        ]);

        SupportTicket::factory()->create([
            'ticket_number' => 'TCK-LOW',
            'priority' => TicketPriority::LOW,
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('admin.support.index', ['priority' => 'urgent']));
        $response->assertStatus(200);
        $response->assertSee('TCK-URGENT');
        $response->assertDontSee('TCK-LOW');
    }

    public function test_admin_can_search_tickets(): void
    {
        SupportTicket::factory()->create([
            'ticket_number' => 'TCK-SEARCH-99',
            'subject' => 'Unique refund inquiry',
        ]);

        SupportTicket::factory()->create([
            'ticket_number' => 'TCK-OTHER-01',
            'subject' => 'Standard packaging question',
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('admin.support.index', ['search' => 'Unique refund']));
        $response->assertStatus(200);
        $response->assertSee('TCK-SEARCH-99');
        $response->assertDontSee('TCK-OTHER-01');
    }

    public function test_admin_can_view_ticket_thread_and_messages(): void
    {
        $ticket = SupportTicket::factory()->create([
            'ticket_number' => 'TCK-THREAD-1',
            'subject' => 'Need help tracking item',
            'user_id' => $this->customerUser->id,
            'status' => TicketStatus::OPEN,
        ]);

        TicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $this->customerUser->id,
            'message' => 'Where is my order? It was placed 3 days ago.',
            'is_internal' => false,
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('admin.support.show', $ticket));
        $response->assertStatus(200);
        $response->assertSee('TCK-THREAD-1');
        $response->assertSee('Where is my order? It was placed 3 days ago.');
        $response->assertSee('Customer Joe');
    }

    public function test_admin_can_post_public_reply_and_updates_status(): void
    {
        $ticket = SupportTicket::factory()->create([
            'status' => TicketStatus::OPEN,
            'user_id' => $this->customerUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)->post(route('admin.support.reply', $ticket), [
            'message' => 'Hello! We have escalated this to BlueDart courier.',
            'is_internal' => 0,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('ticket_messages', [
            'support_ticket_id' => $ticket->id,
            'user_id' => $this->adminUser->id,
            'message' => 'Hello! We have escalated this to BlueDart courier.',
            'is_internal' => false,
        ]);

        $this->assertEquals(TicketStatus::IN_PROGRESS, $ticket->fresh()->status);
    }

    public function test_admin_can_post_internal_note(): void
    {
        $ticket = SupportTicket::factory()->create([
            'status' => TicketStatus::OPEN,
        ]);

        $response = $this->actingAs($this->adminUser)->post(route('admin.support.reply', $ticket), [
            'message' => 'Internal note: Customer called hotline twice.',
            'is_internal' => 1,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('ticket_messages', [
            'support_ticket_id' => $ticket->id,
            'user_id' => $this->adminUser->id,
            'message' => 'Internal note: Customer called hotline twice.',
            'is_internal' => true,
        ]);
    }

    public function test_admin_can_assign_ticket_to_staff(): void
    {
        $ticket = SupportTicket::factory()->create([
            'status' => TicketStatus::OPEN,
            'assigned_to' => null,
        ]);

        $response = $this->actingAs($this->adminUser)->post(route('admin.support.assign', $ticket), [
            'assigned_to' => $this->staffUser->id,
        ]);

        $response->assertRedirect();
        $this->assertEquals($this->staffUser->id, $ticket->fresh()->assigned_to);
    }

    public function test_admin_can_update_ticket_status_including_waiting_customer(): void
    {
        $ticket = SupportTicket::factory()->create([
            'status' => TicketStatus::OPEN,
        ]);

        $response = $this->actingAs($this->adminUser)->post(route('admin.support.status', $ticket), [
            'status' => 'waiting_customer',
        ]);

        $response->assertRedirect();
        $this->assertEquals(TicketStatus::WAITING_CUSTOMER, $ticket->fresh()->status);

        $response = $this->actingAs($this->adminUser)->post(route('admin.support.status', $ticket), [
            'status' => 'resolved',
        ]);

        $response->assertRedirect();
        $this->assertEquals(TicketStatus::RESOLVED, $ticket->fresh()->status);
    }

    public function test_admin_can_export_tickets_csv(): void
    {
        SupportTicket::factory()->create([
            'ticket_number' => 'TCK-EXP-1',
            'subject' => 'Export test ticket',
            'status' => TicketStatus::OPEN,
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('admin.support.export'));
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();
        $this->assertStringStartsWith(chr(0xEF) . chr(0xBB) . chr(0xBF), $content);
        $this->assertStringContainsString('Ticket #', $content);
        $this->assertStringContainsString('Assigned Agent', $content);
        $this->assertStringContainsString('TCK-EXP-1', $content);
        $this->assertStringContainsString('Export test ticket', $content);
    }

    public function test_admin_can_request_ai_reply_draft(): void
    {
        $ticket = SupportTicket::factory()->create([
            'ticket_number' => 'TCK-AI-1',
            'subject' => 'Where is my delivery?',
            'user_id' => $this->customerUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson(route('admin.support.ai_reply', $ticket), [
                'auto_send' => false,
                'message' => 'Where is my order tracking information?',
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'intent',
                'confidence',
                'reply_text',
                'auto_posted',
            ],
        ]);
    }
}
