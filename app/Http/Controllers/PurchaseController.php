<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class PurchaseController extends Controller
{
    public function index()
    {
        $purchases = Purchase::with(['supplier', 'user', 'items.product'])->latest('purchased_at')->paginate(10);
        return view('purchases.index', compact('purchases'));
    }

    public function create()
    {
        $suppliers = Supplier::all();
        $products = Product::orderBy('name')->get();
        return view('purchases.create', compact('suppliers', 'products'));
    }

    /**
     * Menyimpan purchase / stock-in dan menambah stok secara atomik
     */
    public function store(Request $request)
    {
        $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
        ]);

        return DB::transaction(function () use ($request) {
            $purchase = Purchase::create([
                'supplier_id' => $request->supplier_id,
                'user_id' => Auth::id(),
                'purchase_number' => 'PO-' . now()->format('Ymd') . '-' . str_pad(Purchase::whereDate('purchased_at', today())->count() + 1, 4, '0', STR_PAD_LEFT),
                'total' => 0,
                'status' => 'received',
                'purchased_at' => now(),
            ]);

            $totalCost = 0;

            foreach ($request->items as $item) {
                $product = Product::lockForUpdate()->findOrFail($item['product_id']);
                $qty = (int) $item['quantity'];
                $cost = (float) $item['unit_cost'];
                $subtotal = $cost * $qty;

                $purchase->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $qty,
                    'unit_cost' => $cost,
                    'subtotal' => $subtotal,
                ]);

                // Stok otomatis bertambah saat restock diterima
                $product->increment('stock', $qty);
                $product->update(['purchase_price' => $cost]);

                $totalCost += $subtotal;
            }

            $purchase->update(['total' => $totalCost]);

            return redirect()->route('purchases.index')
                ->with('success', "Stok berhasil ditambahkan melalui PO: {$purchase->purchase_number}.");
        });
    }
}
