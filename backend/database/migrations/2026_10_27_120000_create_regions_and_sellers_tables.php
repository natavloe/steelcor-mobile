<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Группа A: регионы, юрлица-продавцы, версии реквизитов, пункты самовывоза.
 * См. docs/02-arhitektura/01-baza-dannyh.md, раздел A.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        Schema::create('seller_entities', function (Blueprint $table) {
            $table->id();
            $table->string('name_short');
            $table->string('inn', 12)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('seller_requisites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_entity_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('name_full');
            $table->string('name_short');
            $table->string('inn', 12);
            $table->string('kpp', 9)->nullable();
            $table->string('ogrn', 15)->nullable();
            $table->string('okpo', 14)->nullable();
            $table->text('legal_address');
            $table->text('postal_address')->nullable();
            $table->string('bank_name');
            $table->string('bik', 9);
            $table->string('account', 20);
            $table->string('corr_account', 20)->nullable();
            $table->string('signer_name');
            $table->string('signer_position');
            $table->string('signer_basis');
            $table->decimal('vat_rate_default', 5, 2)->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->date('valid_from');
            $table->string('status', 20)->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('staff_users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('staff_users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['seller_entity_id', 'version']);
        });

        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 16)->unique();
            $table->string('name');
            $table->string('timezone', 64)->default('Europe/Moscow');
            $table->foreignId('seller_entity_id')->nullable()->constrained()->nullOnDelete();
            $table->text('ship_from_address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_active')->default(false);
            $table->integer('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('pickup_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('address');
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('work_hours')->nullable();
            $table->text('instructions')->nullable();
            $table->boolean('is_public')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('staff_region', function (Blueprint $table) {
            $table->foreignId('staff_user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('region_id')->constrained()->cascadeOnDelete();
            $table->primary(['staff_user_id', 'region_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('default_region_id')->references('id')->on('regions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['default_region_id']);
        });
        Schema::dropIfExists('staff_region');
        Schema::dropIfExists('pickup_points');
        Schema::dropIfExists('regions');
        Schema::dropIfExists('seller_requisites');
        Schema::dropIfExists('seller_entities');
    }
};
