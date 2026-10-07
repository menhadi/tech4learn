<?php

namespace App\Http\Controllers;

use App\Models\Counter;
use App\Models\Features;
use App\Models\Testimonial;
use App\Models\Titles;
use Illuminate\Support\Facades\Schema;

class HomepageContentController extends Controller
{
    private function tenantId(): ?int
    {
        return class_exists(\App\Support\Tenant::class)
            ? (\App\Support\Tenant::hostId(request()->getHost()) ?: \App\Support\Tenant::id())
            : null;
    }

    private function tenantCollection(string $modelClass)
    {
        $model = new $modelClass;
        $query = $modelClass::query();
        if ($this->tenantId() && Schema::hasColumn($model->getTable(), 'organization_id')) {
            $query->where('organization_id', $this->tenantId());
        }
        return $query->orderBy('id')->get();
    }

    private function sectionTitle(string $key, int $legacyId): ?Titles
    {
        $query = Titles::query();
        if (Schema::hasColumn('titles', 'section_key')) {
            $query->where('section_key', $key);
        } else {
            $query->where('id', $legacyId);
        }

        if ($this->tenantId() && Schema::hasColumn('titles', 'organization_id')) {
            $tenantTitle = (clone $query)->where('organization_id', $this->tenantId())->first();
            if ($tenantTitle) {
                return $tenantTitle;
            }
        }
        return $query->first();
    }

    public function index()
    {
        return view('homepage_content.index', [
            'features' => $this->tenantCollection(Features::class),
            'counters' => $this->tenantCollection(Counter::class),
            'testimonials' => $this->tenantCollection(Testimonial::class),
            'featureTitle' => $this->sectionTitle('features', 1),
            'testimonialTitle' => $this->sectionTitle('testimonials', 2),
            'counterTitle' => $this->sectionTitle('counters', 6),
            'languages' => ['en' => 'English', 'hi' => 'Hindi'],
        ]);
    }
}