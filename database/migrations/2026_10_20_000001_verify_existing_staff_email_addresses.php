<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Email verification is now enforced on every authenticated route, the admin
 * area included. Existing staff accounts that were never verified would be
 * locked out of /admin on the next request, so they are marked verified
 * once here. Regular unverified members are deliberately left alone: they
 * are asked to verify on their next visit, which is the point of the change.
 *
 * Accounts created by an admin are already auto-verified in
 * AdminUserController, so this only matters for accounts that predate it.
 */
return new class extends Migration
{
    private const STAFF_ROLES = ['admin', 'super_admin', 'support', 'finance'];

    public function up(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'email_verified_at')) {
            return;
        }

        $hasRole = Schema::hasColumn('users', 'role');
        $hasSystemRole = Schema::hasColumn('users', 'system_role');

        if (! $hasRole && ! $hasSystemRole) {
            return;
        }

        DB::table('users')
            ->whereNull('email_verified_at')
            ->where(function ($query) use ($hasRole, $hasSystemRole): void {
                if ($hasRole) {
                    $query->orWhereIn('role', self::STAFF_ROLES);
                }

                if ($hasSystemRole) {
                    $query->orWhereIn('system_role', self::STAFF_ROLES);
                }
            })
            ->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        // Not reversible: there is no record of which staff were unverified
        // before, and un-verifying staff would lock them out of the admin area.
    }
};
