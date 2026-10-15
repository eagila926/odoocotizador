<?php

namespace App\Http\Controllers;

use App\Models\Activo;
use App\Models\ActivoTemp;
use App\Models\Formula;
use App\Models\FormulaItem;
use App\Models\Medico;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use App\Http\Controllers\FormulasEstController;


class FormulaController extends Controller
{
    
    // Vista principal
    public function index(Request $request)
    {
        return view('formulas.nuevas');
    }

    // Autocompletar de productos
    public function buscarProducto(Request $request)
    {
        $term = trim($request->input('producto', ''));
        if ($term === '') return '';

        $items = Activo::query()
            ->where('nombre', 'like', "%{$term}%")
            ->orWhere('cod_odoo', 'like', "%{$term}%")
            ->orderBy('nombre')
            ->limit(15)
            ->get(['cod_odoo','nombre','minimo','maximo','unidad']);

        $html = '';
        foreach ($items as $it) {
            // IMPORTANTE: asegurar que min/max salgan como número (por si en DB hay "100 mg")
            $min = is_null($it->minimo) ? '' : (float)$it->minimo;
            $max = is_null($it->maximo) ? '' : (float)$it->maximo;
            $unidad = $it->unidad ?: 'mg';

            $html .= '<div class="suggest-element" '
                .'data-cod_odoo="'.e($it->cod_odoo).'" '
                .'data-minimo="'.e($min).'" '
                .'data-maximo="'.e($max).'" '
                .'data-unidad="'.e($unidad).'" '
                .'style="padding:6px; cursor:pointer; border-bottom:1px solid #eee">'
                .e($it->nombre)
                .'</div>';
        }
        return $html;
    }



    // Agregar a temporales del usuario
    public function agregarTemp(Request $request)
    {
        $request->validate([
            'activo'   => 'required|string|max:255',
            'cod_odoo' => 'required|integer',
            'cantidad' => 'required|numeric|min:0.0001',
            'unidad'   => 'required|in:mg,mcg,UI',
        ]);

        $userId = Auth::id() ?? $request->session()->get('user_id');
        if (!$userId) return response('NO_USER', 401);

        // Límite de 15
        $count = ActivoTemp::where('user_id', $userId)->count();
        if ($count >= 15) {
            return response('LIMITE_SUPERADO', 422);
        }

        // Ficha del activo
        $act = Activo::where('cod_odoo', $request->cod_odoo)
            ->first(['minimo','maximo','unidad']);
        if (!$act) return response('ACTIVO_NO_ENCONTRADO', 404);

        // Unidad única permitida
        $unidadPermitida = $act->unidad ?: 'mg';
        if ($request->unidad !== $unidadPermitida) {
            return response('UNIDAD_INVALIDA', 422);
        }

        // ⚠️ Soft warnings: NO bloquea
        $min  = $act->minimo ?? null;
        $max  = $act->maximo ?? null;
        $cant = (float) $request->cantidad;

        $warnings = [];
        if (!is_null($min) && $cant < (float)$min) { $warnings[] = 'BAJO_MIN'; }
        if (!is_null($max) && $cant > (float)$max) { $warnings[] = 'SOBRE_MAX'; }

        // No duplicados
        $exists = ActivoTemp::where('user_id',$userId)
            ->where('cod_odoo',$request->cod_odoo)
            ->exists();
        if ($exists) return response('duplicado', 200);

        // Guardar SIEMPRE aunque esté fuera de rango
        ActivoTemp::create([
            'user_id'  => $userId,
            'cod_odoo' => $request->cod_odoo,
            'activo'   => $request->activo,
            'cantidad' => $request->cantidad,
            'unidad'   => $request->unidad,
        ]);

        // Devuelve OK + warnings (si los hubo)
        return response()->json([
            'status'   => 'ok',
            'warnings' => $warnings,
        ]);
    }
    
    
    private function minMasaMes(float $value): float
    {
        // Solo corrige valores positivos muy pequeños
        if ($value > 0 && $value < 0.001) {
            return 0.001;
        }
        return $value;
    }



    // Listar temporales (HTML o JSON)
    public function listarTemp(Request $request)
    {
        $userId = Auth::id() ?? $request->session()->get('user_id');
        if (!$userId) return '';

        $rows = ActivoTemp::where('user_id', $userId)
            ->orderBy('id')
            ->get();

        if ($request->boolean('json')) {
            return response()->json($rows);
        }

        $i = 1; $html = '';
        foreach ($rows as $r) {
            $html .= '<tr>';
            $html .= '<td class="d-none d-md-table-cell">'.($i++).'</td>';
            $html .= '<td class="d-none d-md-table-cell">'.e($r->cod_odoo).'</td>';
            $html .= '<td>'.e($r->activo).'</td>';
            $html .= '<td>'.e($r->cantidad).'</td>';
            $html .= '<td>'.e($r->unidad).'</td>';
            $html .= '<td><button class="btn btn-sm btn-danger" onclick="eliminarFila('.$r->id.')">X</button></td>';
            $html .= '</tr>';
        }
        return $html;
    }

    // Eliminar una fila temporal
    public function eliminarTemp(Request $request)
    {
        $request->validate(['id' => 'required|integer']);
        $userId = Auth::id() ?? $request->session()->get('user_id');
        if (!$userId) return response('NO_USER', 401);

        $row = ActivoTemp::where('user_id',$userId)->where('id',$request->id)->first();
        if (!$row) return response('NOT_FOUND', 404);

        $row->delete();
        return response('ok', 200);
    }

    // Vaciar temporales
    public function eliminarTodos(Request $request)
    {
        $userId = Auth::id() ?? $request->session()->get('user_id');
        if (!$userId) return response('NO_USER', 401);

        ActivoTemp::where('user_id', $userId)->delete();
        return response('OK', 200);
    }

    // Resúmenes
    public function resumenCapsulas(Request $request) { return $this->renderResumen($request, 'capsulas'); }
    public function resumenSobres(Request $request)   { return $this->renderResumen($request, 'sobres'); }

    // =================== Helpers para CODIGO ===================

    private function slugNoAcentos(string $texto): string
    {
        return (string) Str::of($texto)->squish()->lower()->ascii();
    }

    /**
     * Redondea SIEMPRE hacia arriba al siguiente múltiplo de 0.10
     * Ej: 14.31 -> 14.40 | 14.30 -> 14.30
     */
    private function roundUpTenth(float $value): float
    {
        return ceil($value * 10) / 10;
    }

    private function roundUpToStep(float $value, float $step = 0.10): float
    {
        if ($step <= 0) return $value;

        // Evita problemas típicos de floats (43.4 puede venir como 43.399999999)
        $inv = 1 / $step;

        return ceil(($value * $inv) - 1e-9) / $inv;
    }


    private function buildCodFormula(): string
    {
        $user = Auth::user();

        $nombre   = trim((string)($user->nombre ?? ''));
        $apellido = trim((string)($user->apellido ?? ''));

        $iniNombre   = mb_substr($this->slugNoAcentos($nombre), 0, 1);
        $iniApellido = mb_substr($this->slugNoAcentos($apellido), 0, 2);
        $iniciales   = mb_strtoupper($iniNombre.$iniApellido, 'UTF-8'); // EAG

        $mes = now()->format('n'); // 1..12

        // Generar número aleatorio de 6 dígitos
        $random = random_int(100000, 999999);

        return "FO{$iniciales}{$mes}.{$random}";
    }

    /** Código para SOBRES: SFO + iniciales + mes + . + 6 dígitos */
    private function buildCodSobres(): string
    {
        $user = Auth::user();

        $nombre   = trim((string)($user->nombre ?? ''));
        $apellido = trim((string)($user->apellido ?? ''));

        $iniNombre   = mb_substr($this->slugNoAcentos($nombre), 0, 1);
        $iniApellido = mb_substr($this->slugNoAcentos($apellido), 0, 2);
        $iniciales   = mb_strtoupper($iniNombre.$iniApellido, 'UTF-8'); // ej. EAG

        $mes = now()->format('n'); // 1..12
        $random = random_int(100000, 999999); // 6 dígitos

        return "SFO{$iniciales}{$mes}.{$random}";
    }


    // =================== Render de Resumen (cálculos) ===================

    private function renderResumen(Request $request, string $tipo)
    {
        $userId = Auth::id() ?? $request->session()->get('user_id');

        // =========================
        //       RESUMEN: SOBRES
        // =========================
        if ($tipo === 'sobres') {
            $items  = ActivoTemp::where('user_id', $userId)->orderBy('id')->get();

            // Catálogo: temporales + fijos
            $codes = $items->pluck('cod_odoo')->map(fn($c)=>(int)$c)->all();
            $codes = array_unique(array_merge($codes, [70277, 70299, 70256, 9585])); // CAJA/SOBRES/CLIGHT/SUCRALOSA

            $catalogo = Activo::whereIn('cod_odoo', $codes)
                ->get(['cod_odoo','nombre','valor_costo','factor','factor_venta','densidad'])
                ->keyBy('cod_odoo');

            $rows = collect();

            // Helpers
            $calcSubtotal = function (int $cod, float $mg_dia) use ($catalogo): array {
                $a = $catalogo->get($cod);
                $valor_costo  = (float)($a->valor_costo  ?? 0.0); // $/mg
                $factor       = (float)($a->factor       ?? 1.0);
                $factor_venta = (float)($a->factor_venta ?? 1.0);

                // Cantidad para mostrar (sigue con factor si quieres ver "Cant. total")
                $cant_total_mg = $mg_dia * $factor;

                // >>> PRECIO SIN FACTOR <<<
                $subtotal = round($mg_dia * $valor_costo * $factor_venta, 6); // $/día

                return [$valor_costo, $factor_venta, $cant_total_mg, $subtotal];
            };
            $calcPesaje = function (int $cod, float $mg_dia) use ($catalogo): array {
                $a        = $catalogo->get($cod);
                $factor   = (float)($a->factor   ?? 1.0);
                $densidad = (float)($a->densidad ?? 0.0); // g/ml
                $mg_dia_tot = $mg_dia * $factor;
                $g_dia      = $mg_dia_tot / 1000.0;                   // g/día
                $vol_ml_dia = $densidad > 0 ? ($g_dia / $densidad) : 0;// ml/día
                $masa_mes_g = $g_dia * 30.0;                           // g/mes
                return [$densidad, $g_dia, $vol_ml_dia, $masa_mes_g];
            };

            // 1) Fijos und
            $rows->push([
                'cod_odoo'=>70277,'activo'=>'CAJA','cantidad'=>1,'unidad'=>'und',
                'cantidad_total'=>null,'valor_costo'=>null,'factor_venta'=>null,'subtotal'=>0,
                'densidad'=>null,'cant_total_pesaje'=>null,'vol_ml'=>null,'masa_mes'=>1,
            ]);
            $rows->push([
                'cod_odoo'=>70299,'activo'=>'SOBRES','cantidad'=>30,'unidad'=>'und',
                'cantidad_total'=>null,'valor_costo'=>null,'factor_venta'=>null,'subtotal'=>0,
                'densidad'=>null,'cant_total_pesaje'=>null,'vol_ml'=>null,'masa_mes'=>30,
            ]);

            // 2) Fijos mg
            [$vc,$fv,$cant,$sub] = $calcSubtotal(70256, 1500/30);
            [$dens,$g_dia,$ml_dia,$g_mes] = $calcPesaje(70256, 1500/30);
            $rows->push([
                'cod_odoo'=>70256,'activo'=>'CLIGHT','cantidad'=>7000,'unidad'=>'mg',
                'cantidad_total'=>$cant,'valor_costo'=>$vc,'factor_venta'=>$fv,'subtotal'=>$sub,
                'densidad'=>$dens,'cant_total_pesaje'=>$g_dia,'vol_ml'=>$ml_dia,'masa_mes'=>7,
            ]);

            [$vc,$fv,$cant,$sub] = $calcSubtotal(9585, 100/30);
            [$dens,$g_dia,$ml_dia,$g_mes] = $calcPesaje(9585, 100/30);
            $rows->push([
                'cod_odoo'=>9585,'activo'=>'SUCARALOSA','cantidad'=>100,'unidad'=>'mg',
                'cantidad_total'=>$cant,'valor_costo'=>$vc,'factor_venta'=>$fv,'subtotal'=>$sub,
                'densidad'=>$dens,'cant_total_pesaje'=>$g_dia,'vol_ml'=>$ml_dia,'masa_mes'=>0.1,
            ]);

            // 3) Variables por sobre (por día)
            foreach ($items as $r) {
                $mg_dia = match ($r->unidad) {
                    'g'   => (float)$r->cantidad * 1000,
                    'mg'  => (float)$r->cantidad,
                    'mcg' => (float)$r->cantidad / 1000,
                    'UI'  => ($r->cod_odoo == 2730)
                            ? ((float)$r->cantidad * 0.000025)
                            : ((float)$r->cantidad * 0.67),
                    default => 0.0,
                };
                [$vc,$fv,$cant,$sub] = $calcSubtotal((int)$r->cod_odoo, $mg_dia);
                [$dens,$g_dia,$ml_dia,$g_mes] = $calcPesaje((int)$r->cod_odoo, $mg_dia);

                $rows->push([
                    'cod_odoo'=>(int)$r->cod_odoo,
                    'activo'=>(string)$r->activo,
                    'cantidad'=>(float)$r->cantidad,
                    'unidad'=>$r->unidad,
                    'cantidad_total'=>$cant,
                    'valor_costo'=>$vc,
                    'factor_venta'=>$fv,
                    'subtotal'=>$sub,
                    'densidad'=>$dens,
                    'cant_total_pesaje'=>$g_dia,
                    'vol_ml'=>$ml_dia,
                    'masa_mes'=>$g_mes,
                ]);
            }

            // Totales y precios
            $totalVolMl   = (float)$rows->sum('vol_ml');
            $totalMasaMes = (float)$rows->sum('masa_mes');
            $totalGeneral = (float)$rows->sum('subtotal'); // $/día

            $precio_med = max(12, ($totalGeneral * 30) + 4);
            $precio_med = $this->roundUpToStep($precio_med, 0.10); // <-- AQUI

            $precio_dis = $precio_med * 0.65;
            $precio_pvp = round($precio_med * (4 / 3), 2);


            return view('formulas.resumen_sobres', [
                'rows'            => $rows,
                'diasTratamiento' => 30,
                'tomasDiarias'    => 1,
                'codFormula'      => $this->buildCodSobres(),
                'totalGeneral'    => $totalGeneral,
                'precio_med'   => round($precio_med, 2),
                'precio_dis'   => round($precio_dis, 2),
                'precio_pvp'   => round($precio_pvp, 2),
                'totalVolMl'      => $totalVolMl,
                'totalMasaMes'    => $totalMasaMes,
            ]);
        }

        // =========================
        //      RESUMEN: CÁPSULAS
        // =========================
        $items  = ActivoTemp::where('user_id', $userId)->orderBy('id')->get();

        // NUEVO: override por querystring: auto|00|0
        $capsulaReq = $request->query('capsula', 'auto');
        $capsulaReq = in_array($capsulaReq, ['auto','00','0'], true) ? $capsulaReq : 'auto';

        // Catálogo: temporales + filas fijas que se agregan
        $codes = $items->pluck('cod_odoo')->map(fn($c)=>(int)$c)->all();
        $codes = array_unique(array_merge($codes, [1101,1077,1078,1219,1220]));

        // ➜ Necesitamos densidad para calcular volúmenes + nombres de estearato/caps/pastillero
        $catalogo = Activo::whereIn('cod_odoo', $codes)
            ->get(['cod_odoo','nombre','valor_costo','factor','factor_venta','densidad'])
            ->keyBy('cod_odoo');

        // Constantes
        $CAP_VOL_00 = 0.95;   // ml
        $CAP_VOL_0  = 0.68;   // ml
        $D_EST      = 0.3228; // g/ml

        $totalMgDia            = 0.0;  // (costos)
        $totalVolMlDia         = 0.0;  // (ml/día activos)
        $totalMasaMesActivos_g = 0.0;  // (g/mes activos)

        $rows = $items->map(function ($r) use (&$totalMgDia, &$totalVolMlDia, &$totalMasaMesActivos_g, $catalogo) {
            $mgDia = match ($r->unidad) {
                'g'   => (float)$r->cantidad * 1000,
                'mg'  => (float)$r->cantidad,
                'mcg' => (float)$r->cantidad / 1000,
                'UI'  => ((int)$r->cod_odoo === 2730)
                            ? ((float)$r->cantidad * 0.000025)
                            : ((float)$r->cantidad * 0.67),
                default => 0.0,
            };

            $a             = $catalogo->get((int)$r->cod_odoo);
            $valor_costo   = (float)($a->valor_costo  ?? 0.0);
            $factor        = (float)($a->factor       ?? 1.0);
            $factor_venta  = (float)($a->factor_venta ?? 1.0);
            $densidad      = (float)($a->densidad     ?? 0.0); // g/ml

            // Cantidad total con factor
            $mgTotalDia = $mgDia * $factor;

            // Costos SIN factor (como lo tienes)
            $subtotal = round($mgDia * $valor_costo * $factor_venta, 6);

            // Pesaje CON factor
            $gDiaTotal = $mgTotalDia / 1000.0;      // g/día
            $gMesTotal = $gDiaTotal * 30.0;         // g/mes
            $volMlDia  = ($densidad > 0) ? ($gDiaTotal / $densidad) : 0.0;

            $totalMgDia            += $mgDia;
            $totalVolMlDia         += $volMlDia;
            $totalMasaMesActivos_g += $gMesTotal;

            return [
                'cod_odoo'          => (int)$r->cod_odoo,
                'activo'            => (string)$r->activo,
                'cantidad'          => (float)$r->cantidad,
                'unidad'            => (string)$r->unidad,
                'mg'                => $mgDia,

                'valor_costo'       => $valor_costo,
                'factor_venta'      => $factor_venta,

                'cantidad_total'    => $mgTotalDia,      // mg/día con factor
                'subtotal'          => $subtotal,        // $/día sin factor

                'densidad'          => $densidad,
                'cant_total_pesaje' => $gDiaTotal,       // g/día con factor
                'vol_ml'            => $volMlDia,        // ml/día
                'masa_mes'          => $gMesTotal,       // g/mes con factor
            ];
        });

        // Totales base (antes de añadir estearato/cápsulas/pastillero)
        $totalGeneral = (float)collect($rows)->sum('subtotal'); // $/día
        $totalVolMl   = (float)collect($rows)->sum('vol_ml');   // ml/día (activos)
        $totalMasaMes = (float)collect($rows)->sum('masa_mes'); // g/mes (activos)

        // Cálculo de cápsulas por volumen
        $capsDia_00 = (int)ceil($totalVolMl / $CAP_VOL_00);
        $capsDia_0  = (int)ceil($totalVolMl / $CAP_VOL_0);

        $capVolMes_00 = $capsDia_00 * $CAP_VOL_00 * 30.0;
        $capVolMes_0  = $capsDia_0  * $CAP_VOL_0  * 30.0;

        $volNecesarioMes = $totalVolMl * 30.0;

        $volFaltante_00 = max(0.0, $capVolMes_00 - $volNecesarioMes);
        $volFaltante_0  = max(0.0, $capVolMes_0  - $volNecesarioMes);

        $esteratoBase_gMes_00 = $volFaltante_00 * $D_EST;
        $esteratoBase_gMes_0  = $volFaltante_0  * $D_EST;

        $bonus95_g = $totalMasaMes * 0.095;

        $esteratoTotal_gMes_00 = $esteratoBase_gMes_00 + $bonus95_g;
        $esteratoTotal_gMes_0  = $esteratoBase_gMes_0  + $bonus95_g;

        // Selección final respetando override
        $autoElegida  = ($esteratoTotal_gMes_00 <= $esteratoTotal_gMes_0) ? '00' : '0';
        $finalElegida = ($capsulaReq === 'auto') ? $autoElegida : $capsulaReq;

        if ($finalElegida === '00') {
            $capsulaElegida      = '00';
            $capsDiaElegida      = $capsDia_00;
            $totalCapsElegida    = $capsDia_00 * 30;
            $esteratoFinal_gMes  = $esteratoTotal_gMes_00;
            $capacidadTotalFinal = $capVolMes_00;
            $capsCod             = 1078;
        } else {
            $capsulaElegida      = '0';
            $capsDiaElegida      = $capsDia_0;
            $totalCapsElegida    = $capsDia_0 * 30;
            $esteratoFinal_gMes  = $esteratoTotal_gMes_0;
            $capacidadTotalFinal = $capVolMes_0;
            $capsCod             = 1077;
        }

        // Añadir fila ESTEARATO
        $rows = collect($rows);
        $rows->push([
            'cod_odoo'          => 1101,
            'activo'            => $catalogo->get(1101)->nombre ?? 'ESTEARATO DE MAGNESIO',
            'cantidad'          => ((float)($esteratoFinal_gMes * 1000.0) / 30.0), // mg/día aprox (para mostrar)
            'unidad'            => 'mg',
            'mg'                => $esteratoFinal_gMes * 1000.0, // mg/mes (si lo usas en otra tabla)
            'valor_costo'       => (float)($catalogo->get(1101)->valor_costo ?? 0),
            'factor_venta'      => (float)($catalogo->get(1101)->factor_venta ?? 1),
            'cantidad_total'    => $esteratoFinal_gMes * 1000.0,
            'subtotal'          => 0,
            'densidad'          => $D_EST,
            'cant_total_pesaje' => $esteratoFinal_gMes / 30.0,                 // g/día aprox
            'vol_ml'            => ($esteratoFinal_gMes / $D_EST) / 30.0,       // ml/día aprox
            'masa_mes'          => $esteratoFinal_gMes,                         // g/mes
        ]);

        // Añadir fila CÁPSULAS
        $rows->push([
            'cod_odoo'          => $capsCod,
            'activo'            => $catalogo->get($capsCod)->nombre ?? ('CÁPSULA '.$capsulaElegida),
            'cantidad'          => $totalCapsElegida,
            'unidad'            => 'und',
            'mg'                => null,
            'valor_costo'       => (float)($catalogo->get($capsCod)->valor_costo ?? 0),
            'factor_venta'      => (float)($catalogo->get($capsCod)->factor_venta ?? 1),
            'cantidad_total'    => null,
            'subtotal'          => 0,
            'densidad'          => null,
            'cant_total_pesaje' => $totalCapsElegida,
            'vol_ml'            => null,
            'masa_mes'          => null,
        ]);

        // Pastillero
        if ($capsulaElegida === '00') { $capSmall = 30; $capLarge = 90; }
        else                          { $capSmall = 60; $capLarge = 150; }

        $needed = (int)$totalCapsElegida;
        if    ($needed <= $capSmall) { $pastCod = 1219; $pastCount = 1; }
        elseif($needed <= $capLarge) { $pastCod = 1219; $pastCount = 1; }
        else                         { $pastCod = 1219; $pastCount = (int)ceil($needed / $capLarge); }

        $rows->push([
            'cod_odoo'          => $pastCod,
            'activo'            => $catalogo->get($pastCod)->nombre ?? 'PASTILLERO',
            'cantidad'          => $pastCount,
            'unidad'            => 'und',
            'mg'                => null,
            'valor_costo'       => 0,
            'factor_venta'      => 1,
            'cantidad_total'    => null,
            'subtotal'          => 0,
            'densidad'          => null,
            'cant_total_pesaje' => $pastCount,
            'vol_ml'            => null,
            'masa_mes'          => null,
        ]);

        // Totales finales (incluye aprox de estearato)
        $totalVolMl   = (float)$rows->sum('vol_ml');
        $totalMasaMes = (float)$rows->sum('masa_mes');

        // Precios (tu criterio actual)
        //$precio_med = max(10, $totalGeneral * 30);
        $precio_med = max(12, ($totalGeneral * 30) + 4); // piso 12
        $precio_med = $this->roundUpToStep($precio_med, 0.10); // <-- AQUI

        $precio_dis = $precio_med * 0.65;
        $precio_pvp = round($precio_med * (4 / 3), 2);


        $codFormula = $this->buildCodFormula();

        return view("formulas.resumen_{$tipo}", [
            'rows'               => $rows,
            'totalMg'            => $totalMgDia,
            'totalVolMl'         => $totalVolMl,
            'totalMasaMes'       => $totalMasaMes,

            // auditoría
            'capsDia95'          => $capsDia_00,
            'capsDia68'          => $capsDia_0,
            'totalCaps95'        => $capsDia_00 * 30,
            'totalCaps68'        => $capsDia_0  * 30,
            'capacidadTotal95'   => $capVolMes_00,
            'capacidadTotal68'   => $capVolMes_0,
            'esterato95'         => $esteratoTotal_gMes_00 * 1000.0,
            'esterato68'         => $esteratoTotal_gMes_0  * 1000.0,

            'capsulaElegida'     => $capsulaElegida,
            'capsDiaElegida'     => $capsDiaElegida,
            'totalCapsElegida'   => $totalCapsElegida,
            'esteratoFinal'      => $esteratoFinal_gMes * 1000.0,
            'capacidadTotalFinal'=> $capacidadTotalFinal,
            'volMes'             => $volNecesarioMes,

            'totalGeneral'       => $totalGeneral,
            'precio_med'         => round($precio_med, 2),
            'precio_dis'         => round($precio_dis, 2),
            'precio_pvp'         => round($precio_pvp, 2),
            'codFormula'         => $codFormula,

            // NUEVO: para marcar el selector en el Blade
            'capsulaReq'         => $capsulaReq,
        ]);

    }

    // =================== Guardar cabecera de fórmula ===================

    public function guardar(Request $request)
    {
        $request->validate([
            'nombre_etiqueta' => ['required','string','max:150'],
            'medico'          => ['required','regex:/^[A-Z\s]+$/'],
        ], [
            'regex' => 'Solo se permiten letras mayúsculas sin acentos ni símbolos.',
        ]);

        $request->validate([
            'cod_formula'           => ['required','string','max:30'],
            'nombre_etiqueta'       => ['nullable','string','max:150'],
            'medico'                => ['nullable','string','max:120'],
            'precio_medico'         => ['nullable','numeric'],
            'precio_publico'        => ['nullable','numeric'],
            'precio_distribuidor'   => ['nullable','numeric'],
            'tomas_diarias'         => ['nullable','numeric'],
            'capsula'               => ['nullable','in:auto,00,0'],
        ]);

        $userId = Auth::id();
        if (!$userId) abort(401);

        $codigoBackend = $this->buildCodFormula();

        $capsulaReq = $request->input('capsula', 'auto');
        if (!in_array($capsulaReq, ['auto','00','0'], true)) {
            $capsulaReq = 'auto';
        }

        $formulaId = DB::transaction(function () use ($request, $userId, $codigoBackend, $capsulaReq) {

            // 2) Calcular filas (incluye subtotal en activos)
            $rows = $this->calcularFilasParaGuardar($userId, $capsulaReq);

            // 2.1) Total diario real (solo activos suman; los otros van en 0)
            $totalGeneralDia = (float)$rows->sum('subtotal');

            // 2.2) Regla de precio: (total*30)+4 y piso 12
            $precio_medico = max(12, ($totalGeneralDia * 30) + 4);
            $precio_medico = $this->roundUpToStep($precio_medico, 0.10); // <-- AQUI



            // Mantengo tu esquema: público/distribuidor vienen del request
            $precio_publico      = (float)$request->input('precio_publico', 0);
            $precio_distribuidor = (float)$request->input('precio_distribuidor', 0);

            // 1) Guardar cabecera (precio_medico YA blindado)
            $formula = Formula::create([
                'codigo'              => $codigoBackend,
                'nombre_etiqueta'     => $request->input('nombre_etiqueta'),
                'user_id'             => $userId,
                'precio_medico'       => round($precio_medico, 2),
                'precio_publico'      => round($precio_publico, 2),
                'precio_distribuidor' => round($precio_distribuidor, 2),
                'medico'              => $request->input('medico'),
                'paciente'            => $request->input('paciente'),
                'tomas_diarias'       => (float)$request->input('tomas_diarias', 0),
            ]);

            // 3) Insertar items
            $now = now();
            $insert = $rows->map(function ($r) use ($codigoBackend, $now) {
                return [
                    'codigo'     => $codigoBackend,
                    'cod_odoo'   => (int)($r['cod_odoo'] ?? 0),
                    'activo'     => (string)($r['activo'] ?? ''),
                    'unidad'     => $r['unidad'] ?? null,
                    'masa_mes'   => isset($r['masa_mes']) ? (float)$r['masa_mes'] : null,
                    'cantidad'   => isset($r['cantidad']) ? (float)$r['cantidad'] : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            })->all();

            if (!empty($insert)) {
                FormulaItem::insert($insert);
            }

            // 4) Limpiar temporales
            ActivoTemp::where('user_id', $userId)->delete();

            return (int) $formula->id;
        });

        // 5) Añadir automáticamente a "Fórmulas Establecidas" (sesión)
        $sessionKey = \App\Http\Controllers\FormulasEstController::SESSION_KEY;

        $items = $request->session()->get($sessionKey, []);
        if (!collect($items)->firstWhere('id', $formulaId)) {
            $items[] = ['id' => $formulaId, 'tipo' => null];
            $request->session()->put($sessionKey, $items);
        }

        return redirect()
            ->route('fe.index')
            ->with('ok', 'Fórmula guardada y añadida a Fórmulas Establecidas.');
    }



    /**
     * Recomendación A aplicada aquí:
     * - Devuelve filas con 'subtotal' para poder calcular el total real en backend
     * - subtotal = costo diario (sin factor), consistente con tu resumen
     */
    private function calcularFilasParaGuardar(int $userId, string $capsulaReq = 'auto'): \Illuminate\Support\Collection
    {
        if (!in_array($capsulaReq, ['auto','00','0'], true)) {
            $capsulaReq = 'auto';
        }

        $items = ActivoTemp::where('user_id', $userId)->orderBy('id')->get();

        $codes = $items->pluck('cod_odoo')->map(fn($c)=>(int)$c)->all();
        $codes = array_unique(array_merge($codes, [1101,1077,1078,1219,1220]));

        $catalogo = Activo::whereIn('cod_odoo', $codes)
            ->get(['cod_odoo','nombre','valor_costo','factor','factor_venta','densidad'])
            ->keyBy('cod_odoo');

        $CAP_VOL_00 = 0.95;
        $CAP_VOL_0  = 0.68;
        $D_EST      = 0.3228;

        $rows = collect();

        $volDiaTotal_ml        = 0.0; // activos
        $masaMesActivosTotal_g = 0.0; // activos

        foreach ($items as $r) {
            $mgDia = match ($r->unidad) {
                'g'   => (float)$r->cantidad * 1000,
                'mg'  => (float)$r->cantidad,
                'mcg' => (float)$r->cantidad / 1000,
                'UI'  => ((int)$r->cod_odoo === 2730)
                    ? ((float)$r->cantidad * 0.000025)
                    : ((float)$r->cantidad * 0.67),
                default => 0.0,
            };

            $a            = $catalogo->get((int)$r->cod_odoo);
            $factor       = (float)($a->factor       ?? 1.0);
            $densidad     = (float)($a->densidad     ?? 0.0);
            $valor_costo  = (float)($a->valor_costo  ?? 0.0); // $/mg
            $factor_venta = (float)($a->factor_venta ?? 1.0);

            // CON factor para pesaje/volumen
            $mgDiaTotal = $mgDia * $factor;
            $gDiaTotal  = $mgDiaTotal / 1000.0;
            $gMesTotal = $this->minMasaMes($gDiaTotal * 30.0);
            $volMlDia   = ($densidad > 0) ? ($gDiaTotal / $densidad) : 0.0;

            $volDiaTotal_ml        += $volMlDia;
            $masaMesActivosTotal_g += $gMesTotal;

            // >>> subtotal diario SIN factor (igual que resumen cápsulas) <<<
            $subtotal = round($mgDia * $valor_costo * $factor_venta, 6);

            $rows->push([
                'cod_odoo'          => (int)$r->cod_odoo,
                'activo'            => (string)$r->activo,
                'unidad'            => (string)$r->unidad,
                'cantidad'          => (float)$r->cantidad, // por día (entrada)
                'masa_mes'          => $gMesTotal,          // g/mes (CON factor)
                'cant_total_pesaje' => $gDiaTotal,          // g/día (CON factor)
                'vol_ml'            => $volMlDia,           // ml/día
                'subtotal'          => $subtotal,           // $/día (SIN factor)
            ]);
        }

        // Cálculo por volumen de cápsulas y estearato (+9.5%)
        $capsDia_00 = (int)ceil($volDiaTotal_ml / $CAP_VOL_00);
        $capsDia_0  = (int)ceil($volDiaTotal_ml / $CAP_VOL_0);

        $capsMes_00 = $capsDia_00 * 30;
        $capsMes_0  = $capsDia_0  * 30;

        $capVolMes_00     = $capsDia_00 * $CAP_VOL_00 * 30.0;
        $capVolMes_0      = $capsDia_0  * $CAP_VOL_0  * 30.0;
        $volNecesarioMes  = $volDiaTotal_ml * 30.0;

        $volFalt_00 = max(0.0, $capVolMes_00 - $volNecesarioMes);
        $volFalt_0  = max(0.0, $capVolMes_0  - $volNecesarioMes);

        $esteratoBase_gMes_00 = $volFalt_00 * $D_EST;
        $esteratoBase_gMes_0  = $volFalt_0  * $D_EST;

        $bonus95_g = $masaMesActivosTotal_g * 0.095;

        $esterato_gMes_00 = $esteratoBase_gMes_00 + $bonus95_g;
        $esterato_gMes_0  = $esteratoBase_gMes_0  + $bonus95_g;

        $autoElegida  = ($esterato_gMes_00 <= $esterato_gMes_0) ? '00' : '0';
        $finalElegida = ($capsulaReq === 'auto') ? $autoElegida : $capsulaReq;

        if ($finalElegida === '00') {
            $capsulaElegida   = '00';
            $totalCapsElegida = $capsMes_00;
            $esteratoFinal_g  = $esterato_gMes_00;
            $capsCod          = 1078;
        } else {
            $capsulaElegida   = '0';
            $totalCapsElegida = $capsMes_0;
            $esteratoFinal_g  = $esterato_gMes_0;
            $capsCod          = 1077;
        }

        // Estearato (subtotal = 0)
        $rows->push([
            'cod_odoo'          => 1101,
            'activo'            => $catalogo->get(1101)->nombre ?? 'ESTEARATO DE MAGNESIO',
            'unidad'            => 'mg',
            'cantidad'          => (float)($esteratoFinal_g * 1000.0), // mg/mes
            'masa_mes'          => (float)$esteratoFinal_g,            // g/mes
            'cant_total_pesaje' => (float)($esteratoFinal_g / 30.0),
            'vol_ml'            => (float)($esteratoFinal_g / $D_EST / 30.0),
            'subtotal'          => 0.0,
        ]);

        // Cápsulas (subtotal = 0)
        $rows->push([
            'cod_odoo'          => $capsCod,
            'activo'            => $catalogo->get($capsCod)->nombre ?? ('CAPSULA '.$capsulaElegida),
            'unidad'            => 'und',
            'cantidad'          => (float)$totalCapsElegida,
            'masa_mes'          => $totalCapsElegida,
            'cant_total_pesaje' => $totalCapsElegida,
            'vol_ml'            => null,
            'subtotal'          => 0.0,
        ]);

        // Pastillero (subtotal = 0)
        if ($capsulaElegida === '00') { $capSmall = 30; $capLarge = 90; }
        else                          { $capSmall = 60; $capLarge = 150; }

        $need = (int)$totalCapsElegida;
        if    ($need <= $capSmall) { $pastCod = 1219; $pastCount = 1; }
        elseif($need <= $capLarge) { $pastCod = 1219; $pastCount = 1; }
        else                       { $pastCod = 1219; $pastCount = (int)ceil($need / $capLarge); }

        $rows->push([
            'cod_odoo'          => $pastCod,
            'activo'            => $catalogo->get($pastCod)->nombre ?? 'PASTILLERO',
            'unidad'            => 'und',
            'cantidad'          => (float)$pastCount,
            'masa_mes'          => $pastCount,
            'cant_total_pesaje' => $pastCount,
            'vol_ml'            => null,
            'subtotal'          => 0.0,
        ]);

        return $rows;
    }


    // =================== Guardar SOBRES (blindado backend con la misma regla) ===================

    public function guardarSobres(Request $request)
    {
        $request->validate([
            'nombre_etiqueta' => ['required','string','max:150'],
            'medico'          => ['required','regex:/^[A-Z\s]+$/'],
        ], ['regex' => 'Solo se permiten letras mayúsculas sin acentos ni símbolos.']);

        $request->validate([
            'cod_formula'           => ['required','string','max:30'],
            'nombre_etiqueta'       => ['nullable','string','max:150'],
            'medico'                => ['nullable','string','max:120'],
            'precio_medico'         => ['nullable','numeric'],
            'precio_publico'        => ['nullable','numeric'],
            'precio_distribuidor'   => ['nullable','numeric'],
            'tomas_diarias'         => ['nullable','numeric'],
        ]);

        $userId = Auth::id();
        if (!$userId) abort(401);

        $codigoBackend = $request->input('cod_formula');

        $formulaId = DB::transaction(function () use ($request, $userId, $codigoBackend) {

            // Recalcular precio en backend (recomendación A aplicada a sobres)
            $temp = ActivoTemp::where('user_id', $userId)->orderBy('id')->get();

            // códigos a consultar (temp + fijos)
            $codes = $temp->pluck('cod_odoo')->map(fn($c)=>(int)$c)->all();
            $codes = array_unique(array_merge($codes, [70256, 9585])); // CLIGHT + SUCRALOSA

            $catalogo = Activo::whereIn('cod_odoo', $codes)
                ->get(['cod_odoo','valor_costo','factor_venta'])
                ->keyBy('cod_odoo');

            $calcSubtotal = function (int $cod, float $mg_dia) use ($catalogo): float {
                $a = $catalogo->get($cod);
                $valor_costo  = (float)($a->valor_costo  ?? 0.0);
                $factor_venta = (float)($a->factor_venta ?? 1.0);
                return round($mg_dia * $valor_costo * $factor_venta, 6); // $/día
            };

            $totalGeneralDia = 0.0;

            // Fijos mg (igual que resumen sobres)
            $totalGeneralDia += $calcSubtotal(70256, 1500/30);
            $totalGeneralDia += $calcSubtotal(9585,  100/30);

            // Variables por sobre (por día)
            foreach ($temp as $t) {
                $mg_dia = match ($t->unidad) {
                    'g'   => (float)$t->cantidad * 1000,
                    'mg'  => (float)$t->cantidad,
                    'mcg' => (float)$t->cantidad / 1000,
                    'UI'  => ((int)$t->cod_odoo === 2730)
                        ? ((float)$t->cantidad * 0.000025)
                        : ((float)$t->cantidad * 0.67),
                    default => 0.0,
                };

                $totalGeneralDia += $calcSubtotal((int)$t->cod_odoo, (float)$mg_dia);
            }

            // Regla: (total*30)+4 con piso 12
            $precio_medico = max(12, ($totalGeneralDia * 30) + 4);
            $precio_medico = $this->roundUpToStep($precio_medico, 0.10); // <-- AQUI



            // Mantengo tu esquema: público/distribuidor del request
            $precio_publico      = (float)$request->input('precio_publico', 0);
            $precio_distribuidor = (float)$request->input('precio_distribuidor', 0);

            // 1) Cabecera
            $formula = Formula::create([
                'codigo'              => $codigoBackend,
                'nombre_etiqueta'     => $request->input('nombre_etiqueta'),
                'user_id'             => $userId,
                'precio_medico'       => round($precio_medico, 2),
                'precio_publico'      => round($precio_publico, 2),
                'precio_distribuidor' => round($precio_distribuidor, 2),
                'medico'              => $request->input('medico'),
                'paciente'            => $request->input('paciente'),
                'tomas_diarias'       => (float)$request->input('tomas_diarias', 1) ?: 1,
            ]);

            // 2) Ítems (tu misma lógica)
            $rows = collect([
                ['cod_odoo' => 70277, 'activo' => 'CAJA',       'cantidad' => 1,    'unidad' => 'und', 'masa_mes' => 1],
                ['cod_odoo' => 70299, 'activo' => 'SOBRES',     'cantidad' => 30,   'unidad' => 'und', 'masa_mes' => 30],
                ['cod_odoo' => 70256, 'activo' => 'CLIGHT',     'cantidad' => 1500, 'unidad' => 'mg',  'masa_mes' => 1500/1000],
                ['cod_odoo' =>  9585, 'activo' => 'SUCARALOSA', 'cantidad' => 100,  'unidad' => 'mg',  'masa_mes' => 100/1000],
            ]);

            foreach ($temp as $t) {
                $mg_dia = 0.0;
                switch ($t->unidad) {
                    case 'g':   $mg_dia = (float)$t->cantidad * 1000; break;
                    case 'mg':  $mg_dia = (float)$t->cantidad; break;
                    case 'mcg': $mg_dia = (float)$t->cantidad / 1000; break;
                    case 'UI':
                        $mg_dia = ((int)$t->cod_odoo === 2730)
                            ? ((float)$t->cantidad * 0.000025)
                            : ((float)$t->cantidad * 0.67);
                        break;
                }

                $masa_mes = $this->minMasaMes(($mg_dia * 30) / 1000.0);

                $rows->push([
                    'cod_odoo' => (int)$t->cod_odoo,
                    'activo'   => (string)$t->activo,
                    'cantidad' => (float)$t->cantidad,
                    'unidad'   => $t->unidad,
                    'masa_mes' => round($masa_mes, 6),
                ]);
            }

            // 3) Insert items
            $now = now();
            $insert = $rows->map(fn($r) => [
                'codigo'     => $codigoBackend,
                'cod_odoo'   => (int)$r['cod_odoo'],
                'activo'     => (string)$r['activo'],
                'unidad'     => $r['unidad'],
                'masa_mes'   => $r['masa_mes'],
                'cantidad'   => $r['cantidad'],
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

            if (!empty($insert)) {
                FormulaItem::insert($insert);
            }

            // 4) Limpiar temporales
            ActivoTemp::where('user_id', $userId)->delete();

            return (int) $formula->id;
        });

        // 5) Auto-add a Fórmulas Establecidas (sesión)
        $sessionKey = \App\Http\Controllers\FormulasEstController::SESSION_KEY;

        $items = $request->session()->get($sessionKey, []);
        if (!collect($items)->firstWhere('id', $formulaId)) {
            $items[] = ['id' => $formulaId, 'tipo' => null];
            $request->session()->put($sessionKey, $items);
        }

        return redirect()
            ->route('fe.index')
            ->with('ok', 'Fórmula en sobres guardada y añadida a Fórmulas Establecidas.');
    }

    public function recientes(Request $request)
    {
        $userId = auth()->id();

        $query = Formula::query()
            ->where('user_id', $userId);

        // Filtro por nombre de fórmula o código
        if ($request->filled('nombre')) {
            $term = trim($request->input('nombre'));
            $query->where(function ($q) use ($term) {
                $q->where('nombre_etiqueta', 'like', "%{$term}%")
                ->orWhere('codigo', 'like', "%{$term}%");
            });
        }

        // Filtro por médico
        if ($request->filled('medico')) {
            $medico = trim($request->input('medico'));
            $query->where('medico', 'like', "%{$medico}%");
        }

        // Filtro por fecha desde
        if ($request->filled('desde')) {
            $query->whereDate('created_at', '>=', $request->input('desde'));
        }

        // Filtro por fecha hasta
        if ($request->filled('hasta')) {
            $query->whereDate('created_at', '<=', $request->input('hasta'));
        }

        $formulas = $query
            ->orderByDesc('id')
            ->simplePaginate(15)
            ->appends($request->query()); // para mantener los filtros en la paginación

        return view('formulas.recientes', compact('formulas'));
    }


    public function cargarParaEditar(int $id)
    {
        $userId  = Auth::id();
    
        // Quitamos el filtro por user_id
        $formula = Formula::findOrFail($id);
    
        $codsExcluir  = [1077, 1078, 1219, 1220, 70277, 70299, 70256, 9585];
        $regexExcluir = '/(capsula|cápsula|capsulas|cápsulas|pastillero|pastilleros|estearato|esterato)/i';
    
        DB::transaction(function () use ($userId, $formula, $codsExcluir, $regexExcluir) {
            ActivoTemp::where('user_id', $userId)->delete();
    
            $items = FormulaItem::query()
                ->where('codigo', $formula->codigo)
                ->whereNotIn('cod_odoo', $codsExcluir)
                ->get(['cod_odoo','activo','unidad','cantidad','masa_mes']);
    
            foreach ($items as $it) {
                $cod    = (int)($it->cod_odoo ?? 0);
                $nombre = (string)($it->activo   ?? '');
    
                if (preg_match($regexExcluir, $nombre)) continue;
    
                $unidad   = $it->unidad ?: 'mg';
                $cantidad = null;
    
                if (!is_null($it->cantidad)) {
                    $cantidad = (float)$it->cantidad;
                } elseif (!is_null($it->masa_mes)) {
                    $cantidad = (float)$it->masa_mes / 30.0;
                } else {
                    continue;
                }
    
                ActivoTemp::create([
                    'user_id'  => $userId,
                    'cod_odoo' => $cod,
                    'activo'   => $nombre,
                    'cantidad' => $cantidad,
                    'unidad'   => $unidad,
                ]);
            }
        });
    
        return redirect()
            ->route('formulas.nuevas')
            ->with('ok', 'Fórmula cargada para edición. Ajusta los activos y guarda.');
    }


    public function buscarMedico(Request $request)
    {
        $term = $request->get('q', '');

        $medicos = Medico::where('full_name', 'like', "%{$term}%")
            ->orderBy('full_name')
            ->limit(10)
            ->pluck('full_name');

        return response()->json($medicos);
    }


}
