<?php

namespace App\Models\SyncForce\Api;


use App\Models\Base\Model;
use App\Models\CronLog\Classes\CronLog;
use App\Models\CronLog\Repositories\CronLogRepository;
use App\Models\SyncForce\Exceptions\AlreadySyncedException;
use Carbon\Carbon;
use GuzzleHttp\Client;

/**
 * @property Client $guzzleClient
 * @property CronLogRepository $cronLogRepository
 */
abstract class SalesForceApi
{
    /**
     * TODO:: Move configuration to its own SalesForceConfig file, or combine with an appropriate existing one.
     */
    public const LOCALE = 'nl';

    public const REQUEST_ALL = 'all';

    public const ACTION_CREATE = 'create';
    public const ACTION_UPDATE = 'update';
    public const ACTION_DELETE = 'delete';

    private const AUTH_REQUEST = 'oauth2/token';
    private const REQUEST_PREFIX = 'services/data/v44.0/sobjects/';
    private const REQUEST_QUERY = 'services/data/v44.0/query/';

    static private $authenticatedToken = null;
    static private $authenticatedUrl = null;

    private $cronLog = null;

    public function __construct(Client $guzzleClient, CronLogRepository $cronLogRepository)
    {
        $this->guzzleClient = $guzzleClient;
        $this->cronLogRepository = $cronLogRepository;
    }


    protected function createRecord($request, array $data)
    {
        $options = $this->prepare($data);
        $result = $this->fire('POST', self::REQUEST_PREFIX . $request, $options);
        return $result->id;
    }


    protected function deleteRecord($request, string $salesForceId)
    {
        $options = $this->prepare();
        $result = $this->fire('DELETE', self::REQUEST_PREFIX . $request . '/' . $salesForceId, $options);
        return true;
    }


    public function updateRecord(string $request, string $salesForceId, array $data)
    {
        $options = $this->prepare($data);
        $result = $this->fire('PATCH', self::REQUEST_PREFIX . $request . '/' . $salesForceId, $options);
        return true;
    }

    /**
     * Query SalesForce for data
     * @param string $object
     * @param array $select
     * @param array $params
     * @return mixed
     */
    protected function getRecords(string $object, array $select, array $params)
    {
        $options = $this->prepare();
        /**
         * The idea is you provide a SOQL query urlencoded to the api endpoint
         * For readability I guess plus signs were used for spaces in the initial creation of this method
         * Note that that will only be valid as long as SalesForce accepts application/x-www-form-urlencoded
         * as content-type. If this should ever change I recommend converting the plus signs to %20 to get spaces.
         * Or else they will be interpreted as literal plus signs. But beware you do not do this conversion on the entire
         * query string, but just the parts between the $selectString, $object and $whereString
         * If you need a literal plus sign use %2B
         */
        $selectString = implode(',+',$select);
        $whereString = '';
        foreach($params as $param => $value){
            // if parameter value contains plus signs
            // (as is common in email addresses and telephone numbers)
            // convert them to url-safe entities
            $value = str_replace('+','%2B',$value);
            $whereString .= '+AND+'.$param . "+=+" . (is_string($value) ?  "'$value'": $value );
        }
        $request = self::REQUEST_QUERY . '?q=SELECT+'. $selectString .'+FROM+' . $object . '+WHERE+' . substr($whereString,5);
        // Note: we substract the "AND" from the first WHERE condition like this, because it is less
        // verbose than checking whether you are in the first iteration of the loop. Now that I'm typing
        // this I realise explaining this in a comment is a lot more verbose than that :)

        $result = $this->fire('GET', $request, $options);
        return $result->records;
    }


    private function fire(string $method, string $request, array $options)
    {
        $url = $this->getUrl() . '/' . $request;
        $response = $this->guzzleClient->request($method, $url, $options);
        $json = $response->getBody()->getContents();
        $response = json_decode($json);
        return $response;
    }


    /**
     * Prepares connection with the Request Endpoint and builds up the header options.
     * @param array $data
     * @return array $options
     */
    private function prepare(array $data = []): array
    {
        if (!self::isAuthenticated()) {
            $this->authenticate();
        }

        $headers = [
            'Authorization' => 'Bearer ' . self::$authenticatedToken,
            'Content-Type' => 'application/json',
        ];
        $options = [
            'headers' => $headers,
        ];
        if (!empty($data)) {
            $options['json'] = $data;
        }
        return $options;
    }

    /**
     * Get last date a particular entity was synced.
     *
     * We consider only non filtered logs, because else records that should be synced would be skipped
     * @param $request
     * @return Carbon
     */
    protected function getLastSyncDate($request): Carbon
    {
        $cronLog = $this->getCronLog($request);
        return $cronLog->created_at;
    }

    private function getCronLog($request): CronLog
    {
        if ($this->cronLog === null) {
            $this->cronLog = $this->cronLogRepository->getLastSynced($request);
        }
        return $this->cronLog;
    }

    static private function isAuthenticated(): bool
    {
        return (self::$authenticatedUrl !== null && self::$authenticatedToken !== null);
    }

    private function authenticate()
    {
        $url = self::getUrl() . '/' . self::AUTH_REQUEST;
        $options = [
            'grant_type' => 'password',
            'client_id' => config('salesforce.client_id'),
            'client_secret' => config('salesforce.client_secret'),
            'username' => config('salesforce.auth_username'),
            'password' => config('salesforce.auth_password'),
        ];
        $responseJson = $this->guzzleClient->post($url, ['form_params' => $options])->getBody()->getContents();
        $response = json_decode($responseJson);

        self::$authenticatedToken = $response->access_token;
        self::$authenticatedUrl = $response->instance_url;
    }

    static private function getUrl()
    {
        if (self::isAuthenticated()) {
            return self::$authenticatedUrl;
        }
        return config('salesforce.base_url');
    }

    /**
     * @param $record
     * @param Carbon $lastSyncDate
     * @return string
     * @throws AlreadySyncedException
     * @throws \Exception
     */
    protected function getAction($record, Carbon $lastSyncDate)
    {
        if ($record->salesforce_synced_at !== null && $record->salesforce_synced_at->gt($lastSyncDate)) {
            // happens if last sync couldn't be completed, and needs to be re-run
            // we'll skip this record for now and first handle the records that couldn't be synced.
            throw new AlreadySyncedException('Record ' . $record->id . ' has already been synced. Skipping this record to first handle the data that failed last run.');
        }

        if ($record->deleted_at !== null && $record->salesforce_id !== null && $record->deleted_at->gt($record->salesforce_synced_at)) {
            return self::ACTION_DELETE;
        }

        if ($record->deleted_at === null && $record->salesforce_id === null && $record->created_at->gt($record->salesforce_synced_at)) {
            return self::ACTION_CREATE;
        }

        if ($record->deleted_at === null && $record->salesforce_id !== null && $record->updated_at->gt($record->salesforce_synced_at)) {
            return self::ACTION_UPDATE;
        }

        // missing use-case? break off and solve it first.
        throw new \Exception('No action could be assigned to record ' . $record->id . '. Please fix the getAction or query conditions.');
    }

    /**
     * Operations that need to take place after a successful sync to SalesForce
     * @param $model
     * @param $action
     */
    protected function updateFieldsAfterSync($model)
    {
        $model->salesforce_synced_at = Carbon::now();
        $model->timestamps = false;
        $model->save();
    }

    public function __call($method, $arguments)
    {
        if (method_exists($this, $method)) {
            if($method === 'sync' && config('app.inTinker')) {
                $this->preSync($arguments[0]);
            }
            return call_user_func_array(array($this,$method), $arguments);
        }

        throw new \BadMethodCallException();
    }

    /**
     * this helps us track progress if we're fixing some sync with tinker
     * @param $model
     */
    protected function preSync($model)
    {
        dump('Starting sync of '.get_class($model).' #'.($model->id??'N/A'));
    }
}
