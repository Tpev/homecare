<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\CareAssistanceApplicationShow;
use App\Livewire\Admin\CareAssistanceApplicationsIndex;
use App\Models\CareAssistanceApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CareAssistanceReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_and_unauthorized_roles_cannot_read_applications_or_review_components(): void
    {
        $application = $this->application();
        $this->get(route('admin.care-assistance.index'))->assertRedirect(route('login'));
        $this->get(route('admin.care-assistance.show', $application))->assertRedirect(route('login'));
        foreach (['family', 'caregiver', 'sales', 'partner'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get(route('admin.care-assistance.index'))->assertForbidden();
            $this->get(route('admin.care-assistance.show', $application))->assertForbidden();
            Livewire::actingAs($user)->test(CareAssistanceApplicationShow::class, ['application' => $application])->assertForbidden();
            Livewire::actingAs($user)->test(CareAssistanceApplicationsIndex::class)->assertForbidden();
        }
    }

    #[DataProvider('reviewerRoles')]
    public function test_authorized_staff_can_filter_queue_and_open_an_application(string $role): void
    {
        $admin = User::factory()->create(['role' => $role]);
        $application = $this->application();
        $this->application(['name' => 'Morgan Reviewed', 'status' => 'declined']);
        $this->actingAs($admin)->get(route('admin.care-assistance.index'))->assertOk();
        $response = $this->get(route('admin.care-assistance.show', $application))->assertOk()->assertSee('Avery Applicant')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertDontSee('googletagmanager.com');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        Livewire::actingAs($admin)->test(CareAssistanceApplicationsIndex::class)
            ->set('status', 'new')->assertSee('Avery Applicant')->assertDontSee('Morgan Reviewed')
            ->set('status', 'all')->set('search', 'Morgan')->assertSee('Morgan Reviewed')->assertDontSee('Avery Applicant');
    }

    #[DataProvider('reviewerRoles')]
    public function test_authorized_staff_can_save_private_notes_approve_and_decline_with_reviewer_identity(string $role): void
    {
        Mail::fake();
        Notification::fake();
        $admin = User::factory()->create(['role' => $role]);
        $application = $this->application();
        $component = Livewire::actingAs($admin)->test(CareAssistanceApplicationShow::class, ['application' => $application]);
        $component->set('internalNotes', 'Discuss availability with the applicant.')->call('saveNotes')->assertHasNoErrors();
        $this->assertSame('new', $application->fresh()->status);
        $component->call('updateStatus', 'approved')->assertHasNoErrors();
        $this->assertSame('approved', $application->fresh()->status);
        $this->assertSame($admin->id, $application->fresh()->reviewed_by);
        $this->assertNotNull($application->fresh()->reviewed_at);
        $this->assertSame('Discuss availability with the applicant.', $application->fresh()->internal_notes);

        $component->call('updateStatus', 'under_review')->assertHasNoErrors();
        $this->assertNull($application->fresh()->reviewed_at);
        $component->call('updateStatus', 'declined')->assertHasNoErrors();
        $this->assertSame('declined', $application->fresh()->status);
        $this->assertDatabaseCount('care_pricing_agreements', 0);
        Mail::assertNothingOutgoing();
        Notification::assertNothingSent();
    }

    public function test_status_and_note_input_are_validated(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $application = $this->application();
        Livewire::actingAs($admin)->test(CareAssistanceApplicationShow::class, ['application' => $application])
            ->call('updateStatus', 'made_up')->assertHasErrors()
            ->set('internalNotes', str_repeat('a', 5001))->call('saveNotes')->assertHasErrors('internalNotes');
        $this->assertSame('new', $application->fresh()->status);
        $this->assertNull($application->fresh()->internal_notes);
    }

    public function test_stale_review_does_not_overwrite_another_admins_decision_even_in_the_same_second(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $otherAdmin = User::factory()->create(['role' => 'admin']);
        $application = $this->application();
        $component = Livewire::actingAs($admin)->test(CareAssistanceApplicationShow::class, ['application' => $application]);
        $application->update(['status' => 'approved', 'reviewed_by' => $otherAdmin->id, 'reviewed_at' => now()]);
        $component->set('internalNotes', 'Unsaved note')->call('updateStatus', 'declined')->assertHasErrors('review');
        $this->assertSame('approved', $application->fresh()->status);
        $this->assertSame($otherAdmin->id, $application->fresh()->reviewed_by);
        $this->assertNull($application->fresh()->internal_notes);
    }

    #[DataProvider('changedReviewerAccess')]
    public function test_review_actions_recheck_persisted_access_after_mount(string $role, bool $revoked, string $action): void
    {
        $admin = User::factory()->create(['role' => $role]);
        $application = $this->application();
        $component = Livewire::actingAs($admin)->test(CareAssistanceApplicationShow::class, ['application' => $application])
            ->set('internalNotes', 'This note must not be saved.');
        User::whereKey($admin->id)->update($revoked ? ['access_revoked_at' => now()] : ['role' => 'family']);
        $component->call($action, ...($action === 'updateStatus' ? ['approved'] : []))->assertForbidden();
        $this->assertSame('new', $application->fresh()->status);
        $this->assertNull($application->fresh()->internal_notes);
        $this->assertNull($application->fresh()->reviewed_by);
    }

    public static function reviewerRoles(): array
    {
        return [['admin'], ['sdr'], ['customer_success']];
    }

    public static function changedReviewerAccess(): array
    {
        $cases = [];
        foreach (['admin', 'sdr'] as $role) {
            foreach ([false, true] as $revoked) {
                foreach (['saveNotes', 'updateStatus', 'reloadApplication'] as $action) {
                    $cases[$role.' '.$action.($revoked ? ' revoked' : ' role changed')] = [$role, $revoked, $action];
                }
            }
        }

        return $cases;
    }

    public function test_sdr_care_assistance_access_does_not_grant_other_admin_permissions(): void
    {
        $sdr = User::factory()->create(['role' => 'sdr']);
        $this->actingAs($sdr)->get(route('admin.care-assistance.index'))->assertOk();
        foreach (['admin.users.index', 'admin.payments.ops', 'admin.sms.index'] as $route) {
            $this->get(route($route))->assertForbidden();
        }
        $this->assertFalse($sdr->isAdministrator());
    }

    public function test_applicant_html_is_rendered_as_text(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $script = '<script>alert("private")</script>';
        $application = $this->application(['hardship_details' => $script]);
        $this->actingAs($admin)->get(route('admin.care-assistance.show', $application))
            ->assertOk()->assertSee(e($script), false)->assertDontSee($script, false);
    }

    private function application(array $attributes = []): CareAssistanceApplication
    {
        return CareAssistanceApplication::query()->create(array_merge([
            'name' => 'Avery Applicant', 'email' => 'avery@example.test', 'phone' => '(919) 555-0123',
            'relationship' => 'parent', 'city' => 'Raleigh', 'zip' => '27601',
            'care_needs' => ['companionship'], 'weekly_hours' => '5_10', 'start_timeframe' => 'within_month',
            'hardship_details' => 'We need support after a change in household income.', 'consented_at' => now(), 'status' => 'new',
        ], $attributes));
    }
}
