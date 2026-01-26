<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('expense_number')->unique();
            $table->foreignId('vendor_id')->nullable()->constrained('customers'); // Vendors are also in customers
            $table->foreignId('account_id')->constrained('accounts'); // Expense account
            $table->foreignId('bank_account_id')->nullable()->constrained();
            $table->date('expense_date');
            $table->decimal('amount', 15, 2);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2);
            $table->string('payment_method')->nullable();
            $table->string('reference_number')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_billable')->default(false);
            $table->foreignId('customer_id')->nullable()->constrained(); // If billable to customer
            $table->string('status')->default('recorded'); // recorded, reconciled
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['expense_date', 'account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
