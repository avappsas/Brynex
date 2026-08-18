<?php

namespace Tests\Unit\Services;

use App\Services\MoraClienteService;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * Pruebas de festivosColombia() y getNthDiaHabil(): ambas son puras (sin BD),
 * de las que depende diaHabilVencimiento() para decidir cuándo empieza a
 * correr la mora de TODOS los clientes de un aliado cada mes.
 *
 * Se prueban invariantes de la Ley 51 de 1983 (Ley Emiliani) en vez de fechas
 * fijas memorizadas: así el test sigue siendo válido para cualquier año y
 * detecta un error real si alguien cambia el offset de días de un festivo
 * móvil, no solo si cambia un año concreto.
 */
class MoraClienteServiceTest extends TestCase
{
    public function test_festivos_fijos_estan_presentes(): void
    {
        $festivos = MoraClienteService::festivosColombia(2026);

        $this->assertContains('2026-01-01', $festivos, 'Año Nuevo');
        $this->assertContains('2026-05-01', $festivos, 'Día del Trabajo');
        $this->assertContains('2026-07-20', $festivos, 'Grito de Independencia');
        $this->assertContains('2026-08-07', $festivos, 'Batalla de Boyacá');
        $this->assertContains('2026-12-08', $festivos, 'Inmaculada Concepción');
        $this->assertContains('2026-12-25', $festivos, 'Navidad');
    }

    public function test_festivos_emiliani_siempre_caen_en_lunes(): void
    {
        // Ley Emiliani: estos 7 festivos se trasladan al lunes siguiente si no
        // caen en lunes. Probar varios años (incluye años donde la fecha base
        // ya es lunes, para cubrir ambas ramas del if en festivosColombia()).
        foreach ([2024, 2025, 2026, 2027, 2028] as $anio) {
            $festivos = MoraClienteService::festivosColombia($anio);

            $basesEmiliani = ["{$anio}-01-06", "{$anio}-03-19", "{$anio}-06-29",
                "{$anio}-08-15", "{$anio}-10-12", "{$anio}-11-01", "{$anio}-11-11"];

            foreach ($basesEmiliani as $base) {
                $fechaBase = Carbon::createFromFormat('Y-m-d', $base)->startOfDay();
                $fechaObservada = $fechaBase->dayOfWeek === Carbon::MONDAY
                    ? $fechaBase
                    : $fechaBase->copy()->next(Carbon::MONDAY);

                $this->assertContains(
                    $fechaObservada->format('Y-m-d'),
                    $festivos,
                    "Festivo Emiliani basado en {$base} ({$anio}) debe caer en lunes"
                );
            }
        }
    }

    public function test_festivos_moviles_de_pascua_caen_en_los_dias_correctos(): void
    {
        // Pascua 2026 = 5 de abril (verificado con easter_days(2026) = 15,
        // 21-mar + 15 = 5-abr).
        $festivos = MoraClienteService::festivosColombia(2026);

        $this->assertContains('2026-04-02', $festivos, 'Jueves Santo (Pascua - 3 días)');
        $this->assertContains('2026-04-03', $festivos, 'Viernes Santo (Pascua - 2 días)');
        $this->assertContains('2026-05-18', $festivos, 'Ascensión del Señor (Pascua + 43 días)');
        $this->assertContains('2026-06-08', $festivos, 'Corpus Christi (Pascua + 64 días)');
        $this->assertContains('2026-06-15', $festivos, 'Sagrado Corazón (Pascua + 71 días)');
    }

    public function test_festivos_moviles_ascension_corpus_sagrado_corazon_siempre_en_lunes(): void
    {
        // Los tres festivos móviles "trasladados" deben caer en lunes en
        // cualquier año, no solo en 2026 (Pascua siempre cae en domingo, y los
        // offsets +43/+64/+71 están calculados para aterrizar en lunes).
        foreach ([2024, 2025, 2026, 2027, 2028] as $anio) {
            $diasDesdeMarzo21 = easter_days($anio);
            $pascua = Carbon::create($anio, 3, 21)->startOfDay()->addDays($diasDesdeMarzo21);
            $festivos = MoraClienteService::festivosColombia($anio);

            foreach ([43, 64, 71] as $offset) {
                $fecha = $pascua->copy()->addDays($offset);
                $this->assertSame(
                    Carbon::MONDAY,
                    $fecha->dayOfWeek,
                    "Offset +{$offset} desde Pascua debe caer en lunes en {$anio}"
                );
                $this->assertContains($fecha->format('Y-m-d'), $festivos);
            }
        }
    }

    public function test_virgen_de_chiquinquira_es_festivo_y_cae_en_lunes_desde_2026(): void
    {
        // Ley 2578, sancionada el 1-jun-2026: el 9 de julio entra al calendario
        // sujeto a la Ley Emiliani. En 2029 la fecha base ya es lunes, así que
        // cubre las dos ramas del traslado.
        foreach ([2026, 2027, 2028, 2029, 2030] as $anio) {
            $festivos = MoraClienteService::festivosColombia($anio);

            $base = Carbon::createFromFormat('Y-m-d', "{$anio}-07-09")->startOfDay();
            $observado = $base->dayOfWeek === Carbon::MONDAY
                ? $base
                : $base->copy()->next(Carbon::MONDAY);

            $this->assertSame(Carbon::MONDAY, $observado->dayOfWeek);
            $this->assertContains(
                $observado->format('Y-m-d'),
                $festivos,
                "Virgen de Chiquinquirá debe ser festivo en {$anio}"
            );
        }
    }

    public function test_virgen_de_chiquinquira_no_aplica_antes_de_2026(): void
    {
        // La ley no es retroactiva. Si se colara en años anteriores movería la
        // mora de facturas ya liquidadas. Se comprueba contra la fecha trasladada
        // exacta y no contra "julio no tiene más festivos", porque San Pedro
        // (29-jun) se traslada a julio en varios años: el 3-jul-2023, por ejemplo.
        foreach ([2023, 2024, 2025] as $anio) {
            $base = Carbon::createFromFormat('Y-m-d', "{$anio}-07-09")->startOfDay();
            $observado = $base->dayOfWeek === Carbon::MONDAY
                ? $base
                : $base->copy()->next(Carbon::MONDAY);

            $this->assertNotContains(
                $observado->format('Y-m-d'),
                MoraClienteService::festivosColombia($anio),
                "Virgen de Chiquinquirá no existía en {$anio}"
            );
        }
    }

    public function test_get_nth_dia_habil_salta_fines_de_semana_y_festivos(): void
    {
        // Enero 2026: 1-ene es festivo (jueves). El primer día hábil debe ser
        // el 2 de enero (viernes), el segundo el 5 (lunes, salta el finde).
        $primero = MoraClienteService::getNthDiaHabil(2026, 1, 1);
        $segundo = MoraClienteService::getNthDiaHabil(2026, 1, 2);

        $this->assertNotNull($primero);
        $this->assertSame('2026-01-02', $primero->format('Y-m-d'));
        $this->assertNotNull($segundo);
        $this->assertSame('2026-01-05', $segundo->format('Y-m-d'));
    }

    public function test_get_nth_dia_habil_nunca_devuelve_sabado_domingo_ni_festivo(): void
    {
        $festivos = array_flip(MoraClienteService::festivosColombia(2026));

        for ($n = 1; $n <= 20; $n++) {
            $dia = MoraClienteService::getNthDiaHabil(2026, 1, $n);
            if ($dia === null) {
                break; // se acabó el mes, válido
            }
            $this->assertNotSame(Carbon::SATURDAY, $dia->dayOfWeek);
            $this->assertNotSame(Carbon::SUNDAY, $dia->dayOfWeek);
            $this->assertArrayNotHasKey($dia->format('Y-m-d'), $festivos);
        }
    }

    public function test_get_nth_dia_habil_devuelve_null_si_n_excede_los_dias_del_mes(): void
    {
        // Ningún mes tiene 28 días hábiles.
        $this->assertNull(MoraClienteService::getNthDiaHabil(2026, 2, 28));
    }
}
