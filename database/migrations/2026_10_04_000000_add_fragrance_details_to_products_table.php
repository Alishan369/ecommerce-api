<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('short_description')->nullable()->after('description');
            $table->string('gender', 10)->nullable()->after('short_description'); // men | women | unisex
            $table->string('concentration', 20)->nullable()->after('gender');     // EDP, EDT, Attar, Body Mist…
            $table->unsignedSmallInteger('size_ml')->nullable()->after('concentration');
            $table->string('fragrance_family', 30)->nullable()->after('size_ml');  // woody, floral, oud…
            // {"top": [...], "heart": [...], "base": [...]} — text (not json) so LIKE search works case-insensitively.
            $table->text('notes')->nullable()->after('fragrance_family');

            $table->index(['gender', 'is_active']);
            $table->index(['fragrance_family', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['gender', 'is_active']);
            $table->dropIndex(['fragrance_family', 'is_active']);
            $table->dropColumn(['short_description', 'gender', 'concentration', 'size_ml', 'fragrance_family', 'notes']);
        });
    }
};
