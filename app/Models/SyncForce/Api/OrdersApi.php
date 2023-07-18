<?php

namespace App\Models\SyncForce\Api;

use App\Models\CronLog\Repositories\CronLogRepository;
use App\Models\Orders\Classes\Order;
use App\Models\Orders\Repositories\OrderRepository;
use App\Models\SyncForce\Transformers\OrderTransformer;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Support\Facades\Log;

/**
 * @property OrderRepository orderRepository
 * @property OrderTransformer orderTransformer
 */
class OrdersApi extends SalesForceApi
{

    public const REQUEST_NAME = 'orders';
    public const REQUEST = 'Order';


    public function __construct(
        ?OrderRepository $orderRepository = null,
        ?OrderTransformer $orderTransformer = null
    ) {
        parent::__construct(app(Client::class), app(CronLogRepository::class));

        if($orderRepository) {
            $this->orderRepository = $orderRepository;
        }
        else {
            $this->orderRepository = app(OrderRepository::class);
        }

        if($orderTransformer) {
            $this->orderTransformer = $orderTransformer;
        } else {
            $this->orderTransformer = new OrderTransformer();
        }
    }

    public function syncById($orderId)
    {
        $order = Order::find($orderId);
        $this->sync($order, 'create');
    }

    public function getData(array $recordIds = null) {
        $date = $this->getLastSyncDate(self::REQUEST_NAME);
        $records = $this->orderRepository->getModifiedSince($date, $recordIds);
        return $records;
    }

    protected function sync(Order $order, $action = null) {
        // find out what to do with order
        if ($action === null) {
            $date = $this->getLastSyncDate(self::REQUEST_NAME);
            $action = $this->getAction($order, $date);
        }
        $action = isset($order->salesforce_id) && !is_null($order->salesforce_id) && $action !== 'delete' ? 'update' : 'create';


        $this->loadRelations($order);
        $this->syncRelations($order, $action);

        // map data and execute the request
        switch($action) {
            case 'create':
                $orderData = $this->orderTransformer->transformItem($order);
                $order->salesforce_id = $this->createRecord(self::REQUEST, $orderData);
                $this->updateFieldsAfterSync($order);
                break;
            case 'update':
                $orderData = $this->orderTransformer->transformItem($order);
                $this->updateRecord(self::REQUEST, $order->salesforce_id, $orderData);
                $this->updateFieldsAfterSync($order);
                break;
            case 'delete':
                $this->deleteRecord(self::REQUEST, $order->salesforce_id);
                $order->salesforce_id = null;
                $this->updateFieldsAfterSync($order);
                break;
        }

        $this->syncOrderLines($order, $action);

        return true;
    }

    private function loadRelations(Order $order): void
    {
        $relations = [];
        $cb = function($qb) {
            $qb->withTrashed();
        };

        if (!$order->relationLoaded('object')) {
            $relations['object'] = $cb;
            $relations['object.address'] = $cb;
        }
        if (!$order->relationLoaded('job_regions')) {
            $relations['job_regions'] = $cb;
        }
        if (!$order->relationLoaded('client')) {
            $relations['client'] = $cb;
        }

        $order->load($relations);
    }

    private function syncRelations(Order $order, $action = null)
    {
        $addressApi = app(AddressApi::class);
        $addressApi->sync($order->object->address, $action);

        // Can theoratically be null, if order->object->address can't be found in job_region_postcodes ranges.
        if ($order->job_regions !== null) {
            if ($order->job_regions->salesforce_id === null) {
                $jobRegionsApi = app(JobRegionsApi::class);
                $jobRegionsApi->sync($order->job_regions, 'create');
            }
        }

        if ($order->client->salesforce_id === null) {
            $clientsApi = app(ClientsApi::class);
            $clientsApi->sync($order->client, 'create');
        }
        $contact = $order->contact ?? $order->alternativeContact;
        if($contact !== null){
            try {
                $contactsApi = app(ContactsApi::class);
                $contactsApi->sync($contact, 'update');
            } catch(ClientException $e) { Log::error($e->getMessage()); }
        }
    }
    private function syncOrderLines(Order $order, $action = null)
    {
        $orderLinesApi = app(OrderLinesApi::class);
        foreach ($order->order_lines as $line){
            $orderLinesApi->sync($line,$action);
        }
    }
}
