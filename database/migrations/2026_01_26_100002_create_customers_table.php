<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('customer_number')->unique();
            $table->string('company_name')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('tax_id')->nullable(); // ABN for Australian businesses
            $table->string('type')->default('individual'); // individual, company
            $table->json('billing_address')->nullable();
            $table->json('shipping_address')->nullable();
            $table->integer('payment_terms')->default(30); // days
            $table->decimal('credit_limit', 15, 2)->nullable();
            $table->string('currency', 3)->default('AUD');
            $table->string('status')->default('active'); // active, inactive, blocked
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'assigned_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
