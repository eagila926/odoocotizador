<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Formula;
use App\Models\FormulaItem;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse; 
use App\Models\Receta;
use App\Models\Medico;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class FormulasEstController extends Controller
{
    public const SESSION_KEY = 'fe_items';
    private const TIPO_ETIQUETA = [
        'Seleccionar','Dr Redin','Dr Li','Dr Walter','Dra Maria Delia',
        'Dra Viteri','Dra Bernarda','M Urdiales','Naturmed','Julissa','Sobre'
    ];

    public function index(Request $request)
    {
        $items = $request->session()->get(self::SESSION_KEY, []); // [['id'=>1,'tipo'=>null], ...]
        $ids   = array_column($items, 'id');
    
        $formulas = $ids
            ? Formula::whereIn('id', $ids)
                ->orderByRaw("
                    CASE
                        WHEN codigo LIKE 'FOTHESCO%' THEN 0
                        ELSE 1
                    END
                ")
                ->orderBy('codigo') // orden dentro de cada grupo
                ->get([
                    'id',
                    'codigo',
                    'nombre_etiqueta',
                    'precio_medico',
                    'precio_publico',
                    'precio_distribuidor'
                ])
            : collect();
    
        $rows = $formulas->map(function ($f) use ($items) {
            $tipo = collect($items)->firstWhere('id', $f->id)['tipo'] ?? null;
    
            return (object)[
                'id'                 => $f->id,
                'codigo'             => $f->codigo,
                'nombre_etiqueta'    => $f->nombre_etiqueta,
                'precio_medico'      => (float) $f->precio_medico,
                'precio_distribuidor'=> (float) $f->precio_distribuidor,
                'precio_publico'     => (float) $f->precio_publico,
                'tipo'               => $tipo,
            ];
        });
    
        return view('formulas.establecidas', [
            'rows'  => $rows,
            'tipos' => self::TIPO_ETIQUETA
        ]);
    }


    public function buscar(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        if ($q === '') return response()->json([]);
    
        $data = Formula::query()
            ->where(function ($qq) use ($q) {
                $qq->where('codigo', 'like', "%{$q}%")
                   ->orWhere('nombre_etiqueta', 'like', "%{$q}%");
            })
            // Prioriza códigos que EMPIECEN con FOTHESCO
            ->orderByRaw("
                CASE
                    WHEN codigo LIKE 'FOTHESCO%' THEN 0
                    ELSE 1
                END
            ")
            ->orderBy('codigo')
            ->limit(12)
            ->get(['id', 'codigo', 'nombre_etiqueta', 'precio_medico', 'precio_publico', 'precio_distribuidor'])
            ->map(fn ($f) => [
                'id'      => $f->id,
                'display' => $f->codigo . ' — ' . $f->nombre_etiqueta,
            ]);
    
        return response()->json($data);
    }

    public function add(Request $request)
    {
        $request->validate(['formula_id'=>'required|integer|exists:formulas,id']);
        $items = $request->session()->get(self::SESSION_KEY, []);
        if (!collect($items)->firstWhere('id',(int)$request->formula_id)) {
            $items[] = ['id'=>(int)$request->formula_id, 'tipo'=>null];
            $request->session()->put(self::SESSION_KEY,$items);
        }
        return back();
    }

    public function updateTipo(Request $request)
    {
        $id   = (int) $request->input('formula_id');
        $tipo = trim((string)$request->input('tipo'));

        $items = $request->session()->get(self::SESSION_KEY, []);
        foreach ($items as &$it) {
            if ((int)$it['id'] === $id) {
                $it['tipo'] = $tipo;
                break;
            }
        }
        $request->session()->put(self::SESSION_KEY, $items);

        return response()->noContent();
    }


    public function remove(Request $request,int $id)
    {
        $items = $request->session()->get(self::SESSION_KEY, []);
        $items = array_values(array_filter($items, fn($it)=>(int)$it['id']!==$id));
        $request->session()->put(self::SESSION_KEY,$items);
        return back();
    }

    public function clear(Request $request)
    {
        $request->session()->forget(self::SESSION_KEY);
        return back();
    }

    public function print(Request $request, int $id)
    {
        // 1) Obtiene la fórmula e ítems
        $formula = \App\Models\Formula::with('items')->findOrFail($id);

        // Ítems de composición
        $excluir = [70274,70272,70275,70273,1101,1078,1077,1219,70276,70271,71497];
        $itemsComposicion = $formula->items
            ->filter(fn($it) => !in_array((int)$it->cod_odoo, $excluir))
            ->values();

        $qf = 'Q.F. EVELYN GARCÍA';

        // 2) Tipo seleccionado desde sesión
        $feItems  = $request->session()->get(self::SESSION_KEY, []); // [['id'=>1,'tipo'=>'Dr Redin'], ...]
        $registro = collect($feItems)->firstWhere('id', $id);
        $tipo     = $registro['tipo'] ?? null;

        // 3) Mapa Tipo -> vista
        $map = [
            'Seleccionar'     => null,
            'Dr Redin'        => 'redin',
            'Dr Li'           => 'li',
            'Dr Walter'       => 'walter',
            'Dra Maria Delia' => 'maria_delia',
            'Dra Viteri'      => 'viteri',
            'Dra Bernarda'    => 'bernarda',
            'M Urdiales'      => 'urdiales',
            'Naturmed'        => 'naturmed',
            'Julissa'        => 'julissa',
            'Sobre'           => 'sobre',
        ];

        $slug = $map[$tipo] ?? null;
        $view = $slug ? "etiquetas.$slug" : "etiquetas.generica";

        // 4) Renderiza la VISTA CORRECTA
        return view($view, [
            'formula'          => $formula,
            'items'            => $itemsComposicion,
            'qf'               => $qf,
            'fechaElaboracion' => now()->format('d-m-Y'),
            'tipo'             => $tipo,
        ]);
    }



    public function excel(Request $request,int $id)
    {
        $f = Formula::findOrFail($id,['codigo','nombre_etiqueta','precio_medico','precio_publico','precio_distribuidor']);
        $csv = "Codigo,Nombre Etiqueta,Precio Médico,Precio Distribuidor,Precio Paciente\n";
        $csv.= "{$f->codigo},\"{$f->nombre_etiqueta}\",{$f->precio_medico},{$f->precio_distribuidor},{$f->precio_publico}\n";
        return response($csv,200,[
            'Content-Type'=>'text/csv; charset=UTF-8',
            'Content-Disposition'=>'attachment; filename="formula_'.$f->codigo.'.csv"',
        ]);
    }

    public function items(int $id)
    {
        $f = Formula::findOrFail($id, ['id','codigo','nombre_etiqueta']);

        $endCodes = [70274,70272,70275,70273,1101,1078,1077,1219,70276,70271,71497];

        $items = FormulaItem::where('codigo', $f->codigo)
            ->orderByRaw('CASE WHEN cod_odoo IN ('.implode(',', $endCodes).') THEN 1 ELSE 0 END')
            ->orderByDesc('id')
            ->get(['cod_odoo','activo','cantidad','unidad','masa_mes']);

        return view('fe.items', [
            'f'     => $f,
            'items' => $items,
        ]);
    }


    // Exportar esos items a CSV
    // public function itemsExport(int $id)
    // {
    //     $f = Formula::findOrFail($id, ['id','codigo','nombre_etiqueta']);

    //     $rows = FormulaItem::where('codigo', $f->codigo)
    //         ->orderBy('id','desc')
    //         ->get(['cod_odoo','activo','cantidad','unidad']);

    //     $csv  = "Codigo,\"Nombre Etiqueta\",cod_odoo,activo,cantidad,unidad\n";
    //     foreach ($rows as $r) {
    //         $cantidad = rtrim(rtrim(number_format((float)$r->cantidad, 6, '.', ''), '0'), '.');
    //         $csv .= "{$f->codigo},\"{$f->nombre_etiqueta}\",{$r->cod_odoo},\"{$r->activo}\",{$cantidad},{$r->unidad}\n";
    //     }

    //     return response($csv, 200, [
    //         'Content-Type'        => 'text/csv; charset=UTF-8',
    //         'Content-Disposition' => 'attachment; filename="items_'.$f->codigo.'.csv"',
    //     ]);
    // }

    public function itemsExportXlsx(int $id)
    {
        $f = Formula::findOrFail($id, ['id','codigo','nombre_etiqueta']);

        $endCodes = [70274,70272,70275,70273,1101,1078,1077,1219,70276,70271,71497];

        $rows = FormulaItem::where('codigo', $f->codigo)
            ->orderByRaw('CASE WHEN cod_odoo IN ('.implode(',', $endCodes).') THEN 1 ELSE 0 END')
            ->orderByDesc('id')
            ->get(['cod_odoo','activo','cantidad','unidad','masa_mes']);

        // Construimos el XLSX (encabezado y datos tal como "Tabla de Exportación Odoo")
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Exportación Odoo');

        // Encabezados
        $headers = [
            'A1' => 'Líneas de LdM/Componente/Id. de la BD',
            'B1' => 'Líneas de LdM/Cantidad',
            'C1' => 'Líneas de LdM/Unidad de medida del producto/ID',
        ];
        foreach ($headers as $col => $text) {
            $sheet->setCellValue($col, $text);
        }
        $sheet->getStyle('A1:C1')->getFont()->setBold(true);

        // Filas
        $r = 2;
        foreach ($rows as $it) {
            $u = strtolower((string)$it->unidad);
            $esMasa = in_array($u, ['mg','mcg','ui','g']);

            $cantidadExport = $esMasa
                ? (float)($it->masa_mes ?? 0)   // g/mes
                : (float)($it->cantidad ?? 0);  // ej. und

            // Unidad base
            $unidadExport = $esMasa ? 'g' : ($it->unidad ?? '');

            // Mapear a ID de unidad de Odoo
            $unidadOdoo = $unidadExport;
            if (strtolower($unidadExport) === 'g') {
                $unidadOdoo = 'uom.product_uom_gram';
            } elseif (strtolower($unidadExport) === 'und') {
                $unidadOdoo = 'uom.product_uom_unit';
            }

            $sheet->setCellValue("A{$r}", (int)$it->cod_odoo);
            $sheet->setCellValue("B{$r}", $cantidadExport);
            $sheet->setCellValue("C{$r}", $unidadOdoo);

            // 4 decimales para Cantidad
            $sheet->getStyle("B{$r}")
                ->getNumberFormat()
                ->setFormatCode('0.0000');

            $r++;
        }


        // Auto-size columnas
        foreach (range('A','C') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = 'export_odoo_'.$f->codigo.'.xlsx';

        // StreamedResponse para no escribir en disco
        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control'       => 'max-age=0, no-cache, no-store, must-revalidate',
            'Pragma'              => 'public',
        ]);
    }

    /**
     * Crear una `Receta` simple para esta fórmula y devolver el PDF descargable.
     * El formulario cliente debe enviar: `so` (required numeric), `cedula_medico` (required),
     * `paciente` (optional), `num_frascos` (optional, default 1).
     */
    public function recetaCreate(Request $request, int $id)
    {
        $f = Formula::findOrFail($id);

        $data = $request->validate([
            'so'             => ['required','regex:/^\\d+$/'],
            'cedula_medico'  => ['required','string','max:50'],
            'paciente'       => ['nullable','string','max:120'],
            'num_frascos'    => ['nullable','integer','min:1','max:200'],
        ],[
            'so.regex' => 'El campo SO solo debe contener números.',
        ]);

        $n = (int) ($data['num_frascos'] ?? 1);
        $pacienteIngresado = trim((string) ($data['paciente'] ?? ''));

        // Verificar médico en BD
        $medico = Medico::where('cedula', $data['cedula_medico'])->first();
        if (!$medico) {
            if ($request->ajax()) {
                return response()->json([
                    'message' => 'El médico no existe.',
                    'errors'  => ['cedula_medico' => ['El médico no existe.']],
                ], 422);
            }
            return redirect()->back()->withErrors(['cedula_medico' => 'El médico no existe.']);
        }

        if (!$medico->firma) {
            if ($request->ajax()) {
                return response()->json([
                    'message' => 'El médico no tiene firma registrada en la base de datos.',
                    'errors'  => ['cedula_medico' => ['El médico no tiene firma registrada en la base de datos.']],
                ], 422);
            }
            return redirect()->back()->withErrors(['cedula_medico' => 'El médico no tiene firma registrada en la base de datos.']);
        }

        // Intentar obtener el archivo de firma (si no existe, continuar sin imagen)
        $firmaBase64 = null;
        if (!empty($medico->firma_path)) {
            $p = $medico->firma_path;
            $rel = str_starts_with($p, 'public/') ? substr($p, 7) : $p;
            if (Storage::disk('public')->exists($rel)) {
                $firmaBase64 = base64_encode(Storage::disk('public')->get($rel));
            } elseif (file_exists(public_path($p))) {
                $firmaBase64 = base64_encode(file_get_contents(public_path($p)));
            } elseif (Storage::exists($p)) {
                $firmaBase64 = base64_encode(Storage::get($p));
            }
        }
        if (!$firmaBase64) {
            $probe = 'firmas/'.$medico->cedula.'.png';
            if (Storage::disk('public')->exists($probe)) {
                $firmaBase64 = base64_encode(Storage::disk('public')->get($probe));
            } else {
                $probe2 = public_path('images/firmas/'.$medico->cedula.'.png');
                if (file_exists($probe2)) $firmaBase64 = base64_encode(file_get_contents($probe2));
            }
        }

        $createdIds = [];
        $fechaReceta = now()->subDays(2)->toDateString();
        // Las fórmulas en sobres se prescriben como un solo tratamiento en cajas.
        $esFormulaSobres = str_starts_with(strtoupper((string) $f->codigo), 'SFO');
        $frascosPorReceta = $esFormulaSobres
            ? [$n]
            : $this->distribuirFrascosReceta($n);

        foreach ($frascosPorReceta as $index => $numFrascos) {
            $r = Receta::create([
                'so'             => $data['so'],
                'codigo_formula' => $f->codigo,
                'fecha'          => $fechaReceta,
                'cedula_medico'  => $data['cedula_medico'],
                'paciente'       => ($esFormulaSobres || $n === 1)
                    ? ($pacienteIngresado !== '' ? $pacienteIngresado : $this->randomName())
                    : $this->randomName(),
                'num_frascos'    => $numFrascos,
            ]);
            $createdIds[] = $r->getKey();
        }

        // Si se creó una sola receta, retornar PDF simple
        if (count($createdIds) === 1) {
            $receta = Receta::findOrFail($createdIds[0]);
            $formula = Formula::with('items')->where('codigo', $receta->codigo_formula)->first();
            $items = $formula?->items->filter(fn($it)=>true)->values() ?? collect();

            $pdf = Pdf::loadView('recetas.pdf', [
                'receta'      => $receta,
                'formula'     => $formula,
                'items'       => $items,
                'medico'      => $medico,
                'firmaBase64' => $firmaBase64,
            ])->setPaper('a4');

            $filename = 'Receta-'.$receta->codigo_formula.'-SO'.$receta->so.'.pdf';
            return response($pdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ] + $this->publicRecipeLinksHeader($createdIds));
        }

        // Si se crearon varias recetas, generar PDF multipágina
        $recetas = Receta::whereIn('id_receta', $createdIds)->get();
        $dataPdf = $recetas->map(function($r) {
            $formula = Formula::with('items')->where('codigo', $r->codigo_formula)->first();
            $items = $formula?->items ?? collect();
            $medico = Medico::where('cedula', $r->cedula_medico)->first();
            $firma = null;
            if ($medico) {
                $probe = 'firmas/'.$medico->cedula.'.png';
                if (Storage::disk('public')->exists($probe)) $firma = base64_encode(Storage::disk('public')->get($probe));
                else {
                    $p2 = public_path('images/firmas/'.$medico->cedula.'.png');
                    if (file_exists($p2)) $firma = base64_encode(file_get_contents($p2));
                }
            }
            return [
                'r' => $r,
                'formula' => $formula,
                'items' => $items,
                'medico' => $medico,
                'firmaBase64' => $firma,
            ];
        });

        $pdf = Pdf::loadView('recetas.lote_pdf', ['lote' => $dataPdf])->setPaper('a4');
        $filename = 'Recetas-'.now()->format('Ymd_His').'.pdf';
        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ] + $this->publicRecipeLinksHeader($createdIds));
    }

    private function publicRecipeLinksHeader(array $recetaIds): array
    {
        $links = collect($recetaIds)
            ->filter()
            ->unique()
            ->values()
            ->map(fn ($id) => [
                'id' => (int) $id,
                'url' => route('recetas.public.show', ['receta' => $id]),
            ])
            ->all();

        return [
            'X-Receta-Public-Links' => base64_encode(json_encode($links)),
        ];
    }

    private function distribuirFrascosReceta(int $cantidad): array
    {
        if ($cantidad <= 12) {
            return array_fill(0, max(1, $cantidad), 1);
        }

        $frascos = [];
        $restantes = $cantidad;

        while ($restantes > 0) {
            $frascos[] = min(6, $restantes);
            $restantes -= 6;
        }

        return $frascos;
    }

    private function randomName(): string
    {
        $nombres = ['Juan','María','Pedro','Luisa','Carlos','Ana','Jorge','Sofía','Diego','Daniela','Andrés','Valeria','Miguel','Camila','Felipe','Fernanda','Pablo','Paola','Ricardo','Andrea','Elvis'];
        $apellidos = ['García','Rodríguez','Martínez','López','González','Pérez','Sánchez','Ramírez','Torres','Flores','Vargas','Castro','Rojas','Moreno','Guerrero','Mendoza'];
        return $nombres[random_int(0, count($nombres)-1)] . ' ' . $apellidos[random_int(0, count($apellidos)-1)];
    }
}
