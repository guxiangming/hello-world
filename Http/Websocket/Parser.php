<?php

namespace Illuminate\Swoole\Http\Websocket;

use Swoole\Websocket\Frame;
use Swoole\Websocket\Server;
use Illuminate\Support\Facades\App;

abstract class Parser
{
    /**
     * Strategy classes need to implement handle method.
     */
    protected $strategies = [];

    /**
     * Execute strategies before decoding payload.
     * If return value is true will skip decoding.
     *
     * @return boolean
     */
    public function execute(Server $server, Frame $frame)
    {
        $skip = false;

        foreach ($this->strategies as $strategy) {
            $result = App::call($strategy . '@handle', [
                'server' => $server,
                'frame' => $frame
            ]);
            if ($result === true) {
                $skip = true;
                break;
            }
        }

        return $skip;
    }

    /**
     * Encode output payload for websocket push.
     *
     * @param string $event
     * @param mixed $data
     * @return mixed
     */
    abstract public function encode(string $event, $data);

    /**
     * Input message on websocket connected.
     * Define and return event name and payload data here.
     *
     * @param \Swoole\Websocket\Frame $frame
     * @return array
     */
    abstract public function decode(Frame $frame);
}
