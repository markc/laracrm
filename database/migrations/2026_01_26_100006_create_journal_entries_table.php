<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->string('entry_number')->unique();
            $table->date('entry_date');
            $table->text('description');
            $table->string('reference_type')->nullable(); // Invoice::class, Payment::class, etc.
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('posted_at')->nullable();
            $table->boolean('is_posted')->default(false);
            $table->boolean('is_locked')->default(false);
            $table->foreignId('reversed_by_id')->nullable()->constrained('journal_entries');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['entry_date', 'is_posted']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
