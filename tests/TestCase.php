<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    // Token formulir sekali pakai (App\Support\FormToken) untuk booking, checkout, bayar, dan jual barang
    protected function formKey(): string
    {
        return (string) Str::uuid();
    }
}
