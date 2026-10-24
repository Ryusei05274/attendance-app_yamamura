<?php

/**
 * 役割: APIの勤怠レコードで既存Attendanceモデルとの互換性を提供する。
 */

namespace App\Models;

/**
 * API-facing model name for the existing attendances table.
 */
class AttendanceRecord extends Attendance
{
    protected $table = 'attendances';
}
