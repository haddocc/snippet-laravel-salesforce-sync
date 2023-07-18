<?php

namespace App\Models\SyncForce\Api;

use App\Models\Clients\Models\Client;
use App\Models\CronLog\Repositories\CronLogRepository;
use App\Models\Finance\Classes\CombinedInvoice;
use App\Models\Finance\Repositories\EloquentInvoiceRepository;
use App\Models\SyncForce\Transformers\CombinedInvoiceTransformer;
use GuzzleHttp\Client as GuzzleClient;

/**
 * @property CombinedInvoiceTransformer $transformer
 * @property EloquentInvoiceRepository invoiceRepository
 */
class CombinedInvoicesApi extends SalesForceApi
{

    public const REQUEST_NAME = 'combined_invoices';
    public const REQUEST = 'Invoice__c';


    public function __construct(
        EloquentInvoiceRepository $invoiceRepository,
        CombinedInvoiceTransformer $combinedInvoiceRepository
    ) {
        parent::__construct(app(GuzzleClient::class), app(CronLogRepository::class));
        $this->invoiceRepository = $invoiceRepository;
        $this->transformer = $combinedInvoiceRepository;
    }


    public function getData(array $recordIds = null) {
        $date = $this->getLastSyncDate(self::REQUEST_NAME);
        $records = $this->invoiceRepository->getCombinedInvoicesModifiedSince($date, $recordIds);
        return $records;
    }

    protected function sync(CombinedInvoice $combinedInvoice, string $action = null) {
        // find out what to do with combinedInvoice
        if ($action === null) {
            $date = $this->getLastSyncDate(self::REQUEST_NAME);
            $action = $this->getAction($combinedInvoice, $date);
        }
        $action = isset($combinedInvoice->salesforce_id) && !is_null($combinedInvoice->salesforce_id) && $action !== 'delete' ? 'update' : 'create';

        $this->loadRelations($combinedInvoice);
        $this->syncRelations($combinedInvoice, $action);

        // map data and execute the request
        switch($action) {
            case 'create':
                $combinedInvoiceData = $this->transformer->transformItem($combinedInvoice);
                $combinedInvoice->salesforce_id = $this->createRecord(self::REQUEST, $combinedInvoiceData);
                $this->updateFieldsAfterSync($combinedInvoice);
                break;
            case 'update':
                $combinedInvoiceData = $this->transformer->transformItem($combinedInvoice);
                $this->updateRecord(self::REQUEST, $combinedInvoice->salesforce_id, $combinedInvoiceData);
                $this->updateFieldsAfterSync($combinedInvoice);
                break;
            case 'delete':
                $this->deleteRecord(self::REQUEST, $combinedInvoice->salesforce_id);
                $combinedInvoice->salesforce_id = null;
                $this->updateFieldsAfterSync($combinedInvoice);
                break;
        }
        $this->syncInvoices($combinedInvoice, $action);

        return true;
    }

    private function loadRelations(CombinedInvoice $combinedInvoice): void
    {
        if (!$combinedInvoice->relationLoaded('billable')) {
            $combinedInvoice->load(['billable' => function($qb) {
                $qb->withTrashed();
            }]);
        }
        if (!$combinedInvoice->relationLoaded('invoices')) {
            $combinedInvoice->load(['invoices' => function($qb) {
                $qb->withTrashed();
            }]);
        }
    }

    private function syncRelations(CombinedInvoice $combinedInvoice, string $action = null): void
    {
        if ($combinedInvoice->billable->salesforce_id === null) {
            if ($combinedInvoice->billable instanceof Client) {
                $billableApi = app(ClientsApi::class);
                $billableApi->sync($combinedInvoice->billable, $action);
            }
        }
    }

    private function syncInvoices(CombinedInvoice $combinedInvoice, string $action = null): void
    {
        $invoicesApi = app(InvoicesApi::class);
        foreach ($combinedInvoice->invoices as $invoice) {
            $invoicesApi->sync($invoice, $action);
        }
    }

}
