<?php

namespace App\Console\Commands;

use App\Models\Aliado;
use App\Models\RedSocialConfig;
use App\Services\RedesSociales\RedesFactory;
use Illuminate\Console\Command;

/**
 * Publica una imagen de prueba en la red social configurada de un aliado, para verificar
 * que las credenciales guardadas realmente funcionan de punta a punta.
 *
 * Uso: php artisan redes:test-publicar brygar facebook
 *      php artisan redes:test-publicar brygar instagram --url=https://... --texto="Prueba"
 */
class ProbarPublicacionRedSocial extends Command
{
    protected $signature = 'redes:test-publicar {aliado} {red} {--url=} {--texto=Publicación de prueba desde Brynex.}';

    protected $description = 'Publica una imagen de prueba en Facebook/Instagram con las credenciales configuradas del aliado';

    public function handle(): int
    {
        $identificadorAliado = $this->argument('aliado');
        $red = $this->argument('red');

        if (!in_array($red, RedSocialConfig::REDES_DISPONIBLES, true)) {
            $this->error("Red '{$red}' no soportada. Disponibles: " . implode(', ', RedSocialConfig::REDES_DISPONIBLES));
            return self::FAILURE;
        }

        $aliado = is_numeric($identificadorAliado)
            ? Aliado::find($identificadorAliado)
            : Aliado::where('slug', $identificadorAliado)->first();

        if (!$aliado) {
            $this->error("Aliado '{$identificadorAliado}' no encontrado (usa el ID o el slug).");
            return self::FAILURE;
        }

        $config = RedSocialConfig::paraAliado($aliado->id, $red);

        if (!$config->credencialesCompletas()) {
            $this->error("El aliado {$aliado->nombre} no tiene credenciales completas para {$red}. Configúralas en admin/redes-sociales primero.");
            return self::FAILURE;
        }

        $url = $this->option('url') ?: ($aliado->logo ? asset('storage/' . $aliado->logo) : null);
        if (!$url) {
            $this->error('No hay --url y el aliado no tiene logo para usar como imagen de prueba.');
            return self::FAILURE;
        }

        $this->info("Publicando en {$red} para {$aliado->nombre} usando: {$url}");

        $resultado = RedesFactory::make($config)->publicarImagen($url, $this->option('texto'));

        if ($resultado['ok']) {
            $this->info('✅ ' . $resultado['mensaje'] . ' (id: ' . ($resultado['id_publicacion'] ?? '—') . ')');
            return self::SUCCESS;
        }

        $this->error('❌ ' . $resultado['mensaje']);
        return self::FAILURE;
    }
}
