{{--
    Estilos del paquete legal. Se mantienen deliberadamente pobres —sin flex,
    sin grid, sin selectores raros— porque el mismo HTML lo renderiza DomPDF
    para el PDF y PHPWord para el .docx, y PHPWord solo entiende HTML básico.
--}}
<style>
    @page { margin: 2.2cm 2.2cm 2cm 2.2cm; }
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10.5pt; line-height: 1.45; color: #000; }
    h1 { font-size: 13pt; text-align: center; margin: 0 0 4pt 0; }
    h2 { font-size: 11pt; text-align: center; font-weight: bold; margin: 0 0 12pt 0; }
    h3 { font-size: 10.5pt; margin: 12pt 0 2pt 0; }
    p { margin: 0 0 8pt 0; text-align: justify; }
    .centrado { text-align: center; }
    .derecha { text-align: right; }
    .sello { font-size: 8.5pt; color: #444; text-align: center; margin-bottom: 10pt; }
    .firma { margin-top: 26pt; }
    .firma-linea { margin: 0 0 2pt 0; }
    .dato { font-size: 9.5pt; margin: 0 0 1pt 0; }
    table { width: 100%; border-collapse: collapse; font-size: 9.5pt; }
    td, th { border: 1px solid #666; padding: 4pt 6pt; text-align: left; vertical-align: top; }
    th { background-color: #eeeeee; }
    .salto { page-break-after: always; }
</style>
