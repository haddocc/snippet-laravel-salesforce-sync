<?php

namespace App\Models\SyncForce\Api;

use App\Models\CronLog\Repositories\CronLogRepository;
use App\Models\Products\Classes\Product;
use App\Models\Products\Repositories\EloquentProductPackageRepository;
use App\Models\SyncForce\Transformers\ProductTransformer;
use GuzzleHttp\Client;

/**
 * @property EloquentProductPackageRepository productRepository
 * @property ProductTransformer productTransformer
 */
class ProductsApi extends SalesForceApi
{

    public const REQUEST_NAME = 'products';
    public const REQUEST = 'Product2';


    public function __construct(
        EloquentProductPackageRepository $productRepository,
        ProductTransformer $productTransformer
    ) {
        parent::__construct(app(Client::class), app(CronLogRepository::class));
        $this->productRepository = $productRepository;
        $this->productTransformer = $productTransformer;
    }


    public function getData(array $recordIds = null) {
        $date = $this->getLastSyncDate(self::REQUEST_NAME);
        $records = $this->productRepository->getProductsModifiedSince($date, $recordIds);
        return $records;
    }

    protected function sync(Product $product, string $action = null) {
        // find out what to do with product
        if ($action === null) {
            $date = $this->getLastSyncDate(self::REQUEST_NAME);
            $action = $this->getAction($product, $date);
        }

        // map data and execute the request
        switch($action) {
            case 'create':
                $productData = $this->productTransformer->transformItem($product);
                $product->salesforce_id = $this->createRecord(self::REQUEST, $productData);
                $this->updateFieldsAfterSync($product);
                break;
            case 'update':
                $productData = $this->productTransformer->transformItem($product);
                $this->updateRecord(self::REQUEST, $product->salesforce_id, $productData);
                $this->updateFieldsAfterSync($product);
                break;
            case 'delete':
                // will soft-delete using transformer
                $productData = $this->productTransformer->transformItem($product);
                $this->updateRecord(self::REQUEST, $product->salesforce_id, $productData);
                $this->updateFieldsAfterSync($product);
                break;
        }

        // now that the record exists we can link it to packages
        $this->loadRelations($product);
        $this->syncRelations($product);

        return true;
    }

    private function loadRelations(Product $product): void
    {
        if (!$product->relationLoaded('packages')) {
            $product->load(['packages' => function($qb) {
                $qb->withTrashed();
            }]);
        }
    }

    private function syncRelations(Product $product): void
    {
        // First check if linked packages are actually synced
        $packagesApi = app(PackagesApi::class);
        foreach($product->packages as $package) {
            if ($package->salesforce_id === null) {
                $packagesApi->sync($package, 'create');
            }
        }

        // now add the N:N link product-packages
        $productPackagesApi = app(ProductPackagesApi::class);
        $packages = $productPackagesApi->getData($product);
        $productPackagesApi->sync($product, $packages);
    }


}
