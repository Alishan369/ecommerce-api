<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->string('video')->nullable()->after('image_mobile'); // optional looping clip (mp4/webm)
        });

        // Admin-replaceable images/videos for fixed spots on the storefront (see SiteMedia::SLOTS).
        Schema::create('site_media', function (Blueprint $table) {
            $table->id();
            $table->string('slot', 60)->unique();
            $table->string('image')->nullable();
            $table->string('video')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_media');

        Schema::table('banners', function (Blueprint $table) {
            $table->dropColumn('video');
        });
    }
};
