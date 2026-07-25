<?php

namespace App\Services\Publicidad;

use App\Models\Aliado;
use App\Models\AutopilotConfig;
use App\Models\IaConfiguracionAliado;
use App\Models\Publicacion;
use App\Models\RedSocialConfig;
use App\Models\WhatsappConfig;
use Illuminate\Support\Facades\Storage;

/**
 * Genera el flyer promocional de UN plan: elige el plan que lleva más tiempo sin promocionarse,
 * pide a Gemini una foto acorde a ese plan (cada plan tiene su propia escena) y compone el
 * flyer con FlyerPlanBuilder — el precio se toma del cotizador real, nunca lo escribe la IA.
 *
 * Es la pieza "promocional" que se publica un par de veces por semana, distinta del post
 * educativo/diario que arma AutopilotGenerator.
 */
class FlyerPlanGenerator
{
    /** Prefijo del campo `tema` para poder rastrear qué plan se promocionó y cuándo. */
    public const PREFIJO_TEMA = 'flyer:';

    /**
     * @return array{ok: bool, publicacion: ?Publicacion, error: ?string}
     */
    public static function generar(Aliado $aliado, AutopilotConfig $config, ?string $claveForzada = null, ?int $nivelArl = null): array
    {
        $iaConfig = IaConfiguracionAliado::paraAliado($aliado->id);
        if (!$iaConfig->tieneGemini()) {
            return self::error('No hay clave de Gemini configurada para generar la imagen (ver Asistente Virtual).');
        }

        $clave = $claveForzada ?: self::elegirPlan($aliado->id);
        $def   = CatalogoPlanesPromocion::obtener($clave);
        if (!$def) {
            return self::error("El plan \"{$clave}\" no está en el catálogo de promoción.");
        }

        $nivelArl = $nivelArl ?: CatalogoPlanesPromocion::NIVEL_ARL_PROMOCIONAL;

        $precio = CatalogoPlanesPromocion::cotizar($clave, $aliado->id, $nivelArl);
        if (!$precio) {
            return self::error("No se pudo cotizar el plan \"{$def['nombre']}\" para este aliado.");
        }

        // En los planes donde la ARL es todo el plan, la escena la manda el nivel de riesgo:
        // el precio anunciado y lo que se ve en la foto tienen que corresponder.
        $escena = !empty($def['escena_por_riesgo'])
            ? \App\Services\NivelesRiesgoArl::escena($nivelArl)
            : $def['escena'];

        $imagen = GeminiImagenGenerator::generarVariantes(
            $iaConfig->gemini_api_key,
            self::promptFoto($escena),
            1,
            GeminiImagenGenerator::MODELO_FOTORREALISTA,
            null,
            '4:5'
        );
        if (!$imagen['ok'] || empty($imagen['rutas'])) {
            return self::error('Imagen: ' . ($imagen['error'] ?? 'Gemini no devolvió imagen.'));
        }

        $flyer = FlyerPlanBuilder::construir($imagen['rutas'][0], $aliado, array_merge($def, $precio, [
            'whatsapp' => self::numeroWhatsapp($aliado),
        ]));

        if (!$flyer) {
            return self::error('No se pudo componer el flyer con la imagen generada.');
        }

        // La foto suelta ya no se necesita: lo que se publica es el flyer compuesto.
        Storage::disk('public')->delete($imagen['rutas'][0]);

        $destinos = array_merge(
            ['web'],
            RedSocialConfig::where('aliado_id', $aliado->id)->where('activo', true)->pluck('red')->all()
        );

        $esAuto = $config->modo === AutopilotConfig::MODO_AUTO;

        $publicacion = Publicacion::create([
            'aliado_id'     => $aliado->id,
            'titulo'        => "Plan {$def['nombre']} — {$def['gancho']}",
            'copy'          => self::copy($def, $precio),
            'imagen_path'   => $flyer,
            'origen'        => 'ia_auto',
            'tema'          => self::PREFIJO_TEMA . $clave,
            'estilo_imagen' => AutopilotConfig::ESTILO_FOTORREALISTA,
            'destinos'      => $destinos,
            'estado'        => $esAuto ? Publicacion::ESTADO_APROBADA : Publicacion::ESTADO_PENDIENTE,
            'creado_por'    => null,
        ]);

        if ($esAuto) {
            PublicacionPublisher::publicar($publicacion);
        }

        return ['ok' => true, 'publicacion' => $publicacion, 'error' => null];
    }

    /**
     * Plan a promocionar: el que lleva más tiempo sin salir. Así se recorre todo el catálogo
     * antes de repetir ninguno, sin depender de que la IA "recuerde" cuál ya usó.
     */
    public static function elegirPlan(int $aliadoId): string
    {
        $claves = array_keys(CatalogoPlanesPromocion::todos());

        $ultimoUso = Publicacion::where('aliado_id', $aliadoId)
            ->where('tema', 'like', self::PREFIJO_TEMA . '%')
            ->orderByDesc('created_at')
            ->pluck('created_at', 'tema');

        // Los que nunca se han usado van primero; entre los usados, el más antiguo.
        usort($claves, function ($a, $b) use ($ultimoUso) {
            $fa = $ultimoUso[self::PREFIJO_TEMA . $a] ?? null;
            $fb = $ultimoUso[self::PREFIJO_TEMA . $b] ?? null;
            if ($fa === null && $fb === null) return 0;
            if ($fa === null) return -1;
            if ($fb === null) return 1;
            return $fa <=> $fb;
        });

        return $claves[0];
    }

    /** Copy del post. El precio sale del cotizador, no de la IA — aquí no se puede improvisar. */
    private static function copy(array $def, array $precio): string
    {
        $mensual = '$' . number_format($precio['valor_mensual'], 0, ',', '.');
        $servicios = implode(', ', $def['servicios']);

        $lineas = ["✅ Plan {$def['nombre']}: {$def['gancho']}.", "Incluye {$servicios}."];

        if ($precio['costo_afiliacion'] > 0) {
            $afiliacion = '$' . number_format($precio['costo_afiliacion'], 0, ',', '.');
            $lineas[] = "Este mes te afilias por {$afiliacion} y desde el mes siguiente pagas {$mensual} al mes.";
        } else {
            $lineas[] = "Desde {$mensual} al mes.";
        }

        $lineas[] = 'Escríbenos por WhatsApp y te afiliamos hoy mismo. 📲';

        return implode("\n", $lineas);
    }

    /**
     * Prompt de la foto. Se pide explícitamente lenguaje de fotografía real (cámara, lente,
     * apertura, piel sin retocar) y se prohíbe el look "render/IA": sin eso los modelos
     * devuelven caras plásticas y colores sobresaturados que se notan al instante.
     */
    private static function promptFoto(string $escena): string
    {
        return "Fotografía documental real, sin pose, de {$escena}. "
            . 'Formato VERTICAL 4:5. Capturada con cámara full frame y lente 85mm a f/1.8: fondo desenfocado natural, '
            . 'plano cercano (retrato o medio cuerpo). Luz natural cálida de media tarde, con sombras suaves reales. '
            . 'Piel con textura auténtica: poros, líneas de expresión, brillo natural — NADA de piel alisada ni '
            . 'retoque de belleza. Ropa con arrugas y uso real, entorno con desorden natural del día a día. '
            . 'Personas colombianas de aspecto corriente y diverso, expresión espontánea y genuina, no sonrisa forzada. '
            . 'Colores naturales y contenidos, como una foto sin filtros — NO sobresaturar. '
            . 'EVITA por completo el aspecto de render 3D, ilustración digital, imagen generada por IA, foto de banco '
            . 'de imágenes, iluminación de estudio o composición simétrica perfecta. '
            . 'Una sola toma que llene todo el encuadre, con las personas ocupando el centro: NO dividas la imagen en '
            . 'partes, ni dejes zonas vacías, ni hagas collage. '
            . 'NO escribas ningún texto, letras, números ni logotipos dentro de la imagen: todo el texto se agrega '
            . 'después por separado.';
    }

    private static function numeroWhatsapp(Aliado $aliado): ?string
    {
        $numero = WhatsappConfig::where('aliado_id', $aliado->id)->where('activo', true)->value('numero_telefono')
            ?: $aliado->whatsapp
            ?: $aliado->celular;

        return $numero ? self::formatearNumero($numero) : null;
    }

    /** 573205400870 -> 320 540 0870 (más legible en el flyer que el formato E.164). */
    private static function formatearNumero(string $numero): string
    {
        $limpio = preg_replace('/\D/', '', $numero);
        if (str_starts_with($limpio, '57') && strlen($limpio) === 12) {
            $limpio = substr($limpio, 2);
        }
        if (strlen($limpio) === 10) {
            return substr($limpio, 0, 3) . ' ' . substr($limpio, 3, 3) . ' ' . substr($limpio, 6);
        }
        return $numero;
    }

    private static function error(string $mensaje): array
    {
        return ['ok' => false, 'publicacion' => null, 'error' => $mensaje];
    }
}
