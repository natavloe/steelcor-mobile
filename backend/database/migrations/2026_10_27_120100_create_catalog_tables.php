<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Группа C: каталог. Остатков склада здесь нет намеренно:
 * компания торгует без собственного запаса.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('image')->nullable();
            $table->integer('sort')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });

        Schema::create('attributes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->string('type', 16);
            $table->string('unit', 16)->nullable();
            $table->jsonb('options')->nullable();
            $table->timestamps();
        });

        Schema::create('category_attributes', function (Blueprint $table) {
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_filterable')->default(true);
            $table->boolean('is_required')->default(false);
            $table->integer('sort')->default(0);
            $table->primary(['category_id', 'attribute_id']);
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->jsonb('attrs')->default('{}');
            $table->string('grade')->nullable()->index();
            $table->string('gost')->nullable()->index();
            $table->string('base_unit', 8);
            $table->decimal('weight_per_m', 12, 4)->nullable();
            $table->decimal('weight_per_pc', 12, 4)->nullable();
            $table->unsignedInteger('std_length_mm')->nullable();
            $table->unsignedInteger('max_length_mm')->nullable();
            $table->boolean('is_oversize')->default(false);
            $table->string('external_ref')->nullable()->unique();
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });

        DB::statement("ALTER TABLE products ADD COLUMN search_text tsvector GENERATED ALWAYS AS (to_tsvector('russian', coalesce(name,'') || ' ' || coalesce(grade,'') || ' ' || coalesce(gost,''))) STORED");
        DB::statement('CREATE INDEX products_search_text_idx ON products USING GIN (search_text)');
        DB::statement('CREATE INDEX products_attrs_idx ON products USING GIN (attrs)');
        DB::statement('CREATE INDEX products_name_trgm_idx ON products USING GIN (name gin_trgm_ops)');

        Schema::create('product_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('unit', 8);
            $table->decimal('to_base_ratio', 18, 8);
            $table->decimal('step', 15, 4)->nullable();
            $table->decimal('min_qty', 15, 4)->nullable();
            $table->unique(['product_id', 'unit']);
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->integer('sort')->default(0);
        });

        Schema::create('region_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('availability', 32);
            $table->decimal('public_price', 15, 2)->nullable();
            $table->string('price_unit', 8)->nullable();
            $table->boolean('vat_included')->default(true);
            $table->date('price_valid_until')->nullable();
            $table->unsignedSmallInteger('lead_time_min_days')->nullable();
            $table->unsignedSmallInteger('lead_time_max_days')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
            $table->unique(['region_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('region_products');
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('product_units');
        Schema::dropIfExists('products');
        Schema::dropIfExists('category_attributes');
        Schema::dropIfExists('attributes');
        Schema::dropIfExists('categories');
    }
};
