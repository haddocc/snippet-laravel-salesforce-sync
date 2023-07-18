<?php

namespace App\Models\SyncForce\Transformers;


use App\Models\Orders\Classes\Order;
use App\Models\SyncForce\Exceptions\NotSyncedYetException;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\RelationNotFoundException;
use League\Fractal\TransformerAbstract;

class OrderTransformer extends TransformerAbstract
{

    protected const STATUS_COMPLETED = 'Completed';
    protected const STATUS_OPEN = 'Draft';
    protected const STATUS_CANCELLED = 'Cancelled';


    public function transformItem(Order $order): array
    {
        // Possibly not loaded yet.
        if ($order->client === null) {
            throw new RelationNotFoundException('Order id ' . $order->id . ' has no Client record where 1 is expected.');
        }
        if ($order->client->salesforce_id === null) {
            throw new NotSyncedYetException('Client id ' . $order->client->id . ' of order id ' . $order->id . ' has not been synced yet.');
        }

        if ($order->object === null) {
            throw new RelationNotFoundException('Order id ' . $order->object->id . ' has no object record where 1 is expected.');
        }
        if ($order->object->address === null) {
            throw new RelationNotFoundException('Order object id ' . $order->object->id . ' of order id ' . $order->id . ' has no address record where 1 is expected.');
        }
        if ($order->object->address->salesforce_id === null) {
            throw new NotSyncedYetException('Address id ' . $order->object->address->id . ' of order id ' . $order->id . ' has not been synced yet.');
        }
        if ($order->job_regions !== null && $order->job_regions->salesforce_id === null) {
            throw new NotSyncedYetException('JobRegion id ' . $order->job_regions->id . ' of Order id ' . $order->id . ' has not been synced yet.');
        }
        if ($order->cancelled_at !== null) {
            $cancelledDate = $order->cancelled_at->format(\DateTime::ISO8601);
        }

        if (!$order->object->address->relationLoaded('addressFields')) {
            $order->object->address->load('addressFields');
        }
        $addressFields = $order->object->address->addressFields->pluck('value', 'key')->toArray();
        if (isset($addressFields['street'], $addressFields['house_number'])) {
            $orderName = $order->id . ' - ' . $addressFields['street'] . ' ' . $addressFields['house_number'];
        } else {
            $orderName = $order->id;
        }
        $contact = $order->contact ?? $order->alternativeContact;
        $lastAppointment = $order->appointments->where('app_status', '!=', 'cancelled')->last();

        $data = [
            'Name' => strlen($orderName) > 80 ? $order->id : $orderName,
            'AccountId' => $order->client->salesforce_id,
            'Status' => $this->getStatusCode($order),
            'EffectiveDate' =>  $order->created_at->format(\DateTime::ISO8601),
            'Cancel_Reason__c' => $order->cancel_reason,
            'Cancelled_At__c' => $cancelledDate ?? null,
            'Address__c' => $order->object->address->salesforce_id,
            'Job_Region__c' => ($order->job_regions !== null) ? $order->job_regions->salesforce_id : null,
            'Is_Paid__c' => (bool) $order->is_paid,
            'Price_without_VAT__c' => $order->price_ex_vat,
            'VAT_Percentage__c' => $order->vat,
            'Contact__c' => !is_null($contact) && !is_null($contact->salesforce_id) ? $contact->salesforce_id : null,
            'Order_nummer__c' => $order->id,
            'Naam_fotograaf__c' => !is_null($lastAppointment) ? $lastAppointment->employee : '',
        ];

        if(!is_null($lastAppointment)) {
            $data['Datum_afspraak__c'] = (new Carbon($lastAppointment->app_date.' '.$lastAppointment->app_time_from))->format(\DateTime::ISO8601);
        }

        return $data;
    }

    protected function getStatusCode(Order $order):? string
    {
        // status codes
        switch($order->status) {
            case 'completed':
                return self::STATUS_COMPLETED;
                break;
            case 'open':
                return self::STATUS_OPEN;
                break;
            case 'cancelled':
                return self::STATUS_CANCELLED;
                break;
            default:
                $statusses = [self::STATUS_COMPLETED, self::STATUS_OPEN, self::STATUS_CANCELLED];
                $msg = 'Order id ' . $order->id . ' has an unexpected Status: ' . $order->status . ' where ' . implode(', ', $statusses)  . ' was expected.';
                throw new RelationNotFoundException($msg);
                break;
        }
    }

}
