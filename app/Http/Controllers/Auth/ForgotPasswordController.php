<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class ForgotPasswordController extends Controller
{
    /**
     * Mostrar formulario "Ingresa tu correo".
     */
    public function showLinkRequestForm()
    {
        return view('auth.passwords.email');
    }

    /**
     * Enviar enlace de recuperación.
     */
    public function sendResetLinkEmail(Request $request)
    {
        // Validar campo correo
        $request->validate([
            'correo' => ['required', 'email'],
        ]);

        // El broker necesita 'email'
        $status = Password::sendResetLink([
            'email' => $request->correo,
        ]);

        if ($status === Password::RESET_LINK_SENT) {
            return back()->with('status', __($status));
        }

        return back()->withErrors([
            'correo' => __($status),
        ]);
    }
}
