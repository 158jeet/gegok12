<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class SendMail extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            //
            'subject'   =>  $this->subject,
            'message'   =>  $this->message,
            'sentAt'    =>  Carbon::parse($this->fired_at)->diffForHumans(),//$this->fired_at->diffForHumans(),
        ];
    }
}
