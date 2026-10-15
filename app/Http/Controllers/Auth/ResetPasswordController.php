<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Auth\Events\PasswordReset;

class ResetPasswordController extends Controller
{
    /**
     * Mostrar formulario para escribir nueva contraseña.
     */
    public function showResetForm(Request $request, $token = null)
    {
        return view('auth.passwords.reset', [
            'token'  => $token,
            'correo' => $request->email,  // el broker siempre pasa email en el link
        ]);
    }

    /**
     * Actualizar contraseña.
     */
    public function reset(Request $request)
    {
        // Validar datos
        $request->validate([
            'token'    => ['required'],
            'correo'   => ['required', 'email'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        // El broker necesita 'email', mandamos 'correo'
        $status = Password::reset(
            [
                'email'                 => $request->correo,
                'password'              => $request->password,
                'password_confirmation' => $request->password_confirmation,
                'token'                 => $request->token,
            ],
            function ($user, $password) {
                $user->password = Hash::make($password);
                $user->setRememberToken(Str::random(60));
                $user->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')
                ->with('status', __($status));
        }

        return back()->withErrors([
            'correo' => __($status),
        ]);
    }
}
