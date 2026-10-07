<?php

namespace App\Http\Controllers;

use App\Services\Updater\UpdateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use App\Models\SystemUpdate;

class UpdateController extends Controller
{
    /**
     * Show update page.
     */
    public function index()
    {
        return view('admin.update.index');
    }

    /**
     * Helper to build the API URL
     * (Ye function purane code me missing tha)
     */
    private function getUpdateApiUrl($currentVersion)
    {
        // .env se URL aur Key uthayega
        $baseUrl = rtrim(env('LICENSE_SERVER_URL', 'https://updates.examcentrelive.com'), '/');
        $licenseKey = env('LICENSE_KEY');
        $domain = request()->getHost(); // Aapka domain (examframe.com)

        if (!$licenseKey) {
            // Agar key nahi mili to error dega
            throw new \RuntimeException('LICENSE_KEY is missing in .env file.');
        }

        // Server API Endpoint banata hai
        return "{$baseUrl}/api/check-update?purchase_code={$licenseKey}&domain={$domain}&current_version={$currentVersion}";
    }

    /**
     * Check for update and return JSON result.
     */
    public function check(UpdateService $svc)
    {
        try {
            // 1. Current Version nikalo
            $current = optional(SystemUpdate::first())->current_version
                ?? (config('app.version') ?: env('APP_VERSION', 'v1.0.0'));

            // 2. Server URL banao (Key + Domain ke sath)
            // YAHAN par change hua hai 👇
            $apiUrl = $this->getUpdateApiUrl($current);

            // 3. Server se data mango
            $manifest = $svc->fetchManifest($apiUrl);

            // 4. Response process karo
            $latest = $manifest['latest_version'] ?? $current;
            $updateAvailable = $manifest['update_available'] ?? false;

            return response()->json([
                'ok'           => true,
                'current'      => $current,
                'latest'       => $latest,
                'needs_update' => $updateAvailable,
                'notes'        => $manifest['changelog'] ?? 'Security & Performance Updates',
                'debug'        => config('app.debug') ? [
                    'server_url' => $apiUrl,
                ] : null,
            ]);

        } catch (\Throwable $e) {
            Log::error('Updater check failed: ' . $e->getMessage());
            return response()->json([
                'ok'      => false,
                'message' => 'Check Failed: ' . $e->getMessage(),
            ], 200);
        }
    }

    /**
     * Apply the update.
     */
    public function apply(UpdateService $updater)
    {
        $zipPath = null;
        $extractPath = null;

        try {
            $current = optional(SystemUpdate::first())->current_version
                ?? (config('app.version') ?: env('APP_VERSION', 'v1.0.0'));

            $apiUrl = $this->getUpdateApiUrl($current);
            $manifest = $updater->fetchManifest($apiUrl);

            // Hamara server 'download_url' bhejta hai
            $downloadUrl = $manifest['download_url'] ?? ($manifest['update_url'] ?? null);
            $newVersion = $manifest['latest_version'];

            if (empty($downloadUrl)) {
                throw new \RuntimeException('Server did not provide a download URL. Check License.');
            }

            // Secure Download
            $zipPath = $updater->downloadToTemp($downloadUrl);
            $extractPath = $updater->extractToTemp($zipPath);
            $updater->applyUpdate($extractPath, $newVersion);
            
            // Cleanup
            if ($zipPath) @unlink($zipPath);
            if ($extractPath) File::deleteDirectory($extractPath);

            return response()->json([
                'ok' => true, 
                'message' => "System successfully updated to {$newVersion}!"
            ]);

        } catch (\Exception $e) {
            if ($zipPath) @unlink($zipPath);
            if ($extractPath) File::deleteDirectory($extractPath);

            Log::error('UPDATE FAILED: ' . $e->getMessage());
            return response()->json([
                'ok' => false, 
                'message' => 'UPDATE FAILED: ' . $e->getMessage()
            ], 200);
        }
    }
}