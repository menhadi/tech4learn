<?php

return [
    /**
     * Manifest file ka URL.
     * Hum Laragon pe test kar rahe hain, isliye local file path use karenge.
     * Yeh 'C:\my_local_updates' folder se manifest.json padhega.
     */
    'manifest_url' => 'file://C:/my_local_updates/manifest.json',

    /**
     * Temp folder jahan update zip download aur extract hogi.
     * (Aapki service file isse use karti hai)
     */
    'storage_path' => storage_path('app/updater'),
];