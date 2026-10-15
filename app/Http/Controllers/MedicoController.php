<?php

namespace App\Http\Controllers;

use App\Models\Medico;
use Illuminate\Http\Request;

class MedicoController extends Controller
{
    public function buscar(Request $request)
    {
        $q = trim($request->query('q', ''));
        if ($q === '') {
            return response()->json([]);
        }

        // Tokenize the query by whitespace so "Moscoso Luis" or "Luis Moscoso"
        // finds matches where each token appears anywhere in the name or cedula.
        $tokens = preg_split('/\s+/', $q, -1, PREG_SPLIT_NO_EMPTY);

        $query = Medico::query()
            ->select('full_name AS label', 'cedula', 'firma');

        // For each token, require it to appear either in full_name OR cedula (AND between tokens)
        foreach ($tokens as $token) {
            $tokenEsc = str_replace(['%','_'], ['\\%','\\_'], $token); // escape LIKE wildcards
            $query->where(function ($sub) use ($tokenEsc) {
                $sub->where('full_name', 'like', "%{$tokenEsc}%")
                    ->orWhere('cedula', 'like', "%{$tokenEsc}%");
            });
        }

        $rows = $query->orderBy('full_name')->limit(10)->get();

        // $rows ya es un array de objetos {label, cedula}
        return response()->json($rows);
    }
}
