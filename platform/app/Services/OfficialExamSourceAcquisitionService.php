<?php

namespace App\Services;

use App\Models\OfficialExamSource;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\CssSelector\CssSelectorConverter;
use Symfony\Component\Process\Process;
use ZipArchive;

class OfficialExamSourceAcquisitionService
{
    private const ROLES = ['question', 'answer', 'combined'];

    public function scan(OfficialExamSource $source): array
    {
        $this->assertPublicUrl($source->source_url);
        $settings = (array) $source->discovery_settings;
        $html = $this->acquireHtml($source, $settings);
        $documents = [];
        foreach (self::ROLES as $role) {
            $roleSettings = (array) ($settings['roles'][$role] ?? []);
            if (empty($roleSettings['enabled'])) continue;
            foreach ($this->links($html, $source->source_url, $roleSettings, ['pdf']) as $position => $link) {
                $link['role'] = $role;
                $link['position'] = count($documents) + $position;
                $documents[] = $link;
            }
        }

        $rows = $this->pair($this->deduplicate($documents));
        foreach ($this->archiveLinks($html, $source->source_url, $settings) as $archive) {
            $rows = array_merge($rows, $this->rowsFromArchive($source, $archive, $settings));
        }

        $directExtension = strtolower(pathinfo((string) parse_url($source->source_url, PHP_URL_PATH), PATHINFO_EXTENSION));
        if ($directExtension === 'pdf' && ! $rows) {
            $rows[] = $this->rowFromDirectPdf($source->source_url);
        } elseif ($directExtension === 'zip' && ! $rows) {
            $rows = $this->rowsFromArchive($source, ['url' => $source->source_url, 'label' => basename((string) parse_url($source->source_url, PHP_URL_PATH))], $settings);
        }

        if (! $rows) throw new \RuntimeException('No PDFs or ZIP archives matched this source profile.');
        return array_map(fn (array $row) => $this->materializeRow($source, $row), $rows);
    }

    public function cleanup(array $row): void
    {
        foreach (self::ROLES as $role) {
            $path = (string) ($row[$role.'_local'] ?? '');
            if ($path !== '' && str_starts_with($path, 'tmp/official-exam-monitor/')) Storage::disk('local')->delete($path);
        }
    }

    private function acquireHtml(OfficialExamSource $source, array $settings): string
    {
        if (in_array(strtolower(pathinfo((string) parse_url($source->source_url, PHP_URL_PATH), PATHINFO_EXTENSION)), ['pdf', 'zip'], true)) return '';
        return match ($source->driver) {
            'form' => $this->formHtml($source->source_url, $settings),
            'api' => $this->apiHtml($source->source_url, $settings),
            'browser' => $this->browserHtml($source->source_url, $settings),
            default => $this->request('GET', $source->source_url)->body(),
        };
    }

    private function formHtml(string $url, array $settings): string
    {
        $dynamic = (array) ($settings['dynamic'] ?? []);
        $method = strtoupper((string) ($dynamic['method'] ?? 'GET'));
        $baseFields = (array) ($dynamic['fields'] ?? []);
        $variants = (array) ($dynamic['variants'] ?? []);
        if (! $variants && ! empty($dynamic['year_field'])) {
            $initial = $this->request('GET', $url)->body();
            $years = $this->selectOptions($initial, (string) $dynamic['year_field']);
            $limit = max(1, min(100, (int) ($dynamic['max_combinations'] ?? 30)));
            foreach ($years as $year) {
                $yearFields = array_merge($baseFields, [(string) $dynamic['year_field'] => $year]);
                if (! empty($dynamic['exam_field'])) {
                    $yearHtml = $this->request($method, $url, $yearFields)->body();
                    $exams = $this->selectOptions($yearHtml, (string) $dynamic['exam_field']);
                    foreach ($exams as $exam) {
                        $variants[] = array_merge($yearFields, [(string) $dynamic['exam_field'] => $exam]);
                        if (count($variants) >= $limit) break 2;
                    }
                } else {
                    $variants[] = $yearFields;
                }
            }
        }
        if (! $variants) $variants = [$baseFields];
        $html = '';
        foreach (array_slice($variants, 0, max(1, min(100, (int) ($dynamic['max_combinations'] ?? 30)))) as $fields) {
            $html .= "\n<div data-examelite-result>".$this->request($method, $url, array_merge($baseFields, (array) $fields))->body().'</div>';
        }
        return $html;
    }

    private function apiHtml(string $url, array $settings): string
    {
        $api = (array) ($settings['api'] ?? []);
        $endpoint = trim((string) ($api['url'] ?? '')) ?: $url;
        $this->assertPublicUrl($endpoint);
        $variants = (array) ($api['variants'] ?? [[]]);
        $itemsPath = trim((string) ($api['items_path'] ?? 'data'));
        $urlPath = trim((string) ($api['url_path'] ?? 'url'));
        $labelPath = trim((string) ($api['label_path'] ?? 'label'));
        $html = '';
        foreach (array_slice($variants ?: [[]], 0, 100) as $payload) {
            $response = $this->request(strtoupper((string) ($api['method'] ?? 'GET')), $endpoint, (array) $payload);
            if (($api['response_type'] ?? 'json') === 'html') {
                $html .= $response->body();
                continue;
            }
            $items = data_get($response->json(), $itemsPath, []);
            foreach (is_array($items) ? $items : [] as $item) {
                $documentUrl = (string) data_get($item, $urlPath, '');
                if ($documentUrl === '') continue;
                $label = (string) data_get($item, $labelPath, basename((string) parse_url($documentUrl, PHP_URL_PATH)));
                $html .= '<a href="'.htmlspecialchars($documentUrl, ENT_QUOTES).'">'.htmlspecialchars($label).'</a>';
            }
        }
        return $html;
    }

    private function browserHtml(string $url, array $settings): string
    {
        $script = base_path('scripts/official-source-browser.mjs');
        if (! is_file($script)) throw new \RuntimeException('The browser discovery helper is missing.');
        $directory = storage_path('app/tmp/official-exam-monitor/browser');
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) throw new \RuntimeException('Browser discovery temporary storage is unavailable.');
        $token = (string) Str::uuid();
        $input = $directory.'/'.$token.'.json';
        $output = $directory.'/'.$token.'.html';
        file_put_contents($input, json_encode(['url' => $url, 'settings' => (array) ($settings['browser'] ?? [])], JSON_THROW_ON_ERROR));
        try {
            $process = new Process(['node', $script, $input, $output], base_path(), null, null, 240);
            $process->run();
            if (! $process->isSuccessful()) throw new \RuntimeException('Browser discovery failed: '.trim($process->getErrorOutput() ?: $process->getOutput()));
            return (string) file_get_contents($output);
        } finally {
            @unlink($input);
            @unlink($output);
        }
    }

    private function request(string $method, string $url, array $fields = []): \Illuminate\Http\Client\Response
    {
        $this->assertPublicUrl($url);
        $pending = Http::timeout(90)->connectTimeout(15)->retry(2, 500)
            ->withHeaders(['User-Agent' => 'ExamElite Official Exam Monitor/1.0', 'Accept' => 'text/html,application/json,*/*']);
        $response = $method === 'POST' ? $pending->asForm()->post($url, $fields) : $pending->get($url, $fields);
        if (! $response->successful()) throw new \RuntimeException('Official source returned HTTP '.$response->status().'.');
        return $response;
    }

    private function selectOptions(string $html, string $field): array
    {
        $document = $this->document($html);
        $xpath = new DOMXPath($document);
        $literal = $this->xpathLiteral($field);
        $nodes = $xpath->query('//select[@name='.$literal.']//option[not(@disabled)]');
        $values = [];
        foreach ($nodes ?: [] as $node) {
            $value = trim($node instanceof DOMElement ? $node->getAttribute('value') : '');
            if ($value !== '' && $value !== '0' && strtolower($value) !== 'all') $values[] = $value;
        }
        return array_values(array_unique($values));
    }

    private function links(string $html, string $baseUrl, array $settings, array $extensions): array
    {
        if ($html === '') return [];
        $document = $this->document($html);
        $xpath = new DOMXPath($document);
        $contexts = [$document];
        $selector = trim((string) ($settings['selector'] ?? ''));
        if ($selector !== '') {
            try {
                $query = str_starts_with($selector, '/') || str_starts_with($selector, '.') ? $selector : (new CssSelectorConverter())->toXPath($selector);
                $matched = $xpath->query($query);
                $contexts = $matched ? iterator_to_array($matched) : [];
            } catch (\Throwable $exception) {
                throw new \RuntimeException('Invalid source selector "'.$selector.'": '.$exception->getMessage(), 0, $exception);
            }
        }
        $found = [];
        foreach ($contexts as $context) {
            $query = $context === $document ? '//*[@href or @data-href or @data-url or @data-download]' : './/*[@href or @data-href or @data-url or @data-download] | self::*[@href or @data-href or @data-url or @data-download]';
            foreach ($xpath->query($query, $context) ?: [] as $node) {
                foreach (['href', 'data-href', 'data-url', 'data-download'] as $attribute) {
                    if (! $node instanceof DOMElement || ! $node->hasAttribute($attribute)) continue;
                    $url = $this->absoluteUrl($node->getAttribute($attribute), $baseUrl);
                    $extension = strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
                    if (! in_array($extension, $extensions, true)) continue;
                    try { $this->assertPublicUrl($url); } catch (\Throwable) { continue; }
                    $label = trim(preg_replace('/\s+/u', ' ', $node->textContent)) ?: basename((string) parse_url($url, PHP_URL_PATH));
                    $contextText = $this->contextText($node, $xpath);
                    if (! $this->matches((string) ($settings['heading'] ?? ''), $contextText)) continue;
                    if (! $this->matches((string) ($settings['pattern'] ?? ''), $label.' '.$url)) continue;
                    $key = hash('sha256', $url);
                    $found[$key] = ['url' => $url, 'label' => $label, 'context' => $contextText];
                }
            }
        }
        return array_values($found);
    }

    private function archiveLinks(string $html, string $baseUrl, array $settings): array
    {
        $archive = (array) ($settings['archive'] ?? []);
        if (empty($archive['enabled'])) return [];
        return $this->links($html, $baseUrl, $archive, ['zip']);
    }

    private function rowsFromArchive(OfficialExamSource $source, array $archive, array $settings): array
    {
        if (! class_exists(ZipArchive::class)) throw new \RuntimeException('PHP ZIP support is required for this source.');
        $zipPath = $this->downloadTemporary((string) $archive['url'], 'zip', (int) data_get($settings, 'archive.max_download_mb', 500));
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) { @unlink($zipPath); throw new \RuntimeException('The official archive is not a valid ZIP file.'); }
        $documents = [];
        $total = 0;
        $maxFiles = max(1, min(2000, (int) data_get($settings, 'archive.max_files', 500)));
        $maxBytes = max(10, (int) data_get($settings, 'archive.max_extracted_mb', 2048)) * 1024 * 1024;
        try {
            if ($zip->numFiles > $maxFiles) throw new \RuntimeException("Archive contains more than {$maxFiles} files.");
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $stat = $zip->statIndex($index);
                $name = str_replace('\\', '/', (string) ($stat['name'] ?? ''));
                if ($name === '' || str_ends_with($name, '/') || str_contains($name, '../') || str_starts_with($name, '/')) continue;
                if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'pdf') continue;
                $size = (int) ($stat['size'] ?? 0);
                $total += $size;
                if ($size < 5 || $total > $maxBytes) throw new \RuntimeException('Archive extraction exceeded the configured safe size.');
                $role = $this->archiveRole($name, $settings);
                if ($role === null) continue;
                $relative = $this->temporaryRelative($source, $role, basename($name));
                Storage::disk('local')->makeDirectory(dirname($relative));
                $input = $zip->getStream((string) $stat['name']);
                if (! is_resource($input)) throw new \RuntimeException('An archive PDF could not be read.');
                try { Storage::disk('local')->put($relative, $input); } finally { fclose($input); }
                $absolute = Storage::disk('local')->path($relative);
                if (file_get_contents($absolute, false, null, 0, 5) !== '%PDF-') { Storage::disk('local')->delete($relative); continue; }
                $documents[] = ['url' => '', 'local' => $relative, 'label' => basename($name), 'role' => $role, 'position' => $index, 'archive_url' => $archive['url']];
            }
        } finally {
            $zip->close();
            @unlink($zipPath);
        }
        if (! $documents) throw new \RuntimeException('The ZIP archive contained no PDFs matching the configured role patterns.');
        return $this->pair($documents);
    }

    private function archiveRole(string $name, array $settings): ?string
    {
        foreach (['combined', 'answer', 'question'] as $role) {
            $roleSettings = (array) data_get($settings, 'roles.'.$role, []);
            if (empty($roleSettings['enabled'])) continue;
            $pattern = trim((string) ($roleSettings['pattern'] ?? ''));
            if ($pattern !== '' && $this->matches($pattern, $name)) return $role;
        }
        if (! empty(data_get($settings, 'roles.question.enabled'))) return 'question';
        if (! empty(data_get($settings, 'roles.combined.enabled'))) return 'combined';
        return null;
    }

    private function pair(array $documents): array
    {
        $questions = array_values(array_filter($documents, fn ($item) => in_array($item['role'], ['question', 'combined'], true)));
        $answers = array_values(array_filter($documents, fn ($item) => $item['role'] === 'answer'));
        $used = [];
        $rows = [];
        foreach ($questions as $question) {
            $answerIndex = $this->bestAnswer($question, $answers, $used);
            if ($answerIndex !== null) $used[$answerIndex] = true;
            $answer = $answerIndex === null ? null : $answers[$answerIndex];
            $name = $this->examName((string) $question['label']);
            $row = ['exam_name' => $name, 'year' => $this->year($question['label'].' '.($question['context'] ?? '')), 'archive_url' => $question['archive_url'] ?? null];
            foreach (self::ROLES as $role) {
                $document = $role === 'answer' ? $answer : ($question['role'] === $role ? $question : null);
                $row[$role.'_url'] = (string) ($document['url'] ?? '');
                $row[$role.'_local'] = (string) ($document['local'] ?? '');
                $row[$role.'_label'] = (string) ($document['label'] ?? '');
            }
            $rows[] = $row;
        }
        return $rows;
    }

    private function materializeRow(OfficialExamSource $source, array $row): array
    {
        $hashes = [];
        foreach (self::ROLES as $role) {
            $local = (string) ($row[$role.'_local'] ?? '');
            $url = (string) ($row[$role.'_url'] ?? '');
            if ($local === '' && $url !== '') {
                $temporary = $this->downloadTemporary($url, 'pdf', 200);
                $relative = $this->temporaryRelative($source, $role, basename((string) parse_url($url, PHP_URL_PATH)) ?: $role.'.pdf');
                Storage::disk('local')->makeDirectory(dirname($relative));
                $input = fopen($temporary, 'rb');
                try { Storage::disk('local')->put($relative, $input); } finally { if (is_resource($input)) fclose($input); @unlink($temporary); }
                $row[$role.'_local'] = $local = $relative;
            }
            if ($local !== '') $hashes[$role] = hash_file('sha256', Storage::disk('local')->path($local));
        }
        $row['document_hashes'] = $hashes;
        $row['content_hash'] = hash('sha256', json_encode($hashes, JSON_UNESCAPED_SLASHES));
        return $row;
    }

    private function downloadTemporary(string $url, string $extension, int $maxMb): string
    {
        $this->assertPublicUrl($url);
        $path = tempnam(sys_get_temp_dir(), 'official-source-');
        $response = Http::timeout(240)->connectTimeout(20)->retry(2, 750)
            ->withHeaders(['User-Agent' => 'ExamElite Official Exam Monitor/1.0'])->sink($path)->get($url);
        if (! $response->successful()) { @unlink($path); throw new \RuntimeException('Document download returned HTTP '.$response->status().'.'); }
        if (! is_file($path) || filesize($path) > $maxMb * 1024 * 1024) { @unlink($path); throw new \RuntimeException("Document exceeded the {$maxMb} MB safety limit."); }
        $signature = (string) file_get_contents($path, false, null, 0, 5);
        if (($extension === 'pdf' && $signature !== '%PDF-') || ($extension === 'zip' && ! str_starts_with($signature, "PK"))) {
            @unlink($path); throw new \RuntimeException('Downloaded file signature did not match its expected type.');
        }
        return $path;
    }

    private function rowFromDirectPdf(string $url): array
    {
        $label = basename((string) parse_url($url, PHP_URL_PATH));
        return ['exam_name' => $this->examName($label), 'year' => $this->year($label), 'question_url' => $url, 'question_local' => '', 'question_label' => $label, 'answer_url' => '', 'answer_local' => '', 'answer_label' => '', 'combined_url' => '', 'combined_local' => '', 'combined_label' => ''];
    }

    private function bestAnswer(array $question, array $answers, array $used): ?int
    {
        $left = $this->tokens((string) $question['label']);
        $best = null; $score = 0.0;
        foreach ($answers as $index => $answer) {
            if (isset($used[$index])) continue;
            $right = $this->tokens((string) $answer['label']);
            $union = count(array_unique(array_merge($left, $right)));
            $candidate = $union ? count(array_intersect($left, $right)) / $union : 0;
            if ($candidate > $score) { $score = $candidate; $best = $index; }
        }
        if (count($answers) === 1 && ! $used) return 0;
        return $score >= .30 ? $best : null;
    }

    private function deduplicate(array $documents): array
    {
        $unique = [];
        foreach ($documents as $document) $unique[$document['role'].'|'.hash('sha256', $document['url'])] = $document;
        return array_values($unique);
    }

    private function tokens(string $value): array
    {
        $value = Str::lower(Str::ascii($value));
        $value = preg_replace('/\b(pdf|download|question|paper|answer|key|solution|final)\b/', ' ', $value);
        return array_values(array_unique(array_filter(preg_split('/[^a-z0-9]+/', $value) ?: [])));
    }

    private function examName(string $label): string
    {
        $value = pathinfo($label, PATHINFO_FILENAME);
        $value = preg_replace('/\b(pdf|download|question|questions|paper|answer|answers|key|solution|solutions|final)\b/i', ' ', str_replace(['-', '_'], ' ', $value));
        return Str::limit(trim(preg_replace('/\s+/', ' ', $value)) ?: 'Official exam', 240, '');
    }

    private function year(string $value): string
    {
        return preg_match('/\b(19|20)\d{2}\b/', $value, $match) ? $match[0] : '';
    }

    private function matches(string $pattern, string $candidate): bool
    {
        $pattern = trim($pattern);
        if ($pattern === '') return true;
        if (strlen($pattern) > 2 && $pattern[0] === '/' && strrpos($pattern, '/') > 0) {
            $matched = @preg_match($pattern, $candidate);
            if ($matched === false) throw new \RuntimeException('Invalid configured regular expression: '.$pattern);
            return $matched === 1;
        }
        return str_contains(Str::lower(Str::ascii($candidate)), Str::lower(Str::ascii($pattern)));
    }

    private function contextText(DOMNode $node, DOMXPath $xpath): string
    {
        $parts = [];
        foreach (['ancestor::tr[1]', 'ancestor::table[1]//caption[1]', 'preceding::*[self::h1 or self::h2 or self::h3 or self::h4][1]'] as $query) {
            $match = $xpath->query($query, $node)?->item(0);
            if ($match) $parts[] = trim(preg_replace('/\s+/u', ' ', $match->textContent));
        }
        return implode(' ', $parts);
    }

    private function document(string $html): DOMDocument
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try { $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET); }
        finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
        return $document;
    }

    private function absoluteUrl(string $url, string $base): string
    {
        $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5));
        if (preg_match('#^https?://#i', $url)) return $url;
        $parts = parse_url($base);
        if (str_starts_with($url, '//')) return ($parts['scheme'] ?? 'https').':'.$url;
        $origin = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '').(isset($parts['port']) ? ':'.$parts['port'] : '');
        if (str_starts_with($url, '/')) return $origin.$url;
        $directory = preg_replace('#/[^/]*$#', '/', (string) ($parts['path'] ?? '/'));
        $segments = [];
        foreach (explode('/', $directory.$url) as $segment) {
            if ($segment === '..') array_pop($segments); elseif ($segment !== '.') $segments[] = $segment;
        }
        return $origin.implode('/', $segments);
    }

    private function assertPublicUrl(string $url): void
    {
        $parts = parse_url(trim($url));
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (! in_array($scheme, ['http', 'https'], true) || $host === '' || $host === 'localhost' || str_ends_with($host, '.local')) throw new \RuntimeException('Only public HTTP or HTTPS source URLs are allowed.');
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
        foreach ($ips as $ip) if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) throw new \RuntimeException('Private or reserved source URLs are not allowed.');
    }

    private function temporaryRelative(OfficialExamSource $source, string $role, string $name): string
    {
        $base = Str::slug(pathinfo($name, PATHINFO_FILENAME)) ?: $role;
        return 'tmp/official-exam-monitor/org-'.$source->organization_id.'/source-'.$source->id.'/'.Str::uuid().'/'.$role.'/'.$base.'.pdf';
    }

    private function xpathLiteral(string $value): string
    {
        if (! str_contains($value, "'")) return "'{$value}'";
        if (! str_contains($value, '"')) return '"'.$value.'"';
        return "concat('".str_replace("'", "',\"'\",'", $value)."')";
    }
}
