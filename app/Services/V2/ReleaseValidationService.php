<?php

namespace App\Services\V2;

use App\Models\Distribution\Release;
use App\Models\DistributionStore;
use Illuminate\Support\Carbon;

class ReleaseValidationService
{
    public function validateForSubmission(
        Release $release
    ): array {
        $release->loadMissing('tracks');

        $errors = [];

        $this->validateReleaseMetadata(
            $release,
            $errors
        );

        $this->validateArtists(
            $release,
            $errors
        );

        $this->validateTracks(
            $release,
            $errors
        );

        $this->validateDistribution(
            $release,
            $errors
        );

        return $errors;
    }

    public function isReady(
        Release $release
    ): bool {
        return $this->validateForSubmission(
            $release
        ) === [];
    }

    public function checklist(
        Release $release
    ): array {
        $errors = $this->validateForSubmission(
            $release
        );

        return [
            'ready' => empty($errors),
            'errors' => $errors,
            'checks' => [
                'metadata' => !$this->hasErrorsWithPrefix(
                    $errors,
                    'release.'
                ),

                'artists' => !$this->hasErrorsWithPrefix(
                    $errors,
                    'artists.'
                ),

                'tracks' => !$this->hasErrorsWithPrefix(
                    $errors,
                    'tracks.'
                ),

                'distribution' =>
                    !$this->hasErrorsWithPrefix(
                        $errors,
                        'distribution.'
                    ),
            ],
        ];
    }

    private function validateReleaseMetadata(
        Release $release,
        array &$errors
    ): void {
        if (!trim((string) $release->title)) {
            $errors['release.title'] =
                'Release title is required.';
        }

        if (!trim((string) $release->release_type)) {
            $errors['release.release_type'] =
                'Release type is required.';
        }

        if (!trim((string) $release->catalog_number)) {
            $errors['release.catalog_number'] =
                'Catalogue number is required.';
        }

        if (!$release->digital_release_date) {
            $errors['release.digital_release_date'] =
                'Digital release date is required.';
        } else {
            $releaseDate = Carbon::parse(
                $release->digital_release_date
            )->startOfDay();

            if ($releaseDate->lte(now()->startOfDay())) {
                $errors['release.digital_release_date'] =
                    'Digital release date must be after today.';
            }
        }

        if (!$release->artwork_path) {
            $errors['release.artwork'] =
                'Cover artwork is required.';
        }

        if (!trim((string) $release->language)) {
            $errors['release.language'] =
                'Release language is required.';
        }

        if (!trim((string) $release->primary_genre)) {
            $errors['release.primary_genre'] =
                'Primary genre is required.';
        }
    }

    private function validateArtists(
        Release $release,
        array &$errors
    ): void {
        $primaryArtists = is_array(
            $release->primary_artists
        )
            ? $release->primary_artists
            : [];

        if (empty($primaryArtists)) {
            if (
                !trim(
                    (string)
                    $release->primary_artist_name
                )
            ) {
                $errors['artists.primary'] =
                    'At least one primary artist is required.';
            }

            return;
        }

        foreach (
            $primaryArtists as $index => $artist
        ) {
            if (
                !trim(
                    (string) (
                        $artist['name'] ?? ''
                    )
                )
            ) {
                $number = $index + 1;

                $errors[
                    "artists.primary.{$index}.name"
                ] =
                    "Primary artist {$number} name is required.";
            }
        }

        /*
         * Artist-owned releases do not require a Label.
         * Only standalone label releases must have label_id.
         */
        $isArtistOwned =
            !empty($release->artist_id);

        if (
            !$isArtistOwned &&
            !$release->label_id
        ) {
            $errors['artists.label'] =
                'Label is required.';
        }
    }

    private function validateTracks(
        Release $release,
        array &$errors
    ): void {
        if ($release->tracks->isEmpty()) {
            $errors['tracks.empty'] =
                'Add at least one track.';

            return;
        }

        foreach (
            $release->tracks as $index => $track
        ) {
            $number = $index + 1;

            if (!trim((string) $track->title)) {
                $errors[
                    "tracks.{$track->id}.title"
                ] =
                    "Track {$number} title is required.";
            }

            if (
                !trim(
                    (string)
                    $track->primary_artist_name
                )
            ) {
                $errors[
                    "tracks.{$track->id}.artist"
                ] =
                    "Track {$number} primary artist is required.";
            }

            if (!$track->audio_path) {
                $errors[
                    "tracks.{$track->id}.audio"
                ] =
                    "Track {$number} WAV file is required.";
            }
        }
    }

    private function validateDistribution(
        Release $release,
        array &$errors
    ): void {
        $stores = is_array($release->stores)
            ? array_values(
                array_unique(
                    array_map(
                        'intval',
                        $release->stores
                    )
                )
            )
            : [];

        /*
         * All active stores are the system default.
         * A release created before store selection may use
         * all active stores automatically.
         */
        if (empty($stores)) {
            $stores = DistributionStore::query()
                ->where('is_active', true)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        if (empty($stores)) {
            $errors['distribution.stores'] =
                'No active distribution stores are available.';

            return;
        }

        $validStoreCount =
            DistributionStore::query()
                ->where('is_active', true)
                ->whereIn('id', $stores)
                ->count();

        if ($validStoreCount !== count($stores)) {
            $errors['distribution.stores'] =
                'One or more stores are inactive or invalid.';
        }

        $territories = is_array(
            $release->territories
        )
            ? $release->territories
            : [];

        /*
         * Worldwide is the default territory.
         */
        $worldwide = $release->worldwide === null
            ? true
            : (bool) $release->worldwide;

        if (!$worldwide && empty($territories)) {
            $errors['distribution.territories'] =
                'Enable Worldwide or select at least one territory.';
        }
    }

    private function hasErrorsWithPrefix(
        array $errors,
        string $prefix
    ): bool {
        foreach (array_keys($errors) as $key) {
            if (str_starts_with($key, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
