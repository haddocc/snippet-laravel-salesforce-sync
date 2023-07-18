<?php

namespace App\Models\SyncForce\Api;


use App\Helpers\CustomLog;
use App\Models\CronLog\Repositories\CronLogRepository;
use App\Models\Orders\Classes\Contact;
use App\Models\SyncForce\Transformers\ContactTransformer;
use GuzzleHttp\Client;

/**
 * @property ContactTransformer contactTransformer
 */
class ContactsApi extends SalesForceApi
{

    public const REQUEST_NAME = 'contact';
    public const REQUEST = 'Contact';

    public function __construct(
        ContactTransformer $contactTransformer
    ) {
        parent::__construct(app(Client::class), app(CronLogRepository::class));
        $this->contactTransformer = $contactTransformer;
    }

    protected function sync(Contact $contact, string $action) {
        $duplicate = $this->getRecords(self::REQUEST,['id'],['Email' => $contact->email, 'Phone'=> $contact->telephone_number]);
        $hasDuplicate = isset($duplicate[0]);
        if(!$hasDuplicate){
            $duplicate = $this->getRecords(self::REQUEST,['id'],['Email' => $contact->email]);
            $hasDuplicate = isset($duplicate[0]);
        }
        $action = isset($contact->salesforce_id) && !is_null($contact->salesforce_id) && $action !== 'delete' ? 'update' : 'create';
        // If somehow there is a duplicate and we do not have a salesforce id locally we need to update it ofcourse
        $contact->salesforce_id =
            $hasDuplicate && is_null($contact->salesforce_id) ?
                $duplicate[0]->Id : $contact->salesforce_id;
        switch($action) {
            case 'create':
                $contactData = $this->contactTransformer->transformItem($contact); // todo: this can be abstracted in parent method
                if($hasDuplicate){
                    $this->updateRecord(self::REQUEST, $duplicate[0]->Id, $contactData);
                    $contact->salesforce_id = $duplicate[0]->Id;
                } else {
                    $contact->salesforce_id = $this->createRecord(self::REQUEST, $contactData);
                }
                $this->updateFieldsAfterSync($contact);
                break;
            case 'update':
                $contactData = $this->contactTransformer->transformItem($contact);
                $this->updateRecord(self::REQUEST, $contact->salesforce_id, $contactData);
                $this->updateFieldsAfterSync($contact);
                break;
            case 'delete':
                $this->deleteRecord(self::REQUEST, $contact->salesforce_id);
                break;
            default:
                // nothing to do
                return false;
        }
        return true;
    }
}
