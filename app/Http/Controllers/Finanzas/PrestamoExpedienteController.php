<?php

namespace App\Http\Controllers\Finanzas;

use App\Http\Controllers\Controller;
use App\Models\Finanzas\Cuenta;
use App\Models\Finanzas\Prestamista;
use App\Models\Finanzas\PrestamoExpediente;
use App\Services\Finanzas\ExpedienteDocumentosService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Préstamos formales: los que se documentan con contrato de mutuo, pagaré y
 * carta de instrucciones antes de entregar un peso.
 *
 * El expediente se llena, se imprimen los documentos, se firman en físico y
 * solo entonces se registra el desembolso, que es el momento en que nace el
 * préstamo de verdad en `finanzas_prestamos`.
 */
class PrestamoExpedienteController extends Controller
{
    use \App\Http\Controllers\Finanzas\Concerns\RegistraPrestamo;

    public function __construct(protected ExpedienteDocumentosService $documentos)
    {
        $this->middleware('auth');
    }

    /**
     * Expedientes en trámite y su historial.
     */
    public function index(Request $request)
    {
        $estado = $request->input('estado', 'tramite');

        $query = PrestamoExpediente::where('user_id', Auth::id())->with('prestamista');

        if ($estado === 'tramite') {
            $query->enTramite();
        } elseif ($estado === 'desembolsado') {
            $query->where('estado', PrestamoExpediente::ESTADO_DESEMBOLSADO);
        } elseif ($estado === 'anulado') {
            $query->where('estado', PrestamoExpediente::ESTADO_ANULADO);
        }

        $expedientes = $query->orderByDesc('id')->get();
        $prestamistas = Prestamista::where('user_id', Auth::id())->orderByDesc('por_defecto')->get();

        return view('finanzas.prestamos.expedientes.index', compact('expedientes', 'estado', 'prestamistas'));
    }

    /**
     * Crea el expediente. Todavía no hay préstamo ni salida de dinero: eso
     * ocurre en `desembolsar()`, cuando los documentos ya están firmados.
     */
    public function store(Request $request)
    {
        $datos = $this->validar($request);

        $prestamista = Prestamista::where('user_id', Auth::id())->findOrFail($datos['prestamista_id']);

        $desembolso = Carbon::parse($datos['fecha_desembolso']);

        $expediente = PrestamoExpediente::create($datos + [
            'user_id' => Auth::id(),
            'prestamista_id' => $prestamista->id,
            'estado' => PrestamoExpediente::ESTADO_PENDIENTE,
            'pagare_numero' => PrestamoExpediente::siguienteNumeroPagare(Auth::id()),
            'dia_cobro' => $desembolso->day,
            'fecha_vencimiento' => $desembolso->copy()->addMonthsNoOverflow((int) $datos['plazo_meses'])->toDateString(),
            'pagare_tope' => $datos['monto'] * (int) $datos['pagare_factor'],
        ]);

        return redirect()->route('finanzas.expedientes.show', $expediente->id)
            ->with('success', 'Expediente creado. Descarga los documentos, hazlos firmar y luego registra el desembolso.');
    }

    public function show($id)
    {
        $expediente = $this->expediente($id);

        $cuentas = Cuenta::where('user_id', Auth::id())->activas()->orderBy('orden')->get();

        return view('finanzas.prestamos.expedientes.show', [
            'exp' => $expediente,
            'cuentas' => $cuentas,
            'documentos' => $this->documentos->documentosDe($expediente),
            'servicio' => $this->documentos,
            'faltanDatosPrestamista' => $expediente->prestamista->camposFaltantes(),
        ]);
    }

    public function edit($id)
    {
        $expediente = $this->expediente($id);

        if ($expediente->estado === PrestamoExpediente::ESTADO_DESEMBOLSADO) {
            return redirect()->route('finanzas.expedientes.show', $expediente->id)
                ->with('error', 'El expediente ya fue desembolsado: sus datos no se pueden cambiar.');
        }

        return view('finanzas.prestamos.expedientes.edit', [
            'exp' => $expediente,
            'prestamistas' => Prestamista::where('user_id', Auth::id())->activos()->orderByDesc('por_defecto')->get(),
        ]);
    }

    public function update(Request $request, $id)
    {
        $expediente = $this->expediente($id);

        if ($expediente->estado === PrestamoExpediente::ESTADO_DESEMBOLSADO) {
            return back()->with('error', 'El expediente ya fue desembolsado: sus datos no se pueden cambiar.');
        }

        $datos = $this->validar($request);

        $desembolso = Carbon::parse($datos['fecha_desembolso']);

        $expediente->update($datos + [
            'dia_cobro' => $desembolso->day,
            'fecha_vencimiento' => $desembolso->copy()->addMonthsNoOverflow((int) $datos['plazo_meses'])->toDateString(),
            'pagare_tope' => $datos['monto'] * (int) $datos['pagare_factor'],
        ]);

        return redirect()->route('finanzas.expedientes.show', $expediente->id)
            ->with('success', 'Expediente actualizado. Vuelve a descargar los documentos para que salgan con los datos nuevos.');
    }

    /**
     * Descarga el paquete legal. `?doc=pagare` baja uno solo; sin parámetro,
     * todos en un mismo archivo.
     */
    public function descargarDocumentos(Request $request, $id, string $formato = 'pdf')
    {
        $expediente = $this->expediente($id);

        $pedidos = $request->filled('doc') ? [$request->input('doc')] : null;
        $sufijo = $pedidos ? '-'.$pedidos[0] : '';
        $nombre = $expediente->pagare_numero.$sufijo;

        if ($formato === 'word') {
            $ruta = $this->documentos->word($expediente, $pedidos);

            return response()->download($ruta, $nombre.'.docx')->deleteFileAfterSend(true);
        }

        return $this->documentos->pdf($expediente, $pedidos)->download($nombre.'.pdf');
    }

    /**
     * Marca el expediente como firmado y guarda el PDF escaneado, que es la
     * prueba de la obligación: sin él no hay con qué cobrar.
     */
    public function registrarFirma(Request $request, $id)
    {
        $expediente = $this->expediente($id);

        $request->validate([
            'fecha_firma' => 'required|date',
            'documentos' => 'nullable|file|mimes:pdf|max:30720', // 30 MB
        ], [], ['documentos' => 'PDF firmado']);

        $datos = ['fecha_firma' => $request->fecha_firma];

        if ($request->hasFile('documentos')) {
            // Datos personales: disco local, nunca public.
            $datos['documentos_path'] = $request->file('documentos')
                ->store('finanzas/expedientes/'.$expediente->id, 'local');
        }

        if ($expediente->estado === PrestamoExpediente::ESTADO_PENDIENTE) {
            $datos['estado'] = PrestamoExpediente::ESTADO_FIRMADO;
        }

        $expediente->update($datos);

        $aviso = $expediente->documentos_path
            ? 'Firma registrada. Ya puedes registrar el desembolso.'
            : 'Firma registrada, pero falta subir el PDF con los documentos firmados.';

        return back()->with('success', $aviso);
    }

    /**
     * Entrega del dinero: aquí nace el préstamo y sale el egreso de la cuenta.
     */
    public function desembolsar(Request $request, $id)
    {
        $expediente = $this->expediente($id);

        if ($expediente->estado === PrestamoExpediente::ESTADO_DESEMBOLSADO) {
            return back()->with('error', 'Este expediente ya fue desembolsado.');
        }

        if ($expediente->estado !== PrestamoExpediente::ESTADO_FIRMADO) {
            return back()->with('error', 'Primero registra la firma de los documentos.');
        }

        $request->validate([
            'fecha_desembolso' => 'required|date',
            'cuenta_id' => 'nullable|integer',
        ]);

        $fecha = Carbon::parse($request->fecha_desembolso);

        $prestamo = $this->crearPrestamoConDesembolso([
            'nombre_deudor' => $expediente->deudor_nombre,
            'cedula_deudor' => $expediente->deudor_cedula,
            'telefono_deudor' => $expediente->deudor_telefono,
            'monto_original' => $expediente->monto,
            'tasa_interes_mensual' => $expediente->tasa_interes_mensual,
            'fecha_desembolso' => $fecha->toDateString(),
            'ultimo_corte' => $fecha->toDateString(),
            'dia_cobro' => $fecha->day,
            'saldo_actual' => $expediente->monto,
            'dias_mora_alerta' => $expediente->dias_mora_alerta,
            'alertas_activas' => true,
            'soporte_path' => $expediente->documentos_path,
            'descripcion' => $expediente->descripcion ?: 'Préstamo formal '.$expediente->pagare_numero,
            'observaciones' => $expediente->observaciones,
        ], $request->cuenta_id, 'Préstamo formal '.$expediente->pagare_numero.' a: '.$expediente->deudor_nombre);

        // La fecha real manda sobre la prevista: los documentos que se
        // reimpriman después deben cuadrar con el préstamo que quedó vivo.
        $expediente->update([
            'estado' => PrestamoExpediente::ESTADO_DESEMBOLSADO,
            'prestamo_id' => $prestamo->id,
            'fecha_desembolso' => $fecha->toDateString(),
            'dia_cobro' => $fecha->day,
            'fecha_vencimiento' => $fecha->copy()->addMonthsNoOverflow($expediente->plazo_meses)->toDateString(),
        ]);

        return redirect()->route('finanzas.prestamos.show', $prestamo->id)
            ->with('success', 'Desembolso registrado. El préstamo ya está activo y liquidando intereses.');
    }

    public function anular($id)
    {
        $expediente = $this->expediente($id);

        if ($expediente->estado === PrestamoExpediente::ESTADO_DESEMBOLSADO) {
            return back()->with('error', 'No se puede anular un expediente ya desembolsado.');
        }

        $expediente->update(['estado' => PrestamoExpediente::ESTADO_ANULADO]);

        return redirect()->route('finanzas.expedientes.index')->with('success', 'Expediente anulado.');
    }

    /**
     * Descarga el PDF escaneado con los documentos firmados.
     */
    public function descargarFirmados($id)
    {
        $expediente = $this->expediente($id);

        abort_unless($expediente->documentos_path && Storage::disk('local')->exists($expediente->documentos_path), 404);

        return Storage::disk('local')->download(
            $expediente->documentos_path,
            'firmados-'.$expediente->pagare_numero.'.pdf'
        );
    }

    // ---------------------------------------------------------------- Prestamistas

    /**
     * Guarda o actualiza los datos del mutuante que encabeza los documentos.
     */
    public function guardarPrestamista(Request $request, $id = null)
    {
        $datos = $request->validate([
            'nombre' => 'required|string|max:120',
            'cedula' => 'nullable|string|max:20',
            'expedida_en' => 'nullable|string|max:60',
            'direccion' => 'nullable|string|max:150',
            'ciudad' => 'nullable|string|max:60',
            'telefono' => 'nullable|string|max:30',
            'correo' => 'nullable|email|max:120',
            'activo' => 'nullable|boolean',
        ]);

        $datos['activo'] = $request->boolean('activo', true);

        if ($id) {
            $prestamista = Prestamista::where('user_id', Auth::id())->findOrFail($id);
            $prestamista->update($datos);
        } else {
            $prestamista = Prestamista::create($datos + ['user_id' => Auth::id()]);
        }

        // El primero que se crea queda por defecto; después hay que pedirlo.
        if ($request->boolean('por_defecto') || Prestamista::where('user_id', Auth::id())->count() === 1) {
            $prestamista->marcarPorDefecto();
        }

        return back()->with('success', 'Datos del prestamista guardados.');
    }

    // ---------------------------------------------------------------- Internos

    private function expediente($id): PrestamoExpediente
    {
        return PrestamoExpediente::where('user_id', Auth::id())
            ->with('prestamista')
            ->findOrFail($id);
    }

    /**
     * Reglas comunes al alta y a la edición. Los datos del codeudor y de la
     * garantía solo se exigen cuando el interruptor correspondiente está
     * activo: un expediente a medio llenar produce documentos con vacíos.
     */
    private function validar(Request $request): array
    {
        $datos = $request->validate([
            'prestamista_id' => 'required|integer',
            'ciudad' => 'required|string|max:60',
            'deudor_nombre' => 'required|string|max:120',
            'deudor_cedula' => 'required|string|max:20',
            'deudor_expedida_en' => 'nullable|string|max:60',
            'deudor_direccion' => 'nullable|string|max:150',
            'deudor_ciudad' => 'nullable|string|max:60',
            'deudor_telefono' => 'nullable|string|max:30',
            'deudor_correo' => 'nullable|email|max:120',
            'deudor_ocupacion' => 'nullable|string|max:100',

            'tiene_codeudor' => 'nullable|boolean',
            'codeudor_nombre' => 'required_if:tiene_codeudor,1|nullable|string|max:120',
            'codeudor_cedula' => 'required_if:tiene_codeudor,1|nullable|string|max:20',
            'codeudor_expedida_en' => 'nullable|string|max:60',
            'codeudor_direccion' => 'nullable|string|max:150',
            'codeudor_ciudad' => 'nullable|string|max:60',
            'codeudor_telefono' => 'nullable|string|max:30',
            'codeudor_correo' => 'nullable|email|max:120',
            'codeudor_ocupacion' => 'nullable|string|max:100',

            'tiene_prenda' => 'nullable|boolean',
            'prenda_placa' => 'required_if:tiene_prenda,1|nullable|string|max:10',
            'prenda_clase' => 'required_if:tiene_prenda,1|nullable|string|max:40',
            'prenda_marca' => 'required_if:tiene_prenda,1|nullable|string|max:40',
            'prenda_linea' => 'nullable|string|max:60',
            'prenda_modelo' => 'nullable|string|max:10',
            'prenda_color' => 'nullable|string|max:30',
            'prenda_motor' => 'required_if:tiene_prenda,1|nullable|string|max:40',
            'prenda_chasis' => 'required_if:tiene_prenda,1|nullable|string|max:40',
            'prenda_matricula' => 'nullable|string|max:40',
            'prenda_avaluo' => 'nullable|numeric|min:0',
            'prenda_propietario' => 'nullable|string|max:120',
            'prenda_propietario_cedula' => 'nullable|string|max:20',

            'monto' => 'required|numeric|min:1',
            'tasa_interes_mensual' => 'required|numeric|min:0|max:100',
            'plazo_meses' => 'required|integer|min:1|max:120',
            'fecha_desembolso' => 'required|date',
            'dias_mora_alerta' => 'required|integer|min:1',
            'pagare_factor' => 'required|integer|in:2,3',
            'descripcion' => 'nullable|string|max:255',
            'observaciones' => 'nullable|string',
        ], [
            'codeudor_nombre.required_if' => 'El nombre del codeudor es obligatorio.',
            'codeudor_cedula.required_if' => 'La cédula del codeudor es obligatoria.',
            'prenda_placa.required_if' => 'La placa del vehículo es obligatoria.',
            'prenda_clase.required_if' => 'La clase del vehículo es obligatoria.',
            'prenda_marca.required_if' => 'La marca del vehículo es obligatoria.',
            'prenda_motor.required_if' => 'El número de motor es obligatorio.',
            'prenda_chasis.required_if' => 'El número de chasis es obligatorio.',
        ]);

        // El prestamista tiene que ser del propio usuario: sin esto, un id
        // ajeno pondría los datos de otra persona a encabezar el contrato.
        abort_unless(
            Prestamista::where('user_id', Auth::id())->whereKey($datos['prestamista_id'])->exists(),
            404
        );

        $datos['tiene_codeudor'] = $request->boolean('tiene_codeudor');
        $datos['tiene_prenda'] = $request->boolean('tiene_prenda');

        // Apagar el interruptor borra los datos: si no, un expediente que dejó
        // de tener codeudor seguiría imprimiendo su bloque de firma.
        if (! $datos['tiene_codeudor']) {
            $datos = array_merge($datos, array_fill_keys([
                'codeudor_nombre', 'codeudor_cedula', 'codeudor_expedida_en', 'codeudor_direccion',
                'codeudor_ciudad', 'codeudor_telefono', 'codeudor_correo', 'codeudor_ocupacion',
            ], null));
        }

        if (! $datos['tiene_prenda']) {
            $datos = array_merge($datos, array_fill_keys([
                'prenda_placa', 'prenda_clase', 'prenda_marca', 'prenda_linea', 'prenda_modelo',
                'prenda_color', 'prenda_motor', 'prenda_chasis', 'prenda_matricula',
                'prenda_avaluo', 'prenda_propietario', 'prenda_propietario_cedula',
            ], null));
        }

        return $datos;
    }
}
