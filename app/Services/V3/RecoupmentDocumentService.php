<?php

namespace App\Services\V3;

use App\Models\Finance\RecoupmentDocument;
use App\Models\Finance\RecoupmentPlan;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class RecoupmentDocumentService
{
    private const DISK = 'local';

    public function store(
        RecoupmentPlan $plan,
        UploadedFile $file,
        string $documentType,
        ?int $expenseId = null,
        ?User $uploadedBy = null
    ): RecoupmentDocument {
        $directory =
            'private/recoupment/'
            .$plan->id;

        $extension =
            strtolower(
                $file->getClientOriginalExtension()
            );

        $filename =
            now()->format('YmdHis')
            .'-'
            .Str::uuid()
            .($extension !== ''
                ? '.'.$extension
                : '');

        $path = $file->storeAs(
            $directory,
            $filename,
            self::DISK
        );

        if (! $path) {
            throw new RuntimeException(
                'Unable to store recoupment document.'
            );
        }

        return RecoupmentDocument::create([
            'recoupment_plan_id' => $plan->id,
            'recoupment_expense_id' => $expenseId,
            'document_type' => $documentType,
            'original_name' =>
                $file->getClientOriginalName(),
            'storage_disk' => self::DISK,
            'storage_path' => $path,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'uploaded_by' => $uploadedBy?->id,
        ]);
    }

    public function exists(
        RecoupmentDocument $document
    ): bool {
        return Storage::disk(
            $document->storage_disk
        )->exists(
            $document->storage_path
        );
    }

    public function downloadPath(
        RecoupmentDocument $document
    ): string {
        $disk = Storage::disk(
            $document->storage_disk
        );

        if (
            ! $disk->exists(
                $document->storage_path
            )
        ) {
            throw new RuntimeException(
                'Recoupment document file not found.'
            );
        }

        return $disk->path(
            $document->storage_path
        );
    }
}
