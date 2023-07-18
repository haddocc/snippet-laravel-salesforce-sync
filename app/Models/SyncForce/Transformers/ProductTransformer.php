<?php

namespace App\Models\SyncForce\Transformers;


use App\Models\Products\Classes\Product;
use App\Models\SyncForce\Api\SalesForceApi;
use League\Fractal\TransformerAbstract;

class ProductTransformer extends TransformerAbstract
{

    public function transformItem(Product $product): array
    {
        $locale = SalesForceApi::LOCALE;
        $product->setTranslatable([]);
        return [
            'Product_Id__c' => $product->id,
            'name' => $product->name[$locale],
            'Description' => $product->description[$locale],
            'isActive' => ($product->deleted_at === null),
            'Base_Price_Original__c' => json_encode($product->base_price),
            'Description_Original__c' => json_encode($product->description),
            'Name_Original__c' => json_encode($product->name),
        ];
    }

}
