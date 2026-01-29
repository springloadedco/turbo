<?php

namespace Springloaded\Turbo\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Springloaded\Turbo\Turbo
 */
class Turbo extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Springloaded\Turbo\Turbo::class;
    }
}
