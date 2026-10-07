<?php

namespace App\Support;

use App\Models\Category;
use Illuminate\Validation\ValidationException;

class CategoryHierarchy
{
    public static function validate(?int $categoryId, ?int $subcategoryId, array $groupIds, int $tenantId): void
    {
        if (! $categoryId && ! $subcategoryId) return;
        if (! $categoryId) {
            throw ValidationException::withMessages(['category_level_1' => 'Select a category before selecting a subcategory.']);
        }

        $category = Category::where('organization_id', $tenantId)->whereNull('parent_id')->find($categoryId);
        if (! $category) {
            throw ValidationException::withMessages(['category_level_1' => 'The selected category is invalid.']);
        }

        $subcategory = null;
        if ($subcategoryId) {
            $subcategory = Category::where('organization_id', $tenantId)
                ->where('parent_id', $category->id)
                ->find($subcategoryId);
            if (! $subcategory) {
                throw ValidationException::withMessages(['category_level_2' => 'The selected subcategory does not belong to the selected category.']);
            }
        }

        $selected = collect($groupIds)->map(fn ($id) => (int) $id)->filter()->unique();
        $categoryGroups = $category->groups()->pluck('groups.id');
        if ($categoryGroups->isNotEmpty() && $selected->diff($categoryGroups)->isNotEmpty()) {
            throw ValidationException::withMessages(['groups' => 'One or more selected groups are not linked to this category.']);
        }

        $subcategoryGroups = $subcategory?->groups()->pluck('groups.id') ?? collect();
        if ($subcategoryGroups->isNotEmpty() && $selected->diff($subcategoryGroups)->isNotEmpty()) {
            throw ValidationException::withMessages(['groups' => 'One or more selected groups are not linked to this subcategory.']);
        }
    }
}