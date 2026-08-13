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
        Schema::create('tr_details', function (Blueprint $table) {
            $table->id();
            // Relasi ke Header
            $table->foreignId('tr_header_id')->constrained('tr_headers')->cascadeOnDelete();

            // Relasi ke Barang
            $table->foreignId('stock_id')->constrained('tb_stocks')->restrictOnDelete();

            // Quantity
            // Positif untuk masuk (Beli, Retur Jual), Negatif untuk keluar (Jual, Retur Beli)
            // Atau gunakan kolom terpisah 'qty' dan 'direction'.
            // Di sini kita gunakan qty absolut dan tentukan arah berdasarkan tipe header.
            $table->decimal('qty', 10, 2)->default(0);

            // Harga Satuan Saat Transaksi
            // Untuk Pembelian: Harga Beli dari Supplier
            // Untuk Penjualan: Harga Jual ke Customer
            $table->decimal('unit_price', 15, 2)->default(0);

            /**
             * HPP Saat Transaksi (Critical for Profit Calculation)
             * Ini adalah snapshot harga pokok barang pada detik transaksi terjadi.
             * Jangan pernah mengubah nilai ini setelah transaksi diposting.
             */
            $table->decimal('hpp_at_transaction', 15, 2)->default(0);

            // Subtotal (qty * unit_price)
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tr_details');
    }
};
