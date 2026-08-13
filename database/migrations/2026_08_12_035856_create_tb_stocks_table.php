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
        Schema::create('tb_stocks', function (Blueprint $table) {
            $table->id();
            $table->string('code', 15)->unique();
            $table->string('descr', 50);
            $table->string('satuan', 15)->default('PCS');
            $table->decimal('harga_beli', 15, 2)->default(0);
            $table->decimal('harga_jual', 15, 2)->default(0);
            $table->decimal('harga_pokok', 15, 2)->default(0);
            $table->integer('stock')->default(0);
            $table->text('gambar')->nullable();
            $table->foreignId('tb_cate_id')->nullable()
                ->constrained('tb_cates')->restrictOnDelete()->cascadeOnUpdate();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tb_stocks');
    }
};
