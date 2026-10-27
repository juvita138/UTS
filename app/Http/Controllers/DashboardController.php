<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Sale;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Dashboard eksekutif: seluruh angka dihitung langsung dari query agregat database,
     * tidak boleh ditulis statis (Aturan Slide 18).
     */
    public function index()
    {
        $totalProducts = Product::count();
        $totalStock = Product::sum('stock');

        // Penjualan hari ini
        $todaySales = Sale::where('status', 'completed')
            ->whereDate('sold_at', today())
            ->sum('total');

        // Total penjualan sukses keseluruhan
        $totalRevenue = Sale::where('status', 'completed')->sum('total');
        $completedTransactionsCount = Sale::where('status', 'completed')->count();
        $cancelledTransactionsCount = Sale::where('status', 'cancelled')->count();

        // Daftar produk dengan stok menipis (di bawah ambang minimum)
        $lowStockProducts = Product::whereColumn('stock', '<=', 'minimum_stock')
            ->with('category')
            ->orderBy('stock', 'asc')
            ->get();

        // Data tren penjualan 7 hari terakhir untuk grafik dinamis
        $weeklySales = Sale::where('status', 'completed')
            ->where('sold_at', '>=', now()->subDays(7))
            ->selectRaw('DATE(sold_at) as date, SUM(total) as revenue')
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();

        // Stok per kategori bunga untuk grafik donat
        $categoryDistribution = Category::withCount(['products as total_stock' => function ($q) {
            $q->select(DB::raw('COALESCE(SUM(stock), 0)'));
        }])->get();

        return view('dashboard.index', compact(
            'totalProducts',
            'totalStock',
            'todaySales',
            'totalRevenue',
            'completedTransactionsCount',
            'cancelledTransactionsCount',
            'lowStockProducts',
            'weeklySales',
            'categoryDistribution'
        ));
    }
}
