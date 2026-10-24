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
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // 誰の打刻か
            $table->date('date');        // 打刻日
            $table->time('clock_in')->nullable();  // 出勤時刻
            $table->time('clock_out')->nullable(); // 退勤時刻
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
