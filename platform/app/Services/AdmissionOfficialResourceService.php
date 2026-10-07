<?php

namespace App\Services;

use App\Models\{AdmissionExamDefinition, AdmissionOfficialResource, User};
use Illuminate\Support\Arr;
use InvalidArgumentException;

class AdmissionOfficialResourceService
{
    public function __construct(private AdmissionOfficialSourcePolicy $sourcePolicy) {}

    public function registerUrl(
        AdmissionExamDefinition $exam,
        array $attributes,
        ?User $creator = null
    ): AdmissionOfficialResource {
        $url = $this->sourcePolicy->normalizeUrl((string) ($attributes['source_url'] ?? ''));
        $listingUrl = isset($attributes['listing_url'])
            ? $this->sourcePolicy->normalizeUrl((string) $attributes['listing_url'])
            : null;
        $host = $this->sourcePolicy->assertAllowed($exam, $url, $listingUrl);
        $listingHost = $listingUrl
            ? $this->sourcePolicy->assertAuthorityUrl($exam, $listingUrl)
            : null;
        $resourceKind = (string) ($attributes['resource_kind'] ?? '');

        if (! in_array($resourceKind, $exam->resource_kinds ?? [], true)) {
            throw new InvalidArgumentException("Resource kind [{$resourceKind}] is not configured for {$exam->name}.");
        }

        $title = trim((string) ($attributes['title'] ?? ''));
        if ($title === '') {
            throw new InvalidArgumentException('An official resource title is required.');
        }

        $fingerprint = hash('sha256', $exam->code.'|'.$url);
        $resource = AdmissionOfficialResource::query()->firstOrCreate(
            [
                'admission_exam_definition_id' => $exam->id,
                'source_fingerprint' => $fingerprint,
            ],
            [
                'created_by' => $creator?->id,
                'title' => $title,
                'resource_kind' => $resourceKind,
                'exam_year' => Arr::get($attributes, 'exam_year'),
                'source_url' => $url,
                'listing_url' => $listingUrl,
                'source_host' => $host,
                'listing_host' => $listingHost,
                'status' => AdmissionOfficialResource::STATUS_REGISTERED,
                'published_on' => Arr::get($attributes, 'published_on'),
                'metadata' => Arr::get($attributes, 'metadata'),
                'supersedes_resource_id' => Arr::get($attributes, 'supersedes_resource_id'),
            ]
        );

        if ($resource->supersedes_resource_id) {
            $superseded = AdmissionOfficialResource::query()
                ->whereKey($resource->supersedes_resource_id)
                ->where('admission_exam_definition_id', $exam->id)
                ->first();
            if (! $superseded) {
                $resource->delete();
                throw new InvalidArgumentException('A replacement can only supersede a resource for the same exam.');
            }
        }

        return $resource;
    }
}
