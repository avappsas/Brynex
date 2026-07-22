<?php

namespace App\Services\Ia;

/**
 * Mapa curado de páginas del sistema (solo rutas sin parámetros obligatorios,
 * para que la IA pueda indicar "dónde" y ofrecer un botón "Abrir" seguro).
 * Cada entrada requiere que el usuario tenga acceso a la ruta (se valida al navegar).
 */
class CatalogoModulos
{
    public static function todos(): array
    {
        return [
            ['ruta' => 'admin.facturacion.index',        'nombre' => 'Facturación',            'descripcion' => 'Facturar cobros a clientes/empresas, ver saldos y recibos.'],
            ['ruta' => 'admin.facturacion.anuladas',      'nombre' => 'Facturas anuladas',       'descripcion' => 'Historial de facturas/cobros anulados y su restauración.'],
            ['ruta' => 'admin.cotizaciones.index',        'nombre' => 'Cotizaciones',            'descripcion' => 'Prospectos y cotizaciones de planes antes de convertirse en clientes.'],
            ['ruta' => 'admin.prestamos.index',           'nombre' => 'Préstamos / Cartera',     'descripcion' => 'Préstamos a clientes, abonos, condonaciones y gestión de cobro.'],
            ['ruta' => 'admin.caja-menor.index',          'nombre' => 'Caja menor',              'descripcion' => 'Registro de gastos menores de caja.'],
            ['ruta' => 'admin.cuadre-diario.index',       'nombre' => 'Cuadre diario',           'descripcion' => 'Apertura y cierre de caja del día, consignaciones y bancos.'],
            ['ruta' => 'admin.gestion-arl.index',         'nombre' => 'Gestión ARL',             'descripcion' => 'Renovación y seguimiento de ARL de contratos.'],
            ['ruta' => 'admin.configuracion.index',       'nombre' => 'Configuración de parámetros', 'descripcion' => 'Precios/porcentajes del aliado: administración, ARL, seguros, moras.'],
            ['ruta' => 'admin.whatsapp.index',            'nombre' => 'WhatsApp — Conversaciones', 'descripcion' => 'Chat en vivo de WhatsApp con clientes.'],
            ['ruta' => 'brynex.consumo.index',            'nombre' => 'Consumo & Cobros Brynex', 'descripcion' => 'Consumo mensual y cobros de Brynex hacia el aliado.'],
            ['ruta' => 'brynex.hub',                      'nombre' => 'Panel BryNex',            'descripcion' => 'Hub central de administración de aliados (solo usuarios BryNex).'],
        ];
    }

    /** Busca por coincidencia simple de texto en nombre/descripción. */
    public static function buscar(string $consulta): array
    {
        $consulta = mb_strtolower(trim($consulta));
        if ($consulta === '') {
            return self::todos();
        }

        return array_values(array_filter(self::todos(), function ($item) use ($consulta) {
            $texto = mb_strtolower($item['nombre'] . ' ' . $item['descripcion']);
            return str_contains($texto, $consulta);
        }));
    }
}
