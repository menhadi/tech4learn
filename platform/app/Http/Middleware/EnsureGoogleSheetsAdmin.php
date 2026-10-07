<?php

namespace App\Http\Middleware;

use App\Support\GoogleSheetsAdminAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureGoogleSheetsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(GoogleSheetsAdminAccess::allowed(), 403, 'Only a super administrator or organization administrator can use Google Sheets sync.');

        return $next($request);
    }
}
