<?php

namespace Tests\Feature\Identifiers;

use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\Distribution\Release;
use App\Models\Distribution\Track;
use App\Models\User;
use App\Services\V2\IdentifierPool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/* W116 MIXXTUNE_CODE_LISTS (generated test codes only - not real allotments) */
class CodeListsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        if (!Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function ($table) {
                $table->id();
                $table->string('public_id', 40)->unique();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('action', 100);
                $table->string('module', 100)->nullable();
                $table->string('auditable_type')->nullable();
                $table->unsignedBigInteger('auditable_id')->nullable();
                $table->text('description')->nullable();
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->string('ip_address', 64)->nullable();
                $table->text('user_agent')->nullable();
                $table->string('request_method', 10)->nullable();
                $table->text('request_url')->nullable();
                $table->timestamps();
            });
        }
    }

    private function upc(string $body): string
    {
        return $body.IdentifierPool::gs1CheckDigit($body);
    }

    private function release(User $owner, string $title, array $extra = []): Release
    {
        $label = Label::factory()->create(['created_by' => $owner->id]);
        $artist = Artist::factory()->create(['user_id' => $owner->id, 'label_id' => $label->id, 'created_by' => $owner->id]);

        return Release::factory()->create(array_merge(['artist_id' => $artist->id, 'label_id' => $label->id, 'catalog_number' => 'MXT-'.uniqid(), 'title' => $title, 'status' => 'approved', 'upc' => null, 'created_by' => $owner->id, 'updated_by' => $owner->id], $extra));
    }

    public function test_upload_range_and_file_then_generate_takes_the_next_free_code(): void
    {
        $super = User::factory()->create(['role' => 'super_admin', 'account_status' => 'active', 'email_verified_at' => now()]);
        $admin = User::factory()->create(['role' => 'admin', 'account_status' => 'active', 'email_verified_at' => now()]);
        $owner = User::factory()->create(['role' => 'label', 'account_status' => 'active', 'email_verified_at' => now()]);
        $a = $this->release($owner, 'Song A');
        $b = $this->release($owner, 'Song B');
        $typed = $this->release($owner, 'Song Typed', ['upc' => $this->upc('89000000000'.'3')]);

        /* no list yet: Generate refuses, nothing is made up */
        $this->actingAs($super)->postJson("/admin/releases/{$a->id}/upc/generate")->assertStatus(422)->assertJsonFragment(['message' => \App\Services\V2\UpcService::NO_LIST]);

        $this->actingAs($admin)->post('/admin/identifiers/lists', ['type' => 'upc', 'how' => 'range', 'range_first' => $this->upc('89000000000'.'1'), 'range_last' => $this->upc('89000000000'.'5')])->assertForbidden();

        /* range of 5: one is already on a release -> 4 added */
        $this->actingAs($super)->post('/admin/identifiers/lists', ['type' => 'upc', 'how' => 'range', 'range_first' => $this->upc('890000000001'), 'range_last' => $this->upc('890000000005')])
            ->assertSessionHas('success', '4 UPC added to the list. Left out: 1 already on a release.');
        $this->assertSame(4, DB::table('upc_codes')->where('status', 'available')->count());

        /* a csv: header, doubles, a wrong check digit, a new good one */
        $wrong = '8900000000090' === $this->upc('890000000009') ? '8900000000091' : '8900000000090';
        $csv = "UPC\n".$this->upc('890000000001')."\n".$this->upc('890000000009')."\n".$this->upc('890000000009')."\n".$wrong."\n";
        $file = UploadedFile::fake()->createWithContent('allotment.csv', $csv);
        $this->actingAs($super)->post('/admin/identifiers/lists', ['type' => 'upc', 'how' => 'file', 'file' => $file])
            ->assertSessionHas('success', fn ($m) => str_starts_with($m, '1 UPC added') && str_contains($m, '2 already in the list') && str_contains($m, '1 not valid'));

        $this->actingAs($super)->get('/admin/identifiers/pending')->assertInertia(fn (Assert $p) => $p->where('codeLists.upc.available', 5)->where('codeLists.upc.next', $this->upc('890000000001'))->where('canUploadLists', true)->has('codeLists.batches', 2));

        /* Generate: next free in upload order; a code typed by hand meanwhile is skipped */
        $this->actingAs($super)->postJson("/admin/releases/{$a->id}/upc/generate")->assertOk();
        $this->assertSame($this->upc('890000000001'), $a->fresh()->upc);
        $b->forceFill(['upc' => $this->upc('890000000002')])->save(); /* typed in by hand */
        $c = $this->release($owner, 'Song C');
        $this->actingAs($super)->postJson('/admin/identifiers/upc/bulk-generate', ['release_ids' => [$c->id]])->assertOk();
        $this->assertSame($this->upc('890000000004'), $c->fresh()->upc);
        $this->assertSame('used_elsewhere', DB::table('upc_codes')->where('code', $this->upc('890000000002'))->value('status'));
        $this->assertSame($c->id, (int) DB::table('upc_codes')->where('code', $this->upc('890000000004'))->value('release_id'));
        $this->actingAs($super)->postJson("/admin/releases/{$a->id}/upc/generate")->assertStatus(422);
        $this->assertNotNull($typed->fresh()->upc);
    }

    public function test_isrc_list_is_used_first_and_old_way_stays_without_a_list(): void
    {
        $super = User::factory()->create(['role' => 'super_admin', 'account_status' => 'active', 'email_verified_at' => now()]);
        $owner = User::factory()->create(['role' => 'label', 'account_status' => 'active', 'email_verified_at' => now()]);
        $r = $this->release($owner, 'Album');
        $t1 = Track::factory()->withAudio()->create(['release_id' => $r->id, 'track_number' => 1, 'title' => 'One', 'isrc' => null]);
        $t2 = Track::factory()->withAudio()->create(['release_id' => $r->id, 'track_number' => 2, 'title' => 'Two', 'isrc' => null]);

        $this->actingAs($super)->post('/admin/identifiers/lists', ['type' => 'isrc', 'how' => 'text', 'text' => "IN-ABC-26-00001\nINABC2600002, bad-code"])
            ->assertSessionHas('success', '2 ISRC added to the list. Left out: 1 not valid (e.g. bad-code).');
        $this->actingAs($super)->post('/admin/identifiers/lists', ['type' => 'isrc', 'how' => 'range', 'range_first' => 'IN-ABC-26-00001', 'range_last' => 'IN-XYZ-26-00003'])->assertSessionHasErrors('range_last');

        $this->actingAs($super)->postJson("/admin/tracks/{$t1->id}/isrc/generate")->assertOk();
        $this->assertSame('IN-ABC-26-00001', $t1->fresh()->isrc);
        $this->actingAs($super)->postJson("/admin/tracks/{$t2->id}/isrc/generate")->assertOk();
        $this->assertSame('IN-ABC-26-00002', $t2->fresh()->isrc);

        /* list used up: the old way (IN-MXT-yy-nnnnn) as before */
        $t3 = Track::factory()->withAudio()->create(['release_id' => $r->id, 'track_number' => 3, 'title' => 'Three', 'isrc' => null]);
        $this->actingAs($super)->postJson("/admin/tracks/{$t3->id}/isrc/generate")->assertOk();
        $this->assertStringStartsWith('IN-MXT-', (string) $t3->fresh()->isrc);
    }
}
