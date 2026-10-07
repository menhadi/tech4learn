<?php

namespace App\Support;

use App\Models\User;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Illuminate\Http\Request;

class AttendanceBridge
{
    public function context(Request $request, ?ClientInterface $http = null): array
    {
        $organization = Tenant::assertAccess(Tenant::current(), true);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $context = $this->read($request, '/foundation/organisations/'.$organization->id.
            '/staff/'.$actor->id.'/attendance-context', [], $http);
        abort_unless(($context['nativeOrganisationId'] ?? null) === (string)$organization->id
            && ($context['nativeUserId'] ?? null) === (string)$actor->id
            && is_string($context['organisation']['id'] ?? null)
            && preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D', $context['organisation']['id'])
            && is_array($context['permissions'] ?? null) && is_array($context['scope'] ?? null), 502);
        return $context;
    }

    public function records(Request $request, ?ClientInterface $http = null): array
    {
        $date = $request->query('date');
        $parsed = is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date)
            ? \DateTimeImmutable::createFromFormat('!Y-m-d', $date) : false;
        abort_unless($parsed && $parsed->format('Y-m-d') === $date, 422);
        $context = $this->context($request, $http);
        $id = $context['organisation']['id'] ?? '';
        abort_unless(is_string($id) && preg_match('/^[a-f0-9-]{36}$/D', $id), 502);
        $data = $this->read($request, '/organisations/'.$id.'/attendance', ['date' => $date], $http);
        // Recheck both identity maps and permissions after remote work before releasing records.
        abort_unless($this->context($request, $http) === $context, 403);
        return $data;
    }

    private function read(Request $request, string $path, array $query, ?ClientInterface $http): array
    {
        $base = rtrim((string) config('attendance.api_url'), '/');
        $parts = parse_url($base);
        $scheme = $parts['scheme'] ?? '';
        $local = in_array($parts['host'] ?? '', ['127.0.0.1', 'localhost', '::1'], true);
        abort_unless($parts && ($scheme === 'https' || ($scheme === 'http' && $local && app()->environment('local')))
            && !isset($parts['user']) && !isset($parts['pass']) && !isset($parts['query']) && !isset($parts['fragment'])
            && ($parts['path'] ?? '') === '/api/v1', 503, 'Attendance connection is not configured.');
        $name = app()->environment('production') ? '__Host-t4l_session' : 't4l_session';
        $token = $request->cookie($name);
        abort_unless(is_string($token) && preg_match('/^[a-f0-9]{64}$/D', $token), 401, 'Sign in to your linked attendance account.');
        try {
            $response = ($http ?? new Client())->request('GET', $base.$path, [
                'headers' => ['Accept' => 'application/json', 'Cookie' => $name.'='.$token],
                'query' => $query, 'allow_redirects' => false, 'http_errors' => false,
                'connect_timeout' => 3, 'timeout' => 8, 'stream' => true,
            ]);
            $status = $response->getStatusCode();
            if ($status !== 200) {
                $response->getBody()->close();
                abort(in_array($status, [401,403,404], true) ? $status : 502, 'Attendance access is unavailable.');
            }
            $stream = $response->getBody();
            $body = '';
            try {
                while (!$stream->eof() && strlen($body) <= 1048576) {
                    $chunk = $stream->read(8192);
                    if ($chunk === '' && !$stream->eof()) { throw new \RuntimeException('Incomplete response'); }
                    $body .= $chunk;
                }
            } finally { $stream->close(); }
            abort_if(strlen($body) > 1048576, 502);
            $data = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
            abort_unless(is_array($data), 502);
            return $data;
        } catch (\GuzzleHttp\Exception\GuzzleException | \JsonException | \RuntimeException $error) {
            if ($error instanceof \Symfony\Component\HttpKernel\Exception\HttpException) { throw $error; }
            // Never expose remote response text, token or connection URL in an error.
            abort(502, 'Attendance connection could not complete the request.');
        }
    }
}
