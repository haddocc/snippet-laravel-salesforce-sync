<?php

namespace App\Models\SyncForce\Transformers;



use App\Models\Finance\Classes\Invoice;
use App\Models\SyncForce\Exceptions\NotSyncedYetException;
use Illuminate\Database\Eloquent\RelationNotFoundException;
use League\Fractal\TransformerAbstract;

class InvoiceTransformer extends TransformerAbstract
{

    public function transformItem(Invoice $invoice): array
    {
        if ($invoice->billable === null) {
            $invoice->combined_invoice->load('billable');
            if ($invoice->combined_invoice->billable === null) {
                $msg = 'Invoice id ' . $invoice->id . ' and combined-invoice id ' . $invoice->combined_invoice->id
                    . ' both have no billable record where 1 is expected.';
                throw new RelationNotFoundException($msg);
            }
            $billable = $invoice->combined_invoice->billable;
        } else {
            $billable = $invoice->billable;
        }
        if ($billable->salesforce_id === null) {
            throw new NotSyncedYetException('Billable id ' . $billable->id . ' for Invoice id ' . $invoice->id . ' has not been synced yet.');
        }

        if ($invoice->combined_invoice === null) {
            throw new RelationNotFoundException('Invoice id ' . $invoice->id . ' has no combined_invoice record where 1 is expected.');
        }
        if ($invoice->combined_invoice->salesforce_id === null) {
            throw new NotSyncedYetException('Combined Invoice id ' . $invoice->combined_invoice->id . ' for Invoice id ' . $invoice->id . ' has not been synced yet.');
        }

        if ($invoice->order === null) {
            throw new RelationNotFoundException('Invoice id ' . $invoice->id . ' has no order record where 1 is expected.');
        }
        if ($invoice->order->salesforce_id === null) {
            throw new NotSyncedYetException('Order id ' . $invoice->order->id . ' for Invoice id ' . $invoice->id . ' has not been synced yet.');
        }

        return [
            'Invoice__c' => $invoice->combined_invoice->salesforce_id,
            'Price_without_VAT__c' => $invoice->price_ex_vat,
            'VAT_Percentage__c' => $invoice->vat,
            'Can_be_Billed__c' => $invoice->can_be_billed,
            'Is_Billed__c' => $invoice->is_billed,
            'Is_Package__c' => ($invoice->is_package === 1),
            'Order__c' => $invoice->order->salesforce_id ?? null,

            'Billable_Id__c' => $billable->salesforce_id,
            'Name' => $invoice->id,

        ];
    }

}
