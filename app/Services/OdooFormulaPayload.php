<?php

namespace App\Services;

use InvalidArgumentException;

class OdooFormulaPayload
{
    public function formula(array $formula, array $items, array $references, float $quantity, bool $roundComponents = true): array
    {
        $code = trim((string) ($formula['codigo'] ?? ''));
        if ($code === '') { throw new InvalidArgumentException('La fÃ³rmula no tiene cÃ³digo interno.'); }
        $this->positive($quantity, "Cantidad solicitada de {$code}");
        $components = [];
        foreach ($items as $item) {
            $label = $item['activo'] ?? $item['cod_odoo'];
            $ref = trim((string) ($references[$item['cod_odoo']] ?? ''));
            if ($ref === '') { throw new InvalidArgumentException("{$code}: falta ref_interna para {$label}."); }
            $unit = strtolower(trim((string) ($item['unidad'] ?? '')));
            if (in_array($unit, ['mg', 'mcg', 'ui', 'g'], true)) {
                // masa_mes ya contiene gramos para una unidad terminada.
                $amount = $item['masa_mes'] ?? null;
                $unit = 'G';
            } elseif (in_array($unit, ['un', 'und', 'unidad', 'unidades'], true)) {
                $amount = $item['cantidad'] ?? null;
                $unit = 'UN';
            } else {
                throw new InvalidArgumentException("{$code}: unidad no compatible en {$label}.");
            }
            if (!is_numeric($amount)) { throw new InvalidArgumentException("{$code}: falta cantidad para {$label}."); }
            $this->positive((float) $amount, "{$code}: cantidad de {$label}");
            $amount = $roundComponents ? round((float) $amount, 2, PHP_ROUND_HALF_UP) : (float) $amount;
            $this->positive($amount, "{$code}: cantidad exportada de {$label} (dos decimales)");
            $key = $ref;
            if (isset($components[$key])) { throw new InvalidArgumentException("{$code}: componente repetido {$ref}."); }
            $components[$key] = ['component_code' => $ref, 'quantity' => (float) $amount, 'uom_code' => $unit];
        }
        if (!$components) { throw new InvalidArgumentException("{$code}: la receta no tiene componentes."); }
        ksort($components, SORT_STRING);
        $prices = [];
        foreach (['precio_medico' => 'PVMEDICO', 'precio_distribuidor' => 'PVDISTRIBUIDOR'] as $field => $list) {
            $prices[] = ['pricelist_code' => $list, 'fixed_price' => $this->price($formula[$field] ?? null, "{$code}: {$field}")];
        }
        return [
            'external_formula_id' => 'FORM-'.hash('sha256', $code),
            'product' => [
                'internal_code' => $code,
                'name' => trim((string) ($formula['nombre_etiqueta'] ?? '')) ?: 'FORMULA ORTOMED '.$code,
                'category_code' => 'ORTOMED', 'sale_enabled' => true, 'purchase_enabled' => false,
                'uom_code' => 'UN', 'sale_price' => $this->price($formula['precio_publico'] ?? null, "{$code}: precio pÃºblico"),
                'tax_code' => 'IVA_0', 'routes' => ['mto' => true, 'manufacture' => true],
            ],
            'prices' => $prices,
            'bom' => ['type' => 'normal', 'quantity' => 1.0, 'uom_code' => 'UN', 'components' => array_values($components)],
            'sale_line' => ['quantity' => $quantity],
        ];
    }

    public function document(array $entries, string $reference, string $generatedAt): array
    {
        if (!$entries) { throw new InvalidArgumentException('No hay fórmulas para exportar.'); }
        $formulas = [];
        foreach ($entries as $entry) {
            $externalId = 'Ext_'.$entry['product']['internal_code'];
            $entry['product'] = ['external_formula_id' => $externalId] + $entry['product'];
            unset($entry['external_formula_id']);
            $entry['sale_line']['unit_price'] = round($entry['product']['sale_price'], 2, PHP_ROUND_HALF_UP);
            $formulas[] = $entry;
        }
        $single = count($formulas) === 1;
        $source = [
            'system' => 'COTIZADOR_ORTOMED',
            'external_reference' => $single ? $formulas[0]['product']['external_formula_id'] : 'COT-'.$reference,
            'generated_at' => $generatedAt,
        ];
        if ($single) { return ['schema_version' => '1.0', 'source' => $source] + $formulas[0]; }
        return ['schema_version' => '2.0', 'source' => $source, 'formulas' => $formulas];
    }

    public function compositionHash(array $formula): string
    {
        return hash('sha256', json_encode($formula['bom'], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
    }

    private function positive(float $value, string $label): void
    {
        if (!is_finite($value) || $value <= 0) { throw new InvalidArgumentException("{$label} debe ser mayor que cero."); }
    }

    private function price(mixed $value, string $label): float
    {
        if (!is_numeric($value) || !is_finite((float) $value) || (float) $value < 0) {
            throw new InvalidArgumentException("{$label} debe ser un precio vÃ¡lido mayor o igual a cero.");
        }
        return (float) $value;
    }
}
