<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Iniciar sesión</title>

  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons (para adornar inputs) -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <!-- Fuente bonita -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">

  <style>
    :root{
      --brand-1:#6a5cff;   /* morado */
      --brand-2:#19c3ff;   /* celeste */
      --brand-3:#7b61ff;   /* morado botón */
      --card-radius:22px;
    }
    *{font-family: "Inter", system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", "Apple Color Emoji","Segoe UI Emoji","Segoe UI Symbol";}

    body{
      min-height:100vh;
      background: radial-gradient(1200px 600px at 10% 10%, rgba(123,97,255,.25) 0%, transparent 60%),
                  radial-gradient(1000px 500px at 90% 90%, rgba(25,195,255,.25) 0%, transparent 60%),
                  #0f1020;
      display:grid; place-items:center;
      padding:32px 16px;
    }

    .auth-card{
      width:min(1060px, 100%);
      border:0;
      border-radius:var(--card-radius);
      overflow:hidden;
      box-shadow: 0 20px 60px rgba(0,0,0,.35), inset 0 0 0 1px rgba(255,255,255,.04);
      background:transparent;
    }

    .auth-inner{
      display:grid;
      grid-template-columns: 1.1fr 1fr;
      background:#fff;
      border-radius:var(--card-radius);
    }

    /* Lado visual (izquierdo) */
    .auth-visual{
      position:relative;
      padding:48px 44px;
      color:#fff;
      background: linear-gradient(135deg, var(--brand-1) 0%, var(--brand-2) 100%);
      isolation:isolate;
    }
    .auth-visual::after{
      /* ondas suaves */
      content:"";
      position:absolute; inset:0;
      background:
        radial-gradient(60% 40% at 20% 80%, rgba(255,255,255,.16) 0%, transparent 60%),
        radial-gradient(55% 35% at 80% 20%, rgba(255,255,255,.14) 0%, transparent 60%);
      z-index:0;
    }
    .auth-visual .brand{
      font-weight:700; letter-spacing:.5px; opacity:.95;
    }
    .auth-visual .welcome{
      margin-top:28px;
    }
    .auth-visual h1{
      font-size:clamp(28px, 3.2vw, 40px);
      line-height:1.05;
      margin:0 0 10px 0;
      font-weight:800;
    }
    .auth-visual p{opacity:.9; max-width:34ch}
    .auth-visual .footer-note{
      position:absolute; left:44px; bottom:28px; opacity:.85; font-size:.9rem;
    }

    /* Lado formulario (derecho) */
    .auth-form{
      padding:44px 40px;
      background: #ffffff;
    }
    .auth-form .title{
      font-weight:800; color:#2b2d42; margin-bottom:6px;
    }
    .auth-form .subtitle{
      color:#6b7280; font-size:.95rem; margin-bottom:22px;
    }

    /* Inputs con icono */
    .input-icon{
      position:relative;
    }
    .input-icon .bi{
      position:absolute; left:12px; top:50%; transform:translateY(-50%); opacity:.55;
      pointer-events:none;
    }
    .input-icon input{
      padding-left:40px;
      height:46px;
    }
    .form-check-label{ user-select:none; }

    /* Botón */
    .btn-brand{
      background: linear-gradient(135deg, var(--brand-3), var(--brand-1));
      border:none;
      height:48px;
      font-weight:700;
      letter-spacing:.3px;
      transition: transform .08s ease, filter .15s ease;
      color:#fff;
    }
    .btn-brand:hover{ filter:brightness(1.05); }
    .btn-brand:active{ transform: translateY(1px); }

    /* Links utilitarios */
    .muted{ color:#6b7280; }
    .muted a{ text-decoration:none; font-weight:600; }
    .muted a:hover{ text-decoration:underline; }

    /* Errores */
    .errors{
      background:#fff4f5; border:1px solid #ffd6da; color:#9f1239;
      padding:10px 12px; border-radius:10px; font-size:.93rem;
    }

    /* Responsive */
    @media (max-width: 992px){
      .auth-inner{ grid-template-columns:1fr; }
      .auth-visual{ min-height:220px; padding:36px 28px; }
      .auth-visual .footer-note{ position:static; margin-top:16px; }
      .auth-form{ padding:28px; }
    }
  </style>
</head>
<body>

  <main class="auth-card">
    <section class="auth-inner">

      <!-- Lado izquierdo (visual) -->
      <div class="auth-visual">
        <div class="brand">ESCOLLANOS CIA LTDA</div>
        <div class="welcome">
          <h1>Bienvenido a Ortomed</h1>
          <p>Accede para gestionar fórmulas, activos y pedidos en un entorno seguro.</p>
        </div>
        <div class="footer-note">© Escollanos Medicamentos Biológicos - 2026</div>
      </div>

      <!-- Lado derecho (formulario) -->
      <div class="auth-form">

        <h2 class="h4 title">Login</h2>
        <div class="subtitle">Ingresa tus credenciales para continuar.</div>

        @if ($errors->any())
          <div class="errors mb-3">
            @foreach ($errors->all() as $e)
              <div>• {{ $e }}</div>
            @endforeach
          </div>
        @endif

        <form method="POST" action="{{ route('login.post') }}" novalidate>
          @csrf

          <div class="mb-3 input-icon">
            <i class="bi bi-envelope"></i>
            <label class="form-label" for="correo">Correo electrónico</label>
            <input id="correo" type="email" name="correo" class="form-control" value="{{ old('correo') }}" required autofocus>
          </div>

          <div class="mb-2 input-icon">
            <i class="bi bi-lock"></i>
            <label class="form-label" for="password">Contraseña</label>
            <input id="password" type="password" name="password" class="form-control" required>
          </div>

          <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="remember" id="remember">
              <label class="form-check-label" for="remember">Recordarme</label>
            </div>

            @if (Route::has('password.request'))
              <a class="small" href="{{ route('password.request') }}">¿Olvidaste tu contraseña?</a>
            @endif
          </div>

          <button type="submit" class="btn btn-brand w-100">Ingresar</button>
        </form>
      </div>

    </section>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
