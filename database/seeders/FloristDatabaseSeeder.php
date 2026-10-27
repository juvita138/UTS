<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Purchase;
use App\Models\Sale;
use Illuminate\Support\Facades\Hash;

class FloristDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed Demo Users
        $owner = User::create([
            'name' => 'Eleanor Vance (Owner)',
            'email' => 'owner@luminaflora.com',
            'password' => Hash::make('owner123'),
            'role' => 'owner',
        ]);

        $staff = User::create([
            'name' => 'Rian Pratama (Staff)',
            'email' => 'staff@luminaflora.com',
            'password' => Hash::make('staff123'),
            'role' => 'staff',
        ]);

        $customer = User::create([
            'name' => 'Siti Maharani',
            'email' => 'siti@gmail.com',
            'password' => Hash::make('cust123'),
            'role' => 'customer',
        ]);

        // 2. Seed Categories
        $catRose = Category::create(['name' => 'Mawar Eksklusif']);
        $catTulip = Category::create(['name' => 'Tulip & Lily Import']);
        $catOrchid = Category::create(['name' => 'Anggrek Tropis']);
        $catDry = Category::create(['name' => 'Bunga Kering & Aesthetic']);

        // 3. Seed Suppliers
        $supNusantara = Supplier::create([
            'name' => 'PT Nusantara Flora Agro',
            'phone' => '0812-3456-7890',
            'email' => 'supply@nusantaraflora.co.id',
        ]);

        $supHolland = Supplier::create([
            'name' => 'Holland Botanical Import Ltd',
            'phone' => '0819-8765-4321',
            'email' => 'orders@hollandbotanical.nl',
        ]);

        // 4. Seed Products
        $p1 = Product::create([
            'category_id' => $catRose->id,
            'sku' => 'FLR-ROSE-PUR',
            'name' => 'Mawar Ungu Velvet Mystique',
            'purchase_price' => 35000,
            'selling_price' => 75000,
            'stock' => 20,
            'minimum_stock' => 5,
        ]);

        $p2 = Product::create([
            'category_id' => $catRose->id,
            'sku' => 'FLR-ROSE-RED',
            'name' => 'Ecuador Crimson Rose Bouquet',
            'purchase_price' => 60000,
            'selling_price' => 125000,
            'stock' => 12,
            'minimum_stock' => 4,
        ]);

        $p3 = Product::create([
            'category_id' => $catTulip->id,
            'sku' => 'FLR-TULIP-WHT',
            'name' => 'Royal White Dutch Tulip',
            'purchase_price' => 45000,
            'selling_price' => 95000,
            'stock' => 3, // Low stock demo
            'minimum_stock' => 5,
        ]);

        $p4 = Product::create([
            'category_id' => $catTulip->id,
            'sku' => 'FLR-LILY-CAS',
            'name' => 'Casablanca Fragrant Lily',
            'purchase_price' => 50000,
            'selling_price' => 110000,
            'stock' => 10,
            'minimum_stock' => 3,
        ]);

        $p5 = Product::create([
            'category_id' => $catOrchid->id,
            'sku' => 'FLR-ORCH-PHAL',
            'name' => 'Anggrek Bulan Ungu Imperial',
            'purchase_price' => 120000,
            'selling_price' => 240000,
            'stock' => 0, // Out of stock demo
            'minimum_stock' => 2,
        ]);

        $p6 = Product::create([
            'category_id' => $catDry->id,
            'sku' => 'FLR-DRY-LAV',
            'name' => 'French Dried Lavender Bloom',
            'purchase_price' => 28000,
            'selling_price' => 65000,
            'stock' => 25,
            'minimum_stock' => 5,
        ]);

        // 5. Seed Purchase History
        $po = Purchase::create([
            'supplier_id' => $supNusantara->id,
            'user_id' => $staff->id,
            'purchase_number' => 'PO-20261025-0001',
            'total' => 1000000,
            'status' => 'received',
            'purchased_at' => now()->subDays(2),
        ]);
        $po->items()->create([
            'product_id' => $p1->id,
            'quantity' => 20,
            'unit_cost' => 35000,
            'subtotal' => 700000,
        ]);
        $po->items()->create([
            'product_id' => $p2->id,
            'quantity' => 5,
            'unit_cost' => 60000,
            'subtotal' => 300000,
        ]);

        // 6. Seed Sale History
        $sale = Sale::create([
            'user_id' => $customer->id,
            'sale_number' => 'INV-20261026-0001',
            'total' => 200000,
            'status' => 'completed',
            'sold_at' => now()->subDay(),
        ]);
        $sale->items()->create([
            'product_id' => $p1->id,
            'quantity' => 1,
            'unit_price' => 75000,
            'subtotal' => 75000,
        ]);
        $sale->items()->create([
            'product_id' => $p2->id,
            'quantity' => 1,
            'unit_price' => 125000,
            'subtotal' => 125000,
        ]);
    }
}
