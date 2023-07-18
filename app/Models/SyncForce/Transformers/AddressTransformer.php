<?php

namespace App\Models\SyncForce\Transformers;


use App\Models\Addresses\Classes\Address;
use Illuminate\Database\Eloquent\RelationNotFoundException;
use League\Fractal\TransformerAbstract;

class AddressTransformer extends TransformerAbstract
{


    public function transformItem(Address $address): array
    {

        if ($address->addressFields === null) {
            $address->load('addressFields');
        }

        if ($address->addressFields === null) {
            throw new RelationNotFoundException('Address ' . $address->id . ' has no addressFields records where at least 1 is expected.');
        }

        $addressFields = $address->addressFields->pluck('value', 'key')->toArray();
        $street = isset($addressFields['street']) ? $addressFields['street'] : '';
        $houseNumber = (isset($addressFields['house_number']) && isset($addressFields['house_number_suffix'])) ? $addressFields['house_number'] . ' ' . $addressFields['house_number_suffix'] : '';
        $zipNumbers = isset($addressFields['postcode_numbers']) ? $addressFields['postcode_numbers'] : '';
        $zipLetters = isset($addressFields['postcode_letters']) ? $addressFields['postcode_letters'] : '';
        $city = isset($addressFields['city']) ? $addressFields['city'] : '';
        $hasOrder = $address->type === 'order';
        $order = $hasOrder ? $address->object->order : null;
        $orderId = $order->id ?? '';

        // prepend order id to address name if address is of type 'order'
        $addressName =  ( $hasOrder ? '#'.$orderId.' ' : '') . // then append the full address
                        $street . ' ' . $houseNumber.', '.$city;
        // to prevent api errors because the address name is too long we prepare a short variant of the name
        // which will always pass
        $shortAddressName = $hasOrder ?
            'Adres van order #'.$orderId :
            'Adres van '.substr($address->client->name,0,70);

        $data = [
            'Location__Latitude__s' => $address->latitude,
            'Location__Longitude__s' => $address->longitude,
            'Country_Id__c' => $address->country_id,
            'Name' => strlen($addressName) < 80 ?  $addressName : $shortAddressName,
            'Street__c' => strlen($street < 100) ? $street : '',
            'Housenumber__c' => strlen($houseNumber) < 10 ? $houseNumber : '',
            'Zipcode__c' => strlen($zipNumbers . $zipLetters) < 12 ? $zipNumbers . $zipLetters : '',
            'City__c' => strlen($city) < 100 ? $city : '',
        ];

        if($address->type === 'order'){
            $data['Order__c'] = $address->object->order->salesforce_id;
        }
        if($address->type === 'client'){
            $data['Account__c'] = $address->client->salesforce_id;
        }

        return $data;
    }

}
