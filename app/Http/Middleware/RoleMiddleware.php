<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class RoleMiddleware
{
    public function handle($request, Closure $next, ...$roles)
    {
        $user = Auth::user();
        if (!$user || !in_array($user->rol, $roles, true)) {
            abort(403, 'No tienes permisos para acceder a esta sección.');
        }
        return $next($request);
    }
}
