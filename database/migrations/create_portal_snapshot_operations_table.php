<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_snapshot_operations', function (Blueprint $table) {
            $table->id();
            $table->string('type', 32);
            $table->string('status', 32)->default('pending');
            $table->string('snapshot_name')->nullable();
            $table->string('connection_name')->nullable();
            $table->string('disk')->nullable();
            $table->unsignedBigInteger('schedule_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->text('message')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['type', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_snapshot_operations');
    }
};
