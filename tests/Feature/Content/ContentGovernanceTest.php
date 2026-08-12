<?php

namespace Tests\Feature\Content;

use App\Livewire\Admin\Content\ContentSettings;
use App\Models\ContentAuthor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;
use Tests\Concerns\CreatesPublishedBlogPosts;
use Tests\TestCase;

class ContentGovernanceTest extends TestCase
{
    use CreatesPublishedBlogPosts, RefreshDatabase;

    public function test_legacy_production_admin_account_is_promoted_to_the_explicit_admin_role(): void
    {
        $legacyAdmin = User::factory()->create([
            'email' => 'test@test.com',
            'role' => 'family',
        ]);

        $migration = require database_path('migrations/2026_08_12_090300_promote_legacy_admin_account.php');
        $migration->up();

        $this->assertSame('admin', $legacyAdmin->fresh()->role);
        $this->assertTrue($legacyAdmin->fresh()->isAdministrator());
    }

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

    public function test_author_can_be_created_without_identity_references(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Livewire::actingAs($admin)
            ->test(ContentSettings::class)
            ->set('authorForm.name', 'LoLo Care Editorial Team')
            ->set('authorForm.bio', 'The LoLo Care editorial team publishes carefully reviewed information about practical, non-medical help at home for local families.')
            ->set('authorForm.same_as', '')
            ->call('saveAuthor')
            ->assertHasNoErrors();

        $author = ContentAuthor::query()->where('slug', 'lolo-care-editorial-team')->firstOrFail();

        $this->assertSame([], $author->same_as);
    }

    public function test_author_identity_references_are_trimmed_and_empty_lines_are_removed(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Livewire::actingAs($admin)
            ->test(ContentSettings::class)
            ->set('authorForm.name', 'Jane Smith')
            ->set('authorForm.bio', 'Jane Smith writes and reviews practical information for families coordinating flexible, non-medical support for older adults at home.')
            ->set('authorForm.same_as', " https://example.com/jane \r\n\r\nhttps://www.linkedin.com/in/jane-smith ")
            ->call('saveAuthor')
            ->assertHasNoErrors();

        $author = ContentAuthor::query()->where('slug', 'jane-smith')->firstOrFail();

        $this->assertSame([
            'https://example.com/jane',
            'https://www.linkedin.com/in/jane-smith',
        ], $author->same_as);
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
