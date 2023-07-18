<?php
/**
 * To make this as simple as possible we assume the tasks to be transformed
 * only come from employees with a salesforce id
 */
namespace App\Models\SyncForce\Transformers;

use App\Models\Tasks\Classes\Task;
use League\Fractal\TransformerAbstract;

class TaskTransformer extends TransformerAbstract
{

    public function transformItem(Task $task): array
    {
        $data = [
            'Subject' => $task->sf_title ?? $task->title,
            'Description' => $task->description,
            'Status' => 'New',
            'Origin' => 'Bericht via systeem',
            'Type' => 'Problem'
        ];
        if(!is_null($task->user) && !is_null($task->user->userable->salesforce_id)){
            $data['OwnerId'] = $task->user->userable->salesforce_id;
        }
        if(!is_null($task->order) && !is_null($task->order->client->salesforce_id)){
            $data['AccountId'] = $task->order->client->salesforce_id;
            $data['Order_lookup__c'] = $task->order->salesforce_id;
        }

        return $data;
    }

}
