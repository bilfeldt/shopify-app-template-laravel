<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->string('shop_domain')->unique(); // e.g., "mystore.myshopify.com"
            $table->string('name')->nullable(); // Shop name eg 'Awesome Hat Store'
            $table->string('email')->nullable(); // Shop owner email eg 'owner@hatstore.com'
            $table->timestamps();

            $table->index('shop_domain');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shops');
    }
};
