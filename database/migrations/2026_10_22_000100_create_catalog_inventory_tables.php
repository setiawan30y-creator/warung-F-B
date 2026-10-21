<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 2 — Menu, resep & sub-resep, HPP, stok, produksi, pembelian, susut, opname.
 * Blueprint §6, §11.3, §12, §13, §26.
 *
 * Kuantitas disimpan dalam satuan dasar bahan (gram / mililiter / pcs) dengan 4 desimal.
 * Biaya per satuan dasar disimpan dengan 6 desimal agar pembulatan tidak menumpuk.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete(); // null = satuan standar
            $table->string('code', 20);
            $table->string('name', 40);
            $table->string('dimension', 10); // mass | volume | count | pack
            $table->decimal('factor', 18, 6)->nullable(); // terhadap satuan dasar dimensinya; null untuk kemasan
            $table->timestamps();
            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('key', 60);
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
            $table->unique(['tenant_id', 'key']);
        });

        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name');
            $table->string('category', 60)->nullable();
            $table->string('type', 12)->default('purchased'); // purchased | produced
            $table->foreignId('base_unit_id')->constrained('units');
            $table->decimal('min_stock', 18, 4)->default(0);
            $table->decimal('last_cost', 18, 6)->default(0); // harga beli terakhir per satuan dasar
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'type']);
        });

        Schema::create('item_unit_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained();
            $table->decimal('factor', 18, 4); // 1 unit = factor satuan dasar bahan
            $table->timestamps();
            $table->unique(['inventory_item_id', 'unit_id']);
        });

        Schema::create('stock_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 18, 4)->default(0);
            $table->decimal('avg_cost', 18, 6)->default(0); // rata-rata tertimbang per satuan dasar
            $table->timestamps();
            $table->unique(['branch_id', 'inventory_item_id']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->decimal('quantity', 18, 4); // + masuk, - keluar (satuan dasar)
            $table->decimal('unit_cost', 18, 6); // snapshot biaya per satuan dasar
            $table->decimal('total_cost', 18, 2);
            $table->decimal('balance_after', 18, 4);
            $table->nullableMorphs('reference');
            $table->string('reason')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['tenant_id', 'branch_id', 'inventory_item_id', 'id']);
            $table->index(['tenant_id', 'type', 'created_at']);
        });

        Schema::create('menu_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 30);
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('photo_path')->nullable();
            $table->decimal('price', 18, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('branch_menu', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 18, 2)->nullable(); // null = ikut harga dasar
            $table->boolean('is_available')->default(true);
            $table->boolean('is_sold_out')->default(false);
            $table->timestamps();
            $table->unique(['branch_id', 'menu_id']);
        });

        Schema::create('price_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->decimal('old_price', 18, 2)->nullable();
            $table->decimal('new_price', 18, 2)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['menu_id', 'created_at']);
        });

        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10); // menu | sub
            $table->string('name');
            $table->foreignId('menu_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('output_item_id')->nullable()->unique()->constrained('inventory_items')->cascadeOnDelete();
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->timestamps();
        });

        // Versi resep tidak pernah diubah setelah dibuat; perubahan membuat versi baru.
        Schema::create('recipe_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->decimal('yield_quantity', 18, 4); // sub: jumlah hasil (satuan dasar output); menu: porsi
            $table->date('effective_from');
            $table->string('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['recipe_id', 'version']);
        });

        Schema::create('recipe_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained();
            $table->decimal('quantity', 18, 4);
            $table->foreignId('unit_id')->constrained();
            $table->decimal('base_quantity', 18, 4);
        });

        Schema::create('production_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipe_version_id')->constrained();
            $table->foreignId('output_item_id')->constrained('inventory_items');
            $table->string('number', 40);
            $table->string('status', 12)->default('draft'); // draft | posted | cancelled
            $table->decimal('planned_output', 18, 4);
            $table->decimal('expected_output', 18, 4)->nullable(); // dari konsumsi aktual × yield resep
            $table->decimal('actual_output', 18, 4)->nullable();
            $table->decimal('total_cost', 18, 2)->nullable();
            $table->decimal('unit_cost', 18, 6)->nullable();
            $table->string('variance_reason')->nullable();
            $table->timestamp('produced_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'number']);
        });

        Schema::create('production_consumptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained();
            $table->decimal('planned_quantity', 18, 4);
            $table->decimal('actual_quantity', 18, 4);
            $table->decimal('unit_cost', 18, 6)->nullable();
            $table->decimal('total_cost', 18, 2)->nullable();
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->unsignedSmallInteger('lead_time_days')->nullable();
            $table->string('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained();
            $table->string('number', 40);
            $table->string('status', 12)->default('draft'); // draft | ordered | partial | received | cancelled
            $table->date('order_date');
            $table->date('expected_date')->nullable();
            $table->string('notes')->nullable();
            $table->decimal('total', 18, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'number']);
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained();
            $table->decimal('quantity', 18, 4);
            $table->foreignId('unit_id')->constrained();
            $table->decimal('unit_price', 18, 2); // harga per satuan beli
            $table->decimal('base_quantity', 18, 4);
            $table->decimal('received_base_quantity', 18, 4)->default(0);
        });

        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->string('number', 40);
            $table->timestamp('received_at');
            $table->string('notes')->nullable();
            $table->decimal('total', 18, 2)->default(0);
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'number']);
        });

        Schema::create('goods_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->constrained();
            $table->foreignId('inventory_item_id')->constrained();
            $table->decimal('base_quantity', 18, 4);
            $table->decimal('unit_cost', 18, 6);
            $table->decimal('total_cost', 18, 2);
        });

        Schema::create('waste_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained();
            $table->decimal('quantity', 18, 4);
            $table->foreignId('unit_id')->constrained();
            $table->decimal('base_quantity', 18, 4);
            $table->string('reason', 30);
            $table->string('notes')->nullable();
            $table->decimal('estimated_value', 18, 2);
            $table->string('status', 12); // pending | approved | rejected
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'branch_id', 'status']);
        });

        Schema::create('stock_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('number', 40);
            $table->string('status', 12)->default('draft'); // draft | posted
            $table->string('category')->nullable();
            $table->string('notes')->nullable();
            $table->decimal('variance_value', 18, 2)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'number']);
        });

        Schema::create('stock_count_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_count_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained();
            $table->decimal('system_quantity', 18, 4); // saat penghitungan dimulai
            $table->decimal('counted_quantity', 18, 4)->nullable();
            $table->decimal('posted_system_quantity', 18, 4)->nullable(); // saat diposting
            $table->decimal('variance', 18, 4)->nullable();
            $table->decimal('unit_cost', 18, 6)->nullable();
            $table->decimal('variance_value', 18, 2)->nullable();
            $table->unique(['stock_count_id', 'inventory_item_id']);
        });

        $now = now();
        $units = [
            ['g', 'gram', 'mass', 1], ['kg', 'kilogram', 'mass', 1000], ['ons', 'ons', 'mass', 100],
            ['ml', 'mililiter', 'volume', 1], ['l', 'liter', 'volume', 1000],
            ['pcs', 'pcs', 'count', 1], ['butir', 'butir', 'count', 1], ['buah', 'buah', 'count', 1],
            ['porsi', 'porsi', 'count', 1], ['tusuk', 'tusuk', 'count', 1], ['potong', 'potong', 'count', 1],
            ['ekor', 'ekor', 'count', 1], ['lusin', 'lusin', 'count', 12], ['kodi', 'kodi', 'count', 20], ['rim', 'rim', 'count', 500],
            ['ikat', 'ikat', 'pack', null], ['pack', 'pack', 'pack', null], ['sachet', 'sachet', 'pack', null],
            ['renteng', 'renteng', 'pack', null], ['tray', 'tray', 'pack', null], ['krat', 'krat', 'pack', null],
            ['botol', 'botol', 'pack', null], ['kaleng', 'kaleng', 'pack', null], ['cup', 'cup', 'pack', null],
            ['box', 'box', 'pack', null], ['karton', 'karton', 'pack', null], ['karung', 'karung/sak', 'pack', null],
            ['jeriken', 'jeriken', 'pack', null],
        ];
        DB::table('units')->insert(array_map(fn ($u) => [
            'tenant_id' => null, 'code' => $u[0], 'name' => $u[1], 'dimension' => $u[2], 'factor' => $u[3],
            'created_at' => $now, 'updated_at' => $now,
        ], $units));
    }

    public function down(): void
    {
        foreach (['stock_count_lines', 'stock_counts', 'waste_records', 'goods_receipt_items', 'goods_receipts',
            'purchase_order_items', 'purchase_orders', 'suppliers', 'production_consumptions', 'production_batches',
            'recipe_items', 'recipe_versions', 'recipes', 'price_histories', 'branch_menu', 'menus', 'menu_categories',
            'stock_movements', 'stock_balances', 'item_unit_conversions', 'inventory_items', 'document_sequences', 'units'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
