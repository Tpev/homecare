<?php

namespace Tests\Feature\CustomerCare;

use App\Models\CareRequest;
use App\Models\CareRequestConversation;
use App\Models\CareRequestMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CustomerCareAccessAndInboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_can_prepare_a_dedicated_staff_login_without_email(): void
    {
        Notification::fake();
        $this->artisan('customer-care:create-user', ['email' => 'team@example.test', '--name' => 'Care team'])
            ->expectsQuestion('Initial password (at least 12 characters)', 'Secure-local-password-123')
            ->assertExitCode(0);
        $staff = User::where('email', 'team@example.test')->sole();
        $this->assertSame('customer_success', $staff->role);
        $this->assertTrue(Hash::check('Secure-local-password-123', $staff->password));
        $this->actingAs($staff)->get(route('dashboard'))->assertRedirect(route('customer-care.index'));
        Notification::assertNothingSent();
    }

    public function test_provisioning_does_not_convert_customers_or_reset_existing_staff_credentials(): void
    {
        $customer = User::factory()->create(['role' => 'family']);
        $this->artisan('customer-care:create-user', ['email' => $customer->email])->assertExitCode(1);
        $this->assertSame('family', $customer->fresh()->role);
        $staff = User::factory()->create(['role' => 'sdr']);
        $password = $staff->password;
        $this->artisan('customer-care:create-user', ['email' => $staff->email])->assertExitCode(0);
        $this->assertSame($password, $staff->fresh()->password);
        $this->assertSame('sdr', $staff->fresh()->role);
        $staff->forceFill(['access_revoked_at' => now()])->save();
        $this->artisan('customer-care:create-user', ['email' => $staff->email])->assertExitCode(1);
    }

    public function test_team_inbox_records_staff_read_state_without_touching_customer_read_state(): void
    {
        Notification::fake();
        $staff = User::factory()->create(['role' => 'customer_success']);
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        $request = $this->request($family);
        $application = $request->applications()->create(['caregiver_user_id' => $caregiver->id, 'status' => 'hired', 'proposed_rate' => 28]);
        $conversation = CareRequestConversation::findOrCreateForApplication($application, $caregiver->id);
        CareRequestMessage::create(['care_request_conversation_id' => $conversation->id, 'sender_user_id' => $caregiver->id, 'body' => 'I have a question about arrival.']);
        $conversation->update(['last_message_at' => now(), 'last_message_sender_id' => $caregiver->id]);
        $this->actingAs($staff)->get(route('customer-care.index'))->assertOk()->assertSee($caregiver->name);
        $this->get(route('customer-care.show', $request))->assertOk()->assertSee('I have a question about arrival.');
        $this->assertNotNull($conversation->fresh()->staff_last_read_at);
        $this->assertNull($conversation->fresh()->family_last_read_at);
        $this->get(route('customer-care.index'))->assertSee('No unread caregiver replies.');
        [, $otherConversation] = $this->otherConversation($family, $caregiver);
        $this->post(route('customer-care.action', $request), ['action' => 'message', 'caregiver_id' => $caregiver->id, 'conversation_id' => $otherConversation->id, 'body' => 'Wrong request'])->assertNotFound();
        $this->assertDatabaseMissing('care_request_messages', ['body' => 'Wrong request']);
    }

    private function otherConversation(User $family, User $caregiver): array
    {
        $request = $this->request($family);

        return [$request, CareRequestConversation::findOrCreateForRequest($request, $caregiver->id, $caregiver->id)];
    }

    private function request(User $family): CareRequest
    {
        return CareRequest::create(['family_user_id' => $family->id, 'title' => 'Managed care', 'scope_of_work' => 'Companionship',
            'address_line1' => '100 Main Street', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601',
            'request_type' => 'one_time', 'status' => 'filled', 'staff_managed' => true]);
    }
}
