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
        // 💡【修正】rename ではなく、テーブルをゼロから新規作成（create）する記述にします
        Schema::create('attendance_corrections', function (Blueprint $table) {
            $table->id();
            // 外部キーの登録（出勤テーブルおよびユーザーテーブルとの紐付け）
            $table->foreignId('attendance_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->date('original_date');     // 元の日付
            $table->time('new_clock_in');      // 修正後の出勤時間
            $table->time('new_clock_out');     // 修正後の退勤時間
            $table->text('comment');           // 申請理由（備考）
            $table->integer('status')->default(0); // 状態フラグ（0: 承認待ち）
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 💡【修正】ロールバック（取り消し）時はテーブルごと削除します
        Schema::dropIfExists('attendance_corrections');
    }
};
