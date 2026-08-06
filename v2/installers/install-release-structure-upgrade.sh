#!/usr/bin/env bash

set -Eeuo pipefail

PROJECT="/var/www/backstage-distribution"
cd "$PROJECT"

STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP="$PROJECT/v2/backups/release-structure-upgrade/$STAMP"

mkdir -p "$BACKUP"

echo "=============================================="
echo "V2 RELEASE STRUCTURE UPGRADE"
echo "=============================================="

echo "[1/9] Creating backup..."

for FILE in \
    app/Http/Controllers/V2/ReleaseController.php \
    app/Models/Distribution/Release.php \
    resources/js/V2/Shared/Releases/ReleaseWizard.jsx \
    resources/js/V2/Shared/Releases/Steps/ReleaseDetailsStep.jsx \
    resources/js/V2/Shared/Releases/Steps/TrackManager.jsx \
    resources/js/V2/Shared/Releases/Steps/DistributionStep.jsx \
    resources/js/V2/Shared/Releases/Steps/ReviewStep.jsx
do
    if [ -f "$FILE" ]; then
        mkdir -p "$BACKUP/$(dirname "$FILE")"
        cp -a "$FILE" "$BACKUP/$FILE"
    fi
done


echo "[2/9] Creating database migration..."

MIGRATION="database/migrations/2026_08_01_000001_add_multiple_artists_to_releases_table.php"

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
        Schema::table('releases', function (Blueprint $table) {
            if (!Schema::hasColumn('releases', 'primary_artists')) {
                $table->json('primary_artists')
                    ->nullable()
                    ->after('primary_artist_name');
            }

            if (!Schema::hasColumn('releases', 'featuring_artists')) {
                $table->json('featuring_artists')
                    ->nullable()
                    ->after('featuring_artist_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('releases', function (Blueprint $table) {
            if (Schema::hasColumn('releases', 'primary_artists')) {
                $table->dropColumn('primary_artists');
            }

            if (Schema::hasColumn('releases', 'featuring_artists')) {
                $table->dropColumn('featuring_artists');
            }
        });
    }
};
PHP
fi


echo "[3/9] Updating Release model casts..."

python3 - <<'PY'
from pathlib import Path
import re

path = Path("app/Models/Distribution/Release.php")

if not path.exists():
    raise SystemExit("Release model nahi mila.")

text = path.read_text()

# Add fields to fillable when fillable exists.
match = re.search(
    r"protected\s+\$fillable\s*=\s*\[(.*?)\];",
    text,
    re.S
)

if match:
    content = match.group(1)

    additions = []

    if "'primary_artists'" not in content:
        additions.append("        'primary_artists',")

    if "'featuring_artists'" not in content:
        additions.append("        'featuring_artists',")

    if additions:
        replacement = (
            "protected $fillable = ["
            + content.rstrip()
            + "\n"
            + "\n".join(additions)
            + "\n    ];"
        )

        text = (
            text[:match.start()]
            + replacement
            + text[match.end():]
        )

# Add array casts.
casts_match = re.search(
    r"protected\s+\$casts\s*=\s*\[(.*?)\];",
    text,
    re.S
)

if casts_match:
    casts_content = casts_match.group(1)

    additions = []

    if "'primary_artists'" not in casts_content:
        additions.append(
            "        'primary_artists' => 'array',"
        )

    if "'featuring_artists'" not in casts_content:
        additions.append(
            "        'featuring_artists' => 'array',"
        )

    if additions:
        replacement = (
            "protected $casts = ["
            + casts_content.rstrip()
            + "\n"
            + "\n".join(additions)
            + "\n    ];"
        )

        text = (
            text[:casts_match.start()]
            + replacement
            + text[casts_match.end():]
        )
elif "class Release" in text:
    last_brace = text.rfind("}")

    casts = """
    protected $casts = [
        'primary_artists' => 'array',
        'featuring_artists' => 'array',
        'stores' => 'array',
        'territories' => 'array',
        'worldwide' => 'boolean',
        'pre_order' => 'boolean',
    ];

"""

    text = text[:last_brace] + casts + text[last_brace:]

path.write_text(text)

print("Release model updated.")
PY


echo "[4/9] Updating backend validation and saving..."

python3 - <<'PY'
from pathlib import Path

path = Path(
    "app/Http/Controllers/V2/ReleaseController.php"
)

if not path.exists():
    raise SystemExit("V2 ReleaseController nahi mila.")

text = path.read_text()

validation_marker = """            'featuring_artist_name' => [
                'nullable',
                'string',
                'max:255',
            ],
"""

validation_addition = """            'featuring_artist_name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'primary_artists' => [
                'required',
                'array',
                'min:1',
            ],

            'primary_artists.*.name' => [
                'required',
                'string',
                'max:255',
            ],

            'primary_artists.*.spotify_url' => [
                'nullable',
                'url',
                'max:500',
            ],

            'primary_artists.*.apple_music_url' => [
                'nullable',
                'url',
                'max:500',
            ],

            'primary_artists.*.youtube_topic_url' => [
                'nullable',
                'url',
                'max:500',
            ],

            'featuring_artists' => [
                'nullable',
                'array',
            ],

            'featuring_artists.*.name' => [
                'required',
                'string',
                'max:255',
            ],
"""

if (
    "'primary_artists' => [" not in text
    and validation_marker in text
):
    text = text.replace(
        validation_marker,
        validation_addition,
        1
    )

# Digital release date must be after today.
text = text.replace(
    """            'digital_release_date' => [
                'required',
                'date',
            ],""",
    """            'digital_release_date' => [
                'required',
                'date',
                'after:today',
            ],"""
)

# Add values in create payload.
create_marker = """                'featuring_artist_name' =>
                    $validated[
                        'featuring_artist_name'
                    ] ?? null,
"""

create_addition = """                'featuring_artist_name' =>
                    collect(
                        $validated[
                            'featuring_artists'
                        ] ?? []
                    )
                        ->pluck('name')
                        ->filter()
                        ->implode(', ')
                    ?: (
                        $validated[
                            'featuring_artist_name'
                        ] ?? null
                    ),

                'primary_artists' =>
                    array_values(
                        $validated[
                            'primary_artists'
                        ] ?? []
                    ),

                'featuring_artists' =>
                    array_values(
                        $validated[
                            'featuring_artists'
                        ] ?? []
                    ),
"""

if (
    "'primary_artists' =>" not in text
    and create_marker in text
):
    text = text.replace(
        create_marker,
        create_addition,
        1
    )

# Add values in update payload.
update_occurrence = """            'featuring_artist_name' =>
                $validated[
                    'featuring_artist_name'
                ] ?? null,
"""

update_addition = """            'featuring_artist_name' =>
                collect(
                    $validated[
                        'featuring_artists'
                    ] ?? []
                )
                    ->pluck('name')
                    ->filter()
                    ->implode(', ')
                ?: (
                    $validated[
                        'featuring_artist_name'
                    ] ?? null
                ),

            'primary_artists' =>
                array_values(
                    $validated[
                        'primary_artists'
                    ] ?? []
                ),

            'featuring_artists' =>
                array_values(
                    $validated[
                        'featuring_artists'
                    ] ?? []
                ),
"""

if update_occurrence in text:
    text = text.replace(
        update_occurrence,
        update_addition,
        1
    )

path.write_text(text)

print("V2 ReleaseController updated.")
PY


echo "[5/9] Creating advanced Release Details component..."

cat > resources/js/V2/Shared/Releases/Steps/ReleaseDetailsStep.jsx <<'JSX'
const inputClass =
    'w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-violet-500 focus:ring-2 focus:ring-violet-100';

const tomorrow = () => {
    const date = new Date();
    date.setDate(date.getDate() + 1);

    return date.toISOString().substring(0, 10);
};

const emptyPrimaryArtist = () => ({
    name: '',
    spotify_url: '',
    apple_music_url: '',
    youtube_topic_url: '',
});

const emptyFeaturingArtist = () => ({
    name: '',
});

export default function ReleaseDetailsStep({
    role = 'artist',
    data,
    setData,
    errors = {},
    artist = null,
    label = null,
    availableArtists = [],
    availableLabels = [],
}) {
    const primaryArtists =
        Array.isArray(data.primary_artists) &&
        data.primary_artists.length > 0
            ? data.primary_artists
            : [
                  {
                      ...emptyPrimaryArtist(),
                      name:
                          data.primary_artist_name ||
                          artist?.stage_name ||
                          artist?.legal_name ||
                          '',
                  },
              ];

    const featuringArtists = Array.isArray(
        data.featuring_artists
    )
        ? data.featuring_artists
        : [];

    const updatePrimary = (
        index,
        field,
        value
    ) => {
        const updated = primaryArtists.map(
            (item, itemIndex) =>
                itemIndex === index
                    ? {
                          ...item,
                          [field]: value,
                      }
                    : item
        );

        setData('primary_artists', updated);

        setData(
            'primary_artist_name',
            updated
                .map((item) => item.name)
                .filter(Boolean)
                .join(', ')
        );
    };

    const addPrimary = () => {
        setData('primary_artists', [
            ...primaryArtists,
            emptyPrimaryArtist(),
        ]);
    };

    const removePrimary = (index) => {
        if (primaryArtists.length === 1) {
            return;
        }

        const updated =
            primaryArtists.filter(
                (_, itemIndex) =>
                    itemIndex !== index
            );

        setData('primary_artists', updated);

        setData(
            'primary_artist_name',
            updated
                .map((item) => item.name)
                .filter(Boolean)
                .join(', ')
        );
    };

    const updateFeaturing = (
        index,
        value
    ) => {
        const updated = featuringArtists.map(
            (item, itemIndex) =>
                itemIndex === index
                    ? {
                          ...item,
                          name: value,
                      }
                    : item
        );

        setData(
            'featuring_artists',
            updated
        );

        setData(
            'featuring_artist_name',
            updated
                .map((item) => item.name)
                .filter(Boolean)
                .join(', ')
        );
    };

    const addFeaturing = () => {
        setData('featuring_artists', [
            ...featuringArtists,
            emptyFeaturingArtist(),
        ]);
    };

    const removeFeaturing = (index) => {
        const updated =
            featuringArtists.filter(
                (_, itemIndex) =>
                    itemIndex !== index
            );

        setData(
            'featuring_artists',
            updated
        );

        setData(
            'featuring_artist_name',
            updated
                .map((item) => item.name)
                .filter(Boolean)
                .join(', ')
        );
    };

    const selectedLabel =
        label?.name ||
        availableLabels.find(
            (item) =>
                Number(item.id) ===
                Number(data.label_id)
        )?.name ||
        '';

    return (
        <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
            <div className="space-y-7">
                <div>
                    <h2 className="text-xl font-semibold text-slate-900">
                        Release Details
                    </h2>

                    <p className="mt-1 text-sm text-slate-500">
                        Enter release metadata and
                        artist platform profiles.
                    </p>
                </div>

                <div className="grid gap-5 md:grid-cols-2">
                    <Field
                        label="Release Title"
                        required
                        error={errors.title}
                    >
                        <input
                            className={inputClass}
                            value={data.title}
                            onChange={(event) =>
                                setData(
                                    'title',
                                    event.target.value
                                )
                            }
                            placeholder="Enter release title"
                        />
                    </Field>

                    <Field
                        label="Release Type"
                        required
                        error={errors.release_type}
                    >
                        <select
                            className={inputClass}
                            value={data.release_type}
                            onChange={(event) =>
                                setData(
                                    'release_type',
                                    event.target.value
                                )
                            }
                        >
                            <option value="single">
                                Single
                            </option>
                            <option value="ep">
                                EP
                            </option>
                            <option value="album">
                                Album
                            </option>
                        </select>
                    </Field>
                </div>

                <ArtistSection
                    title="Primary Artists"
                    description="Add all main artists and their DSP profile URLs."
                    addLabel="+ Add Primary Artist"
                    onAdd={addPrimary}
                >
                    <div className="space-y-4">
                        {primaryArtists.map(
                            (item, index) => (
                                <div
                                    key={index}
                                    className="rounded-2xl border border-slate-200 bg-slate-50 p-5"
                                >
                                    <div className="flex items-center justify-between gap-4">
                                        <h4 className="font-semibold text-slate-900">
                                            Primary Artist{' '}
                                            {index + 1}
                                        </h4>

                                        {primaryArtists.length >
                                            1 && (
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    removePrimary(
                                                        index
                                                    )
                                                }
                                                className="text-sm font-semibold text-red-600 hover:text-red-700"
                                            >
                                                Remove
                                            </button>
                                        )}
                                    </div>

                                    <div className="mt-4 grid gap-4 md:grid-cols-2">
                                        <Field
                                            label="Artist Name"
                                            required
                                            error={
                                                errors[
                                                    `primary_artists.${index}.name`
                                                ]
                                            }
                                        >
                                            <input
                                                className={
                                                    inputClass
                                                }
                                                value={
                                                    item.name
                                                }
                                                onChange={(
                                                    event
                                                ) =>
                                                    updatePrimary(
                                                        index,
                                                        'name',
                                                        event
                                                            .target
                                                            .value
                                                    )
                                                }
                                                placeholder="Artist name"
                                            />
                                        </Field>

                                        <Field label="Spotify Artist URL">
                                            <input
                                                type="url"
                                                className={
                                                    inputClass
                                                }
                                                value={
                                                    item.spotify_url ||
                                                    ''
                                                }
                                                onChange={(
                                                    event
                                                ) =>
                                                    updatePrimary(
                                                        index,
                                                        'spotify_url',
                                                        event
                                                            .target
                                                            .value
                                                    )
                                                }
                                                placeholder="https://open.spotify.com/artist/..."
                                            />
                                        </Field>

                                        <Field label="Apple Music Artist URL">
                                            <input
                                                type="url"
                                                className={
                                                    inputClass
                                                }
                                                value={
                                                    item.apple_music_url ||
                                                    ''
                                                }
                                                onChange={(
                                                    event
                                                ) =>
                                                    updatePrimary(
                                                        index,
                                                        'apple_music_url',
                                                        event
                                                            .target
                                                            .value
                                                    )
                                                }
                                                placeholder="https://music.apple.com/..."
                                            />
                                        </Field>

                                        <Field label="YouTube Topic URL">
                                            <input
                                                type="url"
                                                className={
                                                    inputClass
                                                }
                                                value={
                                                    item.youtube_topic_url ||
                                                    ''
                                                }
                                                onChange={(
                                                    event
                                                ) =>
                                                    updatePrimary(
                                                        index,
                                                        'youtube_topic_url',
                                                        event
                                                            .target
                                                            .value
                                                    )
                                                }
                                                placeholder="https://youtube.com/channel/..."
                                            />
                                        </Field>
                                    </div>
                                </div>
                            )
                        )}
                    </div>
                </ArtistSection>

                <ArtistSection
                    title="Featuring Artists"
                    description="Add one or more featured artists."
                    addLabel="+ Add Featuring Artist"
                    onAdd={addFeaturing}
                >
                    {featuringArtists.length ===
                    0 ? (
                        <div className="rounded-xl border border-dashed border-slate-300 p-5 text-sm text-slate-500">
                            No featuring artist added.
                        </div>
                    ) : (
                        <div className="space-y-3">
                            {featuringArtists.map(
                                (item, index) => (
                                    <div
                                        key={index}
                                        className="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4"
                                    >
                                        <input
                                            className={
                                                inputClass
                                            }
                                            value={
                                                item.name
                                            }
                                            onChange={(
                                                event
                                            ) =>
                                                updateFeaturing(
                                                    index,
                                                    event
                                                        .target
                                                        .value
                                                )
                                            }
                                            placeholder={`Featuring Artist ${
                                                index + 1
                                            }`}
                                        />

                                        <button
                                            type="button"
                                            onClick={() =>
                                                removeFeaturing(
                                                    index
                                                )
                                            }
                                            className="rounded-lg px-3 py-2 text-sm font-semibold text-red-600 hover:bg-red-50"
                                        >
                                            Remove
                                        </button>
                                    </div>
                                )
                            )}
                        </div>
                    )}
                </ArtistSection>

                <div className="grid gap-5 md:grid-cols-2">
                    <Field
                        label="Label"
                        required
                        error={errors.label_id}
                    >
                        <input
                            className={`${inputClass} bg-slate-100`}
                            value={selectedLabel}
                            readOnly
                        />
                    </Field>

                    <Field
                        label="Digital Release Date"
                        required
                        error={
                            errors.digital_release_date
                        }
                    >
                        <input
                            type="date"
                            min={tomorrow()}
                            className={inputClass}
                            value={
                                data.digital_release_date
                            }
                            onChange={(event) =>
                                setData(
                                    'digital_release_date',
                                    event.target.value
                                )
                            }
                        />

                        <p className="mt-1 text-xs text-slate-500">
                            Release date आज के बाद की
                            होनी चाहिए।
                        </p>
                    </Field>

                    <Field
                        label="UPC"
                        error={errors.upc}
                    >
                        <input
                            className={inputClass}
                            value={data.upc}
                            onChange={(event) =>
                                setData(
                                    'upc',
                                    event.target.value
                                )
                            }
                            placeholder="Leave blank if pending"
                        />
                    </Field>

                    <Field
                        label="Catalogue Number"
                        required
                        error={
                            errors.catalog_number
                        }
                    >
                        <input
                            className={inputClass}
                            value={
                                data.catalog_number
                            }
                            onChange={(event) =>
                                setData(
                                    'catalog_number',
                                    event.target.value
                                )
                            }
                            placeholder="MXT-000001"
                        />
                    </Field>

                    <Field label="Language">
                        <input
                            className={inputClass}
                            value={data.language}
                            onChange={(event) =>
                                setData(
                                    'language',
                                    event.target.value
                                )
                            }
                            placeholder="Hindi"
                        />
                    </Field>

                    <Field label="Primary Genre">
                        <input
                            className={inputClass}
                            value={
                                data.primary_genre
                            }
                            onChange={(event) =>
                                setData(
                                    'primary_genre',
                                    event.target.value
                                )
                            }
                            placeholder="Devotional, Pop..."
                        />
                    </Field>

                    <Field label="Sub Genre">
                        <input
                            className={inputClass}
                            value={data.sub_genre}
                            onChange={(event) =>
                                setData(
                                    'sub_genre',
                                    event.target.value
                                )
                            }
                            placeholder="Optional"
                        />
                    </Field>

                    <Field label="Copyright Year">
                        <input
                            type="number"
                            min="1900"
                            max="2100"
                            className={inputClass}
                            value={
                                data.copyright_year
                            }
                            onChange={(event) =>
                                setData(
                                    'copyright_year',
                                    event.target.value
                                )
                            }
                        />
                    </Field>

                    <Field label="Copyright Owner">
                        <input
                            className={inputClass}
                            value={
                                data.copyright_owner
                            }
                            onChange={(event) =>
                                setData(
                                    'copyright_owner',
                                    event.target.value
                                )
                            }
                            placeholder="Rights owner"
                        />
                    </Field>

                    <Field label="Phonographic Owner">
                        <input
                            className={inputClass}
                            value={
                                data.phonographic_owner
                            }
                            onChange={(event) =>
                                setData(
                                    'phonographic_owner',
                                    event.target.value
                                )
                            }
                            placeholder="℗ owner"
                        />
                    </Field>
                </div>
            </div>

            <div className="space-y-5">
                <div className="rounded-2xl border border-slate-200 p-5">
                    <h3 className="font-semibold text-slate-900">
                        Cover Artwork
                    </h3>

                    <label className="mt-4 flex min-h-64 cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-violet-300 bg-violet-50/40 p-6 text-center hover:bg-violet-50">
                        {data.artwork_preview ? (
                            <img
                                src={
                                    data.artwork_preview
                                }
                                alt="Artwork preview"
                                className="aspect-square w-full rounded-xl object-cover"
                            />
                        ) : (
                            <>
                                <div className="text-4xl">
                                    ☁
                                </div>

                                <div className="mt-3 font-semibold text-slate-900">
                                    Upload Cover Art
                                </div>

                                <div className="mt-1 text-xs text-slate-500">
                                    JPG or PNG, maximum
                                    20 MB
                                </div>
                            </>
                        )}

                        <input
                            type="file"
                            accept="image/jpeg,image/png"
                            className="hidden"
                            onChange={(event) => {
                                const file =
                                    event.target
                                        .files?.[0] ??
                                    null;

                                setData(
                                    'artwork',
                                    file
                                );

                                setData(
                                    'artwork_preview',
                                    file
                                        ? URL.createObjectURL(
                                              file
                                          )
                                        : null
                                );
                            }}
                        />
                    </label>
                </div>

                <div className="rounded-2xl border border-slate-200 p-5">
                    <h3 className="font-semibold text-slate-900">
                        Release Preview
                    </h3>

                    <div className="mt-4 space-y-3 text-sm">
                        <PreviewRow
                            label="Title"
                            value={data.title || '—'}
                        />

                        <PreviewRow
                            label="Primary Artists"
                            value={
                                primaryArtists
                                    .map(
                                        (item) =>
                                            item.name
                                    )
                                    .filter(Boolean)
                                    .join(', ') || '—'
                            }
                        />

                        <PreviewRow
                            label="Featuring"
                            value={
                                featuringArtists
                                    .map(
                                        (item) =>
                                            item.name
                                    )
                                    .filter(Boolean)
                                    .join(', ') || 'None'
                            }
                        />

                        <PreviewRow
                            label="Label"
                            value={
                                selectedLabel || '—'
                            }
                        />

                        <PreviewRow
                            label="Release Date"
                            value={
                                data.digital_release_date ||
                                '—'
                            }
                        />
                    </div>
                </div>
            </div>
        </div>
    );
}

function ArtistSection({
    title,
    description,
    addLabel,
    onAdd,
    children,
}) {
    return (
        <section className="rounded-2xl border border-slate-200 bg-white p-5">
            <div className="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h3 className="font-semibold text-slate-900">
                        {title}
                    </h3>

                    <p className="mt-1 text-sm text-slate-500">
                        {description}
                    </p>
                </div>

                <button
                    type="button"
                    onClick={onAdd}
                    className="rounded-xl bg-violet-50 px-4 py-2.5 text-sm font-semibold text-violet-700 hover:bg-violet-100"
                >
                    {addLabel}
                </button>
            </div>

            {children}
        </section>
    );
}

function Field({
    label,
    required = false,
    error = null,
    children,
}) {
    return (
        <div>
            <label className="mb-2 block text-sm font-medium text-slate-700">
                {label}

                {required && (
                    <span className="ml-1 text-red-500">
                        *
                    </span>
                )}
            </label>

            {children}

            {error && (
                <p className="mt-1 text-sm text-red-600">
                    {error}
                </p>
            )}
        </div>
    );
}

function PreviewRow({ label, value }) {
    return (
        <div className="flex items-start justify-between gap-4">
            <span className="text-slate-500">
                {label}
            </span>

            <span className="max-w-[65%] text-right font-medium text-slate-900">
                {value}
            </span>
        </div>
    );
}
JSX


echo "[6/9] Updating Release Wizard data..."

python3 - <<'PY'
from pathlib import Path

path = Path(
    "resources/js/V2/Shared/Releases/ReleaseWizard.jsx"
)

text = path.read_text()

artist_state = """        primary_artist_name:
            release?.primary_artist_name ??
            artist?.stage_name ??
            '',
"""

artist_new = """        primary_artist_name:
            release?.primary_artist_name ??
            artist?.stage_name ??
            '',

        primary_artists:
            Array.isArray(
                release?.primary_artists
            ) &&
            release.primary_artists.length > 0
                ? release.primary_artists
                : [
                      {
                          name:
                              release?.primary_artist_name ??
                              artist?.stage_name ??
                              '',
                          spotify_url: '',
                          apple_music_url: '',
                          youtube_topic_url: '',
                      },
                  ],
"""

if (
    "primary_artists:" not in text
    and artist_state in text
):
    text = text.replace(
        artist_state,
        artist_new,
        1
    )

featuring_state = """        featuring_artist_name:
            release?.featuring_artist_name ?? '',
"""

featuring_new = """        featuring_artist_name:
            release?.featuring_artist_name ?? '',

        featuring_artists:
            Array.isArray(
                release?.featuring_artists
            )
                ? release.featuring_artists
                : (
                      release?.featuring_artist_name
                          ? [
                                {
                                    name:
                                        release.featuring_artist_name,
                                },
                            ]
                          : []
                  ),
"""

if (
    "featuring_artists:" not in text
    and featuring_state in text
):
    text = text.replace(
        featuring_state,
        featuring_new,
        1
    )

# Step names.
text = text.replace(
    "{ id: 3, label: 'Stores & Distribution' }",
    "{ id: 3, label: 'Stores & Territory' }"
)

path.write_text(text)

print("ReleaseWizard updated.")
PY


echo "[7/9] Creating Stores & Territory component..."

cat > resources/js/V2/Shared/Releases/Steps/DistributionStep.jsx <<'JSX'
import { router } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const countries = [
    ['IN', 'India'],
    ['US', 'United States'],
    ['GB', 'United Kingdom'],
    ['CA', 'Canada'],
    ['AU', 'Australia'],
    ['AE', 'United Arab Emirates'],
    ['SG', 'Singapore'],
    ['DE', 'Germany'],
    ['FR', 'France'],
    ['JP', 'Japan'],
];

export default function DistributionStep({
    release,
    distributionStores = [],
}) {
    const allStoreIds = useMemo(
        () =>
            distributionStores.map(
                (store) => Number(store.id)
            ),
        [distributionStores]
    );

    const savedStores = Array.isArray(
        release?.stores
    )
        ? release.stores.map(Number)
        : [];

    const [selectedStores, setSelectedStores] =
        useState(
            savedStores.length > 0
                ? savedStores
                : allStoreIds
        );

    const [worldwide, setWorldwide] =
        useState(
            release?.worldwide ===
                undefined ||
                release?.worldwide === null
                ? true
                : Boolean(
                      release.worldwide
                  )
        );

    const [territories, setTerritories] =
        useState(
            Array.isArray(
                release?.territories
            )
                ? release.territories
                : []
        );

    const [processing, setProcessing] =
        useState(false);

    const [saved, setSaved] =
        useState(false);

    const toggleStore = (id) => {
        const storeId = Number(id);

        setSelectedStores((current) =>
            current.includes(storeId)
                ? current.filter(
                      (item) =>
                          item !== storeId
                  )
                : [...current, storeId]
        );

        setSaved(false);
    };

    const toggleCountry = (code) => {
        setTerritories((current) =>
            current.includes(code)
                ? current.filter(
                      (item) =>
                          item !== code
                  )
                : [...current, code]
        );

        setSaved(false);
    };

    const save = () => {
        setProcessing(true);

        router.patch(
            `/v2/releases/${release.id}/distribution`,
            {
                stores:
                    selectedStores.length > 0
                        ? selectedStores
                        : allStoreIds,

                worldwide,

                territories: worldwide
                    ? []
                    : territories,

                release_timezone:
                    release?.release_timezone ||
                    'Asia/Kolkata',

                pre_order: Boolean(
                    release?.pre_order
                ),
            },
            {
                preserveScroll: true,

                onSuccess: () => {
                    setSaved(true);
                },

                onFinish: () => {
                    setProcessing(false);
                },
            }
        );
    };

    return (
        <div className="space-y-5">
            <div className="sticky top-[84px] z-30 rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-sm backdrop-blur">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 className="text-xl font-semibold text-slate-900">
                            Stores & Territory
                        </h2>

                        <p className="mt-1 text-sm text-slate-500">
                            All stores and Worldwide
                            are selected by default.
                        </p>
                    </div>

                    <div className="flex items-center gap-3">
                        {saved && (
                            <span className="rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700">
                                Saved
                            </span>
                        )}

                        <button
                            type="button"
                            onClick={save}
                            disabled={processing}
                            className="rounded-xl bg-violet-600 px-5 py-3 text-sm font-semibold text-white hover:bg-violet-700 disabled:opacity-50"
                        >
                            {processing
                                ? 'Saving...'
                                : 'Save Stores & Territory'}
                        </button>
                    </div>
                </div>
            </div>

            <div className="grid gap-6 xl:grid-cols-2">
                <section className="rounded-2xl border border-slate-200 bg-white p-5">
                    <div className="flex items-center justify-between gap-3">
                        <div>
                            <h3 className="font-semibold text-slate-900">
                                Stores
                            </h3>

                            <p className="mt-1 text-sm text-slate-500">
                                {selectedStores.length}{' '}
                                selected
                            </p>
                        </div>

                        <div className="flex gap-2">
                            <button
                                type="button"
                                onClick={() =>
                                    setSelectedStores(
                                        allStoreIds
                                    )
                                }
                                className="rounded-lg bg-violet-50 px-3 py-2 text-xs font-semibold text-violet-700"
                            >
                                Select All
                            </button>

                            <button
                                type="button"
                                onClick={() =>
                                    setSelectedStores(
                                        []
                                    )
                                }
                                className="rounded-lg bg-slate-100 px-3 py-2 text-xs font-semibold text-slate-600"
                            >
                                Clear
                            </button>
                        </div>
                    </div>

                    <div className="mt-5 grid max-h-[620px] gap-2 overflow-y-auto pr-1 sm:grid-cols-2">
                        {distributionStores.map(
                            (store) => {
                                const checked =
                                    selectedStores.includes(
                                        Number(
                                            store.id
                                        )
                                    );

                                return (
                                    <button
                                        key={
                                            store.id
                                        }
                                        type="button"
                                        onClick={() =>
                                            toggleStore(
                                                store.id
                                            )
                                        }
                                        className={`flex items-center gap-3 rounded-xl border p-3 text-left transition ${
                                            checked
                                                ? 'border-violet-400 bg-violet-50'
                                                : 'border-slate-200 bg-white'
                                        }`}
                                    >
                                        <div className="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-slate-100">
                                            {store.logo_path ? (
                                                <img
                                                    src={
                                                        store.logo_path.startsWith(
                                                            'http'
                                                        )
                                                            ? store.logo_path
                                                            : `/storage/${store.logo_path}`
                                                    }
                                                    alt={
                                                        store.name
                                                    }
                                                    className="h-full w-full object-contain p-1"
                                                />
                                            ) : (
                                                <span className="text-xs font-bold">
                                                    {store.name
                                                        ?.charAt(
                                                            0
                                                        )
                                                        ?.toUpperCase()}
                                                </span>
                                            )}
                                        </div>

                                        <span className="min-w-0 flex-1 truncate text-xs font-semibold text-slate-800">
                                            {store.name}
                                        </span>

                                        <span
                                            className={`flex h-5 w-5 shrink-0 items-center justify-center rounded border text-[10px] font-bold ${
                                                checked
                                                    ? 'border-violet-600 bg-violet-600 text-white'
                                                    : 'border-slate-300 text-transparent'
                                            }`}
                                        >
                                            ✓
                                        </span>
                                    </button>
                                );
                            }
                        )}
                    </div>
                </section>

                <section className="rounded-2xl border border-slate-200 bg-white p-5">
                    <h3 className="font-semibold text-slate-900">
                        Territory
                    </h3>

                    <label className="mt-5 flex cursor-pointer items-center justify-between rounded-2xl border border-violet-200 bg-violet-50 p-5">
                        <div>
                            <div className="font-semibold text-slate-900">
                                Worldwide
                            </div>

                            <p className="mt-1 text-sm text-slate-500">
                                Deliver in all supported
                                countries.
                            </p>
                        </div>

                        <input
                            type="checkbox"
                            checked={worldwide}
                            onChange={(event) => {
                                setWorldwide(
                                    event.target
                                        .checked
                                );
                                setSaved(false);
                            }}
                            className="h-6 w-6 rounded border-slate-300 text-violet-600"
                        />
                    </label>

                    {!worldwide && (
                        <div className="mt-5 grid gap-2 sm:grid-cols-2">
                            {countries.map(
                                ([code, name]) => {
                                    const checked =
                                        territories.includes(
                                            code
                                        );

                                    return (
                                        <button
                                            key={code}
                                            type="button"
                                            onClick={() =>
                                                toggleCountry(
                                                    code
                                                )
                                            }
                                            className={`flex items-center justify-between rounded-xl border p-3 text-sm ${
                                                checked
                                                    ? 'border-violet-400 bg-violet-50'
                                                    : 'border-slate-200'
                                            }`}
                                        >
                                            <span>
                                                {name}
                                            </span>

                                            <span
                                                className={`flex h-5 w-5 items-center justify-center rounded border text-[10px] ${
                                                    checked
                                                        ? 'border-violet-600 bg-violet-600 text-white'
                                                        : 'border-slate-300 text-transparent'
                                                }`}
                                            >
                                                ✓
                                            </span>
                                        </button>
                                    );
                                }
                            )}
                        </div>
                    )}

                    {worldwide && (
                        <div className="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
                            <div className="font-semibold text-emerald-900">
                                Worldwide Selected
                            </div>

                            <p className="mt-2 text-sm text-emerald-700">
                                पूरी territory selected है।
                            </p>
                        </div>
                    )}
                </section>
            </div>
        </div>
    );
}
JSX


echo "[8/9] Upgrading audio upload modal..."

python3 - <<'PY'
from pathlib import Path
import re

path = Path(
    "resources/js/V2/Shared/Releases/Steps/TrackManager.jsx"
)

text = path.read_text()

pattern = re.compile(
    r"""\{uploading && \(
                <div className="fixed inset-0.*?
                </div>
            \)\}""",
    re.S
)

replacement = r'''{uploading && (
                <div className="fixed inset-0 z-[120] flex items-center justify-center bg-slate-950/70 p-5 backdrop-blur-md">
                    <div className="relative w-full max-w-lg overflow-hidden rounded-[32px] border border-white/30 bg-white/95 p-8 text-center shadow-[0_40px_120px_rgba(15,23,42,0.55)]">
                        <div className="absolute -left-24 -top-24 h-52 w-52 rounded-full bg-violet-300/40 blur-3xl" />
                        <div className="absolute -bottom-24 -right-24 h-52 w-52 rounded-full bg-cyan-300/40 blur-3xl" />

                        <div className="relative">
                            <div className="mx-auto flex h-24 w-24 items-center justify-center rounded-[28px] bg-gradient-to-br from-violet-600 to-indigo-700 text-4xl text-white shadow-[0_20px_45px_rgba(124,58,237,0.40)] [transform:perspective(800px)_rotateX(8deg)_rotateY(-8deg)]">
                                ♫
                            </div>

                            <h3 className="mt-6 text-2xl font-bold text-slate-900">
                                Uploading Master Audio
                            </h3>

                            <p className="mx-auto mt-3 max-w-sm break-all text-sm font-semibold text-slate-600">
                                {uploadFileName}
                            </p>

                            <div className="mx-auto mt-7 flex h-32 w-32 items-center justify-center rounded-full bg-gradient-to-br from-violet-100 to-indigo-50 shadow-inner">
                                <div className="flex h-24 w-24 items-center justify-center rounded-full bg-white shadow-lg">
                                    <span className="text-2xl font-bold text-violet-700">
                                        {uploadProgress}%
                                    </span>
                                </div>
                            </div>

                            <div className="mt-7 h-3 overflow-hidden rounded-full bg-slate-200 shadow-inner">
                                <div
                                    className="h-full rounded-full bg-gradient-to-r from-violet-600 via-indigo-500 to-cyan-500 transition-all duration-300"
                                    style={{
                                        width: `${uploadProgress}%`,
                                    }}
                                />
                            </div>

                            <p className="mt-4 text-sm text-slate-500">
                                Securely uploading WAV master…
                            </p>

                            <p className="mt-1 text-xs text-slate-400">
                                Upload complete होने के बाद
                                Stores & Territory खुलेगा।
                            </p>
                        </div>
                    </div>
                </div>
            )}'''

text, count = pattern.subn(
    replacement,
    text,
    count=1
)

if count == 0:
    print(
        "Existing upload modal block नहीं मिला; "
        "TrackManager manually check करें."
    )
else:
    path.write_text(text)
    print("Advanced upload modal installed.")
PY


echo "[9/9] Running migrations, build and checks..."

php artisan migrate --force

php -l \
app/Http/Controllers/V2/ReleaseController.php

php -l \
app/Models/Distribution/Release.php

npm run build

php artisan optimize:clear

printf '{\n  "module": "ReleaseStructureUpgrade",\n  "installed": true,\n  "version": "2.1.0",\n  "installed_at": "%s"\n}\n' \
"$(date --iso-8601=seconds)" \
> v2/runtime/state/release-structure-upgrade-installed.json

echo ""
echo "=============================================="
echo "RELEASE STRUCTURE UPGRADE COMPLETE"
echo "=============================================="

echo ""
echo "Backup:"
echo "$BACKUP"

echo ""
echo "State:"
cat \
v2/runtime/state/release-structure-upgrade-installed.json
