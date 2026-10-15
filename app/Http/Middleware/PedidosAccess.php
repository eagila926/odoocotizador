<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class PedidosAccess
{
    public function handle($request, Closure $next)
    {
        $user = Auth::user();

        if (!$user || !in_array($user->rol, ['Admin','Distribuidor'], true)) {
            abort(403, 'No tienes permisos para acceder a Pedidos.');
        }

        return $next($request);
    }
}
