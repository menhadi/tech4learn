<?php

namespace App\Http\Controllers;

use App\Models\Qtype;
use App\Support\SaasAccess;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class QtypeController extends Controller
{
    public function index(Request $request)
    {
        $query = Qtype::query();

        if ($request->has('search')) {
            $query->where('question_type', 'like', '%' . $request->input('search') . '%');
        }

        $qtypes = $query->paginate(10);
        $canManageFixedOptions = $this->canManageFixedOptions();

        return view('qtypes.index', compact('qtypes', 'canManageFixedOptions'));
    }

    public function update(Request $request, Qtype $qtype)
    {
        try {
            $request->validate([
                'question_type' => 'required|unique:qtypes,question_type,' . $qtype->id,
            ]);

            $qtype->update($request->only('question_type'));
            return redirect()->route('qtypes.index')->with('success', 'Qtype updated successfully.');
        } catch (ValidationException $e) {
            return redirect()->route('qtypes.index')->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            return redirect()->route('qtypes.index')->with('error', 'Failed to update qtype.');
        }
    }

    private function canManageFixedOptions(): bool
    {
        return SaasAccess::isPlatformAdmin();
    }
}
