<?php

namespace App\Services\GoogleSheets;

use App\Models\GoogleSheetConnection;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class GoogleSheetSyncService
{
    public const SYSTEM_HEADERS = ['record_id', 'record_version', 'sync_status'];

    public function __construct(
        private readonly GoogleSheetsClient $client,
        private readonly GoogleSheetResourceRegistry $registry,
    ) {}

    public function configured(): bool
    {
        return $this->client->configured();
    }

    public function export(string $resource, int $organizationId, int $userId, string $shareEmail, array $selectedFields, array $filters): GoogleSheetConnection
    {
        $definition = $this->registry->definition($resource);
        $selectedFields = $this->registry->selectedFields($resource, $selectedFields);
        if ($selectedFields === []) {
            throw ValidationException::withMessages(['fields' => 'Select at least one editable field.']);
        }

        $filters = $this->registry->filters($resource, $filters);
        $maxRows = max(1, (int) config('google_sheets.max_rows', 20000));
        $records = $this->registry->query($resource, $organizationId, $filters)->limit($maxRows + 1)->get();
        if ($records->count() > $maxRows) {
            throw ValidationException::withMessages(['filters' => "This selection exceeds the {$maxRows}-row safety limit. Apply filters and try again."]);
        }

        $connection = GoogleSheetConnection::where('organization_id', $organizationId)->where('resource', $resource)->first();
        $tabName = (string) config('google_sheets.tab_name', 'Data');
        $newSheetId = null;
        if (! $connection) {
            $created = $this->client->createSpreadsheet('Exam Elite - '.$definition['title'].' - '.now()->format('Y-m-d H-i'), $tabName, $shareEmail);
            $newSheetId = $created['sheet_id'];
            $connection = new GoogleSheetConnection([
                'organization_id' => $organizationId, 'created_by' => $userId, 'resource' => $resource,
                'spreadsheet_id' => $created['id'], 'spreadsheet_url' => $created['url'], 'tab_name' => $tabName,
            ]);
        } elseif ($connection->share_email !== $shareEmail) {
            $this->client->shareSpreadsheet($connection->spreadsheet_id, $shareEmail);
        }

        $rows = [array_merge(self::SYSTEM_HEADERS, $selectedFields)];
        foreach ($records as $record) {
            $rows[] = $this->sheetRow($record, $selectedFields, 'READY');
        }
        $this->client->replaceValues($connection->spreadsheet_id, $connection->tab_name, $rows);
        if ($newSheetId !== null) {
            $this->client->formatSpreadsheet($connection->spreadsheet_id, $newSheetId, count($rows[0]));
        }

        $connection->fill([
            'share_email' => $shareEmail, 'selected_fields' => $selectedFields, 'filters' => $filters, 'last_exported_at' => now(),
        ])->save();
        audit_log('google_sheets.exported', $connection, ['resource' => $resource, 'rows' => $records->count(), 'fields' => $selectedFields]);

        return $connection;
    }

    public function sync(GoogleSheetConnection $connection): array
    {
        $rows = $this->client->values($connection->spreadsheet_id, $connection->tab_name);
        if ($rows === []) {
            throw new RuntimeException('The connected Google Sheet is empty. Refresh it from Exam Elite first.');
        }

        $header = array_map('strval', array_shift($rows));
        $expected = array_merge(self::SYSTEM_HEADERS, $connection->selected_fields ?? []);
        if ($header !== $expected) {
            throw new RuntimeException('The sheet columns were changed. Restore the header row or refresh the sheet from Exam Elite.');
        }

        $organizationId = (int) $connection->organization_id;
        $selectedFields = $connection->selected_fields ?? [];
        $summary = ['updated' => 0, 'unchanged' => 0, 'conflicts' => 0, 'errors' => 0];
        $rowUpdates = [];

        foreach ($rows as $offset => $sheetRow) {
            $rowNumber = $offset + 2;
            $sheetRow = array_pad(array_values($sheetRow), count($expected), '');
            $id = (int) ($sheetRow[0] ?? 0);
            if ($id <= 0) {
                $sheetRow[2] = 'ERROR: record_id is missing';
                $summary['errors']++;
                $rowUpdates[$rowNumber] = $sheetRow;
                continue;
            }

            $record = $this->registry->query($connection->resource, $organizationId)->whereKey($id)->first();
            if (! $record) {
                $sheetRow[2] = 'ERROR: record not found or unavailable';
                $summary['errors']++;
                $rowUpdates[$rowNumber] = $sheetRow;
                continue;
            }

            $input = [];
            foreach ($selectedFields as $index => $field) {
                $input[$field] = $this->castInput($connection->resource, $field, $sheetRow[$index + 3] ?? '');
            }

            if (! $this->hasChanges($record, $input)) {
                $sheetRow[1] = $this->version($record);
                $sheetRow[2] = 'UNCHANGED';
                $summary['unchanged']++;
                $rowUpdates[$rowNumber] = $sheetRow;
                continue;
            }
            if ((string) ($sheetRow[1] ?? '') !== $this->version($record)) {
                $sheetRow[2] = 'CONFLICT: record changed in Exam Elite; refresh before editing';
                $summary['conflicts']++;
                $rowUpdates[$rowNumber] = $sheetRow;
                continue;
            }

            try {
                $this->validate($connection->resource, $input, $selectedFields, $organizationId);
                DB::transaction(function () use ($record, $input, $connection) {
                    $before = $record->only(array_keys($input));
                    foreach ($input as $field => $value) {
                        $current = $record->getAttribute($field);
                        if (is_array($current) && ! is_array($value)) {
                            $current[app()->getLocale()] = $value;
                            $value = $current;
                        }
                        $record->setAttribute($field, $value);
                    }
                    $record->save();
                    if ($connection->resource === 'questions' && array_intersect(array_keys($input), ['subject_id', 'topic_id', 'stopic_id'])) {
                        $fresh = $record->fresh(['topic', 'stopic']);
                        app(\App\Services\CurriculumTaxonomyService::class)->syncQuestion($fresh, $fresh->groups()->pluck('groups.id')->all());
                    }
                    audit_log('google_sheets.record_updated', $record, ['resource' => $connection->resource, 'before' => $before, 'after' => $input]);
                });
                $record->refresh();
                $sheetRow[1] = $this->version($record);
                $sheetRow[2] = 'SYNCED '.now()->format('Y-m-d H:i:s');
                $summary['updated']++;
            } catch (ValidationException $exception) {
                $sheetRow[2] = 'ERROR: '.$exception->validator->errors()->first();
                $summary['errors']++;
            } catch (\Throwable $exception) {
                report($exception);
                $sheetRow[2] = 'ERROR: update failed; check application logs';
                $summary['errors']++;
            }
            $rowUpdates[$rowNumber] = $sheetRow;
        }

        $this->client->updateRows($connection->spreadsheet_id, $connection->tab_name, $rowUpdates);
        $connection->forceFill(['last_synced_at' => now()])->save();
        audit_log('google_sheets.synced', $connection, $summary + ['resource' => $connection->resource]);

        return $summary;
    }

    private function validate(string $resource, array $input, array $fields, int $organizationId): void
    {
        Validator::make($input, $this->registry->rules($resource, $fields))->validate();
        foreach ($input as $field => $value) {
            if (! $this->registry->relatedValueBelongsToOrganization($field, $value, $organizationId)) {
                throw ValidationException::withMessages([$field => 'The selected related record is not available in this organization.']);
            }
        }
    }

    private function sheetRow(Model $record, array $fields, string $status): array
    {
        $row = [(int) $record->getKey(), $this->version($record), $status];
        foreach ($fields as $field) {
            $row[] = $this->sheetValue($record->getAttribute($field), $field);
        }
        return $row;
    }

    private function sheetValue(mixed $value, ?string $field = null): mixed
    {
        if ($value instanceof CarbonInterface) return $value->format('Y-m-d H:i:s');
        if (is_bool($value)) return $value ? 1 : 0;
        if ($field === 'correct_option_indices' && is_array($value)) return implode(',', $value);
        if (is_array($value)) return $value[app()->getLocale()] ?? reset($value) ?: '';
        return $value ?? '';
    }

    private function castInput(string $resource, string $field, mixed $value): mixed
    {
        if (is_string($value)) $value = trim($value);
        if ($value === '') return null;
        $definition = $this->registry->definition($resource)['fields'][$field];
        return match ($definition['type']) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (int) $value,
            'number' => is_numeric($value) ? (((float) $value == (int) $value) ? (int) $value : (float) $value) : $value,
            default => $field === 'correct_option_indices'
                ? array_values(array_unique(array_filter(array_map('intval', preg_split('/[\s,;|]+/', (string) $value)), fn ($index) => $index >= 1 && $index <= 6)))
                : $value,
        };
    }

    private function hasChanges(Model $record, array $input): bool
    {
        foreach ($input as $field => $value) {
            if ((string) $this->sheetValue($record->getAttribute($field), $field) !== (string) $this->sheetValue($value, $field)) return true;
        }
        return false;
    }

    private function version(Model $record): string
    {
        $updatedAt = $record->getAttribute('updated_at');
        return $updatedAt instanceof CarbonInterface ? $updatedAt->utc()->format('Y-m-d\TH:i:s.u\Z') : (string) ($updatedAt ?? '');
    }
}
