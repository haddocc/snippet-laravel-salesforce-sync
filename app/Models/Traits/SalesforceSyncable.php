<?php
/**
 * @author Ogier Schelvis
 */

namespace App\Models\Traits;

use App\Helpers\CustomLog;
use Illuminate\Support\Facades\Log;

/**
 * Trait SalesforceSyncable
 * @package App\Models\Traits
 *
 * Makes models capable to sync realtime to SalesForce when creating, updating or deleting.
 * That was the original approach. Most of the times when creating or updating an object multiple operations
 * per HTTP request were performed on the model and data that was required by SalesForce for syncing
 * wasn't available yet. So I decided to introduce a shouldSync property which needs to be provided before saving
 * the model which triggers this trait.
 */

trait SalesforceSyncable
{
    protected static $salesforceApi;
    protected static $logger;
    protected static $logChannel = 'salesforce-sync';

    /**
     * This is a little bit of Eloquent magic called a bootable trait.
     * It was documented in Laravel 5.0 at https://laravel.com/docs/5.0/eloquent#global-scopes
     * Explanation:
     * | If you have a static function on your trait, named boot[TraitName],
     * | it will be executed as the boot() function would on an Eloquent model.
     * | Which is a handy place to register your model events.
     * (quote taken from https://laravel-news.com/booting-eloquent-model-traits)
     *
     * It enables you to execute specific operations on events for models which are using the same trait.
     */
    public static function bootSalesforceSyncable()
    {
        // We load the corresponding SalesForce syncing API
        // (statically, ofcourse. We don't want to flood the memory with object instances that are all the same)
        // Unfortunately there was never a link made between the API and the model
        // apart from the similar namespace which we use to load the proper API.
        // If we were to support this software any further (which we aren't going to)
        // then it would be good to think of something to solve this
        self::$salesforceApi = app('App\Models\SyncForce\Api\\'.class_basename(self::class).'sApi');
        self::$logger = new CustomLog();

        static::creating(function ($model) {
            if(isset($model->shouldSync)) {
                // You should unset this property because, after this event the model is actually saved.
                // and it tries to find a corresponding column in the database which it won't find and
                // throw an error
                unset($model->shouldSync);
                // I do not want a user to get an error on the screen because we
                // can't sync to SalesForce because of some weird anomaly.
                // So we silently catch any Exceptions and log them.
                try {
                    self::$salesforceApi->sync($model, 'create');
                } catch (\Exception $e) {
                    $msg = 'error creating ' . class_basename($model) . ': #' . $model->id . ' ' . $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine() . ' ' . $e->getTraceAsString();
                }
            }
        });

        static::updating(function ($model) {
            if(isset($model->shouldSync)){
                unset($model->shouldSync);
                try {
                    self::$salesforceApi->sync($model,isset($model->salesforce_id)&&!is_null($model->salesforce_id)?'create':'update');
                } catch (\Exception $e){
                    $msg = 'error updating ' . class_basename($model) . ': #'.$model->id.' '.$e->getMessage().' '.$e->getFile().':'.$e->getLine().' '.$e->getTraceAsString();
                    self::$logger->error(self::$logChannel, $msg);
                    Log::error($msg);
                }
            }
        });

        static::deleting(function ($model) {
            if(!is_null($model->salesforce_id)){
                try {
                    self::$salesforceApi->sync($model,'delete');
                    self::$logger
                        ->info(self::$logChannel,
                            'deleting ' . class_basename($model) . ': #'.$model->id);
                } catch (\Exception $e){
                    $msg = 'error deleting ' . class_basename($model) . ': #'.$model->id.' '.$e->getMessage().' '.$e->getFile().':'.$e->getLine().' '.$e->getTraceAsString();
                    self::$logger->error(self::$logChannel, $msg);
                    Log::error($msg);
                }
            }
        });
    }
}