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

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user instanceof StaffUser || ! $user->can($permission)) {
            $this->auditLogger->write('permission_denied', $user instanceof StaffUser ? $user : null, null, [
                'permission' => $permission,
                'path' => $request->path(),
            ]);

            abort(Response::HTTP_FORBIDDEN, __('aqdar.http.forbidden'));
        }

        return $next($request);
    }
}
