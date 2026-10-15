@php
  use Illuminate\Support\Facades\Auth;
  $user = Auth::user();
  $fullName = $user ? trim($user->nombre.' '.$user->apellido) : 'Usuario';
  $initials = strtoupper(mb_substr($user?->nombre ?? 'U',0,1));
  $rol = $user?->rol; // <-- rol actual
@endphp
@php use Illuminate\Support\Facades\Route; @endphp

<style>
  /* Altura y proporción del logo dentro del navbar */
  .brand-logo{
    height: 40px !important;   /* controla la altura del navbar */
    width: auto !important;    /* evita que se estire al 100% del contenedor */
    max-height: 40px !important;
    display: block;
    object-fit: contain;
  }

  /* Quita padding vertical extra del navbar */
  .navbar-brand{ padding-top: 0 !important; padding-bottom: 0 !important; }
  .navbar{ padding-top: .25rem; padding-bottom: .25rem; }

  /* Gradiente del navbar (opcional) */
  .navbar-gradient{ background: linear-gradient(135deg,#0d6efd,#198754); }

  /* Enlaces más compactos */
  .navbar .nav-link{ padding-top:.35rem; padding-bottom:.35rem; }
</style>

<nav class="navbar navbar-expand-lg navbar-dark navbar-gradient sticky-top shadow-sm">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center gap-2" href="{{ route('home') }}">
      <img src="{{ asset('images/logo-dark.png') }}" alt="Escollanos" class="brand-logo">
    </a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#topNav">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="topNav">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">

        {{-- Inicio: todos los roles --}}
        {{-- Inicio: todos --}}
        <li class="nav-item">
          <a class="nav-link {{ request()->routeIs('home') ? 'fw-semibold' : '' }}" href="{{ route('home') }}">
            <i class="bi bi-house-door"></i> Inicio
          </a>
        </li>

        {{-- Producción: Admin, Visitador, Distribuidor, Laboratorio --}}
        @if($user && $user->hasRole(['Admin','Visitador','Laboratorio','Call','Distribuidor']))
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle {{ request()->routeIs('formulas.*') || request()->routeIs('fe.*') ? 'fw-semibold' : '' }}" href="#" role="button" data-bs-toggle="dropdown">
              <i class="bi bi-grid-3x3-gap"></i> Producción
            </a>
            <ul class="dropdown-menu">
              <li><a class="dropdown-item" href="{{ route('fe.index') }}">Fórmulas Establecidas</a></li>
              <li><a class="dropdown-item" href="{{ route('formulas.nuevas') }}">Fórmulas Nuevas</a></li>
              <li>
                <a class="dropdown-item {{ request()->routeIs('formulas-homeo.*') ? 'active' : '' }}"
                   href="{{ route('formulas-homeo.nueva') }}">
                  Fórmulas Nuevas Homeopáticas
                </a>
              </li>
              <li>
                <a class="dropdown-item" href="{{ route('formulas-homeo.establecidas') }}">
                  Fórmulas Establecidas Homeopáticas
                </a>
              </li>
              <li><a class="dropdown-item" href="{{ route('formulas.recientes') }}">Fórmulas Recientes</a></li>
            </ul>
          </li>
        @endif

        {{-- Recetas: todos los usuarios autenticados --}}
        @if($user)
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle {{ request()->routeIs('recetas.*') ? 'fw-semibold' : '' }}"
               href="#" role="button" data-bs-toggle="dropdown">
              <i class="bi bi-journal-text"></i> Recetas
            </a>
            <ul class="dropdown-menu">
              <li>
                <a class="dropdown-item {{ request()->routeIs('recetas.homeopatico') ? 'active' : '' }}"
                   href="{{ route('recetas.homeopatico') }}">
                  Recetas Homeopático
                </a>
              </li>
            </ul>
          </li>
        @endif

        {{-- Pedidos: Admin, Distribuidor --}}
        @if($user && $user->hasRole(['Admin','Distribuidor']))
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle {{ request()->routeIs('pedidos.*') ? 'fw-semibold' : '' }}"
              href="#" role="button" data-bs-toggle="dropdown">
              <i class="bi bi-bag-check"></i> Pedidos
            </a>

            <ul class="dropdown-menu">

              {{-- Submenú: Mis pedidos --}}
              @if (Route::has('pedidos.mis') || Route::has('pedidos.formulas.mis'))
                <li><h6 class="dropdown-header">Mis pedidos</h6></li>

                @if (Route::has('pedidos.mis'))
                  <li>
                    <a class="dropdown-item" href="{{ route('pedidos.mis') }}">
                      Productos
                    </a>
                  </li>
                @endif

                @if (Route::has('pedidos.formulas.mis'))
                  <li>
                    <a class="dropdown-item" href="{{ route('pedidos.formulas.mis') }}">
                      Fórmulas
                    </a>
                  </li>
                @endif

                <li><hr class="dropdown-divider"></li>
              @endif

              {{-- Submenú: Nuevo pedido --}}
              <li><h6 class="dropdown-header">Nuevo pedido</h6></li>

              <li>
                <a class="dropdown-item" href="{{ route('pedidos.productos') }}">
                  Productos Finales
                </a>
              </li>

              @if (Route::has('pedidos.formulas'))
                <li>
                  <a class="dropdown-item" href="{{ route('pedidos.formulas') }}">
                    Fórmulas ortomoleculares
                  </a>
                </li>
              @endif

            </ul>
          </li>
        @endif

        {{-- Usuarios: solo Admin --}}
        @if($user && $user->hasRole(['Admin']))
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle {{ request()->routeIs('usuarios.*') ? 'fw-semibold' : '' }}"
              href="#" role="button" data-bs-toggle="dropdown">
              <i class="bi bi-people"></i> Usuarios
            </a>
            <ul class="dropdown-menu">
              <li>
                <a class="dropdown-item" href="{{ route('usuarios.index') }}">
                  Listar usuarios
                </a>
              </li>
              <li>
                <a class="dropdown-item" href="{{ route('usuarios.create') }}">
                  Registrar usuario
                </a>
              </li>
            </ul>
          </li>
        @endif

        @if($user && $user->hasRole(['Admin']))
          <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('activos.*') ? 'fw-semibold' : '' }}" href="{{ route('activos.index') }}">
              <i class="bi bi-box-seam"></i> Activos
            </a>
          </li>
        @endif
        
        @if($user && $user->hasRole(['Admin','Laboratorio']))
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle {{ request()->routeIs('etiquetas_especiales.*') ? 'fw-semibold' : '' }}"
               href="#" role="button" data-bs-toggle="dropdown">
              <i class="bi bi-tags"></i> Etiquetas
            </a>
            <ul class="dropdown-menu">
              <li><a class="dropdown-item" target="_blank" rel="noopener noreferrer" href="{{ route('etiquetas_especiales.martinez') }}">Martínez-Biolosmed</a></li>
              <li><a class="dropdown-item" target="_blank" rel="noopener noreferrer" href="{{ route('etiquetas_especiales.julisa') }}">Dra. Julisa</a></li>
              <li><a class="dropdown-item" target="_blank" rel="noopener noreferrer" href="{{ route('etiquetas_especiales.balance') }}">Dra Viteri</a></li>
              <li><a class="dropdown-item" target="_blank" rel="noopener noreferrer" href="{{ route('etiquetas_especiales.viteri_elaborado') }}">Dra Viteri Elaborado</a></li>
              <li><a class="dropdown-item" target="_blank" rel="noopener noreferrer" href="{{ route('etiquetas_especiales.mesalbuda') }}">Mesalbuda</a></li>
              <li><a class="dropdown-item" target="_blank" rel="noopener noreferrer" href="{{ route('etiquetas_especiales.editable') }}">Editable</a></li>
              <li><a class="dropdown-item" target="_blank" rel="noopener noreferrer" href="{{ route('etiquetas_especiales.naturmed') }}">Naturmed</a></li>
            </ul>
          </li>
        @endif


        

      </ul>

      {{-- Perfil / Salir --}}
      <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-center gap-2">
        <li class="nav-item dropdown">
          <a class="nav-link d-flex align-items-center gap-2 dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
            <span class="avatar-initial">{{ $initials }}</span>
            <span class="d-none d-sm-inline">{{ $fullName }}</span>
          </a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><h6 class="dropdown-header">{{ $fullName }}</h6></li>
            <li><a class="dropdown-item" href="#"><i class="bi bi-person-circle me-2"></i>Mi perfil (próx.)</a></li>
            <li><hr class="dropdown-divider"></li>
            <li>
              <form method="POST" action="{{ route('logout') }}">@csrf
                <button class="dropdown-item"><i class="bi bi-box-arrow-right me-2"></i>Salir</button>
              </form>
            </li>
          </ul>
        </li>
      </ul>

    </div>
  </div>
</nav>
