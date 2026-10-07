<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\RequestOptions;
use RuntimeException;

class JosaaOrCrAdapter
{
    public const ROUND = 'ctl00$ContentPlaceHolder1$ddlroundno';
    public const INSTITUTE_TYPE = 'ctl00$ContentPlaceHolder1$ddlInstype';
    public const INSTITUTE = 'ctl00$ContentPlaceHolder1$ddlInstitute';
    public const PROGRAM = 'ctl00$ContentPlaceHolder1$ddlBranch';
    public const SEAT_TYPE = 'ctl00$ContentPlaceHolder1$ddlSeattype';
    public const SUBMIT = 'ctl00$ContentPlaceHolder1$btnSubmit';

    private ClientInterface $client;

    private string $url;

    public function __construct(?ClientInterface $client = null, ?string $url = null)
    {
        $this->url = $url ?? 'https://josaa.admissions.nic.in/Applicant/SeatAllotmentResult/currentorcr.aspx';
        $this->client = $client ?? new Client([
            'cookies' => new CookieJar,
            'timeout' => 60,
            'connect_timeout' => 15,
            'allow_redirects' => false,
            'headers' => ['User-Agent' => 'ExamElite-JoSAA-Adapter/1.0'],
            'http_errors' => false,
        ]);
    }

    public function optionSets(array $selections = []): array
    {
        $html = $this->get();
        $sets = ['round' => $this->options($html, self::ROUND)];
        $steps = [
            'round' => [self::ROUND, 'institute_type', self::INSTITUTE_TYPE],
            'institute_type' => [self::INSTITUTE_TYPE, 'institute', self::INSTITUTE],
            'institute' => [self::INSTITUTE, 'program', self::PROGRAM],
            'program' => [self::PROGRAM, 'seat_type', self::SEAT_TYPE],
        ];

        foreach ($steps as $selectionKey => [$field, $nextKey, $nextField]) {
            if (! isset($selections[$selectionKey])) {
                break;
            }
            $html = $this->postback($html, $field, (string) $selections[$selectionKey]);
            $sets[$nextKey] = $this->options($html, $nextField);
        }

        return $sets;
    }

    public function fetchRows(array $criteria): array
    {
        foreach (['round', 'institute_type', 'institute', 'program', 'seat_type'] as $key) {
            if (! isset($criteria[$key]) || (string) $criteria[$key] === '') {
                throw new RuntimeException("JoSAA criterion [{$key}] is required.");
            }
        }

        $html = $this->get();
        foreach ([
            [self::ROUND, 'round'],
            [self::INSTITUTE_TYPE, 'institute_type'],
            [self::INSTITUTE, 'institute'],
            [self::PROGRAM, 'program'],
        ] as [$field, $key]) {
            $html = $this->postback($html, $field, (string) $criteria[$key]);
        }

        $payload = $this->formState($html);
        $payload[self::SEAT_TYPE] = (string) $criteria['seat_type'];
        $payload[self::SUBMIT] = 'Submit';
        $payload['__EVENTTARGET'] = '';
        $payload['__EVENTARGUMENT'] = '';

        return $this->rows($this->post($payload));
    }

    private function get(): string
    {
        $response = $this->client->request('GET', $this->url);

        return $this->successfulBody($response->getStatusCode(), (string) $response->getBody());
    }

    private function postback(string $html, string $field, string $value): string
    {
        $payload = $this->formState($html);
        $payload[$field] = $value;
        $payload['__EVENTTARGET'] = $field;
        $payload['__EVENTARGUMENT'] = '';

        return $this->post($payload);
    }

    private function post(array $payload): string
    {
        $response = $this->client->request('POST', $this->url, [
            RequestOptions::FORM_PARAMS => $payload,
        ]);

        return $this->successfulBody($response->getStatusCode(), (string) $response->getBody());
    }

    private function successfulBody(int $status, string $body): string
    {
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException("JoSAA returned HTTP {$status}.");
        }
        if (trim($body) === '') {
            throw new RuntimeException('JoSAA returned an empty response.');
        }

        return $body;
    }

    private function formState(string $html): array
    {
        $xpath = $this->xpath($html);
        $state = [];

        foreach ($xpath->query('//input[@type="hidden"][@name]') as $input) {
            if ($input instanceof DOMElement) {
                $state[$input->getAttribute('name')] = $input->getAttribute('value');
            }
        }

        foreach ($xpath->query('//select[@name]') as $select) {
            if (! $select instanceof DOMElement) {
                continue;
            }
            $selected = $xpath->query('.//option[@selected]', $select)->item(0)
                ?? $xpath->query('.//option', $select)->item(0);
            if ($selected instanceof DOMElement) {
                $state[$select->getAttribute('name')] = $selected->getAttribute('value');
            }
        }

        return $state;
    }

    private function options(string $html, string $field): array
    {
        $xpath = $this->xpath($html);
        $literal = $this->xpathLiteral($field);
        $nodes = $xpath->query("//select[@name={$literal} or @id={$literal}]/option");
        $options = [];

        foreach ($nodes as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }
            $value = trim($node->getAttribute('value'));
            $label = trim(preg_replace('/\s+/', ' ', $node->textContent));
            if ($value !== '' && $value !== '0' && ! str_contains(strtolower($label), 'select')) {
                $options[$value] = $label;
            }
        }

        return $options;
    }

    private function rows(string $html): array
    {
        $xpath = $this->xpath($html);
        $tables = $xpath->query('//*[@id="ctl00_ContentPlaceHolder1_pnlDisplayDetails"]//table');
        $table = $tables->item(0);
        if (! $table instanceof DOMElement) {
            return [];
        }

        $headers = [];
        $records = [];
        foreach ($xpath->query('.//tr', $table) as $row) {
            $cells = [];
            foreach ($xpath->query('./th|./td', $row) as $cell) {
                $cells[] = trim(preg_replace('/\s+/', ' ', $cell->textContent));
            }
            if ($cells === []) {
                continue;
            }
            if ($headers === []) {
                $headers = array_map([$this, 'headerKey'], $cells);
                continue;
            }
            if (count($cells) !== count($headers)) {
                continue;
            }
            $records[] = array_combine($headers, $cells);
        }

        return $records;
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML($html, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (! $loaded) {
            throw new RuntimeException('JoSAA returned malformed HTML.');
        }

        return new DOMXPath($document);
    }

    private function xpathLiteral(string $value): string
    {
        return '"'.str_replace('"', '&quot;', $value).'"';
    }

    private function headerKey(string $header): string
    {
        $key = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '_', $header), '_'));

        return $key !== '' ? $key : 'column';
    }
}
