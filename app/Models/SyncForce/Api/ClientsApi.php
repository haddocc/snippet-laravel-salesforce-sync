<?php

namespace App\Models\SyncForce\Api;


use App\Models\Clients\Models\Client;
use App\Models\Clients\Repositories\EloquentClientRepository;
use App\Models\CronLog\Repositories\CronLogRepository;
use App\Models\SyncForce\Transformers\ClientTransformer;
use GuzzleHttp\Client as GuzzleClient;

/**
 * @property EloquentClientRepository clientRepository
 * @property ClientTransformer clientTransformer
 */
class ClientsApi extends SalesForceApi
{

    public const REQUEST_NAME = 'clients';
    public const REQUEST = 'Account';

    public function __construct(
        EloquentClientRepository $clientRepository,
        ClientTransformer $clientTransformer
    ) {
        parent::__construct(app(GuzzleClient::class), app(CronLogRepository::class));
        $this->clientRepository = $clientRepository;
        $this->clientTransformer = $clientTransformer;
    }


    public function getData(array $recordIds = null) {
        $date = $this->getLastSyncDate(self::REQUEST_NAME);
        $records = $this->clientRepository->getModifiedSince($date, $recordIds);
        return $records;
    }

    /**
     * @param Client $client
     * @param string $action
     * @return bool
     * @throws \App\Models\SyncForce\Exceptions\NotSyncedYetException
     */
    protected function sync(Client $client, string $action = null) {
        // find out what to do with client
        if ($action === null) {
            $date = $this->getLastSyncDate(self::REQUEST_NAME);
            $action = $this->getAction($client, $date);
        }
        $action = isset($client->salesforce_id) && !is_null($client->salesforce_id) && $action !== 'delete' ? 'update' : 'create';

        $this->loadRelations($client);
        $this->syncRelations($client,$action);

        // map data and execute the request
        switch($action) {
            case 'create':
                $clientData = $this->clientTransformer->transformItem($client);
                $client->salesforce_id = $this->createRecord(self::REQUEST, $clientData);
                $this->updateFieldsAfterSync($client);
                break;
            case 'update':
                $clientData = $this->clientTransformer->transformItem($client);
                $this->updateRecord(self::REQUEST, $client->salesforce_id, $clientData);
                $this->updateFieldsAfterSync($client);
                break;
            case 'delete':
                $this->deleteRecord(self::REQUEST, $client->salesforce_id);
                $client->salesforce_id = null;
                $this->updateFieldsAfterSync($client);
                break;
            default:
                // nothing to do
                return false;
        }

        return true;
    }

    private function loadRelations(Client $client): void
    {
        $relations = [];
        $cb = function($qb) {
            $qb->withTrashed();
        };

        if (!$client->relationLoaded('address')) {
            $relations['address'] = $cb;
        }
        if (!$client->relationLoaded('client_users')) {
            $relations['client_users'] = $cb;
        }
        if (!$client->relationLoaded('employee')) {
            $relations['employee'] = $cb;
        }
        if (!empty($relations)) {
            $client->load($relations);
        }
    }

    private function syncRelations(Client $client, $action = null): void
    {
        // First sync address info so we've got an address-id
        $addressApi = app(AddressApi::class);
        $addressApi->sync($client->address, $action);
    }

}
