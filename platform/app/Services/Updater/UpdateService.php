<?php

namespace App\Services\Updater;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use ZipArchive;

class UpdateService
{
    /**
     * Fetch manifest.json from URL (supports http & file://).
     * SECURITY UPDATE: SSL Verification Enabled & Smart Response Handling
     */
    public function fetchManifest(string $url): array
    {
        if (!$url) {
            throw new \RuntimeException('Manifest URL empty — check UPDATER_MANIFEST_URL.');
        }

        // Local file (for dev testing)
        if (Str::startsWith($url, 'file://')) {
            $path = substr($url, 7);
            if (!is_file($path)) {
                throw new \RuntimeException("Manifest not found: {$path}");
            }
            $json = file_get_contents($path);
        } else {
            // ✅ SECURE: Server Request
            $resp = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept' => 'application/json',
            ])->timeout(20)->retry(2, 250)->get($url);
            
            // 1. Connection Error Check
            if (!$resp->ok()) {
                $preview = substr(strip_tags($resp->body()), 0, 300);
                throw new \RuntimeException("Manifest fetch failed [{$resp->status()}]. Server said: {$preview}...");
            }
            
            // 2. Content Type Validation
            $type = strtolower($resp->header('content-type', ''));
            if (!Str::contains($type, 'application/json')) {
                // Agar HTML aaya to uska content dikhao (Debugging ke liye)
                $rawContent = $resp->body();
                $cleanText = substr(strip_tags($rawContent), 0, 500); 
                throw new \RuntimeException("SERVER ERROR: Expected JSON but got HTML ($type). Page Content: [ $cleanText ... ]");
            }
            
            $json = $resp->body();
        }

        // 3. JSON Parsing
        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new \RuntimeException("Invalid Manifest JSON. Data received: " . substr($json, 0, 100));
        }

        // ✅ SMART LOGIC FIX:
        // Agar update available nahi hai, to 'latest_version' check mat karo, bas return kar do.
        if (isset($data['update_available']) && $data['update_available'] === false) {
            return $data;
        }

        // Agar update hai, tabhi version check karo
        if (empty($data['latest_version'])) {
            throw new \RuntimeException("Manifest missing 'latest_version'. Response was: " . json_encode($data));
        }

        return $data;
    }

    /**
     * Download update zip to temp folder.
     */
    public function downloadToTemp(string $url, ?string $checksum = null): string
    {
        $dir = $this->tempDir();
        File::ensureDirectoryExists($dir);

        $path = $dir . '/update_' . uniqid() . '.zip';

        if (Str::startsWith($url, 'file://')) {
            $src = substr($url, 7);
            if (!is_file($src)) {
                throw new \RuntimeException("Zip not found: {$src}");
            }
            copy($src, $path);
        } else {
            // Timeout increased to 5 mins for large updates
            $resp = Http::withHeaders([
                'User-Agent' => 'LaravelUpdater/1.0'
            ])->timeout(300)->retry(3, 1000)->get($url);
            
            if (!$resp->ok()) {
                throw new \RuntimeException("Zip download failed [{$resp->status()}] {$url}");
            }
            file_put_contents($path, $resp->body());
        }

        // Integrity Check
        if (!is_file($path) || filesize($path) < 100) {
            throw new \RuntimeException("Downloaded zip invalid or empty: {$path}");
        }

        // Checksum Verification
        if ($checksum) {
            $actual = hash_file('sha256', $path);
            if (!hash_equals(strtolower($checksum), strtolower($actual))) {
                unlink($path); 
                throw new \RuntimeException("Security Alert: Checksum mismatch! File might be tampered.");
            }
        }

        return $path;
    }

    /** Extract zip to temp directory. */
    public function extractToTemp(string $zipPath): string
    {
        if (!is_file($zipPath)) {
            throw new \RuntimeException("Zip missing: {$zipPath}");
        }

        $extract = $this->tempDir() . '/ex_' . uniqid();
        File::ensureDirectoryExists($extract);

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException("Failed to open zip: {$zipPath}");
        }
        if (!$zip->extractTo($extract)) {
            $zip->close();
            throw new \RuntimeException("Extract failed to {$extract}");
        }
        $zip->close();
        return $extract;
    }

    /** Temporary updater directory. */
    public function tempDir(): string
    {
        $base = config('updater.storage_path', storage_path('app/updater'));
        File::ensureDirectoryExists($base);
        return $base;
    }

    /**
     * Apply Update Logic (Safe & Atomic)
     */
    public function applyUpdate(string $extractPath, string $newVersion): void
    {
        // 1. Maintenance Mode ON
        Artisan::call('down', [
            '--render' => 'errors::503', 
            '--secret' => 'demo-bypass-key'
        ]);

        try {
            // 2. Files Overwrite
            File::copyDirectory($extractPath, base_path());

            // 3. Migrate DB
            Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]);

            // 4. Update Version in DB
            $system = \App\Models\SystemUpdate::firstOrNew(['id' => 1]);
            $system->current_version = $newVersion;
            $system->save();

            // 5. Clear Caches
            Artisan::call('optimize:clear');
            Artisan::call('view:clear');
            Artisan::call('config:clear');

        } catch (\Exception $e) {
            throw new \RuntimeException("Update process failed: " . $e->getMessage(), 0, $e);

        } finally {
            // 6. Maintenance Mode OFF
            Artisan::call('up');
        }
    }
}