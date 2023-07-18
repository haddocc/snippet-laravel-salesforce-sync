<?php

namespace App\Models\SyncForce\Api;

use App\Models\CronLog\Repositories\CronLogRepository;
use App\Models\Finance\Classes\Invoice;
use App\Models\Finance\Repositories\EloquentInvoiceRepository;
use App\Models\SyncForce\Transformers\InvoiceTransformer;
use GuzzleHttp\Client as GuzzleClient;

/**
 * @property EloquentInvoiceRepository $invoiceRepository
 * @property InvoiceTransformer $transformer
 */
class InvoicesApi extends SalesForceApi
{

    public const REQUEST = 'Invoice_Line__c';
    public const REQUEST_NAME = 'invoices';


    public function __construct(
        EloquentInvoiceRepository $invoiceRepository,
        InvoiceTransformer $transformer
    ) {
        parent::__construct(app(GuzzleClient::class), app(CronLogRepository::class));
        $this->invoiceRepository = $invoiceRepository;
        $this->transformer = $transformer;
    }


    public function getData(array $recordIds = null) {
        $date = $this->getLastSyncDate(self::REQUEST_NAME);
        $records = $this->invoiceRepository->getInvoicesModifiedSince($date, $recordIds);
        return $records;
    }

    protected function sync(Invoice $invoice, string $action = null) {

        $this->loadRelations($invoice);

        if ($action === null) {
            // find out what to do with invoice
            $date = $this->getLastSyncDate(self::REQUEST_NAME);
            $action = $this->getAction($invoice, $date);
        }

        // map data and execute the request
        switch($action) {
            case 'create':
                $invoiceData = $this->transformer->transformItem($invoice);
                $invoice->salesforce_id = $this->createRecord(self::REQUEST, $invoiceData);
                $this->updateFieldsAfterSync($invoice);
                break;
            case 'update':
                $invoiceData = $this->transformer->transformItem($invoice);
                $this->updateRecord(self::REQUEST, $invoice->salesforce_id, $invoiceData);
                $this->updateFieldsAfterSync($invoice);
                break;
            case 'delete':
                $result = $this->deleteRecord(self::REQUEST, $invoice->salesforce_id);
                $invoice->salesforce_id = null;
                $this->updateFieldsAfterSync($invoice);
                break;
        }

        return true;
    }

    private function loadRelations(Invoice $invoice): void
    {
        $relations = [];
        $cb = function($qb) {
            $qb->withTrashed();
        };
        if (!$invoice->relationLoaded('combined_invoice')) {
            $relations['combined_invoice'] = $cb;
        }
        if (!$invoice->relationLoaded('order')) {
            $relations['order'] = $cb;
        }
        if (!$invoice->relationLoaded('billable')) {
            $relations['billable'] = $cb;
        }

        if (!empty($relations)) {
            $invoice->load($relations);
        }
    }

}
