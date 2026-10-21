<?php

namespace Tests\Feature\Releases;

use App\Models\Core\Label;
use App\Models\Distribution\Release;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/* MIXXTUNE_TERRITORIES_V2 (W64) */
class ReleaseWizardStepsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_edit_page_sends_the_label_name_for_the_review_step(): void
    {
        $super = User::factory()->create(['role' => 'super_admin', 'account_status' => 'active', 'email_verified_at' => now()]);
        $label = Label::factory()->create(['name' => 'Wizard Label Music']);
        $release = Release::factory()->create(['status' => 'draft', 'label_id' => $label->id, 'created_by' => $super->id]);

        $this->actingAs($super)->get('/v2/releases/'.$release->id.'/edit')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('release.label_name', 'Wizard Label Music'));
    }

    public function test_territory_save_keeps_all_stores_and_a_country_list(): void
    {
        $super = User::factory()->create(['role' => 'super_admin', 'account_status' => 'active', 'email_verified_at' => now()]);
        $store = \App\Models\DistributionStore::query()->create(['name' => 'Wizard Store', 'slug' => 'wizard-store', 'is_active' => true]);
        $release = Release::factory()->create(['status' => 'draft', 'created_by' => $super->id]);

        $this->actingAs($super)->patch('/v2/releases/'.$release->id.'/distribution', [
            'stores' => [$store->id], 'worldwide' => false, 'territories' => ['IN', 'NP'],
            'release_timezone' => 'Asia/Kolkata', 'pre_order' => false,
        ])->assertSessionHasNoErrors();

        $release->refresh();
        $this->assertFalse((bool) $release->worldwide);
        $this->assertEqualsCanonicalizing(['IN', 'NP'], $release->territories);
        $this->assertSame(4, (int) $release->wizard_step);
    }
}
