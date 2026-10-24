<?php

/**
 * 役割: 各画面・APIコントローラーが共通で継承する基底クラス。
 */

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    use AuthorizesRequests;
}
