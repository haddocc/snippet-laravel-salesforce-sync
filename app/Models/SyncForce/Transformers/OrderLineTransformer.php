<?php

namespace App\Models\SyncForce\Transformers;



use App\Models\Orders\Classes\OrderLine;
use App\Models\SyncForce\Exceptions\NotSyncedYetException;
use Illuminate\Database\Eloquent\RelationNotFoundException;
use League\Fractal\TransformerAbstract;

class OrderLineTransformer extends TransformerAbstract
{

    public function transformItem(OrderLine $orderLine): array
    {
        if ($orderLine->order === null) {
            throw new RelationNotFoundException('OrderLine id ' . $orderLine->id . ' has no Order record where 1 is expected.');
        }
        if ($orderLine->order->salesforce_id === null) {
            throw new NotSyncedYetException('Order id ' . $orderLine->order->id . ' of OrderLine id ' . $orderLine->id . ' has not been synced yet.');
        }

        if ($orderLine->product === null) {
            throw new RelationNotFoundException('OrderLine id ' . $orderLine->id . ' has no Product record where 1 is expected.');
        }
        if ($orderLine->product->salesforce_id === null) {
            throw new NotSyncedYetException('Product id ' . $orderLine->product->id . ' of OrderLine id ' . $orderLine->id . ' has not been synced yet.');
        }
        $packageId = ($orderLine->package !== null) ? $orderLine->package->salesforce_id : null;

        if ($orderLine->completed_at !== null) {
            $date = new \DateTime($orderLine->completed_at->toDateTimeString());
            $completedDate = $date->format(\DateTime::ISO8601);
        }

        return [
            'OrderId' => $orderLine->order->salesforce_id,
            'PricebookEntryId' => null,
            'Product_Group__c' => $packageId,
            'UnitPrice' => $orderLine->product->base_price,
            'Product_Id__c' => $orderLine->product->id,
            'Price_without_VAT__c' => $orderLine->price_ex_vat,
            'VAT_Percentage__c' => $orderLine->vat,
            'Completed_At__c' => $completedDate ?? null,
            'Quantity' => $orderLine->amount,
        ];
    }

}
