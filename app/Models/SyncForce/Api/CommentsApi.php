<?php

namespace App\Models\SyncForce\Api;

use App\Models\Comments\Classes\Comment;
use App\Models\Comments\Interfaces\CommentInterface;
use App\Models\CronLog\Repositories\CronLogRepository;
use App\Models\SyncForce\Transformers\CommentTransformer;
use GuzzleHttp\Client as GuzzleClient;

/**
 * @property CommentInterface $commentInterface
 * @property CommentTransformer $transformer
 */
class CommentsApi extends SalesForceApi
{

    public const REQUEST = 'Case';
    public const REQUEST_NAME = 'comments';


    public function __construct(
        CommentInterface $commentInterface
    ) {
        parent::__construct(app(GuzzleClient::class), app(CronLogRepository::class));

        $this->commentInterface = $commentInterface;
        $this->transformer = new CommentTransformer();
    }

    protected function sync(Comment $comment) {

        $commentData = $this->transformer->transformItem($comment);
        $this->createRecord(self::REQUEST, $commentData);
        return true;
    }

}
