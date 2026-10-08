<?php
/** Plantilla institucional de dos páginas; los datos variables llegan del expediente/BD. */
$v = static function ($key) use ($fields, $e) {
  $value = trim((string)($fields[$key] ?? ''));
  return $value === '' ? '&nbsp;' : nl2br($e($value), false);
};
$datePrinted = date('d/m/Y');
$privacyUrl = 'https://cultura.edomex.gob.mx/sites/cultura.edomex.gob.mx/files/files/transparencia/avisos/3-SCTUR-SDP-2025%20Adquisici%C3%B3n%20de%20Equipos%20de%20C%C3%B3mputo%20para%20uso%20del%20personal%20asignado%20de%20la%20Secretar%C3%ADa%20de%20Cultura%20y%20Turismo..pdf';
?>
<style>
*{box-sizing:border-box} @page{size:letter portrait;margin:25mm 30mm}
body{font:7.5pt Arial,Helvetica,sans-serif;color:#111;margin:0;line-height:1.16}
.page-letterhead{position:fixed;top:-25mm;left:-30mm;width:215.9mm;height:279.4mm;z-index:-1}
.page{page-break-after:always}
.page:last-child{page-break-after:auto}
.page-title{text-align:center;font-size:15pt;font-weight:bold;margin:0 0 5mm;text-transform:none}
.data-grid,.components{width:100%;border-collapse:collapse;table-layout:fixed;border:1px solid #000}
.data-grid th,.data-grid td,.components th,.components td{border:1px solid #000;padding:1mm 1.5mm;vertical-align:top;overflow-wrap:anywhere}
.data-grid th{width:33%;text-align:left;font-weight:bold;background:#f3f3f3}
.data-grid td{width:67%;min-height:5mm}
.components{margin-top:3mm}
.components caption{text-align:left;font-weight:bold;font-size:10pt;margin:0 0 1.5mm}
.components thead th{background:#ededed;text-align:center}
.components td{height:5mm}
.agreement{margin:1.5em 0 1.5mm;text-align:center;text-decoration:underline;font-weight:bold;font-size:8.5pt;line-height:1.2}
.intro{margin:0 0 1.5mm;text-align:justify}
.roman{margin:0 0 0 6mm;padding:0;list-style:none}
.roman li{margin:0 0 1mm;text-align:justify}
.roman .roman-label{font-weight:bold;display:inline-block;width:7mm}
.section-heading{text-align:center;font-weight:bold;margin:0 0 3mm;font-size:9.5pt}
.alpha{margin:0 0 2.5mm 6mm;padding:0;list-style:none}
.alpha li{margin:0 0 2.1mm;text-align:justify}
.alpha .alpha-label{font-weight:bold;display:inline-block;width:7mm}
.privacy{margin:3mm 0 0;text-align:justify;overflow-wrap:anywhere}
a{color:#111;text-decoration:underline}
.signatures{width:100%;border-collapse:collapse;table-layout:fixed;margin-top:12mm}
.signatures td{width:50%;padding:0 7mm;text-align:center;vertical-align:top}
.signatures .heading{height:12mm;font-weight:bold;font-size:8pt}
.signatures .line{border-top:1px solid #000;height:14mm}
.signatures .label{font-size:8pt}
.footer{width:100%;border-collapse:collapse;border-top:1px solid #777;padding-top:2mm;margin-top:6mm;font-size:7.5pt;color:#333}
.footer td{border:0;padding:2mm 0 0}.footer td:last-child{text-align:right}
</style>
<img class="page-letterhead" src="<?= $e($membrete) ?>" alt="">
<section class="page compact" aria-label="Resguardo de equipo, página 1 de 2">
  <h1 class="page-title">Resguardo del equipo</h1>
  <table class="data-grid" aria-label="Información del resguardo"><thead><tr><th>Información</th><th>Detalle</th></tr></thead><tbody>
    <?php foreach ($fields as $label => $_value): ?><tr><th scope="row"><?= $e($label) ?></th><td><?= $v($label) ?></td></tr><?php endforeach; ?>
  </tbody></table>
  <table class="components" aria-label="Componentes del equipo"><caption>Componentes</caption><thead><tr><th>Componente</th><th>Marca</th><th>Modelo</th><th>Serie</th><th>Inventario</th></tr></thead><tbody>
    <?php foreach ($componentRows as $row): ?><tr><td><?= $e($row['componente']) ?></td><td><?= trim((string)($row['marca'] ?? '')) !== '' ? $e($row['marca']) : '&nbsp;' ?></td><td><?= trim((string)($row['modelo'] ?? '')) !== '' ? $e($row['modelo']) : '&nbsp;' ?></td><td><?= trim((string)($row['serie'] ?? '')) !== '' ? $e($row['serie']) : '&nbsp;' ?></td><td><?= trim((string)($row['inventario'] ?? '')) !== '' ? $e($row['inventario']) : '&nbsp;' ?></td></tr><?php endforeach; ?>
  </tbody></table>
  <h2 class="agreement">ACUERDO DE RESPONSABILIDAD PARA EL ACTIVO TECNOLÓGICO INSTITUCIONAL<br>DE LA SECRETARÍA DE CULTURA Y TURISMO DEL GOBIERNO DEL ESTADO DE MÉXICO.</h2>
  <p class="intro">En conformidad con el número de control interno <strong><?= $v('Clave interna') ?></strong>, el servidor público que suscribe recibe el equipo y sus componentes para el desempeño de sus funciones y asume su custodia y uso responsable, conforme a las disposiciones aplicables:</p>
  <ol class="roman">
    <li><span class="roman-label">I.</span> Utilizar el activo tecnológico exclusivamente para el desempeño de las funciones institucionales encomendadas.</li>
    <li><span class="roman-label">II.</span> Conservar el equipo y sus accesorios en condiciones adecuadas de uso y resguardo.</li>
    <li><span class="roman-label">III.</span> No permitir el uso del equipo por personas no autorizadas ni cederlo a terceros.</li>
    <li><span class="roman-label">IV.</span> No trasladar el equipo fuera de las instalaciones o del lugar de trabajo autorizado sin autorización correspondiente.</li>
    <li><span class="roman-label">V.</span> No instalar, desinstalar o modificar programas, componentes o configuraciones institucionales sin autorización del área competente.</li>
    <li><span class="roman-label">VI.</span> No conectar dispositivos ni realizar intervenciones que comprometan la seguridad o integridad del equipo y de la información.</li>
    <li><span class="roman-label">VII.</span> No utilizar el activo para fines personales, comerciales o ajenos a las actividades institucionales.</li>
    <li><span class="roman-label">VIII.</span> No retirar etiquetas, números de inventario, sellos o mecanismos de identificación y protección.</li>
  </ol>
  <table class="footer"><tbody><tr><td>Fecha de impresión: <?= $e($datePrinted) ?></td><td>Página 1 de 2</td></tr></tbody></table>
</section>
<section class="page compact" aria-label="Resguardo de equipo, página 2 de 2">
  <ol class="roman" start="9">
    <li><span class="roman-label">IX.</span> No almacenar, consultar o divulgar información contraria a las disposiciones institucionales de seguridad y protección de datos.</li>
    <li><span class="roman-label">X.</span> Informar inmediatamente al área responsable sobre fallas, daños, pérdida, robo o cualquier incidencia del activo.</li>
    <li><span class="roman-label">XI.</span> Devolver el equipo y sus componentes cuando así lo determine la autoridad competente o concluya la asignación.</li>
  </ol>
  <h2 class="section-heading">Disposiciones para la Administración y Uso del Equipo de Cómputo de la Secretaría de Cultura y Turismo del Gobierno del Estado de México:</h2>
  <ol class="alpha" type="a">
    <li><span class="alpha-label">a)</span> El activo se destinará al cumplimiento de las atribuciones y actividades oficiales.</li>
    <li><span class="alpha-label">b)</span> El resguardatario mantendrá el equipo bajo cuidado y evitará exponerlo a riesgos o daños.</li>
    <li><span class="alpha-label">c)</span> Las solicitudes de soporte y mantenimiento se realizarán por los canales institucionales.</li>
    <li><span class="alpha-label">d)</span> El software instalado deberá contar con autorización y licenciamiento aplicables.</li>
    <li><span class="alpha-label">e)</span> Las credenciales de acceso son personales y deberán mantenerse confidenciales.</li>
    <li><span class="alpha-label">f)</span> La información institucional deberá protegerse y almacenarse conforme a las políticas vigentes.</li>
    <li><span class="alpha-label">g)</span> Cualquier cambio de adscripción, ubicación o resguardatario deberá notificarse para actualizar el control patrimonial.</li>
    <li><span class="alpha-label">h)</span> El daño, pérdida o sustracción del equipo deberá reportarse de inmediato a las áreas competentes.</li>
    <li><span class="alpha-label">i)</span> La entrega y devolución de los bienes se documentará conforme al procedimiento institucional vigente.</li>
  </ol>
  <p class="privacy">Para consultar el tratamiento de datos personales relacionado con la asignación y uso de equipos, revise el <a href="<?= $e($privacyUrl) ?>">Aviso de privacidad integral para la adquisición de equipos de cómputo para uso del personal asignado de la Secretaría de Cultura y Turismo</a>.</p>
  <table class="signatures" aria-label="Firmas de entrega y resguardo"><tbody><tr>
    <td><div class="heading">NOMBRE Y FIRMA DEL SERVIDOR PÚBLICO RESGUARDATARIO</div><div class="line"></div><div class="label">Nombre y firma del responsable</div></td>
    <td><div class="heading">SERVIDOR PÚBLICO ADSCRITO AL DEPARTAMENTO DE TECNOLOGÍAS DE LA INFORMACIÓN QUE ENTREGA EL ACTIVO TECNOLÓGICO INSTITUCIONAL</div><div class="line"></div><div class="label">Nombre y firma de quien entrega</div></td>
  </tr></tbody></table>
  <table class="footer"><tbody><tr><td>Fecha de impresión: <?= $e($datePrinted) ?></td><td>Página 2 de 2</td></tr></tbody></table>
</section>
