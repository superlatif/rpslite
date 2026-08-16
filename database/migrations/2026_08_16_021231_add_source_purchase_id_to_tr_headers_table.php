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
        Schema::table('tr_headers', function (Blueprint $table) {
            $table->foreignId('source_purchase_id')
                ->nullable()
                ->after('source_sale_id')
                ->constrained('tr_headers')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tr_headers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_purchase_id');
        });
    }
};
