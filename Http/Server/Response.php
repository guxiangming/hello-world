<?php

namespace Illuminate\Swoole\Http\Server;

use Illuminate\Http\Response as IlluminateResponse;
use Swoole\Http\Response as SwooleResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Base\HelperClass;

class Response
{
    /**
     * @var \Swoole\Http\Response
     */
    protected $swooleResponse;

    /**
     * @var \Illuminate\Http\Response
     */
    protected $illuminateResponse;
    public static $one=0;
    public static $requestUrl="";
    /**
     * Make a response.
     *
     * @param $illuminateResponse
     * @param \Swoole\Http\Response $swooleResponse
     * @return \Illuminate\Swoole\Http\Server\Response
     */
    public static function make($illuminateResponse, SwooleResponse $swooleResponse)
    {
        return new static($illuminateResponse, $swooleResponse);
    }

    /**
     * Response constructor.
     *
     * @param mixed $illuminateResponse
     * @param \Swoole\Http\Response $swooleResponse
     */
    public function __construct($illuminateResponse, SwooleResponse $swooleResponse)
    {
        $this->setIlluminateResponse($illuminateResponse);
        $this->setSwooleResponse($swooleResponse);
    }

    /**
     * Sends HTTP headers and content.
     *
     * @throws \InvalidArgumentException
     */
    public function send()
    {
        $this->sendHeaders();
        $this->sendContent();
    }

    /**
     * Sends HTTP headers.
     *
     * @throws \InvalidArgumentException
     */
    protected function sendHeaders()
    {
        $illuminateResponse = $this->getIlluminateResponse();

        /* RFC2616 - 14.18 says all Responses need to have a Date */
        if (! $illuminateResponse->headers->has('Date')) {
            $illuminateResponse->setDate(\DateTime::createFromFormat('U', time()));
        }

        // headers
        // allPreserveCaseWithoutCookies() doesn't exist before Laravel 5.3
        $headers = $illuminateResponse->headers->allPreserveCase();
        if (isset($headers['Set-Cookie'])) {
            unset($headers['Set-Cookie']);
        }
        foreach ($headers as $name => $values) {
            foreach ($values as $value) {
                $this->swooleResponse->header($name, $value);
            }
        }

        // status
        $this->swooleResponse->status($illuminateResponse->getStatusCode());
      
        
       
        // cookies
        foreach ($illuminateResponse->headers->getCookies() as $cookie) {
            // may need to consider rawcookie
            $this->swooleResponse->cookie(
                $cookie->getName(), $cookie->getValue(),
                $cookie->getExpiresTime(), $cookie->getPath(),
                $cookie->getDomain(), $cookie->isSecure(),
                $cookie->isHttpOnly()
            );
        }
    }

    /**
     * Sends HTTP content.
     */
    protected function sendContent()
    {
        $illuminateResponse = $this->getIlluminateResponse();
        // $status=$illuminateResponse->getStatusCode();
        // dump($illuminateResponse->headers->allPreserveCase()['Content-Type']);
        // $requestUrl=$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
        // if($status!=200&&$requestUrl!="192.168.1.122:8000/api/center/apiReport")
        // {
        //     //防止重复提交

        //     self::$requestUrl=$requestUrl;
        //     $cli=new \swoole_http_client('192.168.1.122',8000);
 
        //     $params['url']=$requestUrl;
        //     $params['status']=$status;
        //     $params['type']="extremley";
        //     $encrypt = HelperClass::encryptApi();
        //     $params['encrypt'] = $encrypt;  
        //     $url ='/api/center/apiReport';
        //     $cli->setHeaders(['Content-Type'=>'application/x-www-form-urlencoded;charset=UTF-8']);
        //     $cli->post($url,$params,function(){

        //     });  
         
        // }
        if ($illuminateResponse instanceof StreamedResponse &&
            property_exists($illuminateResponse, 'output')
        ) {
            $this->swooleResponse->end($illuminateResponse->output);
        } elseif ($illuminateResponse instanceof BinaryFileResponse) {
            $this->swooleResponse->sendfile($illuminateResponse->getFile()->getPathname());
        } else {
            $this->swooleResponse->end($illuminateResponse->getContent());
        }
    }

    /**
     * @param \Swoole\Http\Response $swooleResponse
     * @return \Illuminate\Swoole\Http\Server\Response
     */
    protected function setSwooleResponse(SwooleResponse $swooleResponse)
    {
        $this->swooleResponse = $swooleResponse;
        
        return $this;
    }

    /**
     * @return \Swoole\Http\Response
     */
    public function getSwooleResponse()
    {
        return $this->swooleResponse;
    }

    /**
     * @param mixed illuminateResponse
     * @return \Illuminate\Swoole\Http\Server\Response
     */
    protected function setIlluminateResponse($illuminateResponse)
    {
        if (! $illuminateResponse instanceof SymfonyResponse) {
            $content = (string) $illuminateResponse;
            $illuminateResponse = new IlluminateResponse($content);
        }

        $this->illuminateResponse = $illuminateResponse;

        return $this;
    }

    /**
     * @return \Illuminate\Http\Response
     */
    public function getIlluminateResponse()
    {
        return $this->illuminateResponse;
    }
}
