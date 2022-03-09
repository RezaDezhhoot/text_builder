<?php


namespace Ermac\TextBuilder;

use Illuminate\Support\Facades\Facade;

class TextBuilderFacade extends Facade
{
    protected static function getFacadeAccessor()
    {
        return TextBuilder::class;
    }
}
