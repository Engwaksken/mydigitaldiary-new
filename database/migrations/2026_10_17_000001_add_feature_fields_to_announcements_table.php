<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "New feature" announcements: a type, an optional catalogue feature
 * (config/features.php), a link and button label for the call to action,
 * and which extra channels (push, email) the announcement goes out on.
 * In-app delivery always happens.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->string('type')->default('general')->after('body'); // general | new_feature
            $table->string('feature_key')->nullable()->after('type');
            $table->string('link_url')->nullable()->after('feature_key');
            $table->string('cta_label', 40)->nullable()->after('link_url');
            $table->boolean('send_push')->default(true)->after('cta_label');
            $table->boolean('send_email')->default(false)->after('send_push');
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn(['type', 'feature_key', 'link_url', 'cta_label', 'send_push', 'send_email']);
        });
    }
};
