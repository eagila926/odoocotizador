<?php

namespace App\Http\Controllers;

use App\Models\ActivoHomeo;
use App\Models\ActivoHomeoTemp;
use App\Models\FormulaHomeo;
use App\Models\FormulaHomeoItem;
use App\Models\Medico;
use App\Models\Receta;
use App\Models\RecetaHomeopatico;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class FormulaHomeoController extends Controller
{
    private const SESSION_SELECCION = 'formulas_homeo_seleccionadas';

    private const PRESENTACIONES = [
        'Gotero 22 ml',
        'Gotero 50 ml',
        'Spray 22 ml',
        'Spray 50 ml',
        'Glóbulos',
    ];

    public function index()
    {
        $categorias = ActivoHomeo::query()
            ->whereNotNull('categoria')
            ->where('categoria', '<>', '')
            ->distinct()
            ->orderBy('categoria')
            ->pluck('categoria');

        $formulaBase = null;
        $formulaBaseId = session('formula_homeo_base');
        if ($formulaBaseId) {
            $formulaBase = FormulaHomeo::find($formulaBaseId);
            if (!$formulaBase) {
                session()->forget('formula_homeo_base');
            }
        }

        return view('formulas_homeo.nueva', [
            'categorias' => $categorias,
            'presentaciones' => self::PRESENTACIONES,
            'formulaEditando' => $formulaBase,
            'esCopia' => (bool) $formulaBase,
        ]);
    }

    public function buscar(Request $request)
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:1', 'max:150'],
        ]);

        return response()->json(
            ActivoHomeo::query()
                ->where('nombre', 'like', '%'.trim($data['q']).'%')
                ->orderBy('nombre')
                ->limit(15)
                ->get(['id', 'nombre', 'categoria'])
        );
    }

    public function listar()
    {
        return response()->json(
            ActivoHomeoTemp::query()
                ->where('user_id', Auth::id())
                ->with('activoHomeo:id,categoria')
                ->orderBy('id')
                ->get()
        );
    }

    public function agregar(Request $request)
    {
        $data = $request->validate([
            'cod_activo' => ['required', 'integer', 'exists:activos_homeo,id'],
            'dilusion' => ['nullable', 'string', 'max:50'],
        ]);

        $userId = (int) Auth::id();
        $activo = ActivoHomeo::findOrFail($data['cod_activo']);

        $item = ActivoHomeoTemp::updateOrCreate(
            ['user_id' => $userId, 'cod_activo' => $activo->id],
            [
                'codigo' => 'TEMP-HOME-'.$userId,
                'activo' => $activo->nombre,
                'dilusion' => trim((string) ($data['dilusion'] ?? '')),
            ]
        );

        return response()->json([
            'message' => $item->wasRecentlyCreated
                ? 'Activo agregado.'
                : 'Activo actualizado.',
        ]);
    }

    public function eliminar(ActivoHomeoTemp $item)
    {
        abort_unless((int) $item->user_id === (int) Auth::id(), 404);
        $item->delete();

        return response()->json(['message' => 'Activo eliminado.']);
    }

    public function limpiar()
    {
        ActivoHomeoTemp::where('user_id', Auth::id())->delete();

        return response()->json(['message' => 'Selección eliminada.']);
    }

    public function guardar(Request $request)
    {
        $data = $request->validate([
            'nombre_etiqueta' => ['required', 'string', 'max:150'],
            'categoria' => [
                'required',
                'string',
                'max:100',
                Rule::exists('activos_homeo', 'categoria'),
            ],
            'medico' => ['required', 'string', 'max:150'],
            'cedula_medico' => ['required', 'string', 'max:50', 'exists:medicos,cedula'],
            'presentacion' => ['required', Rule::in(self::PRESENTACIONES)],
        ]);

        $userId = (int) Auth::id();
        $medico = Medico::where('cedula', $data['cedula_medico'])->first();
        if (!$medico || !$medico->firma) {
            return back()
                ->withErrors([
                    'cedula_medico' => 'Debe seleccionar un médico que tenga firma registrada.',
                ])
                ->withInput();
        }
        $data['medico'] = $medico->full_name;
        $temporales = ActivoHomeoTemp::where('user_id', $userId)
            ->orderBy('id')
            ->get();

        if ($temporales->isEmpty()) {
            return back()
                ->withErrors(['activos' => 'Agrega al menos un activo a la fórmula.'])
                ->withInput();
        }

        $formula = DB::transaction(function () use (
            $data,
            $temporales,
            $userId
        ) {
            $codigo = $this->generarCodigo();
            $formula = FormulaHomeo::create([
                ...$data,
                'codigo' => $codigo,
                'user_id' => $userId,
                'precio' => 0,
            ]);

            FormulaHomeoItem::insert(
                $temporales->map(fn ($item) => [
                    'codigo' => $formula->codigo,
                    'cod_activo' => $item->cod_activo,
                    'activo' => $item->activo,
                    'dilusion' => $item->dilusion ?? '',
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->all()
            );

            ActivoHomeoTemp::where('user_id', $userId)->delete();

            return $formula;
        });

        session()->forget(['formula_homeo_editando', 'formula_homeo_base']);

        // La fórmula recién cotizada se agrega al inicio de Fórmulas establecidas.
        $ids = collect(session(self::SESSION_SELECCION, []))
            ->reject(fn ($id) => (int) $id === (int) $formula->id)
            ->prepend((int) $formula->id)
            ->values()
            ->all();
        session([self::SESSION_SELECCION => $ids]);

        return redirect()
            ->route('formulas-homeo.establecidas')
            ->with('ok', "Fórmula {$formula->codigo} guardada y añadida a Fórmulas establecidas.")
            ->with('ultima_formula_homeo_id', $formula->id);
    }

    private function generarCodigo(): string
    {
        $ultimoCodigo = FormulaHomeo::query()
            ->where('codigo', 'like', 'F-HOMEO-%')
            ->orderByDesc('codigo')
            ->lockForUpdate()
            ->value('codigo');

        $numero = $ultimoCodigo
            ? ((int) Str::afterLast($ultimoCodigo, '-') + 1)
            : 1;

        return 'F-HOMEO-'.str_pad((string) $numero, 6, '0', STR_PAD_LEFT);
    }

    public function establecidas(Request $request)
    {
        $ids = collect(session(self::SESSION_SELECCION, []))->map(fn ($id) => (int) $id);
        $formulas = $ids->isEmpty()
            ? collect()
            : FormulaHomeo::with('items')
                ->whereIn('id', $ids)
                ->get()
                ->sortBy(fn ($formula) => $ids->search($formula->id))
                ->values();

        return view('formulas_homeo.establecidas', [
            'formulas' => $formulas,
            'presentaciones' => self::PRESENTACIONES,
        ]);
    }

    public function buscarEstablecidas(Request $request)
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:1', 'max:150'],
        ]);
        $q = trim($data['q']);

        return response()->json(
            FormulaHomeo::query()
                ->where(function ($query) use ($q) {
                    $query->where('codigo', 'like', "%{$q}%")
                        ->orWhere('nombre_etiqueta', 'like', "%{$q}%")
                        ->orWhere('categoria', 'like', "%{$q}%");
                })
                ->orderByRaw("CASE WHEN codigo LIKE 'FP-HOMEO%' THEN 0 ELSE 1 END")
                ->orderByDesc('id')
                ->limit(15)
                ->get(['id', 'codigo', 'nombre_etiqueta', 'categoria', 'presentacion'])
        );
    }

    public function agregarEstablecida(Request $request)
    {
        $data = $request->validate([
            'formula_id' => ['required', 'integer', 'exists:formulas_homeo,id'],
        ]);
        $ids = collect(session(self::SESSION_SELECCION, []));
        if (!$ids->contains((int) $data['formula_id'])) {
            $ids->push((int) $data['formula_id']);
        }
        session([self::SESSION_SELECCION => $ids->values()->all()]);

        return redirect()->route('formulas-homeo.establecidas');
    }

    public function quitarEstablecida(FormulaHomeo $formula)
    {
        $ids = collect(session(self::SESSION_SELECCION, []))
            ->reject(fn ($id) => (int) $id === (int) $formula->id)
            ->values()
            ->all();
        session([self::SESSION_SELECCION => $ids]);

        return redirect()->route('formulas-homeo.establecidas');
    }

    public function limpiarEstablecidas()
    {
        session()->forget(self::SESSION_SELECCION);

        return redirect()->route('formulas-homeo.establecidas');
    }

    public function editar(FormulaHomeo $formula)
    {
        $userId = (int) Auth::id();

        DB::transaction(function () use ($formula, $userId) {
            ActivoHomeoTemp::where('user_id', $userId)->delete();

            foreach ($formula->items()->orderBy('id')->get() as $item) {
                ActivoHomeoTemp::create([
                    'codigo' => 'TEMP-HOME-'.$userId,
                    'user_id' => $userId,
                    'cod_activo' => $item->cod_activo,
                    'activo' => $item->activo,
                    'dilusion' => $item->dilusion ?? '',
                ]);
            }
        });

        session()->forget('formula_homeo_editando');
        session(['formula_homeo_base' => $formula->id]);

        return redirect()->route('formulas-homeo.nueva');
    }

    public function cancelarEdicion()
    {
        session()->forget(['formula_homeo_editando', 'formula_homeo_base']);
        ActivoHomeoTemp::where('user_id', Auth::id())->delete();

        return redirect()->route('formulas-homeo.nueva');
    }

    public function receta(Request $request, FormulaHomeo $formula)
    {
        $data = $request->validate([
            'so' => ['required', 'regex:/^\d+$/', 'max:50'],
            'cedula_medico' => ['required', 'string', 'max:50', 'exists:medicos,cedula'],
            'paciente' => ['nullable', 'string', 'max:255'],
            'cantidad' => ['required', 'integer', 'min:1', 'max:500'],
        ], [
            'so.regex' => 'El campo SO solo debe contener números.',
        ]);

        $medico = Medico::where('cedula', $data['cedula_medico'])->first();
        if (!$medico) {
            return back()->withErrors([
                'medico' => 'Debe seleccionar un médico válido.',
            ]);
        }
        if (!$medico->firma) {
            return back()->withErrors([
                'medico' => 'El médico seleccionado no tiene una firma registrada.',
            ]);
        }

        $composicion = $formula->items()
            ->orderBy('id')
            ->get()
            ->map(fn ($item) => trim(
                $item->activo.($item->dilusion !== '' ? ' '.$item->dilusion : '')
            ))
            ->implode("\n");

        $cantidad = (int) $data['cantidad'];
        $grupos = [];
        for ($restantes = $cantidad; $restantes > 0; $restantes -= 6) {
            $grupos[] = min(6, $restantes);
        }

        $packs = [];
        $recetaIds = [];
        DB::transaction(function () use (
            $data,
            $formula,
            $composicion,
            $cantidad,
            $grupos,
            $medico,
            &$packs,
            &$recetaIds
        ) {
            foreach ($grupos as $index => $numFrascos) {
                $receta = Receta::create([
                    'so' => $data['so'],
                    'codigo_formula' => $formula->codigo.'-'.($index + 1),
                    'fecha' => now()->toDateString(),
                    'cedula_medico' => $medico->cedula,
                    'paciente' => trim((string) ($data['paciente'] ?? '')),
                    'num_frascos' => $numFrascos,
                ]);

                $homeopatico = RecetaHomeopatico::create([
                    'id_receta' => $receta->id_receta,
                    'producto' => $formula->nombre_etiqueta.' - '.$formula->presentacion,
                    'composicion' => $composicion,
                    'cantidad_solicitada' => $cantidad,
                ]);

                $packs[] = compact('receta', 'homeopatico');
                $recetaIds[] = $receta->id_receta;
            }
        });

        $pdf = Pdf::loadView('recetas.homeopatico_pdf', [
            'packs' => $packs,
            'medico' => $medico,
            'firmaBase64' => $this->firmaBase64($medico),
        ])->setPaper('a4');

        return $pdf
            ->download('Recetas-'.$formula->codigo.'.pdf')
            ->withHeaders($this->publicRecipeLinksHeader($recetaIds));
    }

    public function recetasSeleccionadas(Request $request)
    {
        $data = $request->validate([
            'so' => ['required', 'regex:/^\d+$/', 'max:50'],
            'presentacion' => ['required', Rule::in(self::PRESENTACIONES)],
            'cedula_medico' => ['nullable', 'string', 'max:50', 'exists:medicos,cedula'],
            'paciente' => ['nullable', 'string', 'max:255'],
            'cantidades' => ['required', 'array'],
            'cantidades.*' => ['required', 'integer', 'min:1', 'max:500'],
        ], [
            'so.regex' => 'El campo SO solo debe contener números.',
        ]);

        $ids = collect(session(self::SESSION_SELECCION, []))->map(fn ($id) => (int) $id);
        $formulas = FormulaHomeo::with('items')->whereIn('id', $ids)->get()
            ->sortBy(fn ($formula) => $ids->search($formula->id))
            ->values();

        if ($formulas->isEmpty()) {
            return response()->json([
                'message' => 'Selecciona al menos una fórmula.',
                'errors' => ['formulas' => ['Selecciona al menos una fórmula.']],
            ], 422);
        }

        $medicoReemplazo = null;
        if (!empty($data['cedula_medico'])) {
            $medicoReemplazo = Medico::where('cedula', $data['cedula_medico'])->first();
            if (!$medicoReemplazo?->firma) {
                return response()->json([
                    'message' => 'El médico de reemplazo debe tener firma registrada.',
                    'errors' => ['cedula_medico' => ['El médico de reemplazo debe tener firma registrada.']],
                ], 422);
            }
        }

        $medicos = [];
        foreach ($formulas as $formula) {
            if (!array_key_exists((string) $formula->id, $data['cantidades']) &&
                !array_key_exists($formula->id, $data['cantidades'])) {
                return response()->json([
                    'message' => "Indica la cantidad para la fórmula {$formula->codigo}.",
                    'errors' => ['cantidades' => ["Falta la cantidad de {$formula->codigo}."]],
                ], 422);
            }
            $medico = $medicoReemplazo ?: Medico::where('cedula', $formula->cedula_medico)->first();
            if (!$medico?->firma) {
                return response()->json([
                    'message' => "La fórmula {$formula->codigo} no tiene un médico con firma. Selecciona un médico para reemplazarlo.",
                    'errors' => ['cedula_medico' => ["La fórmula {$formula->codigo} requiere un médico con firma."]],
                ], 422);
            }
            $medicos[$formula->id] = $medico;
        }

        $packs = [];
        $recetaIds = [];
        $pacienteAsignado = false;
        DB::transaction(function () use (
            $data,
            $formulas,
            $medicos,
            &$packs,
            &$recetaIds,
            &$pacienteAsignado
        ) {
            foreach ($formulas as $formula) {
                $medico = $medicos[$formula->id];
                $cantidadTotal = (int) $data['cantidades'][$formula->id];
                $composicion = $formula->items->map(fn ($item) => trim(
                    $item->activo.($item->dilusion !== '' ? ' '.$item->dilusion : '')
                ))->implode("\n");
                $grupos = [];
                for ($restantes = $cantidadTotal; $restantes > 0; $restantes -= 6) {
                    $grupos[] = min(6, $restantes);
                }

                foreach ($grupos as $indice => $numFrascos) {
                    $pacienteIngresado = trim((string) ($data['paciente'] ?? ''));
                    $nombrePaciente = !$pacienteAsignado && $pacienteIngresado !== ''
                        ? $pacienteIngresado
                        : $this->randomName();
                    if (!$pacienteAsignado && $pacienteIngresado !== '') {
                        $pacienteAsignado = true;
                    }

                    $receta = Receta::create([
                        'so' => $data['so'],
                        'codigo_formula' => $formula->codigo.'-'.($indice + 1),
                        'fecha' => now()->toDateString(),
                        'cedula_medico' => $medico->cedula,
                        'paciente' => $nombrePaciente,
                        'num_frascos' => $numFrascos,
                    ]);

                    $homeopatico = RecetaHomeopatico::create([
                        'id_receta' => $receta->id_receta,
                        'producto' => $formula->nombre_etiqueta.' - '.$data['presentacion'],
                        'composicion' => $composicion,
                        'cantidad_solicitada' => $cantidadTotal,
                    ]);

                    $packs[] = [
                        'receta' => $receta,
                        'homeopatico' => $homeopatico,
                        'medico' => $medico,
                        'firmaBase64' => $this->firmaBase64($medico),
                    ];
                    $recetaIds[] = $receta->id_receta;
                }
            }
        });

        session()->forget(self::SESSION_SELECCION);

        $pdf = Pdf::loadView('recetas.homeopatico_pdf', [
            'packs' => $packs,
            'medico' => $packs[0]['medico'],
            'firmaBase64' => $packs[0]['firmaBase64'],
        ])->setPaper('a4');

        return $pdf
            ->download('Recetas-Homeopaticas-'.now()->format('Ymd-His').'.pdf')
            ->withHeaders($this->publicRecipeLinksHeader($recetaIds));
    }

    private function publicRecipeLinksHeader(array $recetaIds): array
    {
        $recetas = Receta::with('homeopatico')
            ->whereIn('id_receta', $recetaIds)
            ->get()
            ->keyBy('id_receta');

        $links = collect($recetaIds)
            ->filter()
            ->unique()
            ->values()
            ->map(function ($id) use ($recetas) {
                $receta = $recetas->get($id);
                $producto = trim((string) ($receta?->homeopatico?->producto ?? ''));
                $nombre = $producto !== ''
                    ? trim(Str::before($producto, ' - '))
                    : (string) ($receta?->codigo_formula ?? 'Receta');

                return [
                    'id' => (int) $id,
                    'nombre' => $nombre,
                    'url' => route('recetas.public.show', ['receta' => $id]),
                ];
            })
            ->all();

        return [
            'X-Receta-Public-Links' => base64_encode(json_encode($links)),
            'Access-Control-Expose-Headers' => 'X-Receta-Public-Links, Content-Disposition',
        ];
    }

    private function firmaBase64(Medico $medico): ?string
    {
        $publica = 'firmas/'.$medico->cedula.'.png';
        if (Storage::disk('public')->exists($publica)) {
            return base64_encode(Storage::disk('public')->get($publica));
        }

        $imagen = public_path('images/firmas/'.$medico->cedula.'.png');
        return file_exists($imagen) ? base64_encode(file_get_contents($imagen)) : null;
    }

    private function randomName(): string
    {
        $nombres = [
            'Juan', 'María', 'Pedro', 'Luisa', 'Carlos', 'Ana', 'Jorge', 'Sofía',
            'Diego', 'Daniela', 'Andrés', 'Valeria', 'Miguel', 'Camila', 'Felipe',
            'Fernanda', 'Pablo', 'Paola', 'Ricardo', 'Andrea', 'José', 'Laura',
        ];
        $apellidos = [
            'García', 'Rodríguez', 'Martínez', 'López', 'González', 'Pérez',
            'Sánchez', 'Ramírez', 'Torres', 'Flores', 'Vargas', 'Castro',
            'Rojas', 'Moreno', 'Guerrero', 'Mendoza', 'Ortega', 'Navarro',
        ];

        return $nombres[array_rand($nombres)].' '.$apellidos[array_rand($apellidos)];
    }
}
