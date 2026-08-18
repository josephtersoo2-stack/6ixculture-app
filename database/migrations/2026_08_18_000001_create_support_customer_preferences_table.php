<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for customer support preferences persistence.
     */
    public function up(): void
    {
        if (!Schema::hasTable('support_customer_preferences')) {
            Schema::create('support_customer_preferences', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained('users')->onDelete('cascade');
                $table->string('preferred_language', 10)->default('en')->index();
                $table->string('preferred_voice', 50)->default('nova');
                $table->decimal('preferred_speaking_rate', 3, 2)->default(1.00);
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('support_customer_preferences');
    }
};
