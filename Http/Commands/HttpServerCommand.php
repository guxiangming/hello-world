<?php

namespace Illuminate\Swoole\Http\Commands;

use Illuminate\Console\Command;
use Swoole\Process;

class HttpServerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'http {action : start|stop|restart|reload|infos|inotify}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Swoole HTTP Server controller.';

    /**
     * The console command action. start|stop|restart|reload
     *
     * @var string
     */
    protected $action;

    /**
     *
     * The pid.
     *
     * @var int
     */
    protected $pid;

    /**
     * The configs for this package.
     *
     * @var array
     */
    protected $configs;
    protected static $lock=true;
    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $this->checkEnvironment();
        $this->loadConfigs();
        $this->initAction();
        $this->runAction();
    }

    /**
     * Load configs.
     */
    protected function loadConfigs()
    {
        $this->configs = $this->laravel['config']->get('swoole_http');
    }

    /**
     * Run action.
     */
    protected function runAction()
    {
        $this->{$this->action}();
    }

    /**
     * Run swoole_http_server.
     */
    protected function start()
    {
   
        if ($this->isRunning($this->getPid())) {
            $this->error('Failed! swoole_http_server process is already running.');
            exit(1);
        }

        $host = $this->configs['server']['host'];
        $port = $this->configs['server']['port'];

        $this->info('Starting swoole http server...');
        $this->info("Swoole http server started: <http://{$host}:{$port}>");
        if ($this->isDaemon()) {
            $this->info('> (You can run this command to ensure the ' .
                'swoole_http_server process is running: ps aux|grep "swoole")');
        }

        $this->laravel->make('swoole.http')->run();
    }

    /**
     * Stop swoole_http_server.
     */
    protected function stop()
    {
        $pid = $this->getPid();

        if (! $this->isRunning($pid)) {
            $this->error("Failed! There is no swoole_http_server process running.");
            exit(1);
        }

        $this->info('Stopping swoole http server...');

        $isRunning = $this->killProcess($pid, SIGTERM, 15);

        if ($isRunning) {
            $this->error('Unable to stop the swoole_http_server process.');
            exit(1);
        }

        // I don't known why Swoole didn't trigger "onShutdown" after sending SIGTERM.
        // So we should manually remove the pid file.
        $this->removePidFile();

        $this->info('> success');
    }

    /**
     * Restart swoole http server.
     */
    protected function restart()
    {
        $pid = $this->getPid();

        if ($this->isRunning($pid)) {
            $this->stop();
        }

        $this->start();
    }
    /**
     * innorify swoole http server.
     */
    protected function inotify(){
        $pid=$this->getPid();
        if(!$pid){
            $this->info('no start service swoole http ');
            exit(1);
        }
        $arrFile=[
            app_path(),
            config_path(),
            database_path(),
            base_path('routes'),
            base_path('vendor'),
            base_path('resources'),
            base_path('.env'),
        ];
        // dump();
        $listFile=$this->findFile($arrFile);
        $notify=inotify_init();
        // dump($listFile);
        foreach ($listFile as $item){
            // 创建 修改 删除
            inotify_add_watch($notify,$item,IN_MODIFY|IN_CREATE|IN_DELETE);           
        }
    
        swoole_event_add($notify,function($notify){
            $event=inotify_read($notify);            
            if($event&&self::$lock){
                $this->info('listen File change');
                // $pid=$this->getPid();
                // if (!$pid&&!$this->isRunning($pid)) {
                //     $this->info('no start service swoole http and inotify close ');
                //     exit(1);
                // }
                self::$lock=false;
                $timer=swoole_timer_after(
                    15000,
                    function(){                    
                        $pid=$this->getPid();
                        $this->info('listen File change restart swoole');
                        $isRunning = $this->killProcess($pid, SIGUSR1);
                        if ($isRunning) {
                            $this->error('Unable to resload the swoole_http_server process.');
                            exit(1);
                        }
                        $this->info('> success');
                        self::$lock=true;

                });
            }
      
        });
    }
    /**
     * find file 
     */
    function findFile($arr){
        if(count($arr)>0){
            $dataSum=array();
            $bl = function ($dir) use (&$bl) {
                $data = array();
                if (is_dir($dir)) {
                    $data[] = $dir;
                    $files = array_diff(scandir($dir), array('.', '..'));
                    foreach ($files as $file) {
                      
                        $data = array_merge($data, $bl($dir . "/" . $file));
                    }
                } else {
                    $data[] = $dir;
                }
                return $data;
            };

            foreach($arr as $dir){
                array_push($dataSum,$bl($dir));
            } 
            $dataSum=array_reduce($dataSum,'array_merge',array());
            return $dataSum;
        }
    }
    
    protected function reload()
    {
        $pid = $this->getPid();

        if (! $this->isRunning($pid)) {
            $this->error("Failed! There is no swoole_http_server process running.");
            exit(1);
        }

        $this->info('Reloading swoole_http_server...');

        $isRunning = $this->killProcess($pid, SIGUSR1);

        if (! $isRunning) {
            $this->error('> failure');
            exit(1);
        }

        $this->info('> success');
    }

    /**
     * Display PHP and Swoole misc info.
     */
    protected function infos()
    {
        $this->showInfos();
    }

    /**
     * Display PHP and Swoole miscs infos.
     *
     * @param bool $more
     */
    protected function showInfos()
    {
        $pid = $this->getPid();
        $isRunning = $this->isRunning($pid);
        $host = $this->configs['server']['host'];
        $port = $this->configs['server']['port'];
        $reactorNum = $this->configs['server']['options']['reactor_num'];
        $workerNum = $this->configs['server']['options']['worker_num'];
        $taskWorkerNum = $this->configs['server']['options']['task_worker_num'];
        $isWebsocket = $this->configs['websocket']['enabled'];
        $logFile = $this->configs['server']['options']['log_file'];

        $table = [
            ['PHP Version', 'Version' => phpversion()],
            ['Swoole Version', 'Version' => swoole_version()],
            ['Laravel Version', $this->getApplication()->getVersion()],
            ['Listen IP', $host],
            ['Listen Port', $port],
            ['Server Status', $isRunning ? 'Online' : 'Offline'],
            ['Reactor Num', $reactorNum],
            ['Worker Num', $workerNum],
            ['Task Worker Num', $isWebsocket ? $taskWorkerNum : 0],
            ['Websocket Mode', $isWebsocket ? 'On' : 'Off'],
            ['PID', $isRunning ? $pid : 'None'],
            ['Log Path', $logFile],
        ];

        $this->table(['Name', 'Value'], $table);
    }

    /**
     * Initialize command action.
     */
    protected function initAction()
    {
        $this->action = $this->argument('action');

        if (! in_array($this->action, ['start', 'stop', 'restart', 'reload', 'infos','inotify'])) {
            $this->error("Invalid argument '{$this->action}'. Expected 'start', 'stop', 'restart', 'inotify',reload' or 'infos'.");
            exit(1);
        }
    }

    /**
     * If Swoole process is running.
     *
     * @param int $pid
     * @return bool
     */
    protected function isRunning($pid)
    {
        if (! $pid) {
            return false;
        }

        Process::kill($pid, 0);

        return ! swoole_errno();
    }

    /**
     * Kill process.
     *
     * @param int $pid
     * @param int $sig
     * @param int $wait
     * @return bool
     */
    protected function killProcess($pid, $sig, $wait = 0)
    {
        Process::kill($pid, $sig);

        if ($wait) {
            $start = time();

            do {
                if (! $this->isRunning($pid)) {
                    break;
                }

                usleep(100000);
            } while (time() < $start + $wait);
        }

        return $this->isRunning($pid);
    }

    /**
     * Get pid.
     *
     * @return int|null
     */
    protected function getPid()
    {
        if ($this->pid) {
            return $this->pid;
        }

        $pid = null;
        $path = $this->getPidPath();

        if (file_exists($path)) {
            $pid = (int) file_get_contents($path);

            if (! $pid) {
                $this->removePidFile();
            } else {
                $this->pid = $pid;
            }
        }

        return $this->pid;
    }

    /**
     * Get Pid file path.
     *
     * @return string
     */
    protected function getPidPath()
    {
        return $this->configs['server']['options']['pid_file'];
    }

    /**
     * Remove Pid file.
     */
    protected function removePidFile()
    {
        if (file_exists($this->getPidPath())) {
            unlink($this->getPidPath());
        }
    }

    /**
     * Return daemonize config.
     */
    protected function isDaemon()
    {
        return $this->configs['server']['options']['daemonize'];
    }

    /**
     * Check running enironment.
     */
    protected function checkEnvironment()
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            throw new \RuntimeException("Swoole extension doesn't support Windows OS yet.");
        } elseif (! extension_loaded('swoole')) {
            throw new \RuntimeException("Can't detect Swoole extension installed.");
        }
    }
}

