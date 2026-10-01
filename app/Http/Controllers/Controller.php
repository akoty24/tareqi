<?php

namespace App\Http\Controllers;

use App\Traits\ApiResponseTrait;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    use ApiResponseTrait, AuthorizesRequests;

    /** Clamp ?per_page to a sane range. */
    protected function perPage(int $default = 15, int $max = 50): int
    {
        return max(1, min($max, (int) request()->integer('per_page', $default)));
    }
}
