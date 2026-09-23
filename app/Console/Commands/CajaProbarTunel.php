<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * ¿El túnel de la oficina llega al portal de Comfenalco?
 *
 * Dos preguntas distintas: si el PC de la oficina tiene el puerto abierto en el
 * servidor, y si por ese puerto se llega de verdad al portal. Lo segundo se
 * comprueba con el certificado: si el TLS lo firma Comfenalco, el túnel va a
 * donde debe.
 *
 * No prueba que el WAF nos deje pasar —eso solo lo dice un Chrome de verdad,
 * `caja:revisar-subsidios --caja=COMFENALCO`—, pero descarta lo de más abajo.
 */
class CajaProbarTunel extends Command
{
    protected $signature = 'caja:probar-tunel';

    protected $description = 'Comprueba que el túnel de la oficina llegue a los portales de Comfenalco';

    public function handle(): int
    {
        $destinos = [
            'virtual.comfenalcovalle.com.co' => config('services.comfenalco.tunel'),
            'authcomfeempresasprod.web.app' => config('services.comfenalco.tunel_auth'),
        ];

        $todoBien = true;

        foreach ($destinos as $host => $destino) {
            if (! $destino) {
                $this->warn("{$host}: sin túnel configurado en el .env.");
                $todoBien = false;

                continue;
            }

            $r = $this->probar($host, $destino);
            $r['ok'] ? $this->info("✅ {$host} por {$destino}: {$r['detalle']}")
                     : $this->error("❌ {$host} por {$destino}: {$r['detalle']}");

            $todoBien = $todoBien && $r['ok'];
        }

        if (! $todoBien) {
            $this->line('');
            $this->line('Si el puerto no responde, el PC de la oficina no tiene el reenvío:');
            $this->line('ver scripts/tunel-portales/ampliar-comfenalco-pc.ps1');
        }

        return $todoBien ? self::SUCCESS : self::FAILURE;
    }

    /** @return array{ok:bool, detalle:string} */
    private function probar(string $host, string $destino): array
    {
        [$ip, $puerto] = array_pad(explode(':', $destino, 2), 2, null);

        // El nombre va en SNI para que el portal presente su certificado; sin
        // eso la comprobación no distinguiría a dónde llega el túnel.
        $contexto = stream_context_create(['ssl' => [
            'peer_name' => $host,
            'capture_peer_cert' => true,
            'verify_peer' => true,
            'verify_peer_name' => true,
        ]]);

        $socket = @stream_socket_client(
            "ssl://{$ip}:{$puerto}", $err, $msg, 8, STREAM_CLIENT_CONNECT, $contexto
        );

        if (! $socket) {
            return ['ok' => false, 'detalle' => $msg ?: 'no respondió (¿el PC está apagado?)'];
        }

        $params = stream_context_get_params($socket);
        $cert = $params['options']['ssl']['peer_certificate'] ?? null;
        $sujeto = $cert ? (openssl_x509_parse($cert)['subject']['CN'] ?? '?') : '?';

        fwrite($socket, "HEAD / HTTP/1.1\r\nHost: {$host}\r\nConnection: close\r\n\r\n");
        $respuesta = trim((string) fgets($socket, 128));
        fclose($socket);

        return ['ok' => true, 'detalle' => "certificado de {$sujeto} · {$respuesta}"];
    }
}
