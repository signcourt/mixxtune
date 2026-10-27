<?php

namespace Tests\Feature\Releases;

use App\Models\Core\Label;
use App\Models\Distribution\Release;
use App\Models\User;
use App\Services\V2\ReleaseValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/* W118 MIXXTUNE_RELEASE_DATE_RULE (generated test data only) */
class ReleaseDateRuleTest extends TestCase
{
    use RefreshDatabase;

    private function send(User $user, Label $label, string $date, string $title)
    {
        return $this->actingAs($user)->post(route('v2.releases.store'), [
            'catalog_number' => 'MT-DT-'.uniqid(),
            'release_type' => 'single',
            'title' => $title,
            'label_id' => $label->id,
            'primary_artist_name' => 'Some Singer',
            'primary_artists' => [['name' => 'Some Singer']],
            'featuring_artists' => [],
            'language' => 'Hindi',
            'primary_genre' => 'Devotional',
            'sub_genre' => 'Bhajan',
            'generate_upc' => true,
            'digital_release_date' => $date,
            'copyright_owner' => 'Label',
            'copyright_year' => now()->format('Y'),
            'phonographic_owner' => 'Label',
            'phonographic_year' => now()->format('Y'),
        ]);
    }

    public function test_label_may_pick_today_or_later_but_not_a_past_day(): void
    {
        $user = User::factory()->create(['role' => 'label', 'account_status' => 'active', 'email_verified_at' => now()]);
        $label = Label::factory()->create(['user_id' => $user->id, 'created_by' => $user->id, 'parent_label_id' => null]);
        $today = Carbon::now('Asia/Kolkata')->toDateString();

        $this->send($user, $label, $today, 'Today Song')->assertSessionHasNoErrors();
        $this->send($user, $label, Carbon::now('Asia/Kolkata')->addDays(2)->toDateString(), 'Soon Song')->assertSessionHasNoErrors();
        $this->send($user, $label, Carbon::now('Asia/Kolkata')->subDay()->toDateString(), 'Old Song')->assertSessionHasErrors('digital_release_date');
        $this->assertDatabaseMissing('releases', ['title' => 'Old Song']);

        /* the send-for-review check agrees */
        $r = Release::query()->where('title', 'Today Song')->firstOrFail();
        $this->assertArrayNotHasKey('release.digital_release_date', app(ReleaseValidationService::class)->validateForSubmission($r));
        $r->forceFill(['digital_release_date' => Carbon::now('Asia/Kolkata')->subDays(3)->toDateString()])->save();
        $this->assertArrayHasKey('release.digital_release_date', app(ReleaseValidationService::class)->validateForSubmission($r->fresh()));
    }

    public function test_super_admin_and_manager_may_backdate(): void
    {
        $super = User::factory()->create(['role' => 'super_admin', 'account_status' => 'active', 'email_verified_at' => now()]);
        $owner = User::factory()->create(['role' => 'label', 'account_status' => 'active', 'email_verified_at' => now()]);
        $label = Label::factory()->create(['user_id' => $owner->id, 'created_by' => $owner->id, 'parent_label_id' => null]);

        $this->send($super, $label, '2019-03-15', 'Old Catalogue Song')->assertSessionHasNoErrors();
        $r = Release::query()->where('title', 'Old Catalogue Song')->firstOrFail();
        $this->assertSame('2019-03-15', $r->digital_release_date->toDateString());
        $this->assertArrayNotHasKey('release.digital_release_date', app(ReleaseValidationService::class)->validateForSubmission($r));

        $manager = User::factory()->create(['role' => 'admin', 'account_status' => 'active', 'email_verified_at' => now()]);
        $r->forceFill(['created_by' => $manager->id])->save();
        $this->assertArrayNotHasKey('release.digital_release_date', app(ReleaseValidationService::class)->validateForSubmission($r->fresh()));
    }
}
