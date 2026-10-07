<?php

namespace App\Services\GoogleSheets;

class GoogleSheetSyncManager extends GoogleSheetSyncService
{
    public function __construct(GoogleSheetsClient $client, GoogleSheetResourceRegistry $registry)
    {
        parent::__construct($client, $registry);
    }
}
