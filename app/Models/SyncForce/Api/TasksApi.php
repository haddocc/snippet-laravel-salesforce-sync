<?php

namespace App\Models\SyncForce\Api;

use App\Models\CronLog\Repositories\CronLogRepository;
use App\Models\SyncForce\Transformers\TaskTransformer;
use App\Models\Tasks\Classes\Task;
use App\Models\Tasks\Interfaces\TaskInterface;
use Carbon\Carbon;
use GuzzleHttp\Client as GuzzleClient;

/**
 * @property TaskInterface $taskInterface
 * @property TaskTransformer $transformer
 */
class TasksApi extends SalesForceApi
{

    public const REQUEST = 'Case';
    public const REQUEST_NAME = 'tasks';

    public function __construct(
        TaskInterface $taskInterface
    ) {
        parent::__construct(app(GuzzleClient::class), app(CronLogRepository::class));

        $this->taskInterface = $taskInterface;
        $this->transformer = new TaskTransformer();
    }

    protected function sync(Task $task) {
        $taskData = $this->transformer->transformItem($task);
        $this->createRecord(self::REQUEST, $taskData);
        // suspend task in the system so the notification list won't get polluted
        $this->suspendTask($task);
        return true;
    }

    private function suspendTask(Task $task)
    {
        $task->suspended_at = Carbon::now();
        $task->is_suspended = 1;
        $task->suspended_times++;
        $timestampMaxInt = 2147483647;
        $task->suspended_till = Carbon::createFromTimestamp($timestampMaxInt); // latest moment in the future
        $task->timestamps = false;
        unset($task->open_time);
        unset($task->sf_title);
        $task->save();
    }

}
