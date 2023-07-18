<?php

namespace App\Models\SyncForce\Api;

use App\Models\CronLog\Repositories\CronLogRepository;
use App\Models\Products\Classes\Package;
use App\Models\Products\Repositories\EloquentProductPackageRepository;
use App\Models\SyncForce\Transformers\PackageTransformer;
use GuzzleHttp\Client;

/**
 * @property PackageTransformer productPackageTransformer
 * @property EloquentProductPackageRepository productPackageRepository
 */
class PackagesApi extends SalesForceApi
{

    public const REQUEST_NAME = 'packages';
    public const REQUEST = 'Product_Group__c';


    public function __construct(
        EloquentProductPackageRepository $productPackageRepository,
        PackageTransformer $packageTransformer
    ) {
        parent::__construct(app(Client::class), app(CronLogRepository::class));
        $this->productPackageRepository = $productPackageRepository;
        $this->productPackageTransformer = $packageTransformer;
    }


    public function getData(array $recordIds = null) {
        $date = $this->getLastSyncDate(self::REQUEST_NAME);
        $records = $this->productPackageRepository->getPackagesModifiedSince($date, $recordIds);
        return $records;
    }

    protected function sync(Package $package, string $action = null) {
        // find out what to do with productPackage
        if ($action === null) {
            $date = $this->getLastSyncDate(self::REQUEST_NAME);
            $action = $this->getAction($package, $date);
        }

        // map data and execute the request
        switch($action) {
            case 'create':
                $packageData = $this->productPackageTransformer->transformItem($package);
                $package->salesforce_id = $this->createRecord(self::REQUEST, $packageData);
                $this->updateFieldsAfterSync($package);
                break;
            case 'update':
                $packageData = $this->productPackageTransformer->transformItem($package);
                $this->updateRecord(self::REQUEST, $package->salesforce_id, $packageData);
                $this->updateFieldsAfterSync($package);
                break;
            case 'delete':
                $this->deleteRecord(self::REQUEST, $package->salesforce_id);
                $package->salesforce_id = null;
                $this->updateFieldsAfterSync($package);
                break;
        }

        return true;
    }

}
