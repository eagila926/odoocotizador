@extends('layouts.app')

@section('content')
<div class="container mt-4">
    <h3>Recuperar contraseña</h3>
    <p>Ingresa tu correo para enviarte un enlace de recuperación.</p>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <label>Correo electrónico</label>
        <input type="email" name="correo" class="form-control" required>
        <button class="btn btn-brand w-100 mt-3">Enviar enlace</button>
    </form>

</div>
@endsection
