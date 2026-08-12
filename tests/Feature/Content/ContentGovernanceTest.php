<?php

namespace Tests\Feature\Content;

use App\Livewire\Admin\Content\ContentSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;
use Tests\Concerns\CreatesPublishedBlogPosts;
use Tests\TestCase;

class ContentGovernanceTest extends TestCase
{
    use CreatesPublishedBlogPosts, RefreshDatabase;

    public function test_only_administrators_assign_content_roles_and_team_members_get_content_navigation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $member = User::factory()->create(['role' => 'family']);

        Livewire::actingAs($admin)
            ->test(ContentSettings::class)
            ->set('contentRoleUserId', (string) $member->id)
            ->set('contentRole', 'author')
            ->call('assignContentRole')
            ->assertHasNoErrors();

        $member->refresh();
        $this->assertSame('author', $member->content_role);
        $this->actingAs($member)
            ->get(route('admin.content.posts.index'))
            ->assertOk()
            ->assertSee('Content workspace')
            ->assertSee('Media Library');
        $this->actingAs($member)->get(route('admin.content.settings'))->assertForbidden();
    }

    public function test_content_audit_passes_healthy_public_revisions_and_can_fail_on_overdue_review(): void
    {
        $fixture = $this->createPublishedBlogPost();

        $this->assertSame(0, Artisan::call('content:audit', ['--fail-on-issues' => true]));
        $this->assertStringContainsString('0 issue(s)', Artisan::output());

        $fixture['post']->update(['content_review_due_at' => now()->subDay()]);
        $this->assertSame(1, Artisan::call('content:audit', ['--fail-on-issues' => true]));
        $this->assertStringContainsString('review overdue', Artisan::output());
    }
}
