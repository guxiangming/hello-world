<?php

namespace Illuminate\Swoole\Http\Websocket\Facades;

use Illuminate\Support\Facades\Facade;

class Room extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'swoole.room';
    }
}