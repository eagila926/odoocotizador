<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EtiquetasAccess
{
    public function handle(Request $request, Closure $next)
    {
        $u = $request->user();
        abort_unless($u && method_exists($u, 'hasRole') && $u->hasRole(['Admin', 'Laboratorio']), 403);
        return $next($request);
    }
}
