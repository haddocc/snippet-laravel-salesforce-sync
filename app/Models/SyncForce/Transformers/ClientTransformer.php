<?php

namespace App\Models\SyncForce\Transformers;


use App\Models\Clients\Models\Client;
use App\Models\SyncForce\Exceptions\NotSyncedYetException;
use Illuminate\Database\Eloquent\RelationNotFoundException;
use Illuminate\Support\Facades\Log;
use League\Fractal\TransformerAbstract;

class ClientTransformer extends TransformerAbstract
{


    public function transformItem(Client $client): array
    {
        if ($client->address === null) {
            throw new RelationNotFoundException('Client ' . $client->id . ' has no address record where 1 is expected.');
        }

        if ($client->address->salesforce_id === null) {
            throw new NotSyncedYetException('Address id '. $client->address->id . ' for Client ' . $client->id . ' has not been synced yet.');
        }
        if ($client->employee === null) {
            //throw new RelationNotFoundException('Client ' . $client->id . ' has no employee attached in the system.');
            Log::warning('Client ' . $client->id . ' has no employee attached in the system.');
        }
        if ($client->employee !== null && $client->employee->salesforce_id === null) {
            //throw new RelationNotFoundException('Employee '. $client->employee->first_name . ' for Client ' . $client->id . ' has no corresponding SalesForce user.');
            Log::warning('Employee '. $client->employee->first_name . ' for Client ' . $client->id . ' has no corresponding SalesForce user.');
        }
        if ($client->client_users->count() === 0) {
            throw new RelationNotFoundException('Client ' . $client->id . ' has no client_user record where 1 is expected.');
        }

        $clientUser = $client->client_users->first();
        return [
            'name' => $client->name,
            'Phone' => (!empty(trim($clientUser->telephone_number))) ? trim($clientUser->telephone_number) : null,
            'Rang__c' => $client->rank,
            'Address__c' => $client->address->salesforce_id ?? null,
            'Is_Active__c' => $client->is_active,
            'Contact_First_Name__c' => $clientUser->first_name,
            'Contact_Last_Name__c' => $clientUser->last_name,
            'Contact_Initials__c' => $clientUser->initials,
            'Contact_Email__c' => $clientUser->email,
            'OwnerId' => $client->employee->salesforce_id ?? '0051t000001T9WZAA0' # Assign to Boy in case salesforce_id is null
        ];
    }

}
