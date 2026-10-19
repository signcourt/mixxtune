<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Services\V2\LabelRateHistoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/* MIXXTUNE_LABEL_RATE_HISTORY_V1: read-only, super admin only. */
class LabelRateHistoryController extends Controller
{
    public function show(Request $request, LabelRateHistoryService $history): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && $user->role === 'super_admin', 403);

        $labelId = $request->integer('label_id') ?: null;
        $label = $labelId
            ? DB::table('labels')->where('id', $labelId)->first(['id', 'name', 'revenue_share_percentage'])
            : null;

        abort_if($labelId && !$label, 404, 'Label not found.');

        return response()->json([
            'label' => $label ? [
                'id' => (int) $label->id,
                'name' => $label->name,
                'current' => $label->revenue_share_percentage !== null ? (float) $label->revenue_share_percentage : null,
                'pending_import_ids' => $history->pendingImportIds((int) $label->id),
            ] : null,
            'changes' => $history->history($labelId, $labelId ? 200 : 300),
            'rates_used' => $label ? $history->ratesUsed((int) $label->id) : [],
        ]);
    }
}
