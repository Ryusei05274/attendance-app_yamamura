<?php

/**
 * 役割: データベースのテーブル構造を作成または変更する。
 */
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('break_logs', function (Blueprint $table) {
            $table->id();
            // どの出勤データに紐づく休憩か
            $table->foreignId('attendance_id')->constrained('attendances')->onDelete('cascade');
            $table->time('break_in');             // 休憩入りの時刻
            $table->time('break_out')->nullable(); // 休憩戻りの時刻（最初は空なのでnullable）
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('break_logs');
    }
};
