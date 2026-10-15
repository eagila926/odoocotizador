@extends('layouts.app')

@section('content')
<div class="container">
    <h3 class="mb-4">Registrar Usuario</h3>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form action="{{ route('usuarios.store') }}" method="POST">
        @csrf

        <div class="row g-3">

            <!-- Columna izquierda -->
            <div class="col-md-6">

                <div class="mb-3">
                    <label>Nombre</label>
                    <input type="text" name="nombre" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label>Apellido</label>
                    <input type="text" name="apellido" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label>Correo</label>
                    <input type="email" name="correo" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label>Rol</label>
                    <select name="rol" class="form-control" required>
                        <option value="">Seleccione un rol</option>
                        <option value="Admin">Admin</option>
                        <option value="Visitador">Visitador</option>
                        <option value="Distribuidor">Distribuidor</option>
                        <option value="Call">Call</option>
                        <option value="Laboratorio">Laboratorio</option>
                    </select>
                </div>

            </div>

            <!-- Columna derecha -->
            <div class="col-md-6">

                <div class="mb-3">
                    <label>Contraseña</label>
                    <div class="input-group">
                        <input type="password" name="password" id="password" class="form-control" required>
                        <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('password')">👁️</button>
                    </div>
                </div>

                <div class="mb-3">
                    <label>Confirmar Contraseña</label>
                    <div class="input-group">
                        <input type="password" name="password_confirmation" id="password_confirmation" class="form-control" required>
                        <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('password_confirmation')">👁️</button>
                    </div>
                </div>

                <div class="mb-3">
                    <label>Estado</label>
                    <select name="estado" class="form-control" required>
                        <option value="1">Activo</option>
                        <option value="0">Inactivo</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label>País</label>
                    <select name="pais" class="form-control" required>
                        <option value="">Seleccione un país</option>
                        <option value="Ecuador">Ecuador</option>
                        <option value="Colombia">Colombia</option>
                        <option value="Chile">Chile</option>
                        <option value="Peru">Perú</option>
                    </select>
                </div>

            </div>

        </div>

        <div class="mt-3">
            <button type="submit" class="btn btn-primary w-60">Registrar</button>
        </div>

    </form>
</div>

<script>
function togglePassword(id) {
    const input = document.getElementById(id);
    input.type = input.type === "password" ? "text" : "password";
}
</script>
@endsection
