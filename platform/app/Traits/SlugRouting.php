<?php

namespace App\Traits;

trait SlugRouting
{
    public function resolveRouteBinding($value, $field = null)
    {
        if (is_numeric($value)) {
            return $this->where('id', $value)->first();
        }
        return $this->where('slug', $value)->first();
    }
}
