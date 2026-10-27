<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Группа E: корзина, заказ, предложения (версии условий), себестоимость, сроки.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('region_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'organization_id', 'region_id']);
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('qty', 15, 4);
            $table->string('unit', 8);
            $table->text('comment')->nullable();
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('region_id')->constrained()->restrictOnDelete();
            $table->foreignId('seller_entity_id')->constrained()->restrictOnDelete();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_staff_id')->nullable()->constrained('staff_users')->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('staff_users')->nullOnDelete();
            $table->string('status', 32)->index();
            $table->string('payment_status', 32)->index();
            $table->string('procurement_status', 32);
            $table->string('delivery_status', 32);
            $table->string('documents_status', 32);
            $table->boolean('is_reagreement')->default(false);
            $table->string('resume_status', 32)->nullable();
            $table->unsignedBigInteger('agreed_offer_id')->nullable();
            $table->string('receive_method', 16);
            $table->foreignId('delivery_address_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('pickup_point_id')->nullable()->constrained()->nullOnDelete();
            $table->date('desired_date_from')->nullable();
            $table->date('desired_date_to')->nullable();
            $table->string('desired_time_slot')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_phone', 20)->nullable();
            $table->text('client_comment')->nullable();
            $table->string('contract_mode', 16)->nullable();
            $table->foreignId('contract_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('deferral_approved_by')->nullable()->constrained('staff_users')->nullOnDelete();
            $table->text('deferral_reason')->nullable();
            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->text('cancel_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description');
            $table->decimal('qty', 15, 4);
            $table->string('unit', 8);
            $table->text('comment')->nullable();
            $table->timestamps();
        });

        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('status', 24)->default('draft');
            $table->text('reason')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->string('payment_terms', 24)->default('prepay_100');
            $table->decimal('prepay_percent', 5, 2)->nullable();
            $table->unsignedSmallInteger('deferral_days')->nullable();
            $table->text('lead_time_text')->nullable();
            $table->string('receive_method', 16)->nullable();
            $table->decimal('goods_net', 15, 2)->default(0);
            $table->decimal('goods_vat', 15, 2)->default(0);
            $table->decimal('goods_total', 15, 2)->default(0);
            $table->decimal('delivery_total', 15, 2)->default(0);
            $table->decimal('services_total', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('accepted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('accepted_ip', 45)->nullable();
            $table->string('accepted_device')->nullable();
            $table->text('client_comment')->nullable();
            $table->foreignId('approved_low_margin_by')->nullable()->constrained('staff_users')->nullOnDelete();
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('staff_users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['order_id', 'version']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreign('agreed_offer_id')->references('id')->on('offers')->nullOnDelete();
        });

        Schema::create('offer_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id')->constrained()->cascadeOnDelete();
            $table->string('line_type', 16)->default('goods');
            $table->foreignId('order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('grade')->nullable();
            $table->string('gost')->nullable();
            $table->string('size_text')->nullable();
            $table->decimal('qty', 15, 4);
            $table->string('unit', 8);
            $table->decimal('qty_base_t', 15, 4)->nullable();
            $table->decimal('unit_price', 15, 2);
            $table->decimal('vat_rate', 5, 2)->default(0);
            $table->decimal('amount_net', 15, 2);
            $table->decimal('amount_vat', 15, 2);
            $table->decimal('amount_total', 15, 2);
            $table->text('lead_time_text')->nullable();
            $table->integer('sort')->default(0);
            $table->timestamps();
        });

        // ВНУТРЕННЕЕ: себестоимость. Клиентский API эту таблицу не читает.
        Schema::create('offer_line_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_line_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_offer_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('purchase_price', 15, 2)->nullable();
            $table->decimal('purchase_amount', 15, 2)->nullable();
            $table->decimal('logistics_cost', 15, 2)->default(0);
            $table->decimal('handling_cost', 15, 2)->default(0);
            $table->decimal('other_costs', 15, 2)->default(0);
            $table->decimal('margin_amount', 15, 2)->nullable();
            $table->decimal('margin_percent', 7, 2)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('order_dates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 32);
            $table->date('date_from');
            $table->date('date_to')->nullable();
            $table->text('reason')->nullable();
            $table->string('set_by_type', 16);
            $table->unsignedBigInteger('set_by_id')->nullable();
            $table->boolean('notify_client')->default(false);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['order_id', 'kind']);
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();
        });
        Schema::table('emails', function (Blueprint $table) {
            $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('emails', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
        });
        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
        });
        Schema::dropIfExists('order_dates');
        Schema::dropIfExists('offer_line_costs');
        Schema::dropIfExists('offer_lines');
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['agreed_offer_id']);
        });
        Schema::dropIfExists('offers');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
    }
};
