<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained();
            $table->foreignId('contact_id')->nullable()->constrained();
            $table->foreignId('opportunity_id')->nullable()->constrained();
            $table->string('type'); // call, email, meeting, task, note
            $table->string('subject');
            $table->text('description')->nullable();
            $table->datetime('activity_date');
            $table->datetime('due_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['customer_id', 'type']);
            $table->index('activity_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
