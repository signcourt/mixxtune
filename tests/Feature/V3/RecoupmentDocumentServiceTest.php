<?php

namespace Tests\Feature\V3;

use App\Models\Finance\RecoupmentPlan;
use App\Models\User;
use App\Services\V3\RecoupmentDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RecoupmentDocumentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_is_stored_on_private_local_disk(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();

        $plan = RecoupmentPlan::create([
            'user_id' => $user->id,
            'plan_number' => 'REC-TEST-001',
            'title' => 'Test Plan',
            'base_percentage' => 20,
            'recovery_uplift_percentage' => 10,
            'total_recoverable_amount' => 100000,
            'total_recovered_amount' => 0,
            'outstanding_amount' => 100000,
            'recovery_method' => 'percentage',
            'status' => 'active',
        ]);

        $file = UploadedFile::fake()->create(
            'advance-agreement.pdf',
            100,
            'application/pdf'
        );

        $service = app(RecoupmentDocumentService::class);

        $document = $service->store(
            $plan,
            $file,
            'advance_agreement',
            null,
            $user
        );

        $this->assertSame(
            'local',
            $document->storage_disk
        );

        $this->assertSame(
            $plan->id,
            $document->recoupment_plan_id
        );

        $this->assertSame(
            'advance_agreement',
            $document->document_type
        );

        $this->assertSame(
            'advance-agreement.pdf',
            $document->original_name
        );

        $this->assertStringStartsWith(
            'private/recoupment/'.$plan->id.'/',
            $document->storage_path
        );

        Storage::disk('local')->assertExists(
            $document->storage_path
        );

        $this->assertTrue(
            $service->exists($document)
        );
    }
}
