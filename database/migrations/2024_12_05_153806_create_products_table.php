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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('live_id');
            $table->string('name');
            $table->string('slug');
            $table->string('type')->default('simple');
            $table->string('status')->default('publish');
            $table->boolean('featured')->default(false);
            $table->string('catalog_visibility')->nullable();
            $table->text('description')->nullable();
            $table->text('short_description')->nullable();
            $table->string('sku');
            $table->decimal('price',10,2);
            $table->decimal('regular_price',10,2);
            $table->decimal('sale_price',10,2)->nullable();
            $table->datetime('date_on_sale_from')->nullable();
            $table->datetime('date_on_sale_to')->nullable();
            $table->boolean('on_sale')->default(false);
            $table->boolean('purchasable')->default(true);
            $table->integer('total_sales')->default(0);
            $table->boolean('virtual')->default(false);
            $table->boolean('downloadable')->default(false);
            $table->string('tax_status')->default('taxable');
            $table->boolean('manage_stock')->default(true);
            $table->integer('stock_quantity')->default(0);
            $table->string('weight')->nullable();
            $table->string('length')->nullable();
            $table->string('width')->nullable();
            $table->string('height')->nullable();
            $table->text('price_html')->nullable();
            $table->string('stock_status')->default('instock');
            $table->boolean('is_processed')->default(false);
            $table->boolean('process_status')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
