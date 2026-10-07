<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\Group;
use App\Models\Package;
use App\Support\CategoryHierarchy;
use Illuminate\Validation\ValidationException;

class ExamScopeService
{
    public function resolve(
        int $organizationId,
        array $packageIds,
        array $manualGroupIds,
        ?int $categoryId,
        ?int $subcategoryId
    ): array {
        $packageIds = collect($packageIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();
        $manualGroupIds = collect($manualGroupIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();
        $packages = Package::where('organization_id', $organizationId)
            ->whereIn('id', $packageIds)
            ->with('groups:id')
            ->get();

        if ($packages->count() !== $packageIds->count()) {
            throw ValidationException::withMessages(['packages' => 'One or more selected packages are invalid.']);
        }

        if ($packages->isNotEmpty()) {
            $groupIds = $packages->flatMap(fn (Package $package) => $package->groups->pluck('id'))
                ->map(fn ($id) => (int) $id)->filter()->unique()->values();
            if ($groupIds->isEmpty()) {
                throw ValidationException::withMessages(['packages' => 'Every selected package must belong to at least one group.']);
            }

            foreach ($packages as $package) {
                CategoryHierarchy::validate(
                    $package->category_level_1 ? (int) $package->category_level_1 : null,
                    $package->category_level_2 ? (int) $package->category_level_2 : null,
                    $package->groups->pluck('id')->all(),
                    $organizationId
                );
            }

            return [
                'package_ids' => $packageIds->all(),
                'group_ids' => $groupIds->all(),
                'category_level_1' => null,
                'category_level_2' => null,
                'derived' => true,
            ];
        }

        if ($manualGroupIds->isEmpty()) {
            throw ValidationException::withMessages(['groups' => 'Select at least one group for a standalone exam.']);
        }
        if (Group::where('organization_id', $organizationId)->whereIn('id', $manualGroupIds)->count() !== $manualGroupIds->count()) {
            throw ValidationException::withMessages(['groups' => 'One or more selected groups are invalid.']);
        }
        CategoryHierarchy::validate($categoryId, $subcategoryId, $manualGroupIds->all(), $organizationId);

        return [
            'package_ids' => [],
            'group_ids' => $manualGroupIds->all(),
            'category_level_1' => $categoryId,
            'category_level_2' => $subcategoryId,
            'derived' => false,
        ];
    }

    public function sync(Exam $exam, array $scope): void
    {
        $exam->packages()->sync($scope['package_ids']);
        $exam->groups()->sync($scope['group_ids']);
        $exam->forceFill([
            'category_level_1' => $scope['category_level_1'],
            'category_level_2' => $scope['category_level_2'],
        ])->saveQuietly();
    }
}
