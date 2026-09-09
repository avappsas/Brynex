{{-- Envoltura del paquete para DomPDF: un documento por página. El .docx no
     usa esta vista — allá cada documento va en su propia sección de Word. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $titulo ?? 'Documentos del préstamo' }}</title>
    @include('finanzas.prestamos.documentos._estilos')
</head>
<body>
@foreach($documentos as $doc)
    <div @class(['salto' => ! $loop->last])>
        @include('finanzas.prestamos.documentos._'.$doc)
    </div>
@endforeach
</body>
</html>
