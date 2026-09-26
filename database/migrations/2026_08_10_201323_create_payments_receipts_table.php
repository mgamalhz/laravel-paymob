<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Paymob\Laravel\Models\Payment;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Payment::class)
                ->constrained()
                ->cascadeOnDelete();
            $table->string('disk');
            $table->string('filename');
            $table->string('key');
            $table->timestamp('stored_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('payment_id');
            $table->index(['disk', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_receipts');
    }
};
