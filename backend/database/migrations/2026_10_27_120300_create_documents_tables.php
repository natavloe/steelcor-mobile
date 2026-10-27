<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Группа H: шаблоны, договоры, документы, подписи, ЭДО, письма.
 * Ссылки documents → orders добавляются после создания заказов.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_templates', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 48);
            $table->foreignId('seller_entity_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('version');
            $table->string('file_path');
            $table->jsonb('placeholders')->nullable();
            $table->string('status', 16)->default('draft');
            $table->foreignId('uploaded_by')->nullable()->constrained('staff_users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('staff_users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();
        });

        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->string('number')->nullable();
            $table->string('type', 16);
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('seller_entity_id')->constrained()->restrictOnDelete();
            $table->date('signed_date')->nullable();
            $table->date('valid_until')->nullable();
            $table->string('status', 32)->default('generating');
            $table->unsignedBigInteger('current_document_id')->nullable();
            $table->timestamps();
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->foreign('framework_contract_id')->references('id')->on('contracts')->nullOnDelete();
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 48);
            $table->string('number')->nullable();
            $table->date('doc_date')->nullable();
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->foreignId('contract_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('document_templates')->nullOnDelete();
            $table->jsonb('data_snapshot')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_sha256', 64)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('supersedes_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->string('status', 32)->default('generating');
            $table->string('visibility', 16)->default('client');
            $table->string('signing_route', 16)->default('none');
            $table->foreignId('created_by')->nullable()->constrained('staff_users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->foreign('current_document_id')->references('id')->on('documents')->nullOnDelete();
        });

        Schema::create('signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->string('side', 8);
            $table->string('method', 24);
            $table->timestamp('signed_at');
            $table->string('signer_name')->nullable();
            $table->string('signer_position')->nullable();
            $table->jsonb('certificate_info')->nullable();
            $table->string('poa_ref')->nullable();
            $table->string('signature_file')->nullable();
            $table->string('evidence_file')->nullable();
            $table->string('recorded_by_type', 8);
            $table->foreignId('recorded_by')->nullable()->constrained('staff_users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('edo_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->string('operator', 32);
            $table->string('external_message_id')->nullable();
            $table->string('external_entity_id')->nullable();
            $table->string('status', 32)->default('queued');
            $table->text('last_error')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();
        });

        Schema::create('emails', function (Blueprint $table) {
            $table->id();
            $table->string('to');
            $table->string('cc')->nullable();
            $table->string('subject');
            $table->string('template')->nullable();
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->string('purpose', 32)->default('info');
            $table->string('provider_message_id')->nullable();
            $table->string('status', 16)->default('queued');
            $table->timestamp('sent_at')->nullable();
            $table->text('error')->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('staff_users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('email_documents', function (Blueprint $table) {
            $table->foreignId('email_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->primary(['email_id', 'document_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_documents');
        Schema::dropIfExists('emails');
        Schema::dropIfExists('edo_submissions');
        Schema::dropIfExists('signatures');
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropForeign(['current_document_id']);
        });
        Schema::dropIfExists('documents');
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropForeign(['framework_contract_id']);
        });
        Schema::dropIfExists('contracts');
        Schema::dropIfExists('document_templates');
    }
};
