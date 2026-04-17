<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('support_debug_logs')) {
            return;
        }

        Schema::create('support_debug_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('user_id')->unsigned()->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->string('type', 16);
            $table->text('command')->nullable();
            $table->integer('exit_code')->nullable();
            $table->integer('duration_ms')->default(0);
            $table->integer('output_bytes')->default(0);
            $table->boolean('truncated')->default(false);
            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'created_at'], 'idx_user_created');
            $table->index(['type', 'created_at'], 'idx_type_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_debug_logs');
    }
};
