<?php

namespace Mix\Concurrent\CoroutinePool;

use Mix\Core\Bean\AbstractObject;
use Mix\Core\Coroutine\Channel;
use Mix\Core\Coroutine\Timer;
use Mix\Core\Coroutine;

/**
 * Class Dispatcher
 * @package Mix\Concurrent
 * @author LIUJIAN <coder.keda@gmail.com>
 */
class Dispatcher extends AbstractObject
{

    /**
     * @var \Mix\Core\Coroutine\Channel
     */
    public $jobQueue;

    /**
     * 最大工人数
     * @var int
     */
    public $maxWorkers;

    /**
     * 工作池
     * 内部数据的是Channel
     * @var \Mix\Core\Coroutine\Channel
     */
    public $workerPool;

    /**
     * 工作者集合
     * @var array
     */
    public $workers = [];

    /**
     * 退出
     * @var \Mix\Core\Coroutine\Channel
     */
    protected $_quit;

    /**
     * 初始化事件
     */
    public function onInitialize()
    {
        parent::onInitialize(); // TODO: Change the autogenerated stub
        // 初始化
        if (!isset($this->workerPool)) {
            $this->workerPool = new Channel($this->maxWorkers);
        }
        $this->_quit = new Channel();
    }

    /**
     * 启动
     */
    public function start()
    {
        for ($i = 0; $i < $this->maxWorkers; $i++) {
            $worker          = new Worker([
                'workerPool' => $this->workerPool,
            ]);
            $this->workers[] = $worker;
            $worker->start();
        }
        $this->dispatch();
    }

    /**
     * 派遣
     */
    public function dispatch()
    {
        Coroutine::create(function () {
            while (true) {
                $job = $this->jobQueue->pop();
                if (!$job) {
                    return;
                }
                $jobChannel = $this->workerPool->pop();
                $jobChannel->push($job);
            }
        });
        Coroutine::create(function () {
            $this->_quit->pop();
            $timer = new Timer();
            $timer->tick(100, function () use ($timer) {
                if ($this->jobQueue->stats()['queue_num'] > 0) {
                    return;
                }
                $timer->clear();
                foreach ($this->workers as $worker) {
                    $worker->stop();
                }
                $this->jobQueue->close();
            });
        });
    }

    /**
     * 停止
     */
    public function stop()
    {
        Coroutine::create(function () {
            $this->_quit->push(true);
        });
    }

}
