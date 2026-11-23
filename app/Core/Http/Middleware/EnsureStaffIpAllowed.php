<?php

namespace App\Core\Http\Middleware;

use App\Core\Audit\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffIpAllowed
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var list<string> $allowlist */
        $allowlist = (array) config('aqdar.staff.ip_allowlist', []);

        if ($allowlist === [] || IpUtils::checkIp((string) $request->ip(), $allowlist)) {
            return $next($request);
        }

        $this->auditLogger->write('auth.ip_blocked', null, null, [
            'guard' => 'web',
            'path' => $request->path(),
        ], $request);

        abort(Response::HTTP_FORBIDDEN, __('aqdar.auth.staff_ip_blocked'));
    }
}
