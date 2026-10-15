<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\FormulaController;
use App\Http\Controllers\FormulasEstController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\MedicoController;
use App\Http\Controllers\RecetaController;
use App\Http\Controllers\PedidoController;
use App\Http\Controllers\PedidoFormulaController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\ActivoController;
use App\Http\Controllers\FormulaHomeoController;

use App\Http\Middleware\ProduccionAccess;
use App\Http\Middleware\PedidosAccess;
use App\Http\Middleware\RecetasAccess;
use App\Http\Middleware\EtiquetasAccess;

/*
|--------------------------------------------------------------------------
| Rutas públicas (sin autenticación)
|--------------------------------------------------------------------------
*/
Route::get('password/forgot', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('password.request');
Route::post('password/email', [ForgotPasswordController::class, 'sendResetLinkEmail'])->name('password.email');

Route::get('password/reset/{token}', [ResetPasswordController::class, 'showResetForm'])->name('password.reset');
Route::post('password/reset', [ResetPasswordController::class, 'reset'])->name('password.update');

Route::get('/recetas/publica/{receta}', [RecetaController::class, 'publicShow'])
    ->whereNumber('receta')
    ->name('recetas.public.show');

Route::middleware('guest')->group(function () {
    Route::get('/login',  [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
});

// Comprobación de DomPDF
Route::get('/dompdf-check', function () {
    return response()->json([
        'pkg_dir_exists'        => is_dir(base_path('vendor/barryvdh/laravel-dompdf')),
        'class_Facade'          => class_exists(\Barryvdh\DomPDF\Facade::class),
        'class_Facade_Pdf'      => class_exists(\Barryvdh\DomPDF\Facade\Pdf::class),
        'class_ServiceProvider' => class_exists(\Barryvdh\DomPDF\ServiceProvider::class),
        'bound_wrapper'         => app()->bound('dompdf.wrapper'),
    ]);
});

// Autocompletado de productos (pedidos)
Route::get('/pedidos/productos/buscar', [PedidoController::class, 'buscarProductos'])
    ->name('pedidos.productos.buscar');

Route::get('/pedidos/formulas/buscar', [PedidoFormulaController::class, 'buscarFormulas'])
    ->name('pedidos.formulas.buscar');

/*
|--------------------------------------------------------------------------
| Rutas protegidas (requieren login)
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {

    // Dashboard principal
    Route::get('/', [HomeController::class, 'index'])->name('home');

    /*
    |------------------------- Usuarios / Médicos -------------------------
    */
    Route::get('/usuarios',                   [UserController::class, 'index'])->name('usuarios.index');
    Route::get('/usuarios/crear',             [UserController::class, 'create'])->name('usuarios.create');
    Route::post('/usuarios',                  [UserController::class, 'store'])->name('usuarios.store');
    Route::get('/usuarios/{usuario}/editar',  [UserController::class, 'edit'])->name('usuarios.edit');
    Route::put('/usuarios/{usuario}',         [UserController::class,'update'])->name('usuarios.update');

    Route::get('/buscar-medico',  [FormulaController::class, 'buscarMedico'])->name('medicos.buscar');
    Route::get('/medicos/buscar', [MedicoController::class, 'buscar'])->name('medicos.buscar');

    /*
    |------------------------- PRODUCCIÓN -------------------------
    | Acceso controlado por ProduccionAccess:
    | - Admin
    | - Visitador
    | - Distribuidor
    | - Laboratorio
    */
    Route::middleware(ProduccionAccess::class)->group(function () {

        // Formulas dinámicas
        Route::prefix('formulas')->name('formulas.')->group(function () {

            Route::view('/nuevas', 'formulas.nuevas')->name('nuevas');
            Route::get('/recientes', [FormulaController::class, 'recientes'])->name('recientes');

            Route::post('/buscar-producto', [FormulaController::class, 'buscarProducto'])->name('buscar');
            Route::post('/agregar-temp',    [FormulaController::class, 'agregarTemp'])->name('agregar');
            Route::get ('/listar-temp',     [FormulaController::class, 'listarTemp'])->name('listar');
            Route::post('/eliminar-temp',   [FormulaController::class, 'eliminarTemp'])->name('eliminar');
            Route::post('/eliminar-todos',  [FormulaController::class, 'eliminarTodos'])->name('eliminarTodos');

            Route::get ('/resumen-capsulas', [FormulaController::class, 'resumenCapsulas'])->name('resumen_capsulas');
            Route::get ('/resumen-sobres',   [FormulaController::class, 'resumenSobres'])->name('resumen_sobres');

            Route::post('/guardar',         [FormulaController::class, 'guardar'])->name('guardar');
            Route::post('/guardar-sobres',  [FormulaController::class, 'guardarSobres'])->name('guardar_sobres');

            // Cargar ítems de una fórmula a activo_temps para edición
            Route::get('/{id}/editar', [FormulaController::class, 'cargarParaEditar'])->name('editar.cargar');
        });

        Route::prefix('formulas-homeopaticas')->name('formulas-homeo.')->group(function () {
            Route::get('/nueva', [FormulaHomeoController::class, 'index'])->name('nueva');
            Route::get('/establecidas', [FormulaHomeoController::class, 'establecidas'])->name('establecidas');
            Route::get('/establecidas/buscar', [FormulaHomeoController::class, 'buscarEstablecidas'])->name('establecidas.buscar');
            Route::post('/establecidas/agregar', [FormulaHomeoController::class, 'agregarEstablecida'])->name('establecidas.agregar');
            Route::delete('/establecidas/limpiar', [FormulaHomeoController::class, 'limpiarEstablecidas'])->name('establecidas.limpiar');
            Route::delete('/establecidas/{formula}', [FormulaHomeoController::class, 'quitarEstablecida'])->name('establecidas.quitar');
            Route::post('/establecidas/recetas', [FormulaHomeoController::class, 'recetasSeleccionadas'])->name('establecidas.recetas');
            Route::get('/buscar-activos', [FormulaHomeoController::class, 'buscar'])->name('buscar');
            Route::get('/items', [FormulaHomeoController::class, 'listar'])->name('listar');
            Route::post('/items', [FormulaHomeoController::class, 'agregar'])->name('agregar');
            Route::delete('/items/{item}', [FormulaHomeoController::class, 'eliminar'])->name('eliminar');
            Route::delete('/items', [FormulaHomeoController::class, 'limpiar'])->name('limpiar');
            Route::post('/cancelar-edicion', [FormulaHomeoController::class, 'cancelarEdicion'])->name('cancelar-edicion');
            Route::get('/{formula}/editar', [FormulaHomeoController::class, 'editar'])->name('editar');
            Route::post('/{formula}/receta', [FormulaHomeoController::class, 'receta'])->name('receta');
            Route::post('/', [FormulaHomeoController::class, 'guardar'])->name('guardar');
        });

        // Formulas establecidas
        Route::prefix('formulas/establecidas')->name('fe.')->group(function () {
            Route::get('/',             [FormulasEstController::class,'index'])->name('index');
            Route::get('/buscar',       [FormulasEstController::class,'buscar'])->name('buscar');
            Route::post('/add',         [FormulasEstController::class,'add'])->name('add');
            Route::post('/update-tipo', [FormulasEstController::class,'updateTipo'])->name('update');
            Route::delete('/{id}',      [FormulasEstController::class,'remove'])->name('remove');
            Route::delete('/clear/all', [FormulasEstController::class,'clear'])->name('clear');

            Route::get('/{id}/print',   [FormulasEstController::class,'print'])->name('print');
            Route::post('/{id}/receta',  [FormulasEstController::class,'recetaCreate'])->name('receta.create');
            Route::get('/{id}/excel',   [FormulasEstController::class,'excel'])->name('excel');

            Route::get('/{id}/items', [FormulasEstController::class,'items'])->name('items');
            Route::get('/{id}/items/export', [FormulasEstController::class,'itemsExportXlsx'])->name('items.export');
        });

    });

    /*
    |------------------------- RECETAS -------------------------
    | Acceso controlado por RecetasAccess:
    | - Admin
    | - Laboratorio
    */
    Route::middleware(RecetasAccess::class)->group(function () {
        Route::post('/recetas', [RecetaController::class, 'storeMultiple'])->name('recetas.storeMultiple');
        Route::get('/recetas/{receta}/enviar-mail', [RecetaController::class, 'testEnviarMail'])->name('recetas.testMail');

        // Nueva vista: crear receta con productos
        Route::get('/recetas/crear', [RecetaController::class, 'create'])->name('recetas.create');
        Route::get('/recetas/buscar-productos', [RecetaController::class, 'buscarProductos'])->name('recetas.buscarProductos');
        Route::post('/recetas/guardar', [RecetaController::class, 'store'])->name('recetas.store');
        Route::get('/recetas/homeopatico', [RecetaController::class, 'homeopatico'])->name('recetas.homeopatico');
        Route::post('/recetas/homeopatico', [RecetaController::class, 'storeHomeopatico'])->name('recetas.homeopatico.store');

        Route::get('/recetas', [RecetaController::class, 'index'])->name('recetas.index');
        Route::get('/recetas/{receta}', [RecetaController::class, 'show'])
            ->whereNumber('receta')
            ->name('recetas.show');
    });

    /*
    |------------------------- PEDIDOS -------------------------
    | Acceso controlado por PedidosAccess:
    | - Admin
    | - Distribuidor
    */
    Route::prefix('pedidos')->name('pedidos.')
        ->middleware(PedidosAccess::class)
        ->group(function () {

            // ======= PRODUCTOS =======
            Route::get('/productos', [PedidoController::class, 'productos'])->name('productos');
            Route::post('/productos/agregar', [PedidoController::class, 'agregarProducto'])->name('productos.agregar');

            Route::delete('/productos/{index}', [PedidoController::class, 'eliminarProducto'])->name('productos.eliminar');

            Route::delete('/pedidos/productos/{id}', [PedidoController::class, 'eliminarProducto'])
                ->name('pedidos.productos.eliminar');

            Route::post('/finalizar', [PedidoController::class, 'finalizar'])->name('finalizar');

            Route::get('/{pedido}/pdf', [PedidoController::class, 'pdf'])->name('pdf');

            Route::get('/mis-pedidos', [PedidoController::class, 'misPedidos'])->name('mis');

            // ======= FÓRMULAS =======
            Route::get('/formulas', [PedidoFormulaController::class, 'formulas'])->name('formulas');
            Route::post('/formulas/agregar', [PedidoFormulaController::class, 'agregarFormula'])->name('formulas.agregar');
            Route::delete('/formulas/{id}', [PedidoFormulaController::class, 'eliminarFormula'])->name('formulas.eliminar');
            Route::post('/formulas/finalizar', [PedidoFormulaController::class, 'finalizarFormulas'])->name('formulas.finalizar');
            Route::get('/formulas/{pedido}/pdf', [PedidoFormulaController::class, 'pdf'])->name('formulas.pdf');
            Route::get('/mis-pedidos-formulas', [PedidoFormulaController::class, 'misPedidosFormulas'])->name('formulas.mis');
        });

    /*
    |------------------------- ACTIVOS -------------------------
    */
    Route::prefix('activos')->name('activos.')->middleware('activos')->group(function () {
        Route::get('/', [ActivoController::class, 'index'])->name('index');
        Route::get('/{activo}/editar', [ActivoController::class, 'edit'])->name('edit');
        Route::put('/{activo}', [ActivoController::class, 'update'])->name('update');
    });

    /*
    |------------------------- ETIQUETAS ESPECIALES -------------------------
    | SOLO LABORATORIO (sin selección de fórmula)
    */
    Route::middleware([EtiquetasAccess::class])
        ->prefix('etiquetas-especiales')
        ->name('etiquetas_especiales.')
        ->group(function () {
    
            Route::view('/martinez',  'etiquetas_especiales.martinez')->name('martinez');
            Route::view('/julisa',    'etiquetas_especiales.julisa')->name('julisa');
            Route::view('/balance',   'etiquetas_especiales.balance')->name('balance');
            Route::view('/viteri_elaborado',   'etiquetas_especiales.viteri_elaborado')->name('viteri_elaborado');
            Route::view('/mesalbuda', 'etiquetas_especiales.mesalbuda')->name('mesalbuda');
            Route::view('/editable',  'etiquetas_especiales.editable')->name('editable');
            Route::view('/naturmed', 'etiquetas_especiales.naturmed')->name('naturmed');
        });


    /*
    |------------------------- Logout -------------------------
    */
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});
