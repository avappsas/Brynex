<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Informe por aliado de lo que `clientes:completar-ruaf` NO tocó porque no le
 * corresponde decidirlo: los datos donde Brynex y el registro oficial no
 * coinciden.
 *
 * Un archivo Excel por aliado, con una hoja por tipo de caso, para enviárselo
 * y que ellos corrijan lo que aplique.
 *
 *   php artisan clientes:informe-ruaf
 *   php artisan clientes:informe-ruaf --aliado=6
 *
 * Los archivos quedan en storage/app/informes-ruaf/ (disco local, NUNCA
 * public: llevan cédulas y nombres — ver docs/auditoria-seguridad.md, C-4).
 */
class InformeRuafClientes extends Command
{
    protected $signature = 'clientes:informe-ruaf
        {--aliado=  : Generar solo el de este aliado (id)}';

    protected $description = 'Genera el Excel por aliado con las diferencias entre Brynex y el registro oficial';

    /**
     * Cada hoja: título, filtro sobre ruaf_consultas y qué significa.
     * El orden es el de prioridad para el aliado.
     */
    private function hojas(): array
    {
        return [
            'EPS distinta' => [
                'filtro' => fn ($q) => $q->where('accion_eps', 'difiere'),
                'nota' => 'La EPS registrada en Brynex no es la que reporta el registro oficial. No se modificó nada.',
                'cols' => ['eps'],
            ],
            'Pensión distinta' => [
                'filtro' => fn ($q) => $q->where('accion_pension', 'difiere'),
                'nota' => 'El fondo de pensión de Brynex no es el que reporta el registro oficial. No se modificó nada.',
                'cols' => ['pension'],
            ],
            'Nombre distinto' => [
                'filtro' => fn ($q) => $q->where('accion_nombre', 'difiere'),
                'nota' => 'El nombre difiere del registro oficial. Los campos que ya tenían dato NO se sobrescribieron.',
                'cols' => ['nombre'],
            ],
            'Nombre desalineado' => [
                'filtro' => fn ($q) => $q->where('accion_nombre', 'desalineado'),
                'nota' => 'Un apellido está en la casilla equivocada (p. ej. el segundo apellido guardado como primero). Hay que reordenarlos a mano.',
                'cols' => ['nombre'],
            ],
            'Revisar tipo de documento' => [
                'filtro' => fn ($q) => $q->where('identidad_dudosa', 1),
                'nota' => 'El registro devolvió una persona distinta para ese número. Suele ser el tipo de documento equivocado (el mismo número puede ser CC de una persona y CE de otra). NO se escribió ningún dato.',
                'cols' => ['nombre'],
            ],
            'No están en el registro' => [
                'filtro' => fn ($q) => $q->where('estado', 'no_hallado'),
                'nota' => 'El registro oficial no tiene a esta persona con ese tipo y número de documento. Verificar los datos del documento.',
                'cols' => [],
            ],
        ];
    }

    public function handle(): int
    {
        $carpeta = storage_path('app/informes-ruaf');

        if (! is_dir($carpeta)) {
            mkdir($carpeta, 0755, true);
        }

        $aliados = DB::table('aliados')
            ->when($this->option('aliado'), fn ($q, $id) => $q->where('id', (int) $id))
            ->orderBy('id')
            ->get(['id', 'nombre']);

        $generados = 0;

        foreach ($aliados as $aliado) {
            $ruta = $this->generarPara($aliado, $carpeta);

            if ($ruta) {
                $generados++;
                $this->line('  ✓ '.$aliado->nombre.' → '.basename($ruta));
            }
        }

        $this->line('');

        if (! $generados) {
            $this->info('No hay diferencias que informar todavía. ¿Ya corrió clientes:completar-ruaf?');

            return self::SUCCESS;
        }

        $this->info("$generados informes en $carpeta");

        return self::SUCCESS;
    }

    private function generarPara(object $aliado, string $carpeta): ?string
    {
        $hojas = $this->hojas();
        $datos = [];

        foreach ($hojas as $titulo => $def) {
            $q = DB::table('ruaf_consultas as r')
                ->join('clientes as c', 'c.id', '=', 'r.cliente_id')
                ->where('r.aliado_id', $aliado->id);

            $def['filtro']($q);

            $filas = $q->orderBy('c.primer_apellido')
                ->get([
                    'r.tipo_doc', 'r.cedula', 'r.nombre_antes', 'r.nombre_ruaf',
                    'r.eps_id_antes', 'r.eps_id_ruaf', 'r.pension_id_antes', 'r.pension_id_ruaf',
                    'r.similitud_nombre', 'c.celular',
                ]);

            if ($filas->isNotEmpty()) {
                $datos[$titulo] = [$def, $filas];
            }
        }

        if (! $datos) {
            return null;
        }

        $epsNombre = DB::table('eps')->pluck('nombre', 'id');
        $penNombre = DB::table('pensiones')->pluck('razon_social', 'id');

        $libro = new Spreadsheet;
        $libro->removeSheetByIndex(0);

        foreach ($datos as $titulo => [$def, $filas]) {
            // Excel limita el nombre de hoja a 31 caracteres.
            $hoja = $libro->createSheet();
            $hoja->setTitle(mb_substr($titulo, 0, 31));

            $hoja->setCellValue('A1', $titulo.' — '.$aliado->nombre);
            $hoja->mergeCells('A1:H1');
            $hoja->getStyle('A1')->getFont()->setBold(true)->setSize(13);

            $hoja->setCellValue('A2', $def['nota']);
            $hoja->mergeCells('A2:H2');
            $hoja->getStyle('A2')->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
            $hoja->getRowDimension(2)->setRowHeight(30);

            $cabeceras = ['Tipo doc.', 'Documento', 'Nombre en Brynex', 'Celular'];

            if (in_array('eps', $def['cols'], true)) {
                $cabeceras = array_merge($cabeceras, ['EPS en Brynex', 'EPS según el registro']);
            }
            if (in_array('pension', $def['cols'], true)) {
                $cabeceras = array_merge($cabeceras, ['Pensión en Brynex', 'Pensión según el registro']);
            }
            if (in_array('nombre', $def['cols'], true)) {
                $cabeceras = array_merge($cabeceras, ['Nombre según el registro', 'Parecido']);
            }

            $col = 'A';

            foreach ($cabeceras as $texto) {
                $hoja->setCellValue($col.'4', $texto);
                $col++;
            }

            $ultima = chr(ord('A') + count($cabeceras) - 1);
            $hoja->getStyle("A4:{$ultima}4")->getFont()->setBold(true);
            $hoja->getStyle("A4:{$ultima}4")->getFill()
                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DCE6F1');

            $f = 5;

            foreach ($filas as $r) {
                $valores = [
                    $r->tipo_doc ?: 'CC',
                    (string) $r->cedula,
                    $r->nombre_antes,
                    $r->celular ? (string) $r->celular : '',
                ];

                if (in_array('eps', $def['cols'], true)) {
                    $valores[] = $epsNombre[$r->eps_id_antes] ?? '(vacía)';
                    $valores[] = $epsNombre[$r->eps_id_ruaf] ?? '(sin dato)';
                }
                if (in_array('pension', $def['cols'], true)) {
                    $valores[] = $penNombre[$r->pension_id_antes] ?? '(vacía)';
                    $valores[] = $penNombre[$r->pension_id_ruaf] ?? '(sin dato)';
                }
                if (in_array('nombre', $def['cols'], true)) {
                    $valores[] = $r->nombre_ruaf;
                    $valores[] = $r->similitud_nombre !== null ? $r->similitud_nombre.'%' : '';
                }

                $col = 'A';

                foreach ($valores as $v) {
                    // Como texto: las cédulas largas se vuelven notación
                    // científica si Excel las interpreta como número.
                    $hoja->setCellValueExplicit($col.$f, (string) $v,
                        \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $col++;
                }

                $f++;
            }

            foreach (range('A', $ultima) as $c) {
                $hoja->getColumnDimension($c)->setAutoSize(true);
            }

            $hoja->freezePane('A5');
        }

        $limpio = preg_replace('/[^A-Za-z0-9]+/', '_', $aliado->nombre);
        $ruta = $carpeta.'/informe_ruaf_'.$aliado->id.'_'.$limpio.'.xlsx';

        (new Xlsx($libro))->save($ruta);
        $libro->disconnectWorksheets();

        return $ruta;
    }
}
