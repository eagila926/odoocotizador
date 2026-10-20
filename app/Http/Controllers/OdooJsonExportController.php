<?php

namespace App\Http\Controllers;

use App\Models\Activo;
use App\Models\Formula;
use App\Services\OdooFormulaPayload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class OdooJsonExportController extends Controller
{
    public function store(Request $request, OdooFormulaPayload $builder)
    {
        abort_if(!$request->user() || in_array($request->user()->rol, ['Distribuidor', 'Laboratorio'], true), 403, 'No tienes permiso para exportar JSON.');
        $data = $request->validate([
            'formula_ids' => ['required', 'array', 'min:1', 'max:100'],
            'formula_ids.*' => ['required', 'integer', 'distinct'],
            'quantities' => ['required', 'array'],
            'quantities.*' => ['required', 'numeric', 'gt:0'],
        ]);
        $ids = array_map('intval', $data['formula_ids']);
        $selected = array_map('intval', array_column($request->session()->get(FormulasEstController::SESSION_KEY, []), 'id'));
        abort_if(array_diff($ids, $selected), 403);
        if (!Schema::hasColumn('activos', 'ref_interna')) {
            throw ValidationException::withMessages(['odoo' => 'Primero agrega y completa activos.ref_interna.']);
        }
        try {
            $export = DB::transaction(function () use ($request, $ids, $data, $builder) {
                // Serializa exportaciones del mismo catálogo para proteger los códigos ya exportados.
                Activo::query()->orderBy('cod_odoo')->lockForUpdate()->get(['cod_odoo']);
                $formulas = Formula::with('items')->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
                if ($formulas->count() !== count($ids)) { throw new InvalidArgumentException('Una fórmula seleccionada ya no existe.'); }
                $codes = $formulas->flatMap(fn ($f) => $f->items->pluck('cod_odoo'))->unique();
                $activos = Activo::whereIn('cod_odoo', $codes)->get();
                $references = $activos->pluck('ref_interna', 'cod_odoo')->all();
                $refs = array_values(array_filter(array_map(fn ($v) => trim((string) $v), $references)));
                $ambiguous = Activo::whereIn('ref_interna', $refs)->get()->groupBy(fn ($a) => trim((string) $a->ref_interna))->first(fn ($group) => $group->count() > 1);
                if ($ambiguous) { throw new InvalidArgumentException('Referencia interna duplicada en activos: '.$ambiguous->first()->ref_interna); }
                $entries = [];
                foreach ($formulas as $formula) {
                    if (!array_key_exists($formula->id, $data['quantities'])) { throw new InvalidArgumentException('Falta cantidad para '.$formula->codigo); }
                    $entry = $builder->formula($formula->toArray(), $formula->items->toArray(), $references, (float) $data['quantities'][$formula->id]);
                    $code = $entry['product']['internal_code'];
                    // Conserva la huella de la receta original, independiente del redondeo de salida.
                    $original = $builder->formula($formula->toArray(), $formula->items->toArray(), $references, (float) $data['quantities'][$formula->id], false);
                    $hash = $builder->compositionHash($original);
                    $previous = DB::table('odoo_formula_exports')->where('internal_code', $code)->first();
                    if ($previous && $previous->composition_hash !== $hash) { throw new InvalidArgumentException("{$code}: composición modificada; guarda la fórmula con un código nuevo."); }
                    $same = DB::table('odoo_formula_exports')->where('composition_hash', $hash)->first();
                    if ($same && $same->internal_code !== $code) { throw new InvalidArgumentException("{$code}: composición ya exportada como {$same->internal_code}."); }
                    if (!$previous) {
                        DB::table('odoo_formula_exports')->insert(['internal_code' => $code, 'composition_hash' => $hash, 'created_at' => now(), 'updated_at' => now()]);
                    }
                    if (isset($entries[$code])) { throw new InvalidArgumentException("Código de fórmula duplicado: {$code}."); }
                    $entries[$code] = $entry;
                }
                $entries = array_values($entries);
                $hash = hash('sha256', 'single-v1-batch-v2-ext-price:'.json_encode($entries, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
                $userId = $request->user()->getAuthIdentifier();
                $saved = DB::table('odoo_json_exports')->where('user_id', $userId)->where('content_hash', $hash)->first();
                if ($saved) { return $saved; }
                $reference = (string) Str::uuid();
                $payload = $builder->document($entries, $reference, now('America/Guayaquil')->format('Y-m-d\TH:i:sP'));
                $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
                DB::table('odoo_json_exports')->insert(['user_id' => $userId, 'content_hash' => $hash, 'reference' => $reference, 'payload' => $json, 'created_at' => now(), 'updated_at' => now()]);
                return (object) ['reference' => $reference, 'payload' => $json];
            });
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['odoo' => $e->getMessage()]);
        }
        $document = json_decode($export->payload, true, 512, JSON_THROW_ON_ERROR);
        $formulas = $document['formulas'] ?? [$document];
        if (count($formulas) === 1) {
            $name = Str::slug((string) ($formulas[0]['product']['name'] ?? ''), '_');
            $filename = substr($name ?: 'formula', 0, 100).'.json';
        } else {
            $filename = 'exp_'.count($formulas).'_odoo.json';
        }
        return response($export->payload, 200, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
