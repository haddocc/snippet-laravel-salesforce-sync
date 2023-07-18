<?php

namespace App\Models\SyncForce\Api;

use App\Models\CronLog\Repositories\CronLogRepository;
use App\Models\Orders\Classes\OrderLine;
use App\Models\Orders\Repositories\OrderRepository;
use App\Models\SyncForce\Transformers\OrderLineTransformer;
use GuzzleHttp\Client;

/**
 * @property OrderRepository repository
 * @property OrderLineTransformer orderLineTransformer
 */
class OrderLinesApi extends SalesForceApi
{

    public const REQUEST_NAME = 'order_lines';
    public const REQUEST = 'OrderItem';


    public function __construct(
        OrderRepository $repository,
        OrderLineTransformer $orderLineTransformer
    ) {
        parent::__construct(app(Client::class), app(CronLogRepository::class));
        $this->repository = $repository;
        $this->orderLineTransformer = $orderLineTransformer;
    }


    public function getData(array $recordIds = null) {
        $date = $this->getLastSyncDate(self::REQUEST_NAME);
        $records = $this->repository->getOrderLinesModifiedSince($date, $recordIds);
        return $records;
    }

    protected function sync(OrderLine $orderLine, string $action = null) {
        if ($action === null) {
            // find out what to do with orderLine
            $date = $this->getLastSyncDate(self::REQUEST_NAME);
            $action = $this->getAction($orderLine, $date);
        }

        $action = isset($orderLine->salesforce_id) && !is_null($orderLine->salesforce_id) && $action !== 'delete' ? 'update' : 'create';
        
        $this->loadRelations($orderLine);
        $this->syncRelations($orderLine);
        
        // map data and execute the request
        switch($action) {
            case 'create':
                $orderLineData = $this->orderLineTransformer->transformItem($orderLine);
                $orderLine->salesforce_id = $this->createRecord(self::REQUEST, $orderLineData);
                $this->updateFieldsAfterSync($orderLine);
                break;
            case 'update':
                $orderLineData = $this->orderLineTransformer->transformItem($orderLine);
                // read-only fields. TODO:: Add seperate update/create transformers.
                unset($orderLineData['PricebookEntryId']);
                unset($orderLineData['OrderId']);
                $this->updateRecord(self::REQUEST, $orderLine->salesforce_id, $orderLineData);
                $this->updateFieldsAfterSync($orderLine);
                break;
            case 'delete':
                $this->deleteRecord(self::REQUEST, $orderLine->salesforce_id);
                $orderLine->salesforce_id = null;
                $this->updateFieldsAfterSync($orderLine);
                break;
        }

        return true;
    }
    
    private function loadRelations(OrderLine $orderLine): void
    {
        $relations = [];
        $cb = function($qb) {
            $qb->withTrashed();
        };
        if (!$orderLine->relationLoaded('order')) {
            $relations['order'] = $cb;
        }
        if (!$orderLine->relationLoaded('product')) {
            $relations['product'] = $cb;
        }
        if (!$orderLine->relationLoaded('package')) {
            $relations['package'] = $cb;
        }

        if (!empty($relations)) {
            $orderLine->load($relations);
        }
    }
    
    private function syncRelations(OrderLine $orderLine): void
    {
        if ($orderLine->product->salesforce_id === null) {
            $productApi = app(ProductsApi::class);
            $productApi->sync($orderLine->product, 'create');
        }
        
        if ($orderLine->package !== null && $orderLine->package->salesforce_id === null) {
            $packageApi = app(PackagesApi::class);
            $packageApi->sync($orderLine->package, 'create');
        }
        
    }

}
