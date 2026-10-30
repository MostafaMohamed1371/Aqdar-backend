<?php

namespace App\Core\Http\Middleware;

use App\Core\Audit\AuditLogger;
use App\Modules\Staff\Models\StaffUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffPermission
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();
        $allowed = $user instanceof StaffUser && $permissions !== []
            && collect($permissions)->contains(fn (string $permission): bool => $user->can($permission));

        if (! $allowed) {
            $this->auditLogger->write('permission_denied', $user instanceof StaffUser ? $user : null, null, [
                'permission' => $permissions[0] ?? 'unknown',
                'path' => $request->path(),
            ]);

            abort(Response::HTTP_FORBIDDEN, __('aqdar.http.forbidden'));
        }

        return $next($request);
    }
}
