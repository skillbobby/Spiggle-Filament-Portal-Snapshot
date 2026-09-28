<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_snapshot_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('action', 32);
            $table->string('frequency', 32);
            $table->string('cron_expression', 64)->nullable();
            $table->time('run_at')->nullable();
            $table->unsignedTinyInteger('weekday')->nullable();
            $table->string('snapshot_name')->nullable();
            $table->string('connection_name')->nullable();
            $table->boolean('compress')->default(true);
            $table->boolean('use_golden')->default(false);
            $table->unsignedInteger('keep')->nullable();
            $table->boolean('copy_to_remote')->default(false);
            $table->string('remote_disk')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->text('notes')->nullable();
            $table->timestamp('last_ran_at')->nullable();
            $table->string('last_status', 32)->nullable();
            $table->text('last_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_snapshot_schedules');
    }
};
