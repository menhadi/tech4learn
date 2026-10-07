<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\ModelNotFoundException;

trait Slugable
{
    /**
     * Find model by slug or ID
     */
    public function findByIdentifier($identifier, $modelClass)
    {
        // Try by slug first
        $record = $modelClass::where('slug', $identifier)->first();
        
        // If not found, try by ID
        if (!$record && is_numeric($identifier)) {
            $record = $modelClass::find($identifier);
        }
        
        if (!$record) {
            abort(404, 'Record not found');
        }
        
        return $record;
    }
}
