<?php

namespace Tests\Feature\Website;

use App\Services\V2\Website\HomepageContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\CreatesProductionOnlyTables;
use Tests\TestCase;

/* W126 MIXXTUNE_JOIN_NO_MANAGER (generated test data only) */
class JoinNoManagerTest extends TestCase
{
    use RefreshDatabase;
    use CreatesProductionOnlyTables;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->createProductionOnlyTables();
        Mail::fake();
    }

    private function roleOptions(): array
    {
        return collect(app(HomepageContent::class)->get()['join']['fields'])->firstWhere('id', 'role')['options'];
    }

    public function test_joining_as_is_label_artist_other_and_manager_is_refused(): void
    {
        $this->assertSame(['Music label', 'Artist', 'Other'], $this->roleOptions());

        $this->from('/')->post('/join', ['answers' => ['name' => 'Aman Verma', 'phone' => '98765 43210', 'role' => 'Manager']])
            ->assertSessionHasErrors();
        $this->assertSame(0, DB::table('website_join_requests')->count());
    }

    public function test_a_published_form_with_manager_loses_only_that_choice(): void
    {
        $content = HomepageContent::defaults();
        foreach ($content['join']['fields'] as $i => $f) {
            if ($f['id'] === 'role') {
                $content['join']['fields'][$i]['options'] = ['Music label', 'Artist', 'Manager', 'Other'];
            }
        }
        $content['hero']['eyebrow'] = 'Owner words kept';
        app(HomepageContent::class)->publish($content, null);
        $this->assertContains('Manager', $this->roleOptions());

        $this->assertTrue(app(HomepageContent::class)->removeManagerChoice());
        $this->assertSame(['Music label', 'Artist', 'Other'], $this->roleOptions());
        $this->assertSame('Owner words kept', app(HomepageContent::class)->get()['hero']['eyebrow']);
        $this->assertFalse(app(HomepageContent::class)->removeManagerChoice()); /* second time: nothing to do */
    }
}
