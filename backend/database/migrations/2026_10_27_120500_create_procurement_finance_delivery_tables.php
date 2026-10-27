<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Группы F (закупки), G (финансы), I (доставка), J (общее).
 */
return new class extends Migration
{
    public function up(): void
    {
        // F. Закупки (ВНУТРЕННЕЕ)
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('region_id')->constrained()->restrictOnDelete();
            $table->string('status', 24)->default('draft')->index();
            $table->string('supplier_ref')->nullable();
            $table->text('ship_from_address')->nullable();
            $table->decimal('total', 15, 2)->default(0);
            $table->string('supplier_payment_status', 16)->default('not_paid');
            $table->date('expected_ready_date')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('staff_users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description');
            $table->decimal('qty', 15, 4);
            $table->string('unit', 8);
            $table->decimal('purchase_price', 15, 2);
            $table->boolean('price_changed')->default(false);
            $table->timestamps();
        });

        Schema::create('allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_line_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_order_line_id')->constrained()->cascadeOnDelete();
            $table->decimal('qty', 15, 4);
            $table->timestamps();
            $table->unique(['offer_line_id', 'purchase_order_line_id']);
        });

        // G. Финансы
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('offer_id')->constrained()->restrictOnDelete();
            $table->foreignId('seller_entity_id')->constrained()->restrictOnDelete();
            $table->foreignId('seller_requisites_id')->constrained('seller_requisites')->restrictOnDelete();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('kind', 16)->default('prepayment');
            $table->decimal('amount', 15, 2);
            $table->decimal('vat', 15, 2)->default(0);
            $table->date('due_date')->nullable();
            $table->string('status', 16)->default('issued');
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_entity_id')->constrained()->restrictOnDelete();
            $table->string('source', 24);
            $table->string('external_id');
            $table->string('direction', 4);
            $table->date('op_date');
            $table->decimal('amount', 15, 2);
            $table->string('payer_inn', 12)->nullable()->index();
            $table->string('payer_kpp', 9)->nullable();
            $table->string('payer_name')->nullable();
            $table->string('payer_account', 20)->nullable();
            $table->text('purpose')->nullable();
            $table->string('match_status', 24)->default('unmatched')->index();
            $table->jsonb('raw')->nullable();
            $table->foreignId('imported_by')->nullable()->constrained('staff_users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['source', 'external_id']);
        });

        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_transaction_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('method', 8);
            $table->foreignId('matched_by')->nullable()->constrained('staff_users')->nullOnDelete();
            $table->timestamp('matched_at');
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('staff_users')->nullOnDelete();
            $table->text('cancel_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('file_path')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();
        });

        // J. Инциденты (нужны возвратам)
        Schema::create('issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 32);
            $table->string('status', 24)->default('open');
            $table->text('description');
            $table->text('client_decision')->nullable();
            $table->timestamp('client_agreed_at')->nullable();
            $table->string('client_agreed_via', 16)->nullable();
            $table->foreignId('new_offer_id')->nullable()->constrained('offers')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('staff_users')->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('staff_users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('issue_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('staff_users')->nullOnDelete();
            $table->text('action');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('issue_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 15, 2);
            $table->text('basis');
            $table->string('status', 16)->default('requested');
            $table->foreignId('requested_by')->nullable()->constrained('staff_users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('staff_users')->nullOnDelete();
            $table->foreignId('bank_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->jsonb('recipient_account')->nullable();
            $table->timestamps();
        });

        // I. Доставка
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('carrier', 16);
            $table->string('status', 32)->default('not_arranged');
            $table->text('from_address')->nullable();
            $table->foreignId('to_address_id')->nullable()->constrained('delivery_addresses')->nullOnDelete();
            $table->foreignId('pickup_point_id')->nullable()->constrained()->nullOnDelete();
            $table->string('tariff_code')->nullable();
            $table->decimal('quoted_cost', 15, 2)->nullable();
            $table->string('quote_source', 8)->nullable();
            $table->boolean('cost_confirmed')->default(false);
            $table->decimal('confirmed_cost', 15, 2)->nullable();
            $table->string('external_order_id')->nullable();
            $table->string('tracking_number')->nullable();
            $table->string('tracking_url')->nullable();
            $table->decimal('weight_kg', 12, 3)->nullable();
            $table->decimal('volume_m3', 10, 3)->nullable();
            $table->unsignedInteger('max_length_mm')->nullable();
            $table->unsignedInteger('places')->nullable();
            $table->string('vehicle_requirements')->nullable();
            $table->date('planned_date')->nullable();
            $table->string('time_slot')->nullable();
            $table->string('driver_name')->nullable();
            $table->string('driver_phone', 20)->nullable();
            $table->string('vehicle_number', 16)->nullable();
            $table->string('received_by')->nullable();
            $table->timestamps();
        });

        Schema::create('shipment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('offer_line_id')->constrained()->restrictOnDelete();
            $table->decimal('qty', 15, 4);
        });

        Schema::create('shipment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->string('status', 32);
            $table->string('carrier_status_code')->nullable();
            $table->text('carrier_status_text')->nullable();
            $table->string('location')->nullable();
            $table->timestamp('occurred_at');
            $table->string('source', 8);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('carrier_quotes', function (Blueprint $table) {
            $table->id();
            $table->string('carrier', 16);
            $table->jsonb('request');
            $table->string('tariff_code')->nullable();
            $table->decimal('cost', 15, 2)->nullable();
            $table->unsignedSmallInteger('days_min')->nullable();
            $table->unsignedSmallInteger('days_max')->nullable();
            $table->boolean('is_error')->default(false);
            $table->text('error_text')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // J. Общее
        Schema::create('status_history', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 32);
            $table->unsignedBigInteger('entity_id');
            $table->string('field', 32);
            $table->string('from_value', 32)->nullable();
            $table->string('to_value', 32);
            $table->string('actor_type', 16);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['entity_type', 'entity_id']);
        });

        Schema::create('activity_log', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type', 64);
            $table->unsignedBigInteger('subject_id');
            $table->string('event', 16);
            $table->jsonb('changes')->nullable();
            $table->string('actor_type', 16);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('push_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 8);
            $table->string('provider', 8);
            $table->string('token')->unique();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::create('integration_logs', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32);
            $table->string('operation', 64);
            $table->jsonb('request')->nullable();
            $table->jsonb('response')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('correlation_id', 64)->nullable()->index();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('document_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_entity_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 32);
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_number')->default(0);
            $table->unique(['seller_entity_id', 'kind', 'year']);
        });
    }

    public function down(): void
    {
        foreach ([
            'document_counters', 'integration_logs', 'push_tokens', 'notifications', 'activity_log',
            'status_history', 'carrier_quotes', 'shipment_events', 'shipment_items', 'shipments',
            'refunds', 'issue_actions', 'issues', 'payment_notices', 'payment_allocations',
            'bank_transactions', 'invoices', 'allocations', 'purchase_order_lines', 'purchase_orders',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
