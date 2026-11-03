<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Applying an extra-minutes request used to set the user's quota expiry to
 * the request's creation date + 30 days, so applying an older request gave
 * minutes that were already expired. Re-derive the expiry the fixed
 * ApplyExtraRequestService would have produced (applied_at + 30 days) for
 * users whose current expiry is earlier than that. Only ever extends.
 */
return new class extends Migration
{
    private const VALIDITY_DAYS = 30;

    public function up(): void
    {
        if (
            ! Schema::hasTable('user_extra_requests')
            || ! Schema::hasColumn('users', 'extra_quota_expires_at')
        ) {
            return;
        }

        $latestApplied = DB::table('user_extra_requests')
            ->where('status', 'applied')
            ->whereNotNull('applied_at')
            ->where('applied_at', '>=', now()->subDays(self::VALIDITY_DAYS))
            ->groupBy('user_id')
            ->select('user_id', DB::raw('MAX(applied_at) as last_applied_at'))
            ->get();

        foreach ($latestApplied as $row) {
            $expiresAt = Carbon::parse($row->last_applied_at)->addDays(self::VALIDITY_DAYS);

            // Null expiry means "never expires" and must stay untouched.
            DB::table('users')
                ->where('id', $row->user_id)
                ->whereNotNull('extra_quota_expires_at')
                ->where('extra_quota_expires_at', '<', $expiresAt)
                ->update(['extra_quota_expires_at' => $expiresAt]);
        }
    }

    public function down(): void
    {
        // Data repair only; the previous (already-expired) dates are not worth restoring.
    }
};
