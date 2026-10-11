<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->string('placement', 20)->default('hero');  // hero (home slider) | promo (row of three)
            $table->string('eyebrow', 60)->nullable();          // small line above the title
            $table->string('title', 120);
            $table->string('subtitle')->nullable();
            $table->string('cta_label', 40)->nullable();
            $table->string('link_url')->nullable();             // site path (/shop?…) or full https:// URL
            $table->string('image');                            // desktop / default image
            $table->string('image_mobile')->nullable();         // optional portrait crop for phones
            $table->string('text_tone', 10)->default('light');  // light text on dark photos, or dark on light
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            // DATETIME (not TIMESTAMP) so MySQL never auto-updates them.
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->timestamps();

            $table->index(['placement', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};
