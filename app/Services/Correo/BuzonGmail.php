<?php

namespace App\Services\Correo;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Envía correos desde la cuenta de Gmail de un aliado (p. ej.
 * seguridadsocial.brygar@gmail.com) por SMTP con la contraseña de aplicación
 * guardada en el módulo de claves. No usa el MAIL_* del .env: BryNex no tiene
 * correo propio y cada aliado envía desde su cuenta.
 */
class BuzonGmail
{
    public function __construct(private string $cuenta, private string $clave) {}

    public static function delAliado(int $aliadoId): self
    {
        $cuenta = config("afiliaciones_correo.buzones.{$aliadoId}");
        if (! $cuenta) {
            throw new RuntimeException('El aliado no tiene buzón de Gmail configurado para enviar afiliaciones.');
        }

        $fila = DB::table('clave_accesos')
            ->where('aliado_id', $aliadoId)
            ->where('usuario', $cuenta)
            ->where('entidad', 'like', '%GMAIL%')
            ->where('activo', true)
            ->orderByDesc('updated_at')
            ->get(['contrasena'])
            // La contraseña de aplicación de Google son 16 letras (a veces guardadas con espacios).
            ->first(fn ($c) => strlen(preg_replace('/\s+/', '', (string) $c->contrasena)) === 16);

        if (! $fila) {
            throw new RuntimeException("No hay contraseña de aplicación de {$cuenta} en el módulo de claves (entidad con GMAIL, 16 letras).");
        }

        return new self($cuenta, preg_replace('/\s+/', '', $fila->contrasena));
    }

    public function cuenta(): string
    {
        return $this->cuenta;
    }

    /**
     * @param  string[]  $para
     * @param  array<int, array{nombre:string, contenido:string, tipo:string}>  $adjuntos
     * @return string Message-ID del correo enviado (sin < >)
     */
    public function enviar(array $para, string $asunto, string $texto, array $adjuntos = [], array $cc = [], ?string $nombreRemitente = null): string
    {
        $messageId = Str::uuid()->toString().'@brynex.co';

        $correo = (new Email())
            ->from(new Address($this->cuenta, $nombreRemitente ?? ''))
            ->to(...$para)
            ->subject($asunto)
            ->text($texto);
        if ($cc) {
            $correo->cc(...$cc);
        }
        $correo->getHeaders()->addIdHeader('Message-ID', $messageId);
        foreach ($adjuntos as $a) {
            $correo->attach($a['contenido'], $a['nombre'], $a['tipo']);
        }

        $this->transporte()->send($correo);

        return $messageId;
    }

    /** Solo abre sesión SMTP y la cierra: sirve para probar la clave sin enviar nada. */
    public function probar(): void
    {
        $t = new EsmtpTransport('smtp.gmail.com', 465, true);
        $t->setUsername($this->cuenta);
        $t->setPassword($this->clave);
        $t->start();
        $t->stop();
    }

    private function transporte(): Mailer
    {
        $t = new EsmtpTransport('smtp.gmail.com', 465, true);
        $t->setUsername($this->cuenta);
        $t->setPassword($this->clave);

        return new Mailer($t);
    }
}
