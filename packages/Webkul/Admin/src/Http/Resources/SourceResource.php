<?php

namespace Webkul\Admin\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Webkul\Admin\Helpers\CommercialLabel;

class SourceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'display_name' => CommercialLabel::display($this->name, 'source'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
