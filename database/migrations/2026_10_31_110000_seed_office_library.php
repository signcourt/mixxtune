<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
 * W127 MIXXTUNE_OFFICE: a starter library of music-company job roles and ready-made task templates.
 * Only runs when the library is empty, so it never doubles up. The owner can edit, add or remove freely.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('office_functional_roles')->exists()) {
            return;
        }

        $library = [
            ['A&R / Artist Management', [
                ['Scout a new artist', ['Listen to the submission', 'Check socials and streams', 'Write a short note']],
                ['Artist call', ['Fix the time', 'Talk through the plan', 'Note next steps']],
                ['Prepare the release calendar', ['List upcoming releases', 'Pick dates', 'Share with the team']],
                ['Contract coordination', ['Collect details', 'Send for signature', 'File the signed copy']],
            ]],
            ['Music Producer', [
                ['Beat / arrangement demo', ['Make the demo', 'Share for feedback', 'Save the session']],
                ['Final session export', ['Bounce the stems', 'Check levels', 'Upload the files']],
                ['Producer review', ['Listen to the mix', 'Note changes', 'Send back']],
                ['Track stems delivery', ['Name the files right', 'Export each stem', 'Upload']],
            ]],
            ['Graphic / Poster Designer', [
                ['Cover art (3000×3000)', ['Draft the cover', 'Check the title spelling', 'Export 3000×3000 JPG']],
                ['Poster', ['Make the poster', 'Add the credits', 'Export print + web sizes']],
                ['Thumbnails', ['Make 3 options', 'Add the text', 'Export 1280×720']],
                ['Revisions', ['Read the feedback', 'Apply changes', 'Re-export']],
            ]],
            ['Video Editor', [
                ['Rough cut', ['Lay the footage', 'Rough timing', 'Share for feedback']],
                ['Final export', ['Apply notes', 'Export 1080p/4K', 'Upload']],
                ['Reels / short-form', ['Pick the moments', 'Add subtitles', 'Export vertical']],
                ['Project archive', ['Collect the files', 'Back up the project', 'Note the location']],
            ]],
            ['Distribution Executive', [
                ['Metadata check', ['Title / artists / credits', 'ISRC / UPC present', 'Explicit flag right']],
                ['Audio & artwork check', ['WAV present', 'Artwork 3000×3000', 'No clipping']],
                ['Store submission', ['Pick the stores', 'Submit', 'Note the submission time']],
                ['Delivery follow-up', ['Check each store', 'Note live URLs', 'Flag any issue']],
            ]],
            ['YouTube / CMS Executive', [
                ['Upload a video', ['Upload the file', 'Title, description, tags', 'Set visibility']],
                ['Rights / Content ID', ['Check claims', 'Resolve disputes', 'Note the status']],
                ['Channel report', ['Pull the numbers', 'Note growth', 'Share with the owner']],
                ['OAC / CMS task', ['Do the task', 'Add proof', 'Submit']],
            ]],
            ['Social Media Manager', [
                ['Content calendar', ['Plan the week', 'Write the copy', 'Share for approval']],
                ['Post', ['Schedule / post', 'Add the link', 'Note the URL']],
                ['Engagement report', ['Pull reach / likes', 'Note best post', 'Share']],
                ['Reply to comments', ['Read comments', 'Reply', 'Flag anything odd']],
            ]],
            ['Accounts / Finance', [
                ['Invoice follow-up', ['List pending', 'Follow up', 'Note replies']],
                ['Ledger reconciliation', ['Match entries', 'Flag gaps', 'Note done']],
                ['TDS / GST support', ['Collect figures', 'Share with CA', 'File the reply']],
                ['Payment proof', ['Download proof', 'Attach', 'Submit']],
            ]],
        ];

        foreach ($library as [$roleName, $templates]) {
            $roleId = DB::table('office_functional_roles')->insertGetId([
                'public_id' => (string) Str::ulid(),
                'name' => $roleName,
                'slug' => Str::slug($roleName).'-'.Str::lower(Str::random(4)),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            foreach ($templates as [$title, $checklist]) {
                DB::table('office_task_templates')->insert([
                    'public_id' => (string) Str::ulid(),
                    'functional_role_id' => $roleId,
                    'title' => $title,
                    'priority' => 'normal',
                    'proof_type' => 'link',
                    'checklist' => json_encode($checklist),
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        /* starter data only; the owner's own edits stay. Nothing removed automatically. */
    }
};
