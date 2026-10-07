<?php

namespace App\Services\GoogleSheets;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleSheetsClient
{
    private ?array $credentials = null;
    private ?string $accessToken = null;

    public function configured(): bool
    {
        $path = (string) config('google_sheets.credentials');

        return (bool) config('google_sheets.enabled') && $path !== '' && is_readable($path);
    }

    public function createSpreadsheet(string $title, string $tabName, string $shareEmail): array
    {
        $response = $this->request()->post('https://sheets.googleapis.com/v4/spreadsheets', [
            'properties' => ['title' => $title],
            'sheets' => [['properties' => ['title' => $tabName]]],
        ])->throw()->json();

        $spreadsheetId = (string) ($response['spreadsheetId'] ?? '');
        if ($spreadsheetId === '') {
            throw new RuntimeException('Google did not return a spreadsheet ID.');
        }

        $this->request()->post(
            'https://www.googleapis.com/drive/v3/files/'.$spreadsheetId.'/permissions?sendNotificationEmail=true',
            ['type' => 'user', 'role' => 'writer', 'emailAddress' => $shareEmail]
        )->throw();

        return [
            'id' => $spreadsheetId,
            'url' => 'https://docs.google.com/spreadsheets/d/'.$spreadsheetId.'/edit',
            'sheet_id' => (int) data_get($response, 'sheets.0.properties.sheetId', 0),
        ];
    }

    public function shareSpreadsheet(string $spreadsheetId, string $shareEmail): void
    {
        $this->request()->post(
            'https://www.googleapis.com/drive/v3/files/'.$spreadsheetId.'/permissions?sendNotificationEmail=true',
            ['type' => 'user', 'role' => 'writer', 'emailAddress' => $shareEmail]
        )->throw();
    }

    public function replaceValues(string $spreadsheetId, string $tabName, array $rows): void
    {
        $range = $this->range($tabName, 'A:ZZ');
        $this->request()
            ->withBody('{}', 'application/json')
            ->post('https://sheets.googleapis.com/v4/spreadsheets/'.$spreadsheetId.'/values/'.$range.':clear')
            ->throw();

        $this->request()->put(
            'https://sheets.googleapis.com/v4/spreadsheets/'.$spreadsheetId.'/values/'.$this->range($tabName, 'A1').'?valueInputOption=RAW',
            ['majorDimension' => 'ROWS', 'values' => $rows]
        )->throw();
    }

    public function values(string $spreadsheetId, string $tabName): array
    {
        return (array) ($this->request()->get(
            'https://sheets.googleapis.com/v4/spreadsheets/'.$spreadsheetId.'/values/'.$this->range($tabName, 'A:ZZ')
        )->throw()->json('values') ?? []);
    }

    public function updateRow(string $spreadsheetId, string $tabName, int $rowNumber, array $row): void
    {
        $this->request()->put(
            'https://sheets.googleapis.com/v4/spreadsheets/'.$spreadsheetId.'/values/'.$this->range($tabName, 'A'.$rowNumber).'?valueInputOption=RAW',
            ['majorDimension' => 'ROWS', 'values' => [$row]]
        )->throw();
    }


    public function updateRows(string $spreadsheetId, string $tabName, array $rows): void
    {
        foreach (array_chunk($rows, 500, true) as $chunk) {
            $data = [];
            foreach ($chunk as $rowNumber => $row) {
                $data[] = [
                    'range' => "'".str_replace("'", "''", $tabName)."'!A".$rowNumber,
                    'majorDimension' => 'ROWS',
                    'values' => [array_values($row)],
                ];
            }
            $this->request()->post(
                'https://sheets.googleapis.com/v4/spreadsheets/'.$spreadsheetId.'/values:batchUpdate',
                ['valueInputOption' => 'RAW', 'data' => $data]
            )->throw();
        }
    }
    public function formatSpreadsheet(string $spreadsheetId, int $sheetId, int $columnCount): void
    {
        $this->request()->post('https://sheets.googleapis.com/v4/spreadsheets/'.$spreadsheetId.':batchUpdate', [
            'requests' => [
                ['updateSheetProperties' => [
                    'properties' => ['sheetId' => $sheetId, 'gridProperties' => ['frozenRowCount' => 1]],
                    'fields' => 'gridProperties.frozenRowCount',
                ]],
                ['repeatCell' => [
                    'range' => ['sheetId' => $sheetId, 'startRowIndex' => 0, 'endRowIndex' => 1, 'startColumnIndex' => 0, 'endColumnIndex' => $columnCount],
                    'cell' => ['userEnteredFormat' => [
                        'backgroundColor' => ['red' => .12, 'green' => .32, 'blue' => .55],
                        'textFormat' => ['foregroundColor' => ['red' => 1, 'green' => 1, 'blue' => 1], 'bold' => true],
                    ]],
                    'fields' => 'userEnteredFormat(backgroundColor,textFormat)',
                ]],
                ['addProtectedRange' => ['protectedRange' => [
                    'range' => ['sheetId' => $sheetId, 'startColumnIndex' => 0, 'endColumnIndex' => 3],
                    'description' => 'Exam Elite system columns',
                    'warningOnly' => false,
                ]]],
                ['setBasicFilter' => ['filter' => [
                    'range' => ['sheetId' => $sheetId, 'startRowIndex' => 0, 'startColumnIndex' => 0, 'endColumnIndex' => $columnCount],
                ]]],
            ],
        ])->throw();
    }

    private function request(): PendingRequest
    {
        if (! $this->configured()) {
            throw new RuntimeException('Google Sheets is not configured. Set GOOGLE_SHEETS_ENABLED and GOOGLE_SERVICE_ACCOUNT_CREDENTIALS.');
        }

        return Http::acceptJson()->asJson()->withToken($this->token())->timeout(60)->retry(2, 300);
    }

    private function token(): string
    {
        if ($this->accessToken !== null) {
            return $this->accessToken;
        }

        $credentials = $this->credentials();
        $now = time();
        $header = $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $claims = $this->base64Url(json_encode([
            'iss' => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/spreadsheets https://www.googleapis.com/auth/drive.file',
            'aud' => $credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ], JSON_THROW_ON_ERROR));
        $unsigned = $header.'.'.$claims;

        if (! openssl_sign($unsigned, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Unable to sign the Google service-account request.');
        }

        $assertion = $unsigned.'.'.$this->base64Url($signature);
        $response = Http::asForm()->timeout(30)->post($credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $assertion,
        ])->throw()->json();

        return $this->accessToken = (string) ($response['access_token'] ?? throw new RuntimeException('Google did not return an access token.'));
    }

    private function credentials(): array
    {
        if ($this->credentials !== null) {
            return $this->credentials;
        }

        $path = (string) config('google_sheets.credentials');
        $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        if (empty($decoded['client_email']) || empty($decoded['private_key'])) {
            throw new RuntimeException('The Google service-account JSON file is invalid.');
        }

        return $this->credentials = $decoded;
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function range(string $tabName, string $cells): string
    {
        return rawurlencode("'".str_replace("'", "''", $tabName)."'!".$cells);
    }
}
