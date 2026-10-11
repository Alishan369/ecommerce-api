<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Admin dashboard and order lists filter/sort by date.
            $table->index('placed_at');
            $table->index(['user_id', 'placed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // MySQL dropped the user_id foreign key's own index once the composite one could serve it,
            // so the key needs an index again before the composite can go (error 1553 otherwise).
            $table->index('user_id');
            $table->dropIndex(['placed_at']);
            $table->dropIndex(['user_id', 'placed_at']);
        });
    }
};
