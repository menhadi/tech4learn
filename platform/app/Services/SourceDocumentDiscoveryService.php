<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class SourceDocumentDiscoveryService
{
    private const DOCUMENT_EXTENSIONS = ['pdf'];
    private array $documentCache = [];

    public function discoverLocal(array $files): array
    {
        $documents = []; $tokens = [];
        foreach ($files as $position => $file) {
            $name = basename((string) $file['name']);
            $fake = 'https://local.invalid/'.rawurlencode($name).'?item='.$position;
            $documents[] = $this->documentMeta($fake, $name, $position);
            $tokens[$fake] = $file['token'];
        }
        return array_map(function (array $row) use ($tokens) {
            foreach (['question', 'answer', 'combined'] as $role) {
                $key = $role.'_url'; $local = $role.'_local';
                $row[$local] = $tokens[$row[$key] ?? ''] ?? '';
                if ($row[$local] !== '') $row[$key] = '';
            }
            return $row;
        }, $this->pairDocuments($documents));
    }
    public function discoverStructured(array $sources): array
    {
        $documents = []; $position = 0; $requiredRoles = [];
        foreach (["question", "answer", "combined"] as $role) {
            $url = trim((string) ($sources[$role]["url"] ?? ""));
            if ($url === "") continue;
            $requiredRoles[] = $role;
            foreach ($this->documentsFromSource($url) as $document) {
                if (! $this->matchesHeading($document, (string) ($sources[$role]["heading"] ?? ""))) continue;
                if (! $this->matchesPattern($document, (string) ($sources[$role]["pattern"] ?? ""))) continue;
                $document["role"] = $role; $document["position"] = $position++; $documents[] = $document;
            }
        }
        if (! $documents) throw new \RuntimeException("No PDFs matched the supplied source fields and strict patterns.");
        return $this->pairDocuments($this->deduplicateRoleDocuments($documents), $requiredRoles, true);
    }

    public function discoverLocalStructured(array $sources): array
    {
        $documents = []; $tokens = []; $position = 0; $requiredRoles = [];
        foreach (["question", "answer", "combined"] as $role) {
            $pattern = (string) ($sources[$role]["pattern"] ?? "");
            if (! empty($sources[$role]["files"])) $requiredRoles[] = $role;
            foreach (($sources[$role]["files"] ?? []) as $file) {
                $name = basename((string) $file["name"]);
                $fake = "https://local.invalid/".rawurlencode($name)."?item=".$position;
                $document = $this->documentMeta($fake, $name, $position++);
                if (! $this->matchesPattern($document, $pattern)) continue;
                $document["role"] = $role; $documents[] = $document; $tokens[$fake] = $file["token"];
            }
        }
        if (! $documents) throw new \RuntimeException("No local PDFs matched the supplied strict patterns.");
        return array_map(function (array $row) use ($tokens) {
            foreach (["question", "answer", "combined"] as $role) {
                $key = $role."_url"; $local = $role."_local"; $row[$local] = $tokens[$row[$key] ?? ""] ?? "";
                if ($row[$local] !== "") $row[$key] = "";
            }
            return $row;
        }, $this->pairDocuments($this->deduplicateRoleDocuments($documents), $requiredRoles, true));
    }

    private function documentsFromSource(string $sourceUrl): array
    {
        $sourceUrl = $this->safeUrl($sourceUrl); $path = (string) parse_url($sourceUrl, PHP_URL_PATH);
        if ($this->isDocumentPath($path)) return [$this->documentMeta($sourceUrl, basename($path) ?: "PDF", 0)];
        return $this->documentCache[$sourceUrl] ??= $this->extractDocuments($this->fetchHtml($sourceUrl), $sourceUrl);
    }

    private function matchesHeading(array $document, string $heading): bool
    {
        $heading = trim(Str::lower(Str::ascii($heading)));
        if ($heading === '') return true;
        $candidate = Str::lower(Str::ascii(implode(' ', [(string) ($document['table_heading'] ?? ''), (string) ($document['section_heading'] ?? '')])));
        return str_contains($candidate, $heading);
    }

    private function matchesPattern(array $document, string $pattern): bool
    {
        $pattern = trim(Str::lower(Str::ascii($pattern)));
        if ($pattern === "") return true;
        $candidate = Str::lower(Str::ascii((string) ($document["label"] ?? "")." ".urldecode((string) parse_url((string) ($document["url"] ?? ""), PHP_URL_PATH))));
        return str_contains($candidate, $pattern);
    }
    public function discover(string $pageUrl): array
    {
        $pageUrl = $this->safeUrl($pageUrl);
        $path = (string) parse_url($pageUrl, PHP_URL_PATH);

        if ($this->isDocumentPath($path)) {
            return [$this->rowFromDocument($pageUrl, basename($path) ?: 'Question paper', 0)];
        }

        $documents = $this->extractDocuments($this->fetchHtml($pageUrl), $pageUrl);
        if (! $documents) {
            throw new \RuntimeException('No direct PDF links were found on this page. JavaScript-only or login-protected links can be added manually in the staging table.');
        }

        return $this->pairDocuments($documents);
    }

    private function fetchHtml(string $url): string
    {
        try {
            $response = Http::timeout(45)->connectTimeout(12)
                ->withHeaders(['User-Agent' => 'ExamElite Source Discovery/1.0', 'Accept' => 'text/html,application/xhtml+xml'])
                ->withOptions(['allow_redirects' => ['max' => 5, 'strict' => true]])->get($url);
            if (! $response->successful()) throw new \RuntimeException('The source page returned HTTP '.$response->status().'.');
            return $response->body();
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            if (! str_contains(strtolower($e->getMessage()), 'unsafe legacy renegotiation')) throw $e;
        }

        $dir = 'tmp/source-discovery';
        Storage::disk('local')->makeDirectory($dir);
        $output = Storage::disk('local')->path($dir.'/'.Str::uuid().'.html');
        $config = Storage::disk('local')->path($dir.'/'.Str::uuid().'.cnf');
        file_put_contents($config, "openssl_conf = openssl_init\n[openssl_init]\nssl_conf = ssl_sect\n[ssl_sect]\nsystem_default = system_default_sect\n[system_default_sect]\nOptions = UnsafeLegacyRenegotiation\n");
        try {
            $process = new Process(['curl','--fail','--location','--silent','--show-error','--max-time','60','--output',$output,$url], null, ['OPENSSL_CONF' => $config]);
            $process->setTimeout(70); $process->run();
            if (! $process->isSuccessful()) throw new \RuntimeException('Compatibility download failed: '.trim($process->getErrorOutput()));
            return (string) file_get_contents($output);
        } finally { @unlink($output); @unlink($config); }
    }
    private function extractDocuments(string $html, string $baseUrl): array
    {
        $previous = libxml_use_internal_errors(true);
        $document = new \DOMDocument();
        $document->loadHTML($html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $found = [];
        $candidates = [];
        $xpath = new \DOMXPath($document);
        foreach ($xpath->query('//*[@href or @data-href or @data-url or @data-download]') ?: [] as $node) {
            foreach (['href', 'data-href', 'data-url', 'data-download'] as $attribute) {
                if ($node->hasAttribute($attribute)) {
                    $candidates[] = [
                        'href' => $node->getAttribute($attribute),
                        'label' => $node->textContent,
                        'context' => $this->nodeContext($node, $xpath, $baseUrl),
                    ];
                }
            }
        }
        preg_match_all("#(?:https?:)?//[^\s\"'<>]+?\.pdf(?:\?[^\s\"'<>]*)?|(?:\.\.?/|/)[^\s\"'<>]+?\.pdf(?:\?[^\s\"'<>]*)?#iu", $html, $embedded);
        foreach ($embedded[0] ?? [] as $href) {
            $candidates[] = ['href' => $href, 'label' => basename((string) parse_url($href, PHP_URL_PATH)), 'context' => []];
        }

        foreach ($candidates as $index => $candidate) {
            $href = trim(html_entity_decode((string) $candidate['href'], ENT_QUOTES | ENT_HTML5));
            if ($href === '' || str_starts_with($href, '#') || preg_match('/^(mailto|javascript|tel):/i', $href)) {
                continue;
            }

            $url = $this->resolveUrl($baseUrl, $href);
            $path = urldecode((string) parse_url($url, PHP_URL_PATH));
            $label = trim(preg_replace('/\s+/u', ' ', (string) ($candidate['label'] ?? '')));
            // Labels such as "PDF" frequently occur on wrappers, buttons and
            // data attributes. Only accept URLs that actually identify a PDF.
            if (! $this->isDocumentPath($path)) {
                continue;
            }

            try {
                $url = $this->safeUrl($url);
            } catch (\Throwable) {
                continue;
            }

            $key = $this->documentIdentity($url);
            if (isset($found[$key])) {
                continue;
            }

            $found[$key] = $this->applyPageContext($this->documentMeta($url, $label, (int) $index), (array) ($candidate['context'] ?? []));
        }

        return array_values($found);
    }

    private function nodeContext(\DOMNode $node, \DOMXPath $xpath, string $baseUrl): array
    {
        $cellNodes = $xpath->query('ancestor::*[self::td or self::th][1]', $node);
        $cell = $cellNodes ? $cellNodes->item(0) : null;
        $rowNodes = $xpath->query('ancestor::tr[1]', $node);
        $row = $rowNodes ? $rowNodes->item(0) : null;
        $tableNodes = $xpath->query('ancestor::table[1]', $node);
        $table = $tableNodes ? $tableNodes->item(0) : null;
        $sectionNodes = $xpath->query('preceding::*[self::h1 or self::h2 or self::h3 or self::h4 or self::h5 or self::h6][1]', $node);
        $section = $sectionNodes ? $sectionNodes->item(0) : null;

        $tableIndex = $this->nodeIndex($xpath->query('//table'), $table);
        $rowIndex = $table ? $this->nodeIndex($xpath->query('.//tr', $table), $row) : null;
        $columnIndex = $cell ? (int) $xpath->evaluate('count(preceding-sibling::*[self::td or self::th])', $cell) : null;
        $heading = '';
        if ($table && $columnIndex !== null) {
            $headerRows = $xpath->query('.//tr[.//th][1]', $table);
            $headerRow = $headerRows && $headerRows->length ? $headerRows->item(0) : null;
            if (! $headerRow) {
                $rows = $xpath->query('.//tr[1]', $table);
                $headerRow = $rows ? $rows->item(0) : null;
            }
            if ($headerRow) {
                $headerCells = $xpath->query('./*[self::th or self::td]', $headerRow);
                $headingCell = $headerCells ? $headerCells->item($columnIndex) : null;
                $heading = trim(preg_replace('/\s+/u', ' ', (string) ($headingCell?->textContent ?? '')));
            }
        }

        $tableKey = $tableIndex === null ? null : sha1($baseUrl.'|table|'.$tableIndex);
        return [
            'table_heading' => $heading,
            'section_heading' => trim(preg_replace('/\s+/u', ' ', (string) ($section?->textContent ?? ''))),
            'table_key' => $tableKey,
            'row_key' => $tableKey !== null && $rowIndex !== null ? $tableKey.'|row|'.$rowIndex : null,
            'column_index' => $columnIndex,
        ];
    }

    private function nodeIndex(\DOMNodeList|false $nodes, ?\DOMNode $target): ?int
    {
        if (! $nodes || ! $target) return null;
        foreach ($nodes as $index => $candidate) {
            if ($candidate === $target || $candidate->isSameNode($target)) return (int) $index;
        }
        return null;
    }

    private function applyPageContext(array $item, array $context): array
    {
        $item = array_merge($item, $context);
        $contextText = implode(' ', [
            (string) ($context['section_heading'] ?? ''),
            (string) ($context['table_heading'] ?? ''),
            (string) ($item['label'] ?? ''),
        ]);
        if (preg_match('/\b(19|20)\d{2}\b/', $contextText, $yearMatch)) {
            $item['year'] = $yearMatch[0];
        }

        $label = trim(preg_replace('/\s+/u', ' ', (string) ($item['label'] ?? '')));
        $isGeneric = $label === '' || preg_match('/^(?:pdf|download|view|click(?:\s+here)?|question\s*paper|answer\s*key|key|solution)$/iu', $label);
        if (! $isGeneric) {
            $name = trim(preg_replace('/\b(?:download|view|click\s*here|pdf)\b/iu', ' ', $label));
            $name = trim(preg_replace('/\s+/u', ' ', $name));
            if ($name !== '' && ! empty($item['year']) && ! str_contains($name, (string) $item['year'])) {
                $name .= ' '.$item['year'];
            }
            if ($name !== '') $item['exam_name'] = Str::limit($name, 240, '');
        }
        $item['pair_key'] = $this->pairKey($label.' '.($item['year'] ?? ''));
        return $item;
    }
    private function documentIdentity(string $url): string
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = preg_replace('#/+#', '/', rawurldecode((string) ($parts['path'] ?? '/')));
        $query = [];
        parse_str((string) ($parts['query'] ?? ''), $query);
        foreach (array_keys($query) as $key) {
            if (preg_match('/^(?:utm_|fbclid$|gclid$|_ga$)/i', (string) $key)) unset($query[$key]);
        }
        ksort($query);
        return $scheme.'://'.$host.$path.($query ? '?'.http_build_query($query) : '');
    }
    private function pairDocuments(array $documents, array $requiredRoles = [], bool $strictShape = false): array
    {
        $questions = array_values(array_filter($documents, fn (array $item) => in_array($item['role'], ['question', 'combined', 'unknown'], true)));
        $answers = array_values(array_filter($documents, fn (array $item) => $item['role'] === 'answer'));
        $usedAnswers = [];
        $rows = [];
        $answerRequired = in_array('answer', $requiredRoles, true);
        $questionRequired = in_array('question', $requiredRoles, true);
        $combinedOnly = in_array('combined', $requiredRoles, true) && ! $questionRequired;

        foreach ($questions as $questionIndex => $question) {
            if ($combinedOnly && $question['role'] !== 'combined') continue;
            $answerIndex = $this->bestAnswerIndex($question, $answers, $usedAnswers);
            if ($strictShape && $answerRequired && $answerIndex === null) {
                $answerIndex = $this->nextUnusedAnswerIndex($answers, $usedAnswers, $questionIndex);
            }
            $answer = $answerIndex === null ? null : $answers[$answerIndex];
            if ($strictShape && $answerRequired && ! $answer) continue;
            if ($answerIndex !== null) {
                $usedAnswers[$answerIndex] = true;
            }

            $rows[] = [
                'selected' => true,
                'exam_name' => $question['exam_name'],
                'year' => $question['year'],
                'question_url' => $question['role'] === 'combined' ? '' : $question['url'],
                'answer_url' => $answer['url'] ?? '',
                'combined_url' => $question['role'] === 'combined' ? $question['url'] : '',
                'question_label' => $question['role'] === 'combined' ? '' : $question['label'],
                'answer_label' => $answer['label'] ?? '',
                'combined_label' => $question['role'] === 'combined' ? $question['label'] : '',
                'confidence' => $answer ? 'paired' : 'review',
                'source_label' => $question['label'],
            ];
        }

        foreach ($answers as $index => $answer) {
            if ($strictShape) continue;
            if (isset($usedAnswers[$index])) {
                continue;
            }
            $rows[] = [
                'selected' => false,
                'exam_name' => $answer['exam_name'],
                'year' => $answer['year'],
                'question_url' => '',
                'answer_url' => $answer['url'],
                'combined_url' => '',
                'question_label' => '',
                'answer_label' => $answer['label'],
                'combined_label' => '',
                'confidence' => 'unmatched-answer',
                'source_label' => $answer['label'],
            ];
        }

        usort($rows, fn (array $a, array $b) => strnatcasecmp($a['exam_name'], $b['exam_name']));
        return array_values($rows);
    }

    private function deduplicateRoleDocuments(array $documents): array
    {
        $unique = [];
        foreach ($documents as $document) {
            $identity = ($document['role'] ?? 'unknown').'|'.$this->documentIdentity((string) $document['url']);
            if (! isset($unique[$identity])) $unique[$identity] = $document;
        }
        return array_values($unique);
    }

    private function nextUnusedAnswerIndex(array $answers, array $used, int $preferredIndex): ?int
    {
        if (isset($answers[$preferredIndex]) && ! isset($used[$preferredIndex])) return $preferredIndex;
        foreach (array_keys($answers) as $index) {
            if (! isset($used[$index])) return $index;
        }
        return null;
    }

    private function bestAnswerIndex(array $question, array $answers, array $used): ?int
    {
        $bestIndex = null;
        $bestScore = 0.0;
        foreach ($answers as $index => $answer) {
            if (isset($used[$index])) {
                continue;
            }
            $score = $this->tokenSimilarity($question['pair_key'], $answer['pair_key']);
            if (! empty($question['row_key']) && $question['row_key'] === ($answer['row_key'] ?? null)) {
                $score += 3.0;
                if (isset($question['column_index'], $answer['column_index']) && $answer['column_index'] === $question['column_index'] + 1) {
                    $score += 2.0;
                }
            }
            $distance = abs($question['position'] - $answer['position']);
            if ($distance <= 3) {
                $score += 0.25;
            }
            if ($question['year'] && $question['year'] === $answer['year']) {
                $score += 0.35;
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestIndex = $index;
            }
        }

        if (count($answers) === 1 && count($used) === 0) {
            return 0;
        }
        return $bestScore >= 0.45 ? $bestIndex : null;
    }

    private function documentMeta(string $url, string $label, int $position): array
    {
        $path = urldecode((string) parse_url($url, PHP_URL_PATH));
        $filename = pathinfo(basename($path), PATHINFO_FILENAME);
        $display = trim($label) ?: str_replace(['-', '_'], ' ', $filename);
        $haystack = strtolower($display.' '.$filename);
        $role = match (true) {
            (bool) preg_match('/\b(combined|questions?\s*(?:and|&)\s*answers?|master\s*response)\b/i', $haystack) => 'combined',
            (bool) preg_match('/\b(answer|answers|answer\s*key|key|solution|solutions|ans)\b/i', $haystack) => 'answer',
            (bool) preg_match('/\b(question|questions|question\s*paper|paper|qp|master\s*question)\b/i', $haystack) => 'question',
            default => 'unknown',
        };

        preg_match('/\b(19|20)\d{2}\b/', $haystack, $yearMatch);
        $year = $yearMatch[0] ?? '';
        // The exam identity belongs to the first/question document. Link labels
        // are often generic ("PDF", "Download", "Question paper"), so derive
        // the editable exam name from its actual filename first.
        $examName = trim(str_replace(['-', '_'], ' ', $filename));
        $examName = preg_replace('/\b(download|view|click\s*here|pdf|docx?|question\s*paper|questions?|answer\s*key|answers?|solutions?|master\s*question|master\s*response|keys?)\b/iu', ' ', $examName);
        $examName = trim(preg_replace('/[\s\-_|:]+/u', ' ', $examName));
        if ($examName === '' || mb_strlen($examName) < 2) {
            $examName = trim($display);
        }

        return [
            'url' => $url,
            'label' => $display,
            'role' => $role,
            'position' => $position,
            'year' => $year,
            'exam_name' => Str::limit($examName, 240, ''),
            'pair_key' => $this->pairKey($display.' '.$filename),
        ];
    }

    private function rowFromDocument(string $url, string $label, int $position): array
    {
        $item = $this->documentMeta($url, $label, $position);
        return [
            'selected' => true,
            'exam_name' => $item['exam_name'],
            'year' => $item['year'],
            'question_url' => $item['role'] === 'combined' ? '' : $item['url'],
            'answer_url' => '',
            'combined_url' => $item['role'] === 'combined' ? $item['url'] : '',
            'confidence' => 'review',
            'source_label' => $item['label'],
        ];
    }

    private function pairKey(string $value): string
    {
        $value = Str::lower(Str::ascii($value));
        $value = preg_replace('/\b(download|view|click|here|pdf|docx?|question|questions|paper|qp|answer|answers|key|solution|solutions|master|response|final)\b/', ' ', $value);
        return trim(preg_replace('/[^a-z0-9]+/', ' ', $value));
    }

    private function tokenSimilarity(string $left, string $right): float
    {
        $a = array_values(array_unique(array_filter(explode(' ', $left))));
        $b = array_values(array_unique(array_filter(explode(' ', $right))));
        if (! $a || ! $b) {
            return 0.0;
        }
        $intersection = count(array_intersect($a, $b));
        $union = count(array_unique(array_merge($a, $b)));
        return $union ? $intersection / $union : 0.0;
    }

    private function isDocumentPath(string $path): bool
    {
        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::DOCUMENT_EXTENSIONS, true);
    }

    private function safeUrl(string $url): string
    {
        $url = trim($url);
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new \RuntimeException('Use a complete HTTP or HTTPS URL.');
        }
        if ($host === 'localhost' || str_ends_with($host, '.local')) {
            throw new \RuntimeException('Local network URLs are not allowed.');
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new \RuntimeException('Private or reserved network URLs are not allowed.');
            }
        }
        return $url;
    }

    private function resolveUrl(string $base, string $relative): string
    {
        if (preg_match('#^https?://#i', $relative)) {
            return $relative;
        }
        $parts = parse_url($base);
        $origin = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '');
        if (isset($parts['port'])) {
            $origin .= ':'.$parts['port'];
        }
        if (str_starts_with($relative, '//')) {
            return ($parts['scheme'] ?? 'https').':'.$relative;
        }
        if (str_starts_with($relative, '/')) {
            return $origin.$relative;
        }
        $directory = preg_replace('#/[^/]*$#', '/', (string) ($parts['path'] ?? '/'));
        $path = $directory.$relative;
        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '..') {
                array_pop($segments);
            } elseif ($segment !== '.') {
                $segments[] = $segment;
            }
        }
        return $origin.implode('/', $segments);
    }
}
