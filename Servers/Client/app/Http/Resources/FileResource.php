<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class FileResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user_name' => $this->user_name,
            'file_path' => asset('storage/' . $this->file_path),
            'type' => $this->type,
            'created_at' => $this->created_at->toDateTimeString(),
        ];
    }
}
