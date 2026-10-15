<?php

namespace App\Http\Controllers;

use App\Models\Activo;
use Illuminate\Http\Request;

class ActivoController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q'));

        $activos = Activo::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where('nombre', 'like', "%{$q}%")
                      ->orWhere('cod_odoo', 'like', "%{$q}%");
            })
            ->orderBy('cod_odoo')
            ->paginate(25)
            ->withQueryString();

        return view('activos.index', compact('activos', 'q'));
    }

    public function edit(Activo $activo)
    {
        return view('activos.edit', compact('activo'));
    }

    public function update(Request $request, Activo $activo)
    {
        $data = $request->validate([
            'nombre'       => ['required','string','max:255'],
            'valor_costo'  => ['nullable','numeric'],
            'factor'       => ['nullable','numeric'],
            'minimo'       => ['nullable','string'], // <- string
            'maximo'       => ['nullable','string'], // <- string
            'unidad'       => ['required','string','max:20'],
            'factor_venta' => ['nullable','numeric'],
            'densidad'     => ['nullable','numeric'],
        ]);

        unset($data['cod_odoo']);

        $activo->update($data);


        return redirect()
            ->route('activos.index')
            ->with('ok', 'Activo actualizado correctamente.');
    }
}
