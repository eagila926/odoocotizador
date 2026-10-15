<?php

namespace App\Http\Controllers;

use App\Models\Formula;
use App\Models\PedidoFormula;
use App\Models\PedidoFormulaItem;
use App\Models\CarritoFormulaItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class PedidoFormulaController extends Controller
{
    /**
     * Página principal: pedidos de fórmulas
     */
    public function formulas(Request $request)
    {
        $user = Auth::user();
        $userId = $user->id_user ?? $user->id;

        // Ítems temporales (carrito de fórmulas)
        $pedido = CarritoFormulaItem::where('user_id', $userId)
                    ->orderBy('id')
                    ->get();

        $total = $pedido->sum('subtotal');

        // Vista similar a pedidos.productos pero para fórmulas
        return view('pedidos.formulas', compact('pedido', 'total'));
    }

    /**
     * Autocompletado de fórmulas
     * Busca en tabla formulas por nombre_etiqueta o código
     */
    public function buscarFormulas(Request $request)
    {
        $q = trim($request->get('q', ''));
        if ($q === '') {
            return response()->json([]);
        }

        $items = Formula::query()
            ->where(function ($s) use ($q) {
                $s->where('nombre_etiqueta', 'like', "%{$q}%")
                ->orWhere('codigo', 'like', "%{$q}%")
                ->orWhere('medico', 'like', "%{$q}%")
                ->orWhere('paciente', 'like', "%{$q}%");
            })
            ->orderBy('nombre_etiqueta')
            ->limit(10)
            ->get([
                'codigo',
                'nombre_etiqueta',
                // no necesitamos mandar medico/paciente al front
                'precio_medico',
                'precio_publico',
                'precio_distribuidor',
            ]);

        return response()->json($items);
    }


    /**
     * Agregar fórmula al carrito temporal de fórmulas
     */
    public function agregarFormula(Request $request)
    {
        $data = $request->validate([
            'formula_codigo' => 'required|string|exists:formulas,codigo',
            'cantidad'       => 'required|numeric|min:1',
            'promocion'      => 'required|in:SI,NO',
            'observacion'    => 'nullable|string|max:500',
        ]);

        $f = Formula::where('codigo', $data['formula_codigo'])->firstOrFail();

        // Si es promoción, precio 0; si no, el precio distribuidor
        $precioUnit = ($data['promocion'] === 'SI') ? 0 : $f->precio_distribuidor ?? 0;

        $subtotal = $precioUnit * (float) $data['cantidad'];

        $user = Auth::user();
        $userId = $user->id_user ?? $user->id;

        CarritoFormulaItem::create([
            'user_id'        => $userId,
            'cod_formula'    => $f->codigo,
            'nombre_formula' => $f->nombre_etiqueta,
            'categoria'      => 'ORTOMOLECULAR',             // SIEMPRE ORTOMOLECULAR
            'cantidad'       => (float)$data['cantidad'],
            'promocion'      => $data['promocion'],
            'observacion'    => $data['observacion'] ?? '',
            'precio'         => $precioUnit,                 // DESCUENTO 35% del PVM
            'subtotal'       => $subtotal,
        ]);

        return back();
    }



    /**
     * Eliminar ítem del carrito de fórmulas
     */
    public function eliminarFormula($id)
    {
        $user = Auth::user();
        $userId = $user->id_user ?? $user->id;

        CarritoFormulaItem::where('id', $id)
            ->where('user_id', $userId)
            ->delete();

        return back();
    }

    /**
     * Finalizar pedido de fórmulas:
     * - Crea registro en pedidos_formulas
     * - Crea registros en pedidos_formulas_items
     * - Limpia carrito_formulas_items del usuario
     */
    public function finalizarFormulas()
    {
        $user = Auth::user();
        $userId = $user->id_user ?? $user->id;

        $lista = CarritoFormulaItem::where('user_id', $userId)->get();

        if ($lista->isEmpty()) {
            return back()->with('error', 'No hay fórmulas en el pedido.');
        }

        $total = $lista->sum('subtotal');

        // Código similar a productos, pero con prefijo diferente (PF = Pedido Fórmulas)
        $iniciales = strtoupper(substr($user->nombre, 0, 1) . substr($user->apellido, 0, 1));

        $ultimo = PedidoFormula::where('user_id', $userId)
                ->orderByDesc('id')->value('codigo');

        $consecutivo = 1;
        if ($ultimo && preg_match('/(\d+)$/', $ultimo, $m)) {
            $consecutivo = intval($m[1]) + 1;
        }
        $codigo = sprintf('PF-%s-%03d', $iniciales, $consecutivo);

        $pedidoId = null;

        DB::transaction(function () use ($lista, $total, $codigo, $userId, &$pedidoId) {
            $pedido = PedidoFormula::create([
                'codigo'  => $codigo,
                'fecha'   => now(),
                'total'   => $total,
                'user_id' => $userId,
            ]);

            foreach ($lista as $it) {
                PedidoFormulaItem::create([
                    'pedido_formula_id' => $pedido->id,
                    'codigo'            => $codigo,
                    'cod_formula'       => $it->cod_formula,
                    'nombre_formula'    => $it->nombre_formula,
                    'categoria'         => $it->categoria,
                    'cantidad'          => $it->cantidad,
                    'detalle'           => $it->observacion ?? '',
                    'precio_unidad'     => $it->precio,
                    'subtotal'          => $it->subtotal,
                    'promocion'         => $it->promocion,
                ]);
            }

            // Limpiar carrito de fórmulas
            CarritoFormulaItem::where('user_id', $userId)->delete();

            $pedidoId = $pedido->id;
        });

        return redirect()
            ->route('pedidos.formulas')
            ->with('success', "Pedido de fórmulas {$codigo} guardado correctamente.")
            ->with('download_url', route('pedidos.formulas.pdf', $pedidoId));
    }

    /**
     * PDF del pedido de fórmulas
     */
    public function pdf(PedidoFormula $pedido)
    {
        $pedido->load(['items', 'user']);

        $pdf = Pdf::loadView('pedidos.formulas-pdf', compact('pedido'))
                ->setPaper('A4', 'portrait');

        return $pdf->download('Pedido-Formulas-'.$pedido->codigo.'.pdf');
    }

    /**
     * Listar mis pedidos de fórmulas
     */
    public function misPedidosFormulas()
    {
        $user = Auth::user();
        $userId = $user->id_user ?? $user->id;

        $pedidos = PedidoFormula::query()
            ->where('user_id', $userId)
            ->with('items')
            ->orderByDesc('fecha')
            ->paginate(10);

        return view('pedidos.mis-pedidos-formulas', compact('pedidos'));
    }
}
