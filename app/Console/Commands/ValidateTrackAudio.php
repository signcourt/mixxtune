<?php

namespace App\Console\Commands;

use App\Models\Distribution\Track;
use App\Services\V2\AudioValidationService;
use Illuminate\Console\Command;

class ValidateTrackAudio extends Command
{
    protected $signature =
        'v2:validate-audio
        {--track= : Validate one track ID}
        {--status=pending : Validate tracks with this status}
        {--limit=100 : Maximum tracks to process}';

    protected $description =
        'Validate uploaded WAV audio files for V2 tracks.';

    public function handle(
        AudioValidationService $validator
    ): int {
        $trackId = $this->option(
            'track'
        );

        if ($trackId) {
            $track = Track::query()
                ->find($trackId);

            if (!$track) {
                $this->error(
                    'Track not found.'
                );

                return self::FAILURE;
            }

            $result = $validator->validate(
                $track
            );

            $this->line(
                json_encode(
                    $result,
                    JSON_PRETTY_PRINT
                )
            );

            return $result['passed']
                ? self::SUCCESS
                : self::FAILURE;
        }

        $limit = min(
            max(
                (int) $this->option(
                    'limit'
                ),
                1
            ),
            1000
        );

        $tracks = Track::query()
            ->whereNotNull('audio_path')
            ->where(
                'audio_validation_status',
                $this->option('status')
            )
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $result = $validator
            ->validateMany($tracks);

        $this->table(
            [
                'Track ID',
                'Title',
                'Status',
                'Errors',
            ],
            collect(
                $result['items']
            )
                ->map(
                    fn (array $item) => [
                        $item['track_id'],
                        $item['title'],
                        $item['status'],
                        implode(
                            ' | ',
                            $item['errors']
                        ),
                    ]
                )
                ->all()
        );

        $this->info(
            "Total: {$result['total']}, Passed: {$result['passed']}, Failed: {$result['failed']}"
        );

        return $result['failed'] > 0
            ? self::FAILURE
            : self::SUCCESS;
    }
}
