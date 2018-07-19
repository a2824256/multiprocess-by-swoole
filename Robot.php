<?php

class Robot
{
    public $workerMaxNum = 2;
    public $works = [];
    public $masterPid;
    public $urls = [
        "www.baidu.com",
        "www.360.cn",
        "blog.diligentyang.com",
        "www.qq.com",
    ];
    public $engines = [
        "https://www.baidu.com/s?ie=utf-8&f=8&rsv_bp=1&tn=baidu&wd=",
        "https://www.sogou.com/web?query=",
        "https://www.so.com/s?ie=utf-8&fr=none&src=360sou_newhome&q="
    ];
    public $engineIndex = 0;

    //构造函数
    public function __construct()
    {
        try {
            //主线程
            $this->masterPid = posix_getpid();
            echo "master Pid " . $this->masterPid . PHP_EOL;
            $this->run();
            $this->processWait();
        } catch (\Exception $e) {
            die('ALL ERROR: ' . $e->getMessage());
        }
    }

    //运行
    public function run()
    {
        for ($i = 0; $i < $this->workerMaxNum; $i++) {
            $this->createProcess();
        }
    }

    public function createProcess()
    {
        $engineIndex = $this->engineIndex;
        $this->engineIndex += 1;
        $process = new swoole_process(function (swoole_process $worker) use ($engineIndex) {
            for ($j = 0; $j < 16000; $j++) {
                $this->checkMasterPid($worker);
            }
            $this->getStatus($this->engines[$engineIndex], $engineIndex);
            echo "engineIndex:" . $engineIndex . PHP_EOL;
        }, false, false);
        $pid = $process->start();
        echo "worker pid:" . $pid . PHP_EOL;
        $this->works[$pid] = $pid;
        return $pid;
    }

    public function getStatus($engine, $engineIndex)
    {
        if ($engineIndex >= count($this->engines)) {
            echo "engines max" . PHP_EOL;
            return;
        }
        foreach ($this->urls as $value) {
            $ch = curl_init($engine . $value);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_exec($ch);
            $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
//            echo $statusCode . " " . $engine . $value .  PHP_EOL;
            curl_close($ch);
        }
        echo $engine . " finish" . PHP_EOL;
        return;
    }

    public function processWait()
    {
        while (1) {
            if (count($this->works)) {
                $ret = swoole_process::wait();
                if ($ret) {
                    $this->rebootProcess($ret);
                }
            } else {
                break;
            }
        }
    }

    public function rebootProcess($ret)
    {
        $pid = $ret['pid'];
        echo "rb pid:" . $pid . PHP_EOL;
        $index = in_array($pid, $this->works);
        if ($index) {
            unset($this->works[$pid]);
            $pid = intval($pid);
            if ($this->engineIndex < count($this->engines)) {
                $new_pid = $this->createProcess($pid);
                echo "rebootProcess: {$pid}={$new_pid} Done\n";
            } else {
                echo "rb engines max,engineIndex:" . $this->engineIndex . ",engines count:" . count($this->engines) . PHP_EOL;
            }
            return;
        } elseif ($pid == $this->masterPid) {
            return;
        }
        throw new \Exception('rebootProcess Error: no pid');
    }

    //主线程退出，自己跟着退出
    public function checkMasterPid(&$worker)
    {
        if (!swoole_process::kill($this->masterPid, 0)) {
            $worker->exit();
            echo "Master process exited, I [{$worker['pid']}] also quit\n";
            sleep(1);
        }
    }

}

$m = new Robot();
$m->run();