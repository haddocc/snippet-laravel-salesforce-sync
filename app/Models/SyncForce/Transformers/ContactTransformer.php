<?php

namespace App\Models\SyncForce\Transformers;


use App\Models\Orders\Classes\Contact;
use League\Fractal\TransformerAbstract;

class ContactTransformer extends TransformerAbstract
{

    public function transformItem(Contact $contact): array
    {
        // Fix possible malformed email injected by ML for sync
        preg_match('/([^\s]*);([^\s]*)/s', preg_replace('/\s*/m','',$contact->email), $matches)[1];
        $contactEmail = $matches[1] ?? $contact->email;
        $contactName = $contact->name === "" ? 'n.v.t.' : $contact->name;
        $data = [
            'LastName' => $contactName,
            'Full_Name__c' => $contactName,
            'Email' => $contactEmail,
            'Phone' => $contact->telephone_number,
        ];

        $order = $contact->order ?? $contact->alternativeOrders->first();

        if (!is_null($order) && !is_null($order->client->employee) && !is_null($order->client->employee->salesforce_id)) {
            $data['OwnerId'] = $order->client->employee->salesforce_id;
            $data['AccountIDcontact2__c'] = $order->client->salesforce_id;
        }

        return $data;
    }

}
