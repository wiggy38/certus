<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SwaggerAdminAccess
{
    /**
     * Restrict Swagger UI and generated docs to authenticated admins only.
     * Accepts either a configured admin token or a logged-in admin user.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $adminToken = (string) env('SWAGGER_ADMIN_TOKEN', '');

        if ($adminToken !== '') {
            $providedToken = $request->header('X-Swagger-Admin');
            $authorization = $request->header('Authorization', '');

            if (str_starts_with($authorization, 'Bearer ')) {
                $providedToken = substr($authorization, 7);
            }

            if ($providedToken !== null && $providedToken === $adminToken) {
                return $next($request);
            }
        }

        if (Auth::check()) {
            $user = Auth::user();

            if (
                (method_exists($user, 'hasRole') && $user->hasRole('admin'))
                || (property_exists($user, 'email') && $user->email === config('certus.admin_email', config('mail.from.address')))
            ) {
                return $next($request);
            }
        }

        abort(403, 'Swagger réservé aux administrateurs.');
    }
}
