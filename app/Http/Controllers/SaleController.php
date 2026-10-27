<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SaleController extends Controller
{
    /**
     * Menampilkan daftar riwayat transaksi penjualan.
     * Menggunakan pagination dan eager loading untuk menghindari masalah N+1 queries.
     */
    public function index(Request $request)
    {
        $query = Sale::with(['user', 'items.product']);

        // Search & Filter
        if ($request->filled('search')) {
            $query->where('sale_number', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $sales = $query->latest('sold_at')->paginate(10)->withQueryString();

        return view('sales.index', compact('sales'));
    }

    /**
     * Menampilkan form transaksi kasir POS multi-item.
     */
    public function create()
    {
        $products = Product::where('stock', '>', 0)->orderBy('name')->get();
        return view('sales.create', compact('products'));
    }

    /**
     * Menyimpan transaksi penjualan multi-item secara ATOMIK.
     * Business rules:
     * 1. Menggunakan DB::transaction
     * 2. Lock baris produk dengan lockForUpdate() agar tidak terjadi race condition / overselling
     * 3. Menghitung harga dari database, bukan dari browser
     * 4. Stok berkurang hanya setelah transaksi berhasil
     */
    public function store(Request $request)
    {
        $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'payment_method' => ['nullable', 'string', 'in:Tunai,QRIS,Transfer Bank,Kartu Debit/Kredit'],
            'customer_name' => ['nullable', 'string', 'max:150'],
        ], [
            'items.required' => 'Keranjang transaksi tidak boleh kosong.',
            'items.*.quantity.min' => 'Jumlah barang yang dibeli minimal 1 tangkai.',
        ]);

        return DB::transaction(function () use ($request) {
            // Buat header transaksi penjualan
            $sale = Sale::create([
                'user_id' => Auth::id(),
                'sale_number' => $this->generateSaleNumber(),
                'customer_name' => $request->customer_name ?? 'Pelanggan',
                'payment_method' => $request->payment_method ?? 'Tunai',
                'total' => 0,
                'status' => 'completed',
                'sold_at' => now(),
            ]);

            $grandTotal = 0;

            // Proses setiap item secara aman
            foreach ($request->items as $itemData) {
                // Kunci baris data produk untuk mencegah overselling dari request lain
                $product = Product::lockForUpdate()->findOrFail($itemData['product_id']);
                $qty = (int) $itemData['quantity'];

                // Validasi kecukupan stok secara server-side
                if ($product->stock < $qty) {
                    throw ValidationException::withMessages([
                        'items' => "Stok produk '{$product->name}' tidak mencukupi! Tersedia: {$product->stock}, diminta: {$qty}."
                    ]);
                }

                // Ambil harga asli dari database (mencegah manipulasi harga dari client)
                $unitPrice = $product->selling_price;
                $subtotal = $unitPrice * $qty;

                // Simpan item detail penjualan
                $sale->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                ]);

                // Kurangi stok produk secara atomik
                $product->decrement('stock', $qty);

                $grandTotal += $subtotal;
            }

            // Perbarui total header transaksi
            $sale->update(['total' => $grandTotal]);

            return redirect()->route('sales.show', $sale->id)
                ->with('success', "Transaksi {$sale->sale_number} berhasil diproses. Stok bunga telah diperbarui.");
        });
    }

    /**
     * Menampilkan detail item transaksi.
     */
    public function show($id)
    {
        $sale = Sale::with(['user', 'items.product'])->findOrFail($id);
        return view('sales.show', compact('sale'));
    }

    /**
     * ALUR PEMBATALAN TRANSAKSI: KHUSUS HAK AKSES OWNER!
     * Saat dibatalkan:
     * 1. Hak akses diverifikasi di server (Gate/Policy)
     * 2. Status berubah menjadi 'cancelled'
     * 3. Seluruh stok item dikembalikan (RESTOCK) ke display secara otomatis
     */
    public function cancel(Request $request, $id)
    {
        // Proteksi Server-Side: Hanya Owner yang berhak membatalkan transaksi!
        if (Auth::user()->role !== 'owner') {
            abort(403, 'Akses Ditolak: Hanya Owner yang memiliki kewenangan membatalkan transaksi dan merestore stok bunga.');
        }

        return DB::transaction(function () use ($id, $request) {
            $sale = Sale::with('items.product')->lockForUpdate()->findOrFail($id);

            if ($sale->status === 'cancelled') {
                return back()->with('error', 'Transaksi ini sudah pernah dibatalkan sebelumnya.');
            }

            // Kembalikan kuantitas setiap item ke stok produk (Auto-Restock)
            foreach ($sale->items as $item) {
                if ($item->product) {
                    $item->product->increment('stock', $item->quantity);
                }
            }

            // Perbarui status transaksi
            $sale->update([
                'status' => 'cancelled',
            ]);

            return redirect()->route('sales.index')
                ->with('success', "Transaksi {$sale->sale_number} berhasil dibatalkan oleh Owner. Stok bunga telah dikembalikan ke etalase.");
        });
    }

    /**
     * Helper untuk membuat nomor invoice unik berurutan
     */
    private function generateSaleNumber(): string
    {
        $date = now()->format('Ymd');
        $countToday = Sale::whereDate('sold_at', today())->count() + 1;
        return 'INV-' . $date . '-' . str_pad($countToday, 4, '0', STR_PAD_LEFT);
    }
}
