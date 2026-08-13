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
        Schema::create('tr_headers', function (Blueprint $table) {
            $table->id();
            // Nomor Transaksi (unik per tipe atau global)
            $table->string('trs_number', 10)->unique();

            // Tanggal Transaksi
            $table->date('trs_date');

            /**
             * Tipe Transaksi:
             * 'PURCHASE'      : Pembelian Barang
             * 'PURCHASE_RET'  : Retur Pembelian (Barang kembali ke supplier)
             * 'SALE'          : Penjualan Barang
             * 'SALE_RET'      : Retur Penjualan (Barang kembali dari customer)
             * 'OPNAME'        : Stock Opname (Penyesuaian stok fisik vs sistem)
             */
            $table->enum('trr_type', ['PURCHASE', 'PURCHASE_RET', 'SALE', 'SALE_RET', 'OPNAME']);

            // Relasi ke Customer (Hanya untuk SALE & SALE_RET)
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();

            // Relasi ke Supplier (Hanya untuk PURCHASE & PURCHASE_RET)
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();

            // Total nilai transaksi (opsional, bisa dihitung dari detail)
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->unsignedTinyInteger('trs_type')->default(0)->comment('0:tunai, 1:kredit');
            $table->decimal('paid_amount', 15, 2)->default(0); // Total yang sudah dibayar
            $table->decimal('remaining_amount', 15, 2)->default(0); // Sisa tagihan
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tr_headers');
    }
};
