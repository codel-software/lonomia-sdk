<?php

namespace CodelSoftware\LonomiaSdk\Facades;

use Illuminate\Support\Facades\Facade;

class Lonomia extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'lonomia-sdk';
    }
}
