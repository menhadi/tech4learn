<?php

namespace App\Http\Controllers;

use App\Models\GoogleSheetConnection;
use App\Services\GoogleSheets\GoogleSheetResourceRegistry;
use App\Services\GoogleSheets\GoogleSheetSyncManager;
use App\Support\SaasAccess;
use Illuminate\Http\Request;

class GoogleSheetSyncController extends Controller
{
    public function show(Request $request, string $resource, GoogleSheetResourceRegistry $registry, GoogleSheetSyncManager $sync)
    {
        $organizationId = (int) (SaasAccess::organization()?->id ?? 0);
        abort_unless($organizationId, 403);
        $definition = $registry->definition($resource);
        $connection = GoogleSheetConnection::where('organization_id', $organizationId)->where('resource', $resource)->first();
        $filters = $registry->filters($resource, $request->query());

        return view('admin.google-sheets.show', [
            'resource' => $resource,
            'definition' => $definition,
            'connection' => $connection,
            'configured' => $sync->configured(),
            'filters' => $filters,
            'estimatedRows' => $registry->query($resource, $organizationId, $filters)->limit((int) config('google_sheets.max_rows', 20000) + 1)->count(),
        ]);
    }

    public function export(Request $request, string $resource, GoogleSheetResourceRegistry $registry, GoogleSheetSyncManager $sync)
    {
        $organizationId = (int) (SaasAccess::organization()?->id ?? 0);
        abort_unless($organizationId, 403);
        $registry->definition($resource);
        $connection = GoogleSheetConnection::where('organization_id', $organizationId)->where('resource', $resource)->first();
        $validated = $request->validate([
            'share_email' => ['required', 'email:rfc', 'max:255'],
            'fields' => ['required', 'array', 'min:1'],
            'fields.*' => ['required', 'string', 'max:100'],
            'filters' => ['nullable', 'array'],
            'filters.*' => ['nullable', 'string', 'max:255'],
        ]);
        if ($connection && strcasecmp((string) $connection->share_email, (string) $validated['share_email']) !== 0) {
            return back()->withErrors(['share_email' => 'Keep the original shared email for this connected sheet. You can share the sheet with additional people from Google Drive.'])->withInput();
        }

        try {
            $connection = $sync->export(
                $resource,
                $organizationId,
                (int) $request->user()->id,
                $validated['share_email'],
                $validated['fields'],
                $validated['filters'] ?? [],
            );
        } catch (\Throwable $exception) {
            report($exception);
            return back()->withErrors(['google_sheets' => $exception->getMessage()])->withInput();
        }

        return redirect()->route('admin.google-sheets.show', $resource)
            ->with('success', 'The connected Google Sheet was refreshed from Exam Elite.')
            ->with('open_google_sheet', $connection->spreadsheet_url);
    }

    public function sync(string $resource, GoogleSheetResourceRegistry $registry, GoogleSheetSyncManager $sync)
    {
        $organizationId = (int) (SaasAccess::organization()?->id ?? 0);
        abort_unless($organizationId, 403);
        $registry->definition($resource);
        $connection = GoogleSheetConnection::where('organization_id', $organizationId)->where('resource', $resource)->firstOrFail();

        try {
            $summary = $sync->sync($connection);
        } catch (\Throwable $exception) {
            report($exception);
            return back()->withErrors(['google_sheets' => $exception->getMessage()]);
        }

        return back()->with('success', sprintf(
            'Sync complete: %d updated, %d unchanged, %d conflicts, %d errors.',
            $summary['updated'], $summary['unchanged'], $summary['conflicts'], $summary['errors']
        ));
    }
}
