<?php

namespace App\Models\SyncForce\Transformers;


use App\Models\Comments\Classes\Comment;
use App\Models\Orders\Classes\Order;
use League\Fractal\TransformerAbstract;

class CommentTransformer extends TransformerAbstract
{
    /**
     * @var Order
     */
    private $order;

    public function transformItem(Comment $comment): array
    {
        $this->order = $comment->commentable()->first();
        $data = [
            'Subject' => $comment->subject,
            'Description' => $comment->content,
            'Status' => 'New',
            'Origin' => 'Bericht via systeem',
            'Type' => ucfirst($comment->subject),
            'AccountId' => $this->order
                ->client
                ->salesforce_id,
            'Order_lookup__c' => $this->order
                ->salesforce_id
        ];

        if(!is_null($this->order) &&
            !is_null($this->order->client->employee) &&
            !is_null($this->order->client->employee->assistent) &&
            !is_null($this->order->client->employee->assistent->salesforce_id)
        ){
            $data['OwnerId'] = $this->order->client->employee->assistent->salesforce_id;
        }

        return $data;
    }

}
