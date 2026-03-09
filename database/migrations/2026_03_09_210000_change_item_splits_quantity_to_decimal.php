<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('item_splits', function (Blueprint $table) {
            $table->decimal('quantity', 12, 4)->default(1)->change();
        });
    }

    public function down(): void
    {
        Schema::table('item_splits', function (Blueprint $table) {
            $table->integer('quantity')->default(1)->change();
        });
    }
};
