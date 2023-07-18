<?php

namespace App\Models\SyncForce\Api;


use App\Models\Addresses\Classes\Address;
use App\Models\CronLog\Repositories\CronLogRepository;
use App\Models\SyncForce\Transformers\AddressTransformer;
use GuzzleHttp\Client;

/**
 * @property AddressTransformer addressTransformer
 */
class AddressApi extends SalesForceApi
{

    public const REQUEST_NAME = 'address';
    public const REQUEST = 'Address__c';

    public function __construct(
        AddressTransformer $addressTransformer
    ) {
        parent::__construct(app(Client::class), app(CronLogRepository::class));
        $this->addressTransformer = $addressTransformer;
    }

    protected function sync(Address $address, string $action) {
        $action = isset($address->salesforce_id) && !is_null($address->salesforce_id) && $action !== 'delete' ? 'update' : 'create';

        switch($action) {
            case 'create':
                $addressData = $this->addressTransformer->transformItem($address);
                $address->salesforce_id = $this->createRecord(self::REQUEST, $addressData);
                $this->updateFieldsAfterSync($address);
                break;
            case 'update':
                $addressData = $this->addressTransformer->transformItem($address);
                $this->updateRecord(self::REQUEST, $address->salesforce_id, $addressData);
                $this->updateFieldsAfterSync($address);
                break;
            case 'delete':
                $this->deleteRecord(self::REQUEST, $address->salesforce_id);
                $address->salesforce_id = null;
                $this->updateFieldsAfterSync($address);
                break;
            default:
                // nothing to do
                return false;
        }
        return true;
    }
}
