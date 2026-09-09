<?php

namespace App\Models\Finanzas;

/**
 * App\Models\Finanzas\Prestamista
 *
 * El MUTUANTE que encabeza los documentos de un préstamo formal. No es el
 * usuario de la aplicación: los préstamos salen a nombre de un tercero, y sus
 * datos se guardan una sola vez para autorrellenar cada expediente.
 *
 * @property int $id
 * @property int $user_id
 * @property string $nombre
 * @property string|null $cedula
 * @property string|null $expedida_en
 * @property string|null $direccion
 * @property string|null $ciudad
 * @property string|null $telefono
 * @property string|null $correo
 * @property bool $por_defecto
 * @property bool $activo
 */
class Prestamista extends BaseFinanzasModel
{
    protected $table = 'finanzas_prestamistas';

    protected $fillable = [
        'user_id',
        'nombre',
        'cedula',
        'expedida_en',
        'direccion',
        'ciudad',
        'telefono',
        'correo',
        'por_defecto',
        'activo',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'por_defecto' => 'boolean',
        'activo' => 'boolean',
    ];

    public function expedientes()
    {
        return $this->hasMany(PrestamoExpediente::class, 'prestamista_id');
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Deja este prestamista como el único marcado por defecto del usuario.
     */
    public function marcarPorDefecto(): void
    {
        static::where('user_id', $this->user_id)
            ->where('id', '!=', $this->id)
            ->update(['por_defecto' => false]);

        $this->forceFill(['por_defecto' => true])->save();
    }

    /**
     * Los campos que los documentos legales necesitan sí o sí. Un expediente
     * con el prestamista incompleto genera un contrato con espacios en blanco,
     * así que la pantalla avisa antes de imprimir.
     */
    public function camposFaltantes(): array
    {
        $requeridos = [
            'cedula' => 'cédula',
            'expedida_en' => 'ciudad de expedición',
            'direccion' => 'dirección',
            'ciudad' => 'ciudad',
            'telefono' => 'teléfono',
        ];

        $faltan = [];
        foreach ($requeridos as $campo => $etiqueta) {
            if (blank($this->{$campo})) {
                $faltan[] = $etiqueta;
            }
        }

        return $faltan;
    }
}
