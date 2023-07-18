<?php

namespace App\Models\SyncForce\Transformers;


use App\Models\Addresses\Classes\JobRegion;
use League\Fractal\TransformerAbstract;

class JobRegionTransformer extends TransformerAbstract
{


    public function transformItem(JobRegion $jobRegion): array
    {
        return [
            'name' => $jobRegion->name,
            'Address__Latitude__s' => $jobRegion->address_latitude,
            'Address__Longitude__s' => $jobRegion->address_longitude,
            'Country_Id__c' => $jobRegion->country_id,
            'Radius__c' => $jobRegion->radius,
        ];
    }

}
