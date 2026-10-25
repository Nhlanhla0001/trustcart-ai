<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('brand');
            $table->string('category')->index();
            $table->string('tagline');
            $table->text('description');
            $table->unsignedInteger('price_cents');
            $table->char('currency', 3)->default('USD');
            $table->unsignedInteger('stock')->default(0);
            $table->string('merchant_name');
            $table->boolean('merchant_verified')->default(false);
            $table->string('illustration');
            $table->json('specs');
            $table->json('data_gaps')->nullable();
            $table->string('status')->default('active')->index();
            $table->timestamp('last_synced_at');
            $table->timestamps();
        });

        Schema::create('shopping_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->text('raw_intent');
            $table->json('requirements');
            $table->string('status')->default('evaluated');
            $table->string('ai_provider')->nullable();
            $table->string('ai_model')->nullable();
            $table->timestamps();
        });

        Schema::create('recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shopping_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('verdict');
            $table->unsignedTinyInteger('score')->default(0);
            $table->text('narrative')->nullable();
            $table->string('narrative_source')->default('fallback');
            $table->json('checks');
            $table->json('candidates');
            $table->json('engine')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_approvals', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('shopping_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recommendation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedInteger('unit_price_cents');
            $table->unsignedInteger('total_cents');
            $table->char('currency', 3);
            $table->string('status')->default('pending')->index();
            $table->timestamp('authorized_at')->nullable();
            $table->timestamp('expires_at');
            $table->string('invalidated_reason')->nullable();
            $table->string('authorized_ip')->nullable();
            $table->text('authorized_user_agent')->nullable();
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('purchase_approval_id')->constrained()->cascadeOnDelete();
            $table->string('provider')->default('paypal');
            $table->string('environment')->default('sandbox');
            $table->string('paypal_order_id')->nullable()->unique();
            $table->string('status')->default('created')->index();
            $table->unsignedInteger('amount_cents');
            $table->char('currency', 3);
            $table->string('capture_id')->nullable();
            $table->string('payer_id')->nullable();
            $table->string('failure_reason')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('provider_event_id')->nullable();
            $table->string('status')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shopping_session_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('data')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('purchase_approvals');
        Schema::dropIfExists('recommendations');
        Schema::dropIfExists('shopping_sessions');
        Schema::dropIfExists('products');
    }
};
