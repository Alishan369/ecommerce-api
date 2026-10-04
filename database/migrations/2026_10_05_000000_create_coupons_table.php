<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();              // stored upper-case
            $table->string('description')->nullable();         // shown to shoppers
            $table->string('type', 20);                         // percent | fixed | free_shipping
            $table->decimal('value', 10, 2)->default(0);        // % or ₹ (unused for free_shipping)
            $table->decimal('max_discount', 10, 2)->nullable(); // cap for percent coupons
            $table->decimal('min_order_amount', 10, 2)->default(0);
            // DATETIME (not TIMESTAMP) so MySQL never auto-updates them.
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->unsignedInteger('usage_limit')->nullable();    // total uses; null = unlimited
            $table->unsignedInteger('per_user_limit')->nullable(); // uses per customer; null = unlimited
            $table->boolean('first_order_only')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_public')->default(true);           // listed under "Available offers"
            $table->timestamps();

            $table->index(['is_active', 'is_public']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
