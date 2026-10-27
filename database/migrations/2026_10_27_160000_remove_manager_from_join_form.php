<?php

use App\Services\V2\Website\HomepageContent;
use Illuminate\Database\Migrations\Migration;

/*
 * W126 MIXXTUNE_JOIN_NO_MANAGER: "I am joining as" = Music label / Artist / Other.
 * Only the "Manager" choice is taken out of the published Join form (system_settings website.homepage);
 * nothing else in the homepage text and no join request is changed.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(HomepageContent::class)->removeManagerChoice();
    }

    public function down(): void
    {
        /* nothing to undo automatically: the choice can be added back in Website -> Homepage */
    }
};
