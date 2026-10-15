<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB; 
use App\Mail\RecetaCreadaMail;
use App\Models\Receta;
use App\Models\RecetaHomeopatico;
use App\Models\RecetaProducto;
use App\Models\Formula;
use App\Models\FormulaItem;
use App\Models\Medico;
use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Mail\RecetasLoteMail;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;

class RecetaController extends Controller
{
    public function index(Request $request)
    {
        if (! $request->user()?->hasRole(['Admin'])) {
            abort(403, 'No tienes permisos para ver el listado de recetas.');
        }

        // filtros opcionales
        $q      = trim((string) $request->query('q', ''));
        $desde  = $request->query('desde');
        $hasta  = $request->query('hasta');

        $recetas = Receta::query()
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($w) use ($q) {
                    $w->where('so', 'like', "%{$q}%")
                    ->orWhere('codigo_formula', 'like', "%{$q}%")
                    ->orWhere('cedula_medico', 'like', "%{$q}%")
                    ->orWhere('paciente', 'like', "%{$q}%");
                });
            })
            ->when($desde, fn($qq) => $qq->whereDate('fecha', '>=', $desde))
            ->when($hasta, fn($qq) => $qq->whereDate('fecha', '<=', $hasta))
            ->orderByDesc('id_receta')
            ->paginate(20)
            ->withQueryString();

        return view('recetas.index', compact('recetas','q','desde','hasta'));
    }

    public function show(Receta $receta)
    {
        if (! request()->user()?->hasRole(['Admin'])) {
            abort(403, 'No tienes permisos para ver el detalle de recetas.');
        }

        return view('recetas.show', $this->recetaViewData($receta, false));
    }

    public function publicShow(Receta $receta)
    {
        return view('recetas.public', $this->recetaViewData($receta, true));
    }

    private function recetaViewData(Receta $receta, bool $publicView = false): array
    {
        $homeopatico = $receta->homeopatico()->first();

        // Cargar los productos asociados si existen
        $productos = $receta->productos()->get();

        // Si no hay productos, es una receta tradicional con fórmula
        if ($productos->isEmpty() && ! $homeopatico) {
            // 1) Traer fórmula por código + sus ítems
            $formula = Formula::with('items')   // relación hasMany('items') en Formula
                ->where('codigo', $receta->codigo_formula)
                ->first();

            $items = [];
            if ($formula) {
                // 2) Excluir auxiliares (mismo filtro que usas en la etiqueta)
                $excluir = [70274,70272,70275,70273,1101,1078,1077,1219,70276,70271,71497];
                $items = $formula->items->filter(fn($it) => !in_array((int)$it->cod_odoo, $excluir))->values();
            }
        } else {
            $formula = null;
            $items = [];
        }

        // 3) Médico por cédula
        $medico = Medico::where('cedula', $receta->cedula_medico)->first();

        // 4) Firma (elige una de estas dos estrategias)
        // 4.a) Si tu tabla medicos tiene columna firma_path (p. ej. 'public/firmas/1712.png')
        $firmaUrl = null;
        if ($medico) {
            // si guardas la ruta relativa en la BD, p.ej. 'firmas/123.png'
            if ($medico->firma_path && Storage::disk('public')->exists($medico->firma_path)) {
                $firmaUrl = Storage::url($medico->firma_path); // /storage/firmas/123.png
            } else {
                $path = 'firmas/'.$medico->cedula.'.png';
                if (Storage::disk('public')->exists($path)) {
                    $firmaUrl = Storage::url($path);
                }
            }
        }

        return [
            'receta'   => $receta,
            'formula'  => $formula ?? null,
            'items'    => $items,
            'productos' => $productos,
            'homeopatico' => $homeopatico,
            'medico'   => $medico,
            'firmaUrl' => $firmaUrl,
            'firmaBase64' => $this->obtenerFirmaBase64($medico),
            'publicView' => $publicView,
        ];
    }

    private function publicRecipeLinks(array $recetaIds): array
    {
        return collect($recetaIds)
            ->filter()
            ->unique()
            ->values()
            ->map(fn ($id) => [
                'id' => (int) $id,
                'url' => route('recetas.public.show', ['receta' => $id]),
            ])
            ->all();
    }

    private function publicRecipeLinksHeader(array $recetaIds): array
    {
        return [
            'X-Receta-Public-Links' => base64_encode(json_encode($this->publicRecipeLinks($recetaIds))),
        ];
    }
    public function storeMultiple(Request $request)
    {
        $data = $request->validate([
            'codigo_formula'   => ['required','string','max:50'],
            'so'               => ['required','regex:/^\d+$/','max:50'],
            'fecha'            => ['nullable','date'],
            'cedula_medico'    => ['required','string','max:50'],
            'medico_nombre'    => ['nullable','string','max:120'],
            'paciente'         => ['nullable','string','max:50'],
            'num_etiquetas'    => ['required','integer','min:1','max:200'],
            'redirect_to'      => ['nullable','string'],
        ],[
            'so.regex' => 'El campo SO solo debe contener números.',
        ]);

        $n       = (int) $data['num_etiquetas'];
        $fecha   = $data['fecha'] ?? now()->toDateString();
        $so      = $data['so'];
        $cod     = $data['codigo_formula'];
        $cedula  = $data['cedula_medico'];
        $medicoNombre = trim((string)($data['medico_nombre'] ?? ''));

        $groupSize   = 6;               // 6 etiquetas = 1 receta
        $numRecetas  = (int) ceil($n / $groupSize);

        $pacienteParaEtiqueta = null;
        $creadasIds = [];

        // 1) Crear recetas y recoger IDs (si algo falla, no enviamos correos)
        DB::transaction(function () use (
            $n,$fecha,$so,$cod,$cedula,$numRecetas,&$creadasIds,$request,&$pacienteParaEtiqueta,$groupSize
        ) {
            // Cantidad total de etiquetas/frascos que hay que repartir
            $restantes = $n;

            if ($n === 1) {
                $pacienteInput = trim((string)$request->input('paciente',''));
                if ($pacienteInput === '') {
                    $nombreParaBD = $this->randomName();
                    $pacienteParaEtiqueta = null;
                } else {
                    $nombreParaBD = $pacienteInput;
                    $pacienteParaEtiqueta = $pacienteInput;
                }

                // Con 1 etiqueta → 1 frasco
                $r = Receta::create([
                    'so'              => $so,
                    'codigo_formula'  => $cod,
                    'fecha'           => $fecha,
                    'cedula_medico'   => $cedula,
                    'paciente'        => $nombreParaBD,
                    'num_frascos'     => 1,
                ]);
                $creadasIds[] = $r->getKey();

            } else {

                for ($i = 0; $i < $numRecetas; $i++) {

                    // ¿Cuántos frascos/etiquetas va a llevar ESTA receta?
                    // Ej.: n=17, groupSize=6
                    // i=0 → min(6,17)=6
                    // i=1 → min(6,11)=6
                    // i=2 → min(6,5)=5
                    $frascos = min($groupSize, $restantes);

                    $r = Receta::create([
                        'so'              => $so,
                        'codigo_formula'  => $cod,
                        'fecha'           => $fecha,
                        'cedula_medico'   => $cedula,
                        'paciente'        => $this->randomName(),
                        'num_frascos'     => $frascos,
                    ]);
                    $creadasIds[] = $r->getKey();

                    // Restar lo que ya se asignó
                    $restantes -= $frascos;
                }

                $pacienteParaEtiqueta = null;
            }
        });


        // 2) Enviar correos (fuera de la transacción)
        // 2) Enviar correos (una vez por lote)
        $internos = array_filter(array_map('trim', explode(',', env('INTERNAL_RECETAS_EMAIL',''))));

        // tomar el médico de la primera receta (todas comparten el mismo en este flujo)
        $primera = \App\Models\Receta::find($creadasIds[0]);
        $medico = \App\Models\Medico::where('cedula', $primera->cedula_medico)->first();
        $doctorEmail = trim((string)($medico->email ?? $medico->correo ?? ''));

        $mailable = new RecetasLoteMail($creadasIds);

        if ($doctorEmail !== '') {
            $mail = \Mail::to($doctorEmail);
            if ($internos) $mail->cc($internos);
            $mail->send($mailable);
        } elseif ($internos) {
            \Mail::to($internos[0])->cc(array_slice($internos,1))->send($mailable);
        }

        // 3) Redirigir (AHORA sí)
        $back = $data['redirect_to'] ?? url()->previous();
        return redirect($back)->with([
            'recetas_guardadas' => $numRecetas,
            'receta_public_links' => $this->publicRecipeLinks($creadasIds),
            'etiqueta_preview'  => [
                'so'      => $so,
                'medico'  => $medicoNombre,
                'paciente'=> $pacienteParaEtiqueta
            ],
        ]);
    }

    private function enviarCorreoReceta(int $recetaId, array $internos): void
    {
        $receta = Receta::findOrFail($recetaId);
        $medico = Medico::where('cedula', $receta->cedula_medico)->first();
        $doctorEmail = trim((string)($medico->email ?? $medico->correo ?? ''));

        $mailable = new RecetaCreadaMail($recetaId);

        if ($doctorEmail !== '') {
            $mail = Mail::to($doctorEmail);
            if ($internos) $mail->cc($internos);
            $mail->send($mailable);  // en local (sandbox) síncrono
        } elseif ($internos) {
            Mail::to($internos[0])
                ->cc(array_slice($internos,1))
                ->send($mailable);
        }
    }

    private function obtenerFirmaBase64(?Medico $medico): ?string
    {
        if (! $medico) {
            return null;
        }

        $firmaBase64 = null;
        if (!empty($medico->firma_path)) {
            $path = $medico->firma_path;
            $rel = Str::startsWith($path, 'public/') ? Str::after($path, 'public/') : $path;

            if (Storage::disk('public')->exists($rel)) {
                $firmaBase64 = base64_encode(Storage::disk('public')->get($rel));
            }

            if (!$firmaBase64 && file_exists(public_path($path))) {
                $firmaBase64 = base64_encode(file_get_contents(public_path($path)));
            }

            if (!$firmaBase64 && Storage::exists($path)) {
                $firmaBase64 = base64_encode(Storage::get($path));
            }
        }

        if (!$firmaBase64) {
            $probe = 'firmas/'.$medico->cedula.'.png';
            if (Storage::disk('public')->exists($probe)) {
                $firmaBase64 = base64_encode(Storage::disk('public')->get($probe));
            } else {
                $probe2 = public_path('images/firmas/'.$medico->cedula.'.png');
                if (file_exists($probe2)) {
                    $firmaBase64 = base64_encode(file_get_contents($probe2));
                }
            }
        }

        return $firmaBase64;
    }

    private function randomName(): string
    {
        $nombres = [
            'Juan','María','Pedro','Luisa','Carlos','Ana','Jorge','Sofía','Diego','Daniela',
            'Andrés','Valeria','Miguel','Camila','Felipe','Fernanda','Pablo','Paola','Ricardo','Andrea','Elvis',
            'José','Laura','Marco','Patricia','Héctor','Alejandra','Iván','Verónica','Francisco','Natalia',
            'Roberto','Lorena','Sebastián','Carolina','Tomás','Rocío','Ángel','Isabel','Mauricio','Renata',
            'Mario','Bianca','Raúl','Julieta','Oscar','Elena','Samuel','Noelia','Adrián','Fabiola',
            'Hugo','Cecilia','Abel','Esperanza','Leandro','Marisol','Cristian','Dayana','Gustavo','Mayra',
            'Emanuel','Gloria','Sergio','Gabriela','Kevin','Mónica','Luis','Silvia','Saúl','Miranda',
            'Emilio','Claudia','Benjamín','Miriam','Alex','Marina','Tadeo','Inés','Bruno','Leticia',
            'Mateo','Alicia','Rafael','Pilar','Iker','Paloma','Alan','Aurora','Julián','Teresa',
            'Gael','Fátima','Hernán','Amalia','Matías','Agustina','Thiago','Josefina','Damián','Beatriz',
            'Cristóbal','Emma','Ethan','Lucía','Israel','Romina','Mauro','Jacinta','Esteban','Viviana',
            'Félix','Salomé','Bastián','Tamara','Joel','Regina','Erick','Sabrina','Álvaro','Adela',
            'Ramiro','Araceli','Guillermo','Candela','Nelson','Elisa','Fabián','Ivanna','Darío','Clara',
            'Rubén','Daniela','Eduardo','Lourdes','Israel','Yuliana','Ariel','Samanta','Ignacio','Javiera'
        ];
        $apellidos = [
            'García','Rodríguez','Martínez','López','González','Pérez','Sánchez','Ramírez','Torres','Flores',
            'Vargas','Castro','Rojas','Moreno','Guerrero','Mendoza','Ortega','Navarro','Espinoza','Cruz',
            'Reyes','Guzmán','Salazar','Aguilar','Medina','Vera','Romero','Herrera','Muñoz','Correa',
            'Fuentes','Suárez','Peña','Cordero','Bravo','Arroyo','Silva','Mejía','Montenegro','Paredes',
            'Zamora','Ibarra','Padilla','Valencia','Camacho','Acosta','Bustamante','Velásquez','Ojeda','Rivas',
            'Flores','Soto','Cedeño','Moreira','Benítez','Granda','Segura','Merino','Montero','Sandoval',
            'Calderón','Villalba','Rosales','Bustos','Chávez','Riquelme','Bravo','Yáñez','Orozco','Salinas',
            'Carrillo','Zúñiga','Torrealba','Maldonado','Domínguez','Barrios','Luna','Barba','Arellano','Patiño',
            'Altamirano','Jaramillo','Báez','Ontaneda','Vinueza','Armas','Rosero','Proaño','Tapia','Chacón',
            'Cáceres','Núñez','Santana','Pozo','Lozano','Delgado','Peralta','Serrano','Cárdenas','Coronel',
            'Ulloa','Solano','Fierro','Zurita','Viteri','Soria','Vivanco','Toledo','Gálvez','Guajardo',
            'Molina','Mojica','Gallo','Reátegui','Coloma','Pérez','Avilés','Zambrano','Farfán','Arce'
        ];

        return $nombres[random_int(0, count($nombres)-1)] . ' ' .
               $apellidos[random_int(0, count($apellidos)-1)];
    }

    /**
     * Mostrar formulario de creación de receta (nueva vista)
     */
    public function create()
    {
        return view('recetas.crear');
    }

    public function homeopatico()
    {
        return view('recetas.homeopatico');
    }

    public function storeHomeopatico(Request $request)
    {
        $data = $request->validate([
            'so' => ['required', 'regex:/^\d+$/', 'max:50'],
            'cedula_medico' => ['required', 'string', 'max:50'],
            'producto' => ['required', 'string', 'max:255'],
            'composicion' => ['required', 'string'],
            'cantidad' => ['required', 'integer', 'min:1', 'max:500'],
            'paciente' => ['nullable', 'string', 'max:255'],
            'fecha' => ['required', 'date'],
        ], [
            'so.required' => 'Debe ingresar el SO.',
            'so.regex' => 'El campo SO solo debe contener numeros.',
            'cedula_medico.required' => 'Debe seleccionar un medico.',
            'producto.required' => 'Debe ingresar el nombre del producto.',
            'composicion.required' => 'Debe ingresar la composicion.',
            'cantidad.required' => 'Debe ingresar la cantidad solicitada.',
        ]);

        $medico = Medico::where('cedula', $data['cedula_medico'])->first();

        if (! $medico) {
            return back()->withErrors(['cedula_medico' => 'Medico no encontrado. Seleccione un medico valido.'])->withInput();
        }

        $cantidadSolicitada = (int) $data['cantidad'];
        $frascosPorReceta = $this->distribuirFrascosHomeopatico($cantidadSolicitada);
        $paciente = trim((string) ($data['paciente'] ?? ''));
        $codigoBase = 'HOM-' . date('YmdHis') . '-' . random_int(1000, 9999);
        $recetas = [];

        DB::transaction(function () use ($data, $frascosPorReceta, $paciente, $codigoBase, $cantidadSolicitada, &$recetas) {
            foreach ($frascosPorReceta as $index => $numFrascos) {
                $nombrePaciente = $cantidadSolicitada === 1 && $paciente !== ''
                    ? $paciente
                    : $this->randomName();

                $receta = Receta::create([
                    'so' => $data['so'],
                    'codigo_formula' => $codigoBase . '-' . ($index + 1),
                    'fecha' => $data['fecha'],
                    'cedula_medico' => $data['cedula_medico'],
                    'paciente' => $nombrePaciente,
                    'num_frascos' => $numFrascos,
                ]);

                $homeopatico = RecetaHomeopatico::create([
                    'id_receta' => $receta->id_receta,
                    'producto' => trim($data['producto']),
                    'composicion' => trim($data['composicion']),
                    'cantidad_solicitada' => $cantidadSolicitada,
                ]);

                $recetas[] = [
                    'receta' => $receta,
                    'homeopatico' => $homeopatico,
                ];
            }
        });

        $firmaBase64 = $this->obtenerFirmaBase64($medico);

        $pdf = Pdf::loadView('recetas.homeopatico_pdf', [
            'packs' => $recetas,
            'medico' => $medico,
            'firmaBase64' => $firmaBase64,
        ])->setPaper('a4');

        $recetaIds = collect($recetas)->pluck('receta.id_receta')->all();

        return $pdf
            ->download('Recetas-Homeopatico-'.$codigoBase.'.pdf')
            ->withHeaders($this->publicRecipeLinksHeader($recetaIds));
    }

    private function distribuirFrascosHomeopatico(int $cantidad): array
    {
        if ($cantidad <= 12) {
            return array_fill(0, $cantidad, 1);
        }

        $frascos = [];
        $restantes = $cantidad;

        while ($restantes > 0) {
            $frascos[] = min(6, $restantes);
            $restantes -= 6;
        }

        return $frascos;
    }

    /**
     * Buscar productos (AJAX)
     */
    public function buscarProductos(Request $request)
    {
        $q = trim($request->query('q', ''));
        
        if ($q === '') {
            return response()->json([]);
        }

        $productos = Producto::query()
            ->where('nombre', 'like', "%{$q}%")
            ->orWhere('cod_product', 'like', "%{$q}%")
            ->orderBy('nombre')
            ->limit(15)
            ->get(['cod_product', 'nombre'])
            ->toArray();

        return response()->json($productos);
    }

    /**
     * Guardar nueva receta con productos seleccionados
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'so' => ['required', 'regex:/^\d+$/', 'max:50'],
            'cedula_medico' => ['required', 'string', 'max:50'],
            'productos_seleccionados' => ['required', 'json'],
            'paciente' => ['nullable', 'string', 'max:255'],
            'fecha' => ['required', 'date'],
        ], [
            'cedula_medico.required' => 'Debe seleccionar un médico.',
            'productos_seleccionados.required' => 'Debe seleccionar al menos un producto.',
        ]);

        $productos = json_decode($data['productos_seleccionados'], true);
        
        if (empty($productos) || !is_array($productos)) {
            return back()->withErrors(['productos_seleccionados' => 'Debe seleccionar al menos un producto.']);
        }

        foreach ($productos as $producto) {
            if (empty($producto['cod_product']) || empty($producto['nombre'])) {
                return back()->withErrors(['productos_seleccionados' => 'Todos los productos seleccionados deben ser validos.'])->withInput();
            }
        }

        $codigoFormula = 'REC-' . date('YmdHis') . '-' . random_int(1000, 9999);
        $paciente = trim((string) ($data['paciente'] ?? ''));

        $receta = Receta::create([
            'so' => $data['so'],
            'codigo_formula' => $codigoFormula,
            'fecha' => $data['fecha'],
            'cedula_medico' => $data['cedula_medico'],
            'paciente' => $paciente !== '' ? $paciente : $this->randomName(),
            'num_frascos' => 1,
        ]);

        foreach ($productos as $producto) {
            $cantidad = (int) ($producto['cantidad'] ?? 1);

            RecetaProducto::create([
                'id_receta' => $receta->id_receta,
                'cod_product' => $producto['cod_product'],
                'nombre' => $producto['nombre'],
                'cantidad' => $cantidad > 0 ? $cantidad : 1,
            ]);
        }

        $medico = Medico::where('cedula', $data['cedula_medico'])->first();

        if (! $medico) {
            return back()->withErrors(['cedula_medico' => 'Médico no encontrado. Seleccione un médico válido.'])->withInput();
        }

        $receta->load('productos');
        $productosReceta = $receta->productos->toArray();
        $firmaBase64 = $this->obtenerFirmaBase64($medico);

        $pdf = Pdf::loadView('recetas.pdf', [
            'receta' => $receta,
            'formula' => null,
            'items' => collect(),
            'productos' => $productosReceta,
            'medico' => $medico,
            'doctorDisplay' => trim((string)($medico->full_name ?? $medico->nombre ?? $medico->name ?? '')),
            'firmaBase64' => $firmaBase64,
        ])->setPaper('a4');

        $fileName = 'Receta-'.$receta->codigo_formula.'-'.$receta->cedula_medico.'.pdf';

        return $pdf
            ->download($fileName)
            ->withHeaders($this->publicRecipeLinksHeader([$receta->id_receta]));
    }
}
