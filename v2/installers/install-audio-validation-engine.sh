#!/usr/bin/env bash

set -Eeuo pipefail

PROJECT="/var/www/backstage-distribution"
cd "$PROJECT"

STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP="$PROJECT/v2/backups/audio-validation-engine/$STAMP"

mkdir -p \
    "$BACKUP" \
    app/Services/V2 \
    app/Http/Controllers/V2/Admin \
    app/Console/Commands \
    v2/runtime/state

echo "=================================================="
echo "V2 AUDIO TECHNICAL VALIDATION ENGINE"
echo "=================================================="

echo "[1/10] Creating backups..."

for FILE in \
    routes/web.php \
    app/Http/Controllers/V2/ReleaseTrackController.php \
    app/Models/Distribution/Track.php \
    app/Services/V2/PermissionService.php \
    resources/js/V2/Shared/Permissions/permissions.js
do
    if [ -f "$FILE" ]; then
        mkdir -p "$BACKUP/$(dirname "$FILE")"
        cp -a "$FILE" "$BACKUP/$FILE"
    fi
done


echo "[2/10] Checking FFmpeg and FFprobe..."

if ! command -v ffprobe >/dev/null 2>&1 || \
   ! command -v ffmpeg >/dev/null 2>&1
then
    echo "FFmpeg missing. Installing..."

    apt-get update -y
    DEBIAN_FRONTEND=noninteractive \
        apt-get install -y ffmpeg
fi

ffmpeg -version | head -1
ffprobe -version | head -1


echo "[3/10] Creating audio validation migration..."

MIGRATION="database/migrations/2026_08_01_000008_add_audio_validation_metadata_to_tracks_table.php"

if [ ! -f "$MIGRATION" ]; then
cat > "$MIGRATION" <<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'tracks',
            function (Blueprint $table) {
                if (
                    !Schema::hasColumn(
                        'tracks',
                        'audio_validation_status'
                    )
                ) {
                    $table->string(
                        'audio_validation_status',
                        30
                    )
                        ->default('pending')
                        ->after('audio_size_bytes');
                }

                if (
                    !Schema::hasColumn(
                        'tracks',
                        'audio_validation_errors'
                    )
                ) {
                    $table->json(
                        'audio_validation_errors'
                    )->nullable();
                }

                if (
                    !Schema::hasColumn(
                        'tracks',
                        'audio_metadata'
                    )
                ) {
                    $table->json(
                        'audio_metadata'
                    )->nullable();
                }

                if (
                    !Schema::hasColumn(
                        'tracks',
                        'audio_codec'
                    )
                ) {
                    $table->string(
                        'audio_codec',
                        100
                    )->nullable();
                }

                if (
                    !Schema::hasColumn(
                        'tracks',
                        'audio_sample_rate'
                    )
                ) {
                    $table->unsignedInteger(
                        'audio_sample_rate'
                    )->nullable();
                }

                if (
                    !Schema::hasColumn(
                        'tracks',
                        'audio_bit_depth'
                    )
                ) {
                    $table->unsignedSmallInteger(
                        'audio_bit_depth'
                    )->nullable();
                }

                if (
                    !Schema::hasColumn(
                        'tracks',
                        'audio_channels'
                    )
                ) {
                    $table->unsignedSmallInteger(
                        'audio_channels'
                    )->nullable();
                }

                if (
                    !Schema::hasColumn(
                        'tracks',
                        'audio_channel_layout'
                    )
                ) {
                    $table->string(
                        'audio_channel_layout',
                        100
                    )->nullable();
                }

                if (
                    !Schema::hasColumn(
                        'tracks',
                        'audio_duration_seconds'
                    )
                ) {
                    $table->decimal(
                        'audio_duration_seconds',
                        12,
                        3
                    )->nullable();
                }

                if (
                    !Schema::hasColumn(
                        'tracks',
                        'audio_peak_db'
                    )
                ) {
                    $table->decimal(
                        'audio_peak_db',
                        8,
                        3
                    )->nullable();
                }

                if (
                    !Schema::hasColumn(
                        'tracks',
                        'audio_mean_volume_db'
                    )
                ) {
                    $table->decimal(
                        'audio_mean_volume_db',
                        8,
                        3
                    )->nullable();
                }

                if (
                    !Schema::hasColumn(
                        'tracks',
                        'audio_silence_start_seconds'
                    )
                ) {
                    $table->decimal(
                        'audio_silence_start_seconds',
                        10,
                        3
                    )->nullable();
                }

                if (
                    !Schema::hasColumn(
                        'tracks',
                        'audio_silence_end_seconds'
                    )
                ) {
                    $table->decimal(
                        'audio_silence_end_seconds',
                        10,
                        3
                    )->nullable();
                }

                if (
                    !Schema::hasColumn(
                        'tracks',
                        'audio_validated_at'
                    )
                ) {
                    $table->timestamp(
                        'audio_validated_at'
                    )->nullable();
                }

                if (
                    !Schema::hasColumn(
                        'tracks',
                        'audio_validated_by'
                    )
                ) {
                    $table->unsignedBigInteger(
                        'audio_validated_by'
                    )->nullable();
                }
            }
        );
    }

    public function down(): void
    {
        /*
         * Technical metadata is production data.
         * Do not remove automatically.
         */
    }
};
PHP
fi


echo "[4/10] Updating Track model casts and fillable..."

python3 - <<'PY'
from pathlib import Path
import re

path = Path(
    "app/Models/Distribution/Track.php"
)

if not path.exists():
    raise SystemExit("Track model nahi mila.")

text = path.read_text()

fillable_fields = [
    "audio_validation_status",
    "audio_validation_errors",
    "audio_metadata",
    "audio_codec",
    "audio_sample_rate",
    "audio_bit_depth",
    "audio_channels",
    "audio_channel_layout",
    "audio_duration_seconds",
    "audio_peak_db",
    "audio_mean_volume_db",
    "audio_silence_start_seconds",
    "audio_silence_end_seconds",
    "audio_validated_at",
    "audio_validated_by",
]

match = re.search(
    r"protected\s+\$fillable\s*=\s*\[(.*?)\];",
    text,
    re.S
)

if match:
    body = match.group(1)
    additions = []

    for field in fillable_fields:
        token = f"'{field}'"

        if token not in body:
            additions.append(
                f"        '{field}',"
            )

    if additions:
        replacement = (
            "protected $fillable = ["
            + body.rstrip()
            + "\n"
            + "\n".join(additions)
            + "\n    ];"
        )

        text = (
            text[:match.start()]
            + replacement
            + text[match.end():]
        )

casts = {
    "audio_validation_errors": "array",
    "audio_metadata": "array",
    "audio_sample_rate": "integer",
    "audio_bit_depth": "integer",
    "audio_channels": "integer",
    "audio_duration_seconds": "float",
    "audio_peak_db": "float",
    "audio_mean_volume_db": "float",
    "audio_silence_start_seconds": "float",
    "audio_silence_end_seconds": "float",
    "audio_validated_at": "datetime",
}

casts_match = re.search(
    r"protected\s+\$casts\s*=\s*\[(.*?)\];",
    text,
    re.S
)

if casts_match:
    body = casts_match.group(1)
    additions = []

    for field, cast in casts.items():
        if f"'{field}'" not in body:
            additions.append(
                f"        '{field}' => '{cast}',"
            )

    if additions:
        replacement = (
            "protected $casts = ["
            + body.rstrip()
            + "\n"
            + "\n".join(additions)
            + "\n    ];"
        )

        text = (
            text[:casts_match.start()]
            + replacement
            + text[casts_match.end():]
        )
else:
    last_brace = text.rfind("}")

    block = "\n    protected $casts = [\n"

    for field, cast in casts.items():
        block += (
            f"        '{field}' => '{cast}',\n"
        )

    block += "    ];\n\n"

    text = (
        text[:last_brace]
        + block
        + text[last_brace:]
    )

path.write_text(text)

print("Track model updated.")
PY


echo "[5/10] Updating audio validation permissions..."

python3 - <<'PY'
from pathlib import Path

paths = [
    Path(
        "app/Services/V2/PermissionService.php"
    ),
    Path(
        "resources/js/V2/Shared/Permissions/permissions.js"
    ),
]

permissions = [
    "audio_validation.view",
    "audio_validation.run",
    "audio_validation.override",
]

for path in paths:
    if not path.exists():
        continue

    text = path.read_text()

    marker = "'delivery.manage',"

    if marker not in text:
        marker = "'releases.update',"

    additions = []

    for permission in permissions:
        token = f"'{permission}',"

        if token not in text:
            additions.append(
                "            " + token
            )

    if additions and marker in text:
        text = text.replace(
            marker,
            marker + "\n" + "\n".join(additions),
            1
        )

        path.write_text(text)

        print(f"Updated permissions: {path}")
PY


echo "[6/10] Creating Audio Validation Service..."

cat > app/Services/V2/AudioValidationService.php <<'PHP'
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

                'audio_bit_depth' =>
                    $metadata['bit_depth']
                    ?? null,

                'audio_channels' =>
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

        $decoded = json_decode(
            $process->getOutput(),
            true
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

        $bitDepth = (int) (
            $stream['bits_per_raw_sample']
            ?: $stream['bits_per_sample']
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
PHP


echo "[7/10] Creating Admin Audio Validation Controller..."

cat > app/Http/Controllers/V2/Admin/AudioValidationController.php <<'PHP'
<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\Distribution\Track;
use App\Services\V2\AudioValidationService;
use App\Services\V2\PermissionService;
use App\Services\V2\ReleaseAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AudioValidationController extends Controller
{
    public function show(
        Request $request,
        Track $track,
        PermissionService $permissions,
        ReleaseAccessService $access
    ): JsonResponse {
        $permissions->authorize(
            $request->user(),
            'audio_validation.view'
        );

        $track->loadMissing('release');

        $access->authorizeView(
            $request->user(),
            $track->release
        );

        return response()->json([
            'track' => [
                'id' =>
                    $track->id,

                'title' =>
                    $track->title,

                'audio_path' =>
                    $track->audio_path,

                'validation_status' =>
                    $track
                        ->audio_validation_status,

                'validation_errors' =>
                    $track
                        ->audio_validation_errors,

                'metadata' =>
                    $track->audio_metadata,

                'validated_at' =>
                    $track->audio_validated_at,
            ],
        ]);
    }

    public function validateTrack(
        Request $request,
        Track $track,
        PermissionService $permissions,
        ReleaseAccessService $access,
        AudioValidationService $validator
    ): JsonResponse {
        $permissions->authorize(
            $request->user(),
            'audio_validation.run'
        );

        $track->loadMissing('release');

        $access->authorizeView(
            $request->user(),
            $track->release
        );

        $result = $validator->validate(
            $track,
            $request->user()
        );

        return response()->json([
            'message' =>
                $result['passed']
                    ? 'Audio validation passed.'
                    : 'Audio validation failed.',

            ...$result,
        ]);
    }

    public function bulkValidate(
        Request $request,
        PermissionService $permissions,
        AudioValidationService $validator
    ): JsonResponse {
        $permissions->authorize(
            $request->user(),
            'audio_validation.run'
        );

        $validated = $request->validate([
            'track_ids' => [
                'required',
                'array',
                'min:1',
                'max:100',
            ],

            'track_ids.*' => [
                'required',
                'integer',
                'distinct',
                'exists:tracks,id',
            ],
        ]);

        $tracks = Track::query()
            ->whereIn(
                'id',
                $validated['track_ids']
            )
            ->whereNotNull('audio_path')
            ->get();

        return response()->json(
            $validator->validateMany(
                $tracks,
                $request->user()
            )
        );
    }
}
PHP


echo "[8/10] Creating bulk validation command..."

cat > app/Console/Commands/ValidateTrackAudio.php <<'PHP'
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
PHP


echo "[9/10] Connecting automatic validation after upload..."

python3 - <<'PY'
from pathlib import Path

path = Path(
    "app/Http/Controllers/V2/ReleaseTrackController.php"
)

if not path.exists():
    raise SystemExit(
        "ReleaseTrackController nahi mila."
    )

text = path.read_text()

import_line = (
    "use App\\Services\\V2\\AudioValidationService;\n"
)

marker = (
    "use App\\Services\\V2\\ReleaseAccessService;\n"
)

if import_line not in text:
    if marker in text:
        text = text.replace(
            marker,
            marker + import_line,
            1
        )
    else:
        controller_marker = (
            "use App\\Http\\Controllers\\Controller;\n"
        )

        text = text.replace(
            controller_marker,
            controller_marker
            + import_line,
            1
        )

# Add service parameter to store method.
old = """        PermissionService $permissions,
        ReleaseAccessService $access
    ): RedirectResponse {"""

new = """        PermissionService $permissions,
        ReleaseAccessService $access,
        AudioValidationService $audioValidator
    ): RedirectResponse {"""

if old in text:
    text = text.replace(
        old,
        new,
        1
    )

store_marker = """        return back()->with([
            'success' =>
                'Track added successfully.',"""

store_replacement = """        $audioValidator->validate(
            $track,
            $request->user()
        );

        return back()->with([
            'success' =>
                'Track added successfully.',"""

if (
    store_marker in text
    and "$audioValidator->validate(\n            $track" not in text
):
    text = text.replace(
        store_marker,
        store_replacement,
        1
    )

# Update method parameter.
old_update = """        PermissionService $permissions,
        ReleaseAccessService $access
    ): RedirectResponse {"""

new_update = """        PermissionService $permissions,
        ReleaseAccessService $access,
        AudioValidationService $audioValidator
    ): RedirectResponse {"""

if old_update in text:
    text = text.replace(
        old_update,
        new_update,
        1
    )

update_marker = """        $track->update([
            ...$validated,
            'updated_by' => Auth::id(),
        ]);

        return back()->with(
            'success',
            'Track updated successfully.'
        );"""

update_replacement = """        $audioWasReplaced =
            $request->hasFile('audio');

        $track->update([
            ...$validated,
            'updated_by' => Auth::id(),
        ]);

        if ($audioWasReplaced) {
            $audioValidator->validate(
                $track->fresh(),
                $request->user()
            );
        }

        return back()->with(
            'success',
            'Track updated successfully.'
        );"""

if update_marker in text:
    text = text.replace(
        update_marker,
        update_replacement,
        1
    )

path.write_text(text)

print(
    "Automatic track audio validation connected."
)
PY


echo "[10/10] Adding routes, migrating and checking..."

python3 - <<'PY'
from pathlib import Path

path = Path("routes/web.php")
text = path.read_text()

routes = {
    "v2.admin.audio-validation.show": r"""
Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/admin/tracks/{track}/audio-validation',
        [\App\Http\Controllers\V2\Admin\AudioValidationController::class, 'show']
    )
    ->name('v2.admin.audio-validation.show');
""",

    "v2.admin.audio-validation.run": r"""
Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/tracks/{track}/audio-validation',
        [\App\Http\Controllers\V2\Admin\AudioValidationController::class, 'validateTrack']
    )
    ->name('v2.admin.audio-validation.run');
""",

    "v2.admin.audio-validation.bulk": r"""
Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/audio-validation/bulk',
        [\App\Http\Controllers\V2\Admin\AudioValidationController::class, 'bulkValidate']
    )
    ->name('v2.admin.audio-validation.bulk');
""",
}

count = 0

for name, route in routes.items():
    if name not in text:
        text += "\n" + route
        count += 1

path.write_text(text)

print(f"{count} audio validation routes added.")
PY

php artisan migrate --force

php -l \
app/Services/V2/AudioValidationService.php

php -l \
app/Http/Controllers/V2/Admin/AudioValidationController.php

php -l \
app/Console/Commands/ValidateTrackAudio.php

php -l \
app/Http/Controllers/V2/ReleaseTrackController.php

php -l \
app/Models/Distribution/Track.php

php -l routes/web.php

php artisan optimize:clear

echo ""
echo "===== SERVICE RESOLUTION ====="

php artisan tinker --execute="
echo get_class(
    app(
        \App\Services\V2\AudioValidationService::class
    )
).PHP_EOL;
"

echo ""
echo "===== COMMAND CHECK ====="

php artisan list | grep \
"v2:validate-audio"

echo ""
echo "===== AUDIO VALIDATION ROUTES ====="

php artisan route:list | grep \
"audio-validation"

printf '{\n  "module": "AudioValidationEngine",\n  "installed": true,\n  "version": "3.1.0",\n  "installed_at": "%s"\n}\n' \
"$(date --iso-8601=seconds)" \
> v2/runtime/state/audio-validation-engine-installed.json

echo ""
echo "=================================================="
echo "AUDIO VALIDATION ENGINE INSTALLED"
echo "=================================================="

cat \
v2/runtime/state/audio-validation-engine-installed.json

echo ""
echo "Backup:"
echo "$BACKUP"
