<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bug fix: on MySQL/MariaDB (explicit_defaults_for_timestamp = OFF, the XAMPP
 * default) the first NOT NULL TIMESTAMP column silently gets
 * `ON UPDATE CURRENT_TIMESTAMP`. That made `orders.placed_at` jump to "now" on
 * every status or payment update — wrong order dates, wrong dashboard figures,
 * and an unpaid-order expiry window that restarted on each update.
 * DATETIME never auto-updates (and has no 2038 limit).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dateTime('placed_at')->change();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('placed_at')->change();
        });
    }
};
