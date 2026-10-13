<?php

namespace Tests\Feature;

use App\Models\M16Topic;
use App\Models\User;
use App\Services\TopicMonitoring;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class M16MonitoringTest extends TestCase
{
    use RefreshDatabase;

    private const TITLE = 'Microlearning videos for digital literacy in vocational schools';

    private const SUMMARY = 'A study of microlearning videos and digital literacy in vocational schools with outcomes for online safety, information evaluation, and content creation.';

    protected function setUp(): void
    {
        parent::setUp();
        config(['m16.enabled' => true, 'm16.requests_per_month' => 60, 'services.openalex.key' => null]);
    }

    public function test_creation_requires_verified_cron_and_explicit_consent(): void
    {
        Http::fake();
        $user = User::factory()->create();
        $this->get('/akun/pantauan')->assertRedirect(route('account.login'));
        $this->actingAs($user)->get('/akun/pantauan')->assertOk()->assertSee('belum aktif');
        $this->post('/akun/pantauan', $this->input())->assertStatus(503);
        app(TopicMonitoring::class)->tick();
        $this->travel(15)->minutes();
        app(TopicMonitoring::class)->tick();
        $input = $this->input();
        unset($input['consent']);
        $this->post('/akun/pantauan', $input)->assertUnprocessable();
        Http::assertNothingSent();
        $this->assertDatabaseCount('m16_topics', 0);
        $this->assertNull(session('_old_input'));
    }

    public function test_cron_finds_new_publications_and_account_is_isolated(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $monitor = app(TopicMonitoring::class);
        $monitor->tick();
        $this->travel(15)->minutes();
        $monitor->tick();
        $workId = 'W100';
        $this->fakeWorks($workId);
        $this->actingAs($user)->post('/akun/pantauan', $this->input())->assertRedirect();
        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.openalex.org/works')
            && str_contains(urldecode($request->url()), ' OR ')
            && ! str_contains(urldecode($request->url()), 'outcomes for online safety'));
        $topic = M16Topic::firstOrFail();
        $this->assertNotSame(self::TITLE, \DB::table('m16_versions')->value('title_cipher'));
        $this->assertDatabaseCount('m16_findings', 0);

        $topic->update(['last_checked_at' => now()->subDays(2)]);
        $workId = 'W200';
        $monitor->tick();
        $this->assertDatabaseCount('m16_findings', 1);
        $this->actingAs($other)->get(route('monitor.show', $topic))->assertNotFound();
        $this->post(route('monitor.read', [$topic, 1]))->assertNotFound();
        $this->delete(route('monitor.destroy', $topic))->assertNotFound();
        $this->actingAs($user)->get(route('account.index'))->assertSee('1 temuan belum dibaca');
        $this->post(route('monitor.read', [$topic, 1]))->assertRedirect();
        $this->assertDatabaseHas('m16_findings', ['id' => 1, 'topic_id' => $topic->id]);
    }

    public function test_versions_compare_identified_publications_and_delete_cascades(): void
    {
        $user = User::factory()->create();
        app(TopicMonitoring::class)->tick();
        $this->travel(15)->minutes();
        app(TopicMonitoring::class)->tick();
        $workId = 'W100';
        $this->fakeWorks($workId);
        $this->actingAs($user)->post('/akun/pantauan', $this->input())->assertRedirect();
        $topic = M16Topic::firstOrFail();
        $workId = 'W200';
        $this->post(route('monitor.revise', $topic), $this->input())->assertRedirect(route('monitor.show', $topic));
        $this->get(route('monitor.show', $topic))->assertOk()->assertSee('Bertambah (1)')->assertSee('Tidak muncul pada versi baru (1)');
        $this->delete(route('monitor.destroy', $topic))->assertRedirect(route('monitor.index'));
        $this->assertDatabaseCount('m16_topics', 0);
        $this->assertDatabaseCount('m16_versions', 0);
    }

    public function test_monthly_request_cap_prevents_provider_calls(): void
    {
        config(['m16.requests_per_month' => 1]);
        app(TopicMonitoring::class)->tick();
        $this->travel(15)->minutes();
        app(TopicMonitoring::class)->tick();
        $workId = 'W100';
        $this->fakeWorks($workId);
        $this->actingAs(User::factory()->create())->post('/akun/pantauan', $this->input())->assertRedirect();
        Http::fake();
        $this->post('/akun/pantauan', $this->input())->assertStatus(503)->assertSee('Batas pemeriksaan bulanan');
        Http::assertNothingSent();
    }

    private function input(): array
    {
        return ['title' => self::TITLE, 'summary' => self::SUMMARY, 'consent' => '1'];
    }

    private function fakeWorks(string &$id): void
    {
        Http::fake(function () use (&$id) {
            return Http::response(['meta' => ['cost_usd' => 0], 'results' => [[
                'id' => 'https://openalex.org/'.$id,
                'title' => 'Microlearning videos and digital literacy in vocational schools',
                'display_name' => 'Microlearning videos and digital literacy in vocational schools',
                'publication_year' => now()->year,
                'primary_location' => [],
                'abstract_inverted_index' => ['online' => [0], 'safety' => [1]],
            ]]]);
        });
    }
}
