<?php

namespace App\Services;

use App\Models\Setting;
use Mike42\Escpos\PrintConnectors\MemoryPrintConnector;
use Mike42\Escpos\PrintConnectors\PrintConnector;
use Mike42\Escpos\Printer;
use Throwable;

class ThermalPrinterService
{
    protected string $printerName = 'ThermalRaw';

    protected string $paperSize = '80';

    protected int $paperWidth = 48; // karakter per baris

    /** @var array<int, int> */
    protected array $columnWidths = [24, 6, 18];

    public function __construct(?string $printerName = null)
    {
        $this->printerName = $printerName ?? (string) config('thermal.printer', 'ThermalRaw');

        $this->paperSize = (string) Setting::get('thermal.paper_size', config('thermal.paper_size', '80'));
        $this->paperWidth = (int) config("thermal.paper_widths.{$this->paperSize}", 48);
        $this->columnWidths = array_values((array) config("thermal.column_widths.{$this->paperSize}", [24, 6, 18]));
    }

    public function setPaperSize(string $size): self
    {
        if (in_array($size, ['58', '80'], true)) {
            $this->paperSize = $size;
            $this->paperWidth = (int) config("thermal.paper_widths.{$size}", 48);
            $this->columnWidths = array_values((array) config("thermal.column_widths.{$size}", [24, 6, 18]));
        }

        return $this;
    }

    public function setPaperWidth(int $chars): self
    {
        $this->paperWidth = $chars;

        return $this;
    }

    public function printRaw(string $content): bool
    {
        $escaped = escapeshellarg(str_replace("\x00", '', $content));
        $cmd = "printf %s {$escaped} | lp -d {$this->printerName} -o raw";

        $result = shell_exec($cmd);

        return $result !== false;
    }

    public function printReceipt(array $data, ?PrintConnector $connector = null): bool
    {
        $connector ??= new MemoryPrintConnector;
        $printer = new Printer($connector);

        try {
            $this->buildReceipt($printer, $data);
            $printer->cut(Printer::CUT_PARTIAL);
            $content = $connector->getData();
            $printer->close();

            return $this->printRaw($content);
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    protected function buildReceipt(Printer $printer, array $data): void
    {
        // Center alignment
        $printer->setJustification(Printer::JUSTIFY_CENTER);

        // Header
        $printer->setEmphasis(true);
        $printer->text($this->center($data['shop_name'] ?? 'TOKO'));
        $printer->setEmphasis(false);
        $printer->text($this->center('STRUK PENJUALAN'));
        $printer->text($this->center('No. : '.($data['number'] ?? '-')));
        $printer->text($this->center('Tgl : '.($data['date'] ?? '-')));

        $printer->feed(1);
        $printer->text($this->line());

        // Customer
        $printer->setJustification(Printer::JUSTIFY_LEFT);
        $printer->text("Yth,\n");
        $printer->text(($data['customer_name'] ?? 'Umum')."\n");
        if (! empty($data['customer_address'])) {
            $printer->text($data['customer_address']."\n");
        }
        if (! empty($data['customer_phone'])) {
            $printer->text('Telp: '.$data['customer_phone']."\n");
        }

        $printer->feed(1);
        $printer->text($this->line());

        // Payment info
        $printer->text($this->twoColumn('Jenis Bayar', $data['payment_type'] ?? 'TUNAI'));
        if (($data['is_credit'] ?? false) && (($data['remaining'] ?? 0) > 0)) {
            $printer->text($this->twoColumn('Sisa Piutang', $this->money($data['remaining'])));
        }

        $printer->feed(1);
        $printer->text($this->line());

        [$nameWidth, $qtyWidth, $amountWidth] = $this->columnWidths;

        // Items header
        $printer->setEmphasis(true);
        $printer->text($this->formatRow(['Barang', 'Qty', 'Jumlah'], [$nameWidth, $qtyWidth, $amountWidth]));
        $printer->setEmphasis(false);
        $printer->text($this->line());

        // Items
        foreach ($data['items'] ?? [] as $item) {
            $name = $item['name'] ?? '';
            $qty = (float) ($item['qty'] ?? 0);
            $subtotal = (float) ($item['subtotal'] ?? 0);
            $unitPrice = (float) ($item['unit_price'] ?? 0);

            // Main row
            $printer->text($this->formatRow([
                $this->truncate($name, $nameWidth),
                number_format($qty, 2, '.', ''),
                $this->money($subtotal),
            ], [$nameWidth, $qtyWidth, $amountWidth]));

            // Unit price row
            $printer->text($this->formatRow([
                '  @ '.$this->money($unitPrice),
                '',
                '',
            ], [$nameWidth, $qtyWidth, $amountWidth]));
        }

        $printer->feed(1);
        $printer->text($this->line());

        // Total
        $printer->setEmphasis(true);
        $printer->text($this->formatRow([
            'TOTAL',
            '',
            $this->money($data['total'] ?? 0),
        ], [$nameWidth, $qtyWidth, $amountWidth]));
        $printer->setEmphasis(false);

        $printer->feed(1);
        $printer->text($this->line());

        // Footer
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->text("*** TERIMA KASIH ***\n");
    }

    protected function center(string $text): string
    {
        return str_pad($text, $this->paperWidth, ' ', STR_PAD_BOTH)."\n";
    }

    protected function line(): string
    {
        return str_repeat('-', $this->paperWidth)."\n";
    }

    protected function twoColumn(string $left, string $right): string
    {
        $leftWidth = $this->paperWidth - strlen($right) - 2;

        return $this->truncate($left, $leftWidth).'  '.$right."\n";
    }

    protected function formatRow(array $cols, array $widths): string
    {
        $out = '';
        foreach ($cols as $i => $col) {
            $w = $widths[$i] ?? 0;
            $align = $i === 0 ? STR_PAD_RIGHT : STR_PAD_LEFT;
            $out .= str_pad($col, $w, ' ', $align);
        }

        return $out."\n";
    }

    protected function truncate(string $text, int $max): string
    {
        return mb_strlen($text) > $max ? mb_substr($text, 0, $max - 3).'...' : $text;
    }

    protected function money(float|int|string $value): string
    {
        return 'Rp '.number_format((float) $value, 0, ',', '.');
    }
}
