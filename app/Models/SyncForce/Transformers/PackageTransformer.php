<?php

namespace App\Models\SyncForce\Transformers;



use App\Models\Products\Classes\Package;
use App\Models\SyncForce\Api\SalesForceApi;
use League\Fractal\TransformerAbstract;

class PackageTransformer extends TransformerAbstract
{

    public function transformItem(Package $package): array
    {
        $package->setTranslatable([]);
        $locale = SalesForceApi::LOCALE;
        return [
            'name' => $package->name[$locale],
            'Description__c' => $package->description[$locale],
            'Base_Price__c' => $package->base_price[$locale],
            'Base_Price_Original__c' => json_encode($package->base_price),
            'Description_Original__c' => json_encode($package->description),
            'Name_Original__c' => json_encode($package->name),
        ];
    }

}
