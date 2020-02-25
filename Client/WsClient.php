<?php
namespace Illuminate\Swoole\Client;
use Illuminate\Swoole\Client\WebSocket;
class WsClient{
    /**
     * @param string $event  
     * 发送数据默认不抓回调 
     * @return NULL
     */
    static function websocketPush($event,$data){
        if(extension_loaded('swoole')){
            $client=new WebSocket(env('SWOOLE_PUBLIC_PUSH_HOST','127.0.0.1'),env('SWOOLE_PUBLIC_PUSH_PORT',9501),env('SWOOLE_PUBLIC_PUSH_URL','/websocketPush'));
            if(!$client->connect())
            {
                echo "connect to server failed.\n";
            }else{
                
                $data=[
                    'event'=>$event,
                    'data'=>$data,
                ];

                $client->sendJson($data);
                // 如果非推送频繁 
                // $client->disconnect();
            }
        }
    }   
}