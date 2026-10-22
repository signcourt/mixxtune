<?php

namespace App\Console\Commands\V2;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/*
 * W91 MIXXTUNE_PENDING_AUDIO_CLEANUP
 * A WAV uploaded in the background (W87) waits in tracks/audio-pending
 * until its track is saved, then moves to tracks/audio. A file nobody
 * used (form closed, browser left) is deleted after 26 hours - the upload
 * token is only valid for 24 hours, so such a file can never be used.
 */
class CleanPendingTrackAudio extends Command
{
    protected $signature = 'tracks:clean-pending-audio {--hours=26} {--dry-run}';

    protected $description = 'Delete background WAV uploads that were never used by a track';

    public function handle(): int
    {
        $disk = Storage::disk('public');
        $limit = now()->subHours(max(25, (int) $this->option('hours')))->getTimestamp();
        $deleted = 0;
        $kept = 0;

        foreach ($disk->files('tracks/audio-pending') as $file) {
            if ($disk->lastModified($file) >= $limit) {
                $kept++;
                continue;
            }
            if (!$this->option('dry-run')) {
                $disk->delete($file);
            }
            $deleted++;
        }

        $this->info(($this->option('dry-run') ? 'Would delete ' : 'Deleted ').$deleted.' unused upload(s); kept '.$kept.' recent one(s).');

        return self::SUCCESS;
    }
}
