<?php

namespace App\Http\Controllers;

use App\Models\Diff;
use App\Support\SaasAccess;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DiffController extends Controller
{
    public function index(Request $request)
    {
        $query = Diff::query();

        if ($request->has('search')) {
            $query->where('diff_level', 'like', '%' . $request->input('search') . '%')
                ->orWhere('type', 'like', '%' . $request->input('search') . '%');
        }

        $diffs = $query->paginate(10);
        $canManageFixedOptions = $this->canManageFixedOptions();

        return view('diffs.index', compact('diffs', 'canManageFixedOptions'));
    }

    public function update(Request $request, Diff $diff)
    {
        try {
            $request->validate([
                'diff_level' => 'required|unique:diffs,diff_level,' . $diff->id,
            ]);

            $diff->update($request->only('diff_level'));
            return redirect()->route('diffs.index')->with('success', 'Diff updated successfully.');
        } catch (ValidationException $e) {
            return redirect()->route('diffs.index')->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            return redirect()->route('diffs.index')->with('error', 'Failed to update diff.');
        }
    }

    private function canManageFixedOptions(): bool
    {
        return SaasAccess::isPlatformAdmin();
    }
}
