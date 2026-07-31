<?php

namespace Database\Seeders;

use App\Models\DistributionStore;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DistributionStoreSeeder extends Seeder
{
    public function run(): void
    {
        $stores = [
            'Spotify',
            'Apple Music',
            'YouTube Music',
            'Amazon Music',
            'JioSaavn',
            'Gaana',
            'Hungama',
            'Wynk Music',
            'Deezer',
            'TIDAL',
            'TikTok',
            'Instagram / Facebook',
            'Snapchat',
            'Boomplay',
            'Audiomack',
            'Anghami',
            'Qobuz',
            'Pandora',
            'KKBOX',
            'NetEase',
        ];

        foreach ($stores as $index => $name) {
            DistributionStore::updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'is_active' => true,
                    'default_selected' => true,
                    'sort_order' => $index + 1,
                ]
            );
        }
    }
}
