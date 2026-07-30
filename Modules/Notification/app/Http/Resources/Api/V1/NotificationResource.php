<?php

declare(strict_types=1);

namespace Modules\Notification\app\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;


class NotificationResource extends JsonResource
{


    public function toArray(Request $request): array
    {

        return [

            'id'=>$this->id,


            'title'=>$this->title,


            'body'=>$this->body,


            'type'=>$this->type,


            'data'=>$this->data,


            'is_read'=>
                !is_null($this->read_at),


            'read_at'=>$this->read_at,


            'created_at'=>$this->created_at,

        ];

    }

}