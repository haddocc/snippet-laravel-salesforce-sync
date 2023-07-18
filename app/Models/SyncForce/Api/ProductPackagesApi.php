<?php

namespace App\Models\SyncForce\Api;

use App\Models\CronLog\Repositories\CronLogRepository;
use App\Models\Products\Classes\Product;
use App\Models\Products\Repositories\EloquentProductPackageRepository;
use App\Models\SyncForce\Transformers\ProductPackageTransformer;
use GuzzleHttp\Client;
use Illuminate\Support\Collection;

/**
 * @property EloquentProductPackageRepository $productPackageRepository
 * @property ProductPackageTransformer productPackageTransformer
 */
class ProductPackagesApi extends SalesForceApi
{

    public const REQUEST_NAME = 'product_packages';
    public const REQUEST = 'Product_Group_Item__c';



    public function __construct(
        EloquentProductPackageRepository $productPackageRepository,
        ProductPackageTransformer $productPackageTransformer
    ) {
        parent::__construct(app(Client::class), app(CronLogRepository::class));
        $this->productPackageRepository = $productPackageRepository;
        $this->productPackageTransformer = $productPackageTransformer;
    }


    public function getData(Product $product) {
        // just in case, could have been synced before packages->sync() was called.
        return $product->packages()->whereNotNull('salesforce_id')->get();
    }

    protected function sync(Product $product, Collection $packages) {

        // Get all linked SalesForce packages
        $sfProductPackages = self::getRecords(
            self::REQUEST,
            ['id', 'Product__c', 'Product_Group__c'],
            ['Product__c' => $product->salesforce_id]
        );

        // get ids from the arrays to avoid looping
        $packageIds = $packages->pluck('salesforce_id')->toArray();
        if (!empty($sfProductPackages)) {
            $sfPackageIds = array_column((array)$sfProductPackages, 'Product_Group__c');
        } else {
            $sfPackageIds = [];
        }
        // Find SalesForce records that don't exist anymore.
        foreach($sfProductPackages as $sfPackage) {
            if (!in_array($sfPackage->Product_Group__c, $packageIds)) {
                // delete
                $this->deleteRecord(ProductPackagesApi::REQUEST, $sfPackage->Id);
            }
        }
        // Find Packages not linked in SalesForce yet
        foreach($packageIds as $packageId) {
            if (!in_array($packageId, $sfPackageIds)) {
                // create
                $this->createRecord(ProductPackagesApi::REQUEST, ['Product_Group__c'=>$packageId, 'Product__c'=>$product->salesforce_id]);
            }
        }
    }

}
