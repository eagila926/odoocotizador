@extends('layouts.app')

@section('content')
<div class="container">
    <h3>Restablecer contraseña</h3>

    <form method="POST" action="{{ route('password.update') }}">
        @csrf

        <input type="hidden" name="token" value="{{ $token }}">

        <label>Correo</label>
        <input type="email" name="correo" class="form-control mb-3" value="{{ $correo }}" required>

        <label>Nueva contraseña</label>
        <input type="password" name="password" class="form-control mb-3" required>

        <label>Confirmar contraseña</label>
        <input type="password" name="password_confirmation" class="form-control mb-3" required>

        <button class="btn btn-brand w-100">Actualizar contraseña</button>
    </form>

</div>
@endsection
