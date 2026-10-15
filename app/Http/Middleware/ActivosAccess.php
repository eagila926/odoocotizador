<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ActivosAccess
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        abort_unless($user && $user->hasRole(['Admin']), 403, 'No autorizado.');
        return $next($request);
    }
}
