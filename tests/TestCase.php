<?php

/**
 * 役割: FeatureテストとUnitテストで共通利用する基底クラス。
 */

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
}
