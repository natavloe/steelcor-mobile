<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Группа B (клиенты) и группа D (ВНУТРЕННЕЕ: поставщики, предложения поставщиков, наценки).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('type', 16);
            $table->string('name_full');
            $table->string('name_short');
            $table->string('inn', 12);
            $table->string('kpp', 9)->nullable();
            $table->string('ogrn', 15)->nullable();
            $table->text('legal_address')->nullable();
            $table->text('postal_address')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bik', 9)->nullable();
            $table->string('account', 20)->nullable();
            $table->string('corr_account', 20)->nullable();
            $table->string('signer_name')->nullable();
            $table->string('signer_position')->nullable();
            $table->string('signer_basis')->nullable();
            $table->string('source', 16)->default('manual');
            $table->jsonb('external_snapshot')->nullable();
            $table->string('verification_status', 16)->default('unverified');
            $table->foreignId('verified_by')->nullable()->constrained('staff_users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('region_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('staff_users')->nullOnDelete();
            $table->string('payment_terms', 16)->default('prepay');
            $table->unsignedSmallInteger('deferral_days')->nullable();
            $table->decimal('credit_limit', 15, 2)->nullable();
            $table->string('edo_operator', 32)->nullable();
            $table->string('edo_box_id')->nullable();
            $table->unsignedBigInteger('framework_contract_id')->nullable();
            $table->text('internal_note')->nullable();
            $table->timestamps();
            $table->unique(['inn', 'kpp']);
        });

        Schema::create('organization_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 16);
            $table->string('status', 16)->default('active');
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'user_id']);
        });

        Schema::create('delivery_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->text('address');
            $table->string('city')->nullable();
            $table->string('fias_id', 64)->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_phone', 20)->nullable();
            $table->text('access_notes')->nullable();
            $table->boolean('is_archived')->default(false);
            $table->timestamps();
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('inn', 12)->nullable();
            $table->string('kpp', 9)->nullable();
            $table->jsonb('contacts')->nullable();
            $table->text('ship_from_address')->nullable();
            $table->text('payment_terms')->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->text('note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('supplier_regions', function (Blueprint $table) {
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('region_id')->constrained()->cascadeOnDelete();
            $table->primary(['supplier_id', 'region_id']);
        });

        Schema::create('supplier_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description')->nullable();
            $table->foreignId('region_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('purchase_price', 15, 2);
            $table->string('unit', 8);
            $table->decimal('available_qty', 15, 4)->nullable();
            $table->unsignedSmallInteger('lead_time_days')->nullable();
            $table->date('valid_until')->nullable();
            $table->string('source', 16)->default('call');
            $table->foreignId('recorded_by')->nullable()->constrained('staff_users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('pricing_rules', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 16);
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->decimal('markup_percent', 7, 2)->nullable();
            $table->decimal('markup_fixed', 15, 2)->nullable();
            $table->decimal('min_margin_percent', 7, 2)->nullable();
            $table->integer('priority')->default(0);
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->timestamps();
            $table->index(['scope', 'scope_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_rules');
        Schema::dropIfExists('supplier_offers');
        Schema::dropIfExists('supplier_regions');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('delivery_addresses');
        Schema::dropIfExists('organization_members');
        Schema::dropIfExists('organizations');
    }
};
