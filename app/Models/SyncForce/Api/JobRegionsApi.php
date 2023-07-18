<?php

namespace App\Models\SyncForce\Api;


use App\Models\Addresses\Classes\JobRegion;
use App\Models\Addresses\Repositories\EloquentJobRegionRepository;
use App\Models\CronLog\Repositories\CronLogRepository;
use App\Models\SyncForce\Transformers\JobRegionTransformer;
use GuzzleHttp\Client;

/**
 * @property EloquentJobRegionRepository jobRegionRepository
 * @property JobRegionTransformer jobRegionTransformer
 */
class JobRegionsApi extends SalesForceApi
{


    public const REQUEST_NAME = 'job_regions';
    public const REQUEST = 'Job_Region__c';


    public function __construct(
        EloquentJobRegionRepository $jobRegionRepository,
        JobRegionTransformer $jobRegionTransformer
    ) {
        parent::__construct(app(Client::class), app(CronLogRepository::class));
        $this->jobRegionRepository = $jobRegionRepository;
        $this->jobRegionTransformer = $jobRegionTransformer;
    }


    public function getData(array $recordIds = null) {
        $date = $this->getLastSyncDate(self::REQUEST_NAME);
        $records = $this->jobRegionRepository->getModifiedSince($date, $recordIds);
        return $records;
    }


    protected function sync(JobRegion $jobRegion, $action = null) {
        // find out what to do with jobRegion
        if ($action === null) {
            $date = $this->getLastSyncDate(self::REQUEST_NAME);
            $action = $this->getAction($jobRegion, $date);
        }

        // map data and execute the request
        switch($action) {
            case 'create':
                $jobRegionData = $this->jobRegionTransformer->transformItem($jobRegion);
                $jobRegion->salesforce_id = $this->createRecord(self::REQUEST, $jobRegionData);
                $this->updateFieldsAfterSync($jobRegion);
                break;
            case 'update':
                $jobRegionData = $this->jobRegionTransformer->transformItem($jobRegion);
                $this->updateRecord(self::REQUEST, $jobRegion->salesforce_id, $jobRegionData);
                $this->updateFieldsAfterSync($jobRegion);
                break;
            case 'delete':
                $this->deleteRecord(self::REQUEST, $jobRegion->salesforce_id);
                $jobRegion->salesforce_id = null;
                $this->updateFieldsAfterSync($jobRegion);
                break;
            default:
                // nothing to do
                return false;
        }

        return true;
    }

}
