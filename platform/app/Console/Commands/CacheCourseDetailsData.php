<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Package;
use App\Models\Configuration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;

class CacheCourseDetailsData extends Command
{
    protected $signature = 'cache:course-details {packageId?}';
    protected $description = 'Pre-cache course details view data for performance';

    public function handle()
    {
        $packageId = $this->argument('packageId');
        
        if ($packageId) {
            $packages = Package::where('id', $packageId)->get();
        } else {
            $packages = Package::with('exams')->where('status', 'active')->get();
        }
        
        $configuration = Configuration::first();
        $bar = $this->output->createProgressBar(count($packages));
        $bar->start();
        
        foreach ($packages as $package) {
            $similarPackages = Package::where('id', '!=', $package->id)
                ->where('category_level_1', $package->category_level_1)
                ->limit(4)
                ->get();
            
            $groupId = null;
            
            $data = [
                'package' => $package,
                'configuration_detail' => $configuration,
                'similarPackages' => $similarPackages,
                'groupId' => $groupId,
                'enrollmentCount' => $package->enrollments()->count(),
            ];
            
            $html = View::make('website.course-detail', $data)->render();
            Cache::put('course_detail_' . $package->id, $html, now()->addHours(24));
            
            $bar->advance();
        }
        
        $bar->finish();
        $this->info("\n✓ Cached " . count($packages) . " course detail pages successfully!");
    }
}
