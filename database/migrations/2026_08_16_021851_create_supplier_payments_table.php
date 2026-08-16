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
        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->id();

            // Relasi ke Supplier
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();

            // Relasi ke Transaksi Pembelian (Opsional tapi disarankan untuk tracking per invoice)
            $table->foreignId('tr_header_id')->nullable()->constrained('tr_headers')->nullOnDelete();

            // Tanggal Pembayaran
            $table->date('payment_date');

            // Jumlah yang dibayar
            $table->decimal('amount', 15, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_payments');
    }
};
