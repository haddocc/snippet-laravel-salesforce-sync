<?php

namespace App\Models\SyncForce\Transformers;



use League\Fractal\TransformerAbstract;

class ProductPackageTransformer extends TransformerAbstract
{

    public function transformItem($productPackage): array
    {
        return [
            'Product_Group__c' => $productPackage->package_salesforce_id,
            'Product__c' => $productPackage->product_salesforce_id,
        ];
    }

}
