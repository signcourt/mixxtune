<?php

namespace App\Services\V2;

use App\Models\Distribution\Track;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class AudioValidationService
{
    private const VALID_SAMPLE_RATES = [
        44100,
        48000,
    ];

    private const VALID_BIT_DEPTHS = [
        16,
        24,
    ];

    private const VALID_CHANNELS = [
        1,
        2,
    ];

    public function validate(
        Track $track,
        ?User $user = null
    ): array {
        $track->update([
            'audio_validation_status' =>
                'processing',

            'audio_validation_errors' =>
                null,

            'audio_validated_at' =>
                null,

            'audio_validated_by' =>
                $user?->id,
        ]);

        try {
            $absolutePath =
                $this->absolutePath($track);

            $probe = $this->probe(
                $absolutePath
            );

            $volume = $this->volumeAnalysis(
                $absolutePath
            );

            $silence = $this->silenceAnalysis(
                $absolutePath,
                (float) (
                    $probe[
                        'duration_seconds'
                    ] ?? 0
                )
            );

            $metadata = [
                ...$probe,
                ...$volume,
                ...$silence,
            ];

            logger()->info(
                'MIXTUNE_AUDIO_PROBE',
                $probe
            );

            logger()->info(
                'MIXTUNE_AUDIO_VOLUME',
                $volume
            );

            logger()->info(
                'MIXTUNE_AUDIO_SILENCE',
                $silence
            );

            logger()->info(
                'MIXTUNE_AUDIO_METADATA',
                $metadata
            );

            $errors = $this->technicalErrors(
                $metadata
            );

            $status = empty($errors)
                ? 'passed'
                : 'failed';

            $track->update([
                'audio_validation_status' =>
                    $status,

                'audio_validation_errors' =>
                    $errors,

                'audio_metadata' =>
                    $metadata,

                'audio_codec' =>
                    $metadata['codec_name']
                    ?? null,

                'audio_sample_rate' =>
                    $metadata['sample_rate']
                    ?? null,

                'sample_rate' =>
                    $metadata['sample_rate']
                    ?? null,

                'audio_bit_depth' =>
                    $metadata['bit_depth']
                    ?? null,

                'bit_depth' =>
                    $metadata['bit_depth']
                    ?? null,

                'audio_channels' =>
                    $metadata['channels']
                    ?? null,

                'channels' =>
                    $metadata['channels']
                    ?? null,

                'audio_channel_layout' =>
                    $metadata[
                        'channel_layout'
                    ] ?? null,

                'audio_duration_seconds' =>
                    $metadata[
                        'duration_seconds'
                    ] ?? null,

                'duration_seconds' =>
                    isset(
                        $metadata[
                            'duration_seconds'
                        ]
                    )
                        ? (int) round(
                            $metadata[
                                'duration_seconds'
                            ]
                        )
                        : $track
                            ->duration_seconds,

                'audio_peak_db' =>
                    $metadata['peak_db']
                    ?? null,

                'audio_mean_volume_db' =>
                    $metadata[
                        'mean_volume_db'
                    ] ?? null,

                'audio_silence_start_seconds' =>
                    $metadata[
                        'leading_silence_seconds'
                    ] ?? null,

                'audio_silence_end_seconds' =>
                    $metadata[
                        'trailing_silence_seconds'
                    ] ?? null,

                'audio_validated_at' =>
                    now(),

                'audio_validated_by' =>
                    $user?->id,
            ]);

            return [
                'passed' =>
                    $status === 'passed',

                'status' =>
                    $status,

                'errors' =>
                    $errors,

                'metadata' =>
                    $metadata,

                'track' =>
                    $track->fresh(),
            ];
        } catch (Throwable $exception) {
            $errors = [
                'technical' =>
                    $exception->getMessage(),
            ];

            $track->update([
                'audio_validation_status' =>
                    'failed',

                'audio_validation_errors' =>
                    $errors,

                'audio_validated_at' =>
                    now(),

                'audio_validated_by' =>
                    $user?->id,
            ]);

            return [
                'passed' => false,
                'status' => 'failed',
                'errors' => $errors,
                'metadata' => [],
                'track' => $track->fresh(),
            ];
        }
    }

    public function validateMany(
        iterable $tracks,
        ?User $user = null
    ): array {
        $results = [
            'total' => 0,
            'passed' => 0,
            'failed' => 0,
            'items' => [],
        ];

        foreach ($tracks as $track) {
            if (!$track instanceof Track) {
                continue;
            }

            $result = $this->validate(
                $track,
                $user
            );

            $results['total']++;

            $result['passed']
                ? $results['passed']++
                : $results['failed']++;

            $results['items'][] = [
                'track_id' =>
                    $track->id,

                'title' =>
                    $track->title,

                'status' =>
                    $result['status'],

                'errors' =>
                    $result['errors'],
            ];
        }

        return $results;
    }

    private function absolutePath(
        Track $track
    ): string {
        if (!$track->audio_path) {
            throw new RuntimeException(
                'Track audio file is missing.'
            );
        }

        $path = Storage::disk(
            'public'
        )->path($track->audio_path);

        if (!is_file($path)) {
            throw new RuntimeException(
                'Stored audio file was not found.'
            );
        }

        if (!is_readable($path)) {
            throw new RuntimeException(
                'Stored audio file is not readable.'
            );
        }

        return $path;
    }

    private function probe(
        string $path
    ): array {
        $process = new Process([
            'ffprobe',
            '-v',
            'error',
            '-select_streams',
            'a:0',
            '-show_entries',
            implode(',', [
                'stream=codec_name,codec_long_name',
                'stream=sample_rate,channels',
                'stream=channel_layout,bits_per_sample',
                'stream=bits_per_raw_sample',
                'format=format_name,duration,size',
            ]),
            '-of',
            'json',
            $path,
        ]);

        $process->setTimeout(120);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException(
                trim(
                    $process->getErrorOutput()
                )
                ?: 'FFprobe could not inspect the audio file.'
            );
        }

        logger()->info(
            'MIXTUNE_FFPROBE_RAW',
            [
                'output' => $process->getOutput(),
            ]
        );

        $decoded = json_decode(
            $process->getOutput(),
            true
        );

        logger()->info(
            'MIXTUNE_FFPROBE_DECODED',
            $decoded ?? []
        );

        if (!is_array($decoded)) {
            throw new RuntimeException(
                'FFprobe returned invalid metadata.'
            );
        }

        $stream =
            $decoded['streams'][0] ?? [];

        $format =
            $decoded['format'] ?? [];

        $rawBitDepth =
            $stream['bits_per_raw_sample']
            ?? null;

        $reportedBitDepth =
            $stream['bits_per_sample']
            ?? null;

        $bitDepth = (int) (
            $rawBitDepth
            ?: $reportedBitDepth
            ?: 0
        );

        if (
            $bitDepth === 0
            && isset($stream['codec_name'])
        ) {
            $bitDepth =
                $this->bitDepthFromCodec(
                    $stream['codec_name']
                );
        }

        return [
            'codec_name' =>
                $stream['codec_name']
                ?? null,

            'codec_long_name' =>
                $stream['codec_long_name']
                ?? null,

            'format_name' =>
                $format['format_name']
                ?? null,

            'sample_rate' =>
                isset($stream['sample_rate'])
                    ? (int) $stream[
                        'sample_rate'
                    ]
                    : null,

            'bit_depth' =>
                $bitDepth ?: null,

            'channels' =>
                isset($stream['channels'])
                    ? (int) $stream[
                        'channels'
                    ]
                    : null,

            'channel_layout' =>
                $stream['channel_layout']
                ?? null,

            'duration_seconds' =>
                isset($format['duration'])
                    ? round(
                        (float) $format[
                            'duration'
                        ],
                        3
                    )
                    : null,

            'file_size_bytes' =>
                isset($format['size'])
                    ? (int) $format['size']
                    : null,

            'file_hash_sha256' =>
                hash_file(
                    'sha256',
                    $path
                ),
        ];
    }

    private function volumeAnalysis(
        string $path
    ): array {
        $process = new Process([
            'ffmpeg',
            '-hide_banner',
            '-nostats',
            '-i',
            $path,
            '-af',
            'volumedetect',
            '-f',
            'null',
            '-',
        ]);

        $process->setTimeout(180);
        $process->run();

        $output =
            $process->getErrorOutput();

        $mean = null;
        $peak = null;

        if (
            preg_match(
                '/mean_volume:\s*(-?[\d.]+)\s*dB/i',
                $output,
                $matches
            )
        ) {
            $mean = (float) $matches[1];
        }

        if (
            preg_match(
                '/max_volume:\s*(-?[\d.]+)\s*dB/i',
                $output,
                $matches
            )
        ) {
            $peak = (float) $matches[1];
        }

        return [
            'mean_volume_db' => $mean,
            'peak_db' => $peak,
        ];
    }

    private function silenceAnalysis(
        string $path,
        float $duration
    ): array {
        $process = new Process([
            'ffmpeg',
            '-hide_banner',
            '-nostats',
            '-i',
            $path,
            '-af',
            'silencedetect=noise=-50dB:d=1',
            '-f',
            'null',
            '-',
        ]);

        $process->setTimeout(180);
        $process->run();

        $output =
            $process->getErrorOutput();

        preg_match_all(
            '/silence_start:\s*([\d.]+)/i',
            $output,
            $starts
        );

        preg_match_all(
            '/silence_end:\s*([\d.]+)/i',
            $output,
            $ends
        );

        $leadingSilence = 0.0;
        $trailingSilence = 0.0;

        $startValues = array_map(
            'floatval',
            $starts[1] ?? []
        );

        $endValues = array_map(
            'floatval',
            $ends[1] ?? []
        );

        if (
            isset($startValues[0])
            && $startValues[0] <= 0.1
            && isset($endValues[0])
        ) {
            $leadingSilence =
                max(
                    0,
                    $endValues[0]
                );
        }

        if (
            $duration > 0
            && !empty($startValues)
        ) {
            $lastStart =
                end($startValues);

            $lastEnd =
                !empty($endValues)
                    ? end($endValues)
                    : null;

            if (
                $lastStart !== false
                && (
                    $lastEnd === null
                    || abs(
                        $lastEnd
                        - $duration
                    ) <= 0.5
                )
            ) {
                $trailingSilence =
                    max(
                        0,
                        $duration
                        - (float) $lastStart
                    );
            }
        }

        return [
            'leading_silence_seconds' =>
                round(
                    $leadingSilence,
                    3
                ),

            'trailing_silence_seconds' =>
                round(
                    $trailingSilence,
                    3
                ),
        ];
    }

    private function technicalErrors(
        array $metadata
    ): array {
        $errors = [];

        $codec = strtolower(
            (string) (
                $metadata['codec_name']
                ?? ''
            )
        );

        $format = strtolower(
            (string) (
                $metadata['format_name']
                ?? ''
            )
        );

        if (
            !Str::startsWith(
                $codec,
                'pcm_'
            )
            || !str_contains(
                $format,
                'wav'
            )
        ) {
            $errors['format'] =
                'Audio must be an uncompressed PCM WAV file.';
        }

        $sampleRate = (int) (
            $metadata['sample_rate']
            ?? 0
        );

        if (
            !in_array(
                $sampleRate,
                self::VALID_SAMPLE_RATES,
                true
            )
        ) {
            $errors['sample_rate'] =
                'Sample rate must be 44.1 kHz or 48 kHz.';
        }

        $bitDepth = (int) (
            $metadata['bit_depth']
            ?? 0
        );

        if (
            !in_array(
                $bitDepth,
                self::VALID_BIT_DEPTHS,
                true
            )
        ) {
            $errors['bit_depth'] =
                'Bit depth must be 16-bit or 24-bit.';
        }

        $channels = (int) (
            $metadata['channels']
            ?? 0
        );

        if (
            !in_array(
                $channels,
                self::VALID_CHANNELS,
                true
            )
        ) {
            $errors['channels'] =
                'Audio must be mono or stereo.';
        }

        $duration = (float) (
            $metadata['duration_seconds']
            ?? 0
        );

        if ($duration <= 0) {
            $errors['duration'] =
                'Audio duration could not be detected.';
        }

        if ($duration > 7200) {
            $errors['duration_limit'] =
                'Audio duration cannot exceed 120 minutes.';
        }

        $peak = $metadata['peak_db']
            ?? null;

        if (
            $peak !== null
            && (float) $peak >= 0
        ) {
            $errors['clipping'] =
                'Audio peak reaches 0 dB and may be clipped.';
        }

        $leading = (float) (
            $metadata[
                'leading_silence_seconds'
            ] ?? 0
        );

        if ($leading > 5) {
            $errors['leading_silence'] =
                'Audio contains more than 5 seconds of silence at the beginning.';
        }

        $trailing = (float) (
            $metadata[
                'trailing_silence_seconds'
            ] ?? 0
        );

        if ($trailing > 10) {
            $errors['trailing_silence'] =
                'Audio contains more than 10 seconds of silence at the end.';
        }

        return $errors;
    }

    private function bitDepthFromCodec(
        string $codec
    ): int {
        return match (
            strtolower($codec)
        ) {
            'pcm_s16le',
            'pcm_s16be' => 16,

            'pcm_s24le',
            'pcm_s24be' => 24,

            'pcm_s32le',
            'pcm_s32be' => 32,

            default => 0,
        };
    }
}
