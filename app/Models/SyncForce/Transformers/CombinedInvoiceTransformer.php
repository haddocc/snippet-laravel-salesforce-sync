<?php

namespace App\Models\SyncForce\Transformers;



use App\Models\Clients\Models\Client;
use App\Models\Finance\Classes\CombinedInvoice;
use App\Models\SyncForce\Exceptions\NotSyncedYetException;
use Illuminate\Database\Eloquent\RelationNotFoundException;
use League\Fractal\TransformerAbstract;

class CombinedInvoiceTransformer extends TransformerAbstract
{

    public function transformItem(CombinedInvoice $combinedInvoice): array
    {

        if ($combinedInvoice->billable === null) {
            throw new RelationNotFoundException('Combined Invoice id ' . $combinedInvoice->id . ' has no billable record where 1 is expected.');
        }
        if (!$combinedInvoice->billable instanceof Client) {
            throw new RelationNotFoundException('Combined Invoice id ' . $combinedInvoice->id . ' doesn\'t have a client set as billable. This is a requirement for SalesForce, skipping this record.');
        }
        if ($combinedInvoice->billable->salesforce_id === null) {
            throw new NotSyncedYetException('Billable id ' . $combinedInvoice->billable->id . ' of invoice ' . $combinedInvoice->id . ' has not been synced yet.');
        }

        return [
            'Is_Paid__c' => (bool) $combinedInvoice->is_payed,
            'Total_Amount__c' => $combinedInvoice->total_amount,
            'Status__c' => 'Final',
            'Name' => $combinedInvoice->id,
            'Billing_Id__c' => $combinedInvoice->billable->salesforce_id,
        ];
    }

}
