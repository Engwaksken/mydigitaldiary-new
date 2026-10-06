<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DailyPlannerResource extends JsonResource
{
    // Preserve the existing mobile response shape (no additional data envelope).
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return is_array($this->resource) ? $this->resource : $this->resource->toArray();
    }
}
