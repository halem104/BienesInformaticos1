<?php

use Dompdf\Dompdf;
use Dompdf\Options;

/** Genera y almacena documentos de expediente en directorio privado. */
class TraspasoDocumentService
{
  public static function storageRoot(): string
  {
    $configured = getenv('BIENES_PRIVATE_STORAGE');
    return ($configured ?: dirname(dirname(ROOT)) . DIRECTORY_SEPARATOR . 'BienesInformaticosPrivate') . DIRECTORY_SEPARATOR . 'traspasos';
  }

  public static function absolutePath(string $relative): string
  {
    $relative = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
    if ($relative === '' || str_contains($relative, '..') || str_starts_with($relative, DIRECTORY_SEPARATOR)) throw new RuntimeException('Ruta documental inválida.');
    $root = realpath(self::storageRoot());
    if ($root === false) throw new RuntimeException('No existe el almacenamiento privado de documentos.');
    $path = realpath($root . DIRECTORY_SEPARATOR . $relative);
    if ($path === false || !str_starts_with(strtolower($path), strtolower($root . DIRECTORY_SEPARATOR))) throw new RuntimeException('El documento no está disponible.');
    return $path;
  }

  /** Genera un PDF por bien para cada tipo solicitado y lo asocia al expediente. */
  public static function generar(int $traspasoId, int $usuarioId, array $tipos = ['TARJETA_RESGUARDO','RESGUARDO_EQUIPO'], array $bajas = []): array
  {
    $exp = TraspasoModel::detalle($traspasoId);
    if (!$exp || !in_array($exp['estado'], ['PENDIENTE_VALIDACION','VALIDADO'], true)) throw new RuntimeException('Solo se generan o actualizan documentos en un expediente activo y no completado.');
    if (!$exp['bienes']) throw new RuntimeException('El expediente no contiene bienes.');
    $creados=[];$formatHashes=[];$generatedDocs=[];$ccpPorBien=[];$tarjetaPorBien=[];
    foreach($exp['eventos'] as $event){$meta=$event['metadatos']??[];if($event['tipo_evento']==='GENERACION_DOCUMENTO'){$documentId=(int)($meta['documento']??0);if($documentId){$generatedDocs[$documentId]=true;if(isset($meta['formato_hash']))$formatHashes[$documentId]=(string)$meta['formato_hash'];}}elseif($event['tipo_evento']==='EDICION_CCP_BAJA'){$bienId=(int)($meta['bien']??0);if($bienId)$ccpPorBien[$bienId]=str_replace(["\r\n","\r"],"\n",(string)($meta['ccp']??''));}elseif($event['tipo_evento']==='EDICION_TARJETA_RESGUARDO'){$bienId=(int)($meta['bien']??0);if($bienId)$tarjetaPorBien[$bienId]=$meta['campos']??[];}}
    foreach ($exp['bienes'] as $line) {
      $bien = BienModel::porId((int) $line['id_bien']);
      if (!$bien) throw new RuntimeException('No se encontró un bien del expediente.');
      $documentoBajaAnterior = !empty($line['id_resguardante_origen']) ? TraspasoModel::documentoGeneradoVigente($traspasoId,(int)$bien['id_bien'],'BAJA_RESGUARDANTE') : null;
      $fechaEmision = null;
      if ($documentoBajaAnterior) foreach ($exp['documentos'] as $documento) {
        if ((int)$documento['id_traspaso_documento'] === $documentoBajaAnterior) { $fechaEmision = $documento['fecha_carga'] ?? null; break; }
      }
      $fechaBaja = $fechaEmision ?: ($exp['fecha_entrega'] ?: ($exp['fecha_completado'] ?: date('Y-m-d')));
      if (self::fechaCorta($fechaBaja) === '') $fechaBaja = date('Y-m-d');
      $resguardo = Model::query('SELECT * FROM resguardo WHERE id_bien=:id AND activo=1 LIMIT 1', ['id' => $bien['id_bien']]);
      $resguardo = $resguardo[0] ?? [];
      // Los formatos deben reflejar el destino propuesto, aunque la custodia
      // real solo cambia al completar el expediente.
      $bien['resguardante_origen_nombre'] = $line['resguardante_origen_nombre'] ?? null;
      $bien['resguardante_destino_nombre'] = $exp['resguardante_destino_nombre'];
      $bien['resguardante_nombre'] = $exp['resguardante_destino_nombre'];
      $bien['resguardante_csp'] = $exp['resguardante_destino_csp'];
      $bien['id_resguardante'] = (int) $exp['id_resguardante_destino'];
      $bien['resguardante_unidad_nombre'] = $exp['unidad_destino_nombre'];
      $bien['resguardante_codigo_ua'] = $exp['unidad_destino_codigo'];
      $bien['unidad_nombre'] = $exp['unidad_destino_nombre'];
      $bien['codigo_ua'] = $exp['unidad_destino_codigo'];
      $bien['traspaso_motivo'] = $exp['motivo'] ?? null;
      $bien['traspaso_observaciones'] = $exp['observaciones'] ?? null;
      $bien['jerarquia_administrativa'] = CatalogoModel::jerarquiaUnidad((int) $exp['id_unidad_destino']);
      $origenId=(int)($line['id_resguardante_origen']??0);$origen=$origenId?ResguardanteModel::porId($origenId):[];
      if($origen){$bien['resguardante_origen_nombre']=$origen['nombre_completo']??$bien['resguardante_origen_nombre'];$bien['resguardante_origen_csp']=$origen['csp']??($line['resguardante_origen_csp']??null);}
      else{$bien['resguardante_origen_csp']=$line['resguardante_origen_csp']??null;}
      $bien['fecha_documento']=$fechaBaja;
      $bien['fecha_retiro']=$fechaBaja;
      $bien['traspaso_observaciones']=$exp['observaciones']??null;
      $bien['ccp_baja']=$ccpPorBien[(int)$bien['id_bien']]??'';
      if (empty($line['ubicacion_sin_cambio']) && !empty($line['id_ubicacion_destino'])) {
        $bien['id_ubicacion'] = (int) $line['id_ubicacion_destino'];
        $bien['municipio'] = $line['municipio_destino'] ?? null;
        $bien['localidad'] = $line['localidad_destino'] ?? null;
        $bien['ubicacion_fisica'] = $line['ubicacion_fisica_destino'] ?? null;
      }
      $resguardo['fecha_asignacion'] = date('Y-m-d');
      $documents = [];
      if (in_array('TARJETA_RESGUARDO',$tipos,true)) {
        $previous=TraspasoModel::documentoGeneradoVigente($traspasoId,(int)$bien['id_bien'],'TARJETA_RESGUARDO');
        if(!$previous||isset($generatedDocs[$previous])){$cardHtml=self::tarjeta($bien,$resguardo,$tarjetaPorBien[(int)$bien['id_bien']]??[]);$cardHash=hash('sha256',$cardHtml);if(!$previous||($formatHashes[$previous]??null)!==$cardHash)$documents['TARJETA_RESGUARDO']=['html'=>$cardHtml,'anterior'=>$previous,'metadata'=>['formato'=>'TARJETA_RESGUARDO_V1','formato_hash'=>$cardHash]];}
      }
      if (in_array('RESGUARDO_EQUIPO',$tipos,true)) {
        $previous=TraspasoModel::documentoGeneradoVigente($traspasoId,(int)$bien['id_bien'],'RESGUARDO_EQUIPO');
        if(!$previous||isset($generatedDocs[$previous])){$equipmentHtml=self::resguardoEquipo($bien,$resguardo);$equipmentHash=hash('sha256',$equipmentHtml);if(!$previous||($formatHashes[$previous]??null)!==$equipmentHash)$documents['RESGUARDO_EQUIPO']=['html'=>$equipmentHtml,'anterior'=>$previous,'metadata'=>['formato'=>'RESGUARDO_EQUIPO_INSTITUCIONAL_V1','formato_hash'=>$equipmentHash]];}
      }
      // Every bien in the expediente conserva el resguardante previo en
      // traspaso_bien. When that relationship exists, its baja documental is
      // mandatory and must not depend on a client checkbox.
      if (!empty($line['id_resguardante_origen'])) {
        if(!$documentoBajaAnterior||isset($generatedDocs[$documentoBajaAnterior])){$bajaHtml=self::bajaResguardante($bien);$formatHash=hash('sha256',$bajaHtml);if(!$documentoBajaAnterior||($formatHashes[$documentoBajaAnterior]??null)!==$formatHash)$documents['BAJA_RESGUARDANTE']=['html'=>$bajaHtml,'anterior'=>$documentoBajaAnterior,'metadata'=>['formato'=>'BAJA_INSTITUCIONAL_V1','formato_hash'=>$formatHash]];}
      }
      foreach ($documents as $tipo => $document) {
        $bytes = self::pdf($document['html']);
        $file = 'doc-' . bin2hex(random_bytes(18)) . '.pdf';
        $relative = $exp['folio'] . DIRECTORY_SEPARATOR . $file;
        $full = self::writePrivate($relative, $bytes);
        $id = null;
        try {
          $id = TraspasoModel::generarDocumento($traspasoId, $tipo, str_replace(DIRECTORY_SEPARATOR, '/', $relative), $tipo . '-' . ($bien['clave_interna'] ?: $bien['id_bien']) . '.pdf', hash('sha256',$bytes), strlen($bytes), $usuarioId, (int) $bien['id_bien'], $document['anterior']??null, $document['metadata']??[]);
        } catch (Throwable $e) { @unlink($full); throw $e; }
        $creados[] = $id;
      }
    }
    return $creados;
  }

  private static function writePrivate(string $relative, string $bytes): string
  {
    $relative = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
    $full = self::storageRoot() . DIRECTORY_SEPARATOR . $relative;
    $dir = dirname($full);
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) throw new RuntimeException('No se pudo preparar el almacenamiento privado.');
    $handle = fopen($full, 'xb');
    if (!$handle) throw new RuntimeException('No se pudo crear el documento sin sobrescribir un archivo existente.');
    try { if (fwrite($handle, $bytes) !== strlen($bytes)) throw new RuntimeException('No se pudo guardar el PDF completo.'); }
    finally { fclose($handle); }
    return $full;
  }

  private static function pdf(string $html): string
  {
    $options = new Options(); $options->set('defaultFont','Arial'); $options->set('isRemoteEnabled',false);
    $styles='';$html=preg_replace_callback('/<style\b[^>]*>.*?<\/style>/is',static function($match)use(&$styles){$styles.=$match[0];return '';},$html)??$html;
    $pdf = new Dompdf($options); $pdf->loadHtml('<!doctype html><html lang="es"><head><meta charset="UTF-8">'.$styles.'</head><body>' . $html . '</body></html>','UTF-8');
    $pdf->setPaper('letter','portrait'); $pdf->render(); return $pdf->output();
  }

  private static function e($v): string { return htmlspecialchars(trim((string)($v ?? '')) ?: '—', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

  /** Mapea los datos del bien, la UA destino y el resguardo a los numerales oficiales. */
  public static function camposTarjeta(array $b,array $r,array $overrides=[]):array
  {
    $u=$b['jerarquia_administrativa']??[];$values=[
      'inventario'=>$b['numero_inventario']??null,'nic_cea'=>$b['nic_cea']??null,
      'secretaria'=>$u['secretaria']??null,'subsecretaria'=>$u['subsecretaria']??null,'direccion'=>$u['direccion_secretaria']??null,
      'direccion_area'=>$u['direccion_area']??null,'subdireccion'=>$u['subdireccion']??null,'departamento'=>$u['departamento']??null,
      'oficina'=>$u['oficina']??null,'codigo_ua'=>$b['codigo_ua']??null,'activo_generico'=>$b['activo_generico_nombre']??null,
      'grupo_activo'=>$b['grupo_nombre']??null,'activo_especifico'=>$b['activo_especifico_nombre']??null,'nombre_bien'=>$b['nombre_bien']??null,
      'material'=>$b['material_nombre']??$b['material']??null,'marca'=>$b['marca_nombre']??null,'modelo'=>$b['modelo_nombre']??null,
      'color'=>$b['color_nombre']??$b['color']??null,'estado_uso'=>$b['estado_nombre']??null,'serie'=>$b['numero_serie']??null,
      'caracteristicas'=>$b['caracteristicas']??null,'observaciones'=>$b['traspaso_observaciones']??null,
      'fecha_alta'=>$b['fecha_alta']??null,'fecha_adquisicion'=>$b['fecha_adquisicion']??null,'fecha_elaboracion'=>$b['fecha_elaboracion']??null,
      'fecha_asignacion'=>$r['fecha_asignacion']??$b['fecha_asignacion_actual']??null,
      'valor'=>isset($b['valor'])&&$b['valor']!==''?'$'.number_format((float)$b['valor'],2):null,
      'ubicacion_fisica'=>$b['ubicacion_fisica']??null,'municipio'=>$b['municipio']??null,'localidad'=>$b['localidad']??null,
    ];
    foreach(['fecha_alta','fecha_adquisicion','fecha_elaboracion','fecha_asignacion'] as $key)if(!empty($values[$key])&&strtotime((string)$values[$key])!==false)$values[$key]=date('d/m/Y',strtotime((string)$values[$key]));
    foreach($values as $key=>$value)if(array_key_exists($key,$overrides))$values[$key]=$overrides[$key];
    return $values;
  }

  /** Valores prellenados para el editor sin alterar los registros del inventario. */
  public static function camposTarjetaDesdeExpediente(array $exp,array $line,array $doc):array
  {
    $b=BienModel::porId((int)$line['id_bien']);if(!$b)return [];
    $b['jerarquia_administrativa']=CatalogoModel::jerarquiaUnidad((int)$exp['id_unidad_destino']);$b['codigo_ua']=$exp['unidad_destino_codigo']??null;$b['traspaso_observaciones']=$exp['observaciones']??null;
    if(empty($line['ubicacion_sin_cambio'])&&!empty($line['id_ubicacion_destino'])){$b['municipio']=$line['municipio_destino']??null;$b['localidad']=$line['localidad_destino']??null;$b['ubicacion_fisica']=$line['ubicacion_fisica_destino']??null;}
    return self::camposTarjeta($b,['fecha_asignacion'=>substr((string)($doc['fecha_carga']??''),0,10)]);
  }

  private static function tarjeta(array $b,array $r,array $overrides=[]):string
  {
    $f=self::camposTarjeta($b,$r,$overrides);$e=static fn($v)=>self::e($v);
    $cell=static fn(string $key,string $label,int $span=4):string=>'<td colspan="'.$span.'"><strong class="field-label">'.$label.'</strong><div class="field-value">'.$e($f[$key]??null).'</div></td>';
    $rows=[];$rows[]='<tr><th scope="row" colspan="3">INVENTARIO</th><td colspan="3">'.$e($f['inventario']).'</td><th scope="row" colspan="3">NIC / CEA</th><td colspan="3">'.$e($f['nic_cea']).'</td></tr>';
    foreach([['secretaria','1) SECRETARÍA'],['subsecretaria','2) SUBSECRETARÍA'],['direccion','3) DIRECCIÓN'],['direccion_area','4) DIRECCIÓN DE ÁREA'],['subdireccion','5) SUBDIRECCIÓN'],['departamento','6) DEPARTAMENTO']] as [$key,$label])$rows[]='<tr>'.$cell($key,$label,12).'</tr>';
    $rows[]='<tr>'.$cell('oficina','7) OFICINA',6).$cell('codigo_ua','8) CÓDIGO DE UNIDAD ADMINISTRATIVA',6).'</tr>';
    $rows[]='<tr>'.$cell('activo_generico','9) ACTIVO GENÉRICO',12).'</tr>';
    $rows[]='<tr>'.$cell('grupo_activo','10) GRUPO DEL ACTIVO',6).$cell('activo_especifico','11) ACTIVO ESPECÍFICO',6).'</tr>';
    $rows[]='<tr>'.$cell('nombre_bien','12) NOMBRE DEL BIEN',12).'</tr>';
    $rows[]='<tr>'.$cell('material','13) MATERIAL').$cell('marca','14) MARCA').$cell('modelo','15) MODELO').'</tr>';
    $rows[]='<tr>'.$cell('color','16) COLOR').$cell('estado_uso','17) ESTADO DE USO').$cell('serie','18) SERIE').'</tr>';
    $rows[]='<tr>'.$cell('caracteristicas','19) CARACTERÍSTICAS (MATRÍCULA O REGISTRO)',12).'</tr>';
    $rows[]='<tr>'.$cell('observaciones','20) OBSERVACIONES',12).'</tr>';
    $rows[]='<tr>'.$cell('fecha_alta','21) FECHA DE ALTA',6).$cell('fecha_adquisicion','22) FECHA DE ADQUISICIÓN',6).'</tr>';
    $rows[]='<tr>'.$cell('fecha_elaboracion','23) FECHA DE ELABORACIÓN',6).$cell('fecha_asignacion','24) FECHA DE ASIGNACIÓN',6).'</tr>';
    $rows[]='<tr>'.$cell('valor','25) VALOR',12).'</tr>';
    $rows[]='<tr>'.$cell('ubicacion_fisica','26) UBICACIÓN FÍSICA DEL BIEN',6).$cell('municipio','27) MUNICIPIO',3).$cell('localidad','28) LOCALIDAD',3).'</tr>';
    $body='<h1 class="card-title">TARJETA DE RESGUARDO</h1><table class="card-grid" aria-label="Tarjeta de resguardo del bien"><colgroup>'.str_repeat('<col>',12).'</colgroup><tbody>'.implode('',$rows).'</tbody></table>';
    $body.='<p class="legal-note"><strong>NOTA:</strong> La firma y la fecha de quien suscribe es el responsable de la custodia y buen uso del bien que ampara.</p><table class="signature-grid" aria-label="Fecha y firma del resguardatario"><tbody><tr><td><strong>FECHA (DD/MM/AAAA)</strong><div class="signature-space"></div></td><td><strong>NOMBRE Y FIRMA DEL RESGUARDATARIO</strong><div class="signature-space"></div><span>'.$e($b['resguardante_destino_nombre']??$b['resguardante_nombre']??null).'</span></td></tr></tbody></table><footer class="document-note">Documento generado dinámicamente con los datos recuperados de la base de datos para la ruta /BienesInformaticos1/traspasos/ver-documento/24.</footer>';
    $membrete=self::imageData('MembreteOficialiaMayor.jpg');
    return '<style>*{box-sizing:border-box}@page{size:Letter portrait;margin:25mm 30mm}body{font:7.5pt Arial,Helvetica,sans-serif;color:#111;margin:0;line-height:1.08}.page-letterhead{position:fixed;top:-25mm;left:-30mm;width:215.9mm;height:279.4mm;z-index:-1}.card-title{text-align:center;font-size:14pt;margin:10mm 0 3mm;font-weight:bold}.card-grid{width:100%;border-collapse:collapse;table-layout:fixed;border:1px solid #000}.card-grid td,.card-grid th{border:1px solid #000;padding:1mm 1.5mm;vertical-align:top;overflow-wrap:anywhere}.card-grid th{text-align:left}.field-label{display:block;font-size:6.5pt;margin-bottom:.4mm}.field-value{min-height:3mm;white-space:pre-wrap}.legal-note{font-size:7.5pt;margin:3mm 0 1.5mm}.signature-grid{width:100%;border-collapse:collapse;table-layout:fixed;margin-top:2mm}.signature-grid td{width:50%;border:0;text-align:center;vertical-align:top;padding:0 5mm;font-size:7.5pt}.signature-space{display:block;height:15mm}.document-note{margin-top:3mm;text-align:center;font-size:6.5pt;color:#444;border-top:1px solid #777;padding-top:1.5mm}</style><img class="page-letterhead" src="'.$membrete.'" alt="">'.$body;
  }

  private static function resguardoEquipo(array $b,array $r): string
  {
    $componentes=[];
    foreach(($b['componentes']??[]) as $c)$componentes[strtoupper((string)($c['tipo_componente']??''))]=$c;
    $notes=Model::query('SELECT notas FROM resguardante WHERE id_resguardante=:id LIMIT 1',['id'=>$b['id_resguardante']??0]);
    $clave=trim((string)($b['clave_interna']??''));if($clave!==''&&!str_starts_with(strtoupper($clave),'CI-'))$clave='CI-'.$clave;
    $candado=$r['candado']??null;
    $fields=[
      'Clave interna'=>$clave,'Nombre del S.P.'=>$b['resguardante_nombre']??null,'C.S.P.'=>$b['resguardante_csp']??null,
      'Notas'=>$notes[0]['notas']??null,'Tipo de equipo'=>$r['tipo_equipo']??$b['tipo_equipo']??null,
      'Estado del equipo'=>$r['estado_equipo']??$b['estado_equipo']??null,'Observaciones'=>$r['observaciones']??$b['traspaso_observaciones']??null,
      'Candado'=>$candado===null||$candado===''?null:(in_array(strtoupper((string)$candado),['SI','SÍ','1','YES'],true)?'Sí':'No'),
    ];
    $componentRows=[];
    foreach(['CPU','CARGADOR','MONITOR','MOUSE','TECLADO','UPS'] as $type){$c=$componentes[$type]??[];$componentRows[]=['componente'=>$type,'marca'=>$c['marca']??null,'modelo'=>$c['modelo']??null,'serie'=>$c['numero_serie']??null,'inventario'=>$c['numero_inventario']??null];}
    $e=static fn($value)=>htmlspecialchars(trim((string)($value??'')),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    $membrete=self::imageData('MembreteResguardo.jpg');
    $template=dirname(__DIR__,2).DIRECTORY_SEPARATOR.'templates'.DIRECTORY_SEPARATOR.'pdf'.DIRECTORY_SEPARATOR.'resguardo-equipo.php';
    if(!is_file($template))throw new RuntimeException('No se encontró la plantilla HTML del Resguardo de equipo.');
    ob_start();require $template;return (string)ob_get_clean();
  }

  private static function bajaResguardante(array $b): string
  {
    $componentes=[];foreach(($b['componentes']??[]) as $component)$componentes[strtoupper((string)($component['tipo_componente']??''))]=$component;
    $esc=static fn($value)=>self::e($value);
    $date=self::fechaCorta($b['fecha_documento']??null);
    if($date==='')throw new RuntimeException('No se pudo determinar una fecha válida para emitir la baja de resguardante.');
    $dateLong=self::fechaInstitucional($b['fecha_documento']??null);
    $personName=trim((string)($b['resguardante_origen_nombre']??''));
    if($personName!==''){$personName=preg_replace('/^C\.\s*/iu','',$personName)??$personName;$person='<strong>C. '.$esc($personName).'</strong>';}
    else{$person='<span class="editable-orange">[Nombre del servidor público pendiente de captura]</span>';}
    $observations=trim((string)($b['traspaso_observaciones']??''));
    $observationsHtml=$observations!==''?'<div class="observations-box">'.nl2br($esc($observations)).'</div>':'<div class="observations-box observations-space">&nbsp;</div>';
    $monitor=$componentes['MONITOR']??[];$cpu=$componentes['CPU']??[];$keyboard=$componentes['TECLADO']??[];$mouse=$componentes['MOUSE']??[];$ups=$componentes['UPS']??[];$charger=$componentes['CARGADOR']??[];
    $profile=trim((string)($b['perfil']??''));
    $lastColumn=[];if(!empty($ups['numero_serie']))$lastColumn[]='<strong>UPS:</strong> '.$esc($ups['numero_serie']);if(!empty($charger['numero_serie']))$lastColumn[]='<strong>Cargador:</strong> '.$esc($charger['numero_serie']);
    $inventoryParts=[];if(!empty($b['numero_inventario']))$inventoryParts[]='<strong>Inventario:</strong> '.$esc($b['numero_inventario']);if(!empty($b['clave_interna']))$inventoryParts[]='<strong>CI:</strong> '.$esc($b['clave_interna']);
    $table='<table class="equipment"><thead><tr><th>Perfil</th><th>Marca</th><th>Número de Inventario</th><th>Número de Monitor</th><th>Número de Serie CPU</th><th>Número de Serie Teclado</th><th>Número de Serie Mouse</th><th>Número de Serie UPS / Cargador</th></tr></thead><tbody><tr>';
    $cells=[$profile?:'—',$b['marca_nombre']??null,implode('<br>',$inventoryParts),$monitor['numero_inventario']??null,$cpu['numero_serie']??null,$keyboard['numero_serie']??null,$mouse['numero_serie']??null,implode('<br>',$lastColumn)];
    foreach($cells as $index=>$value){$table.='<td>'.($index===2||$index===7?($value!==''?$value:'—'):$esc($value)).'</td>';}$table.='</tr></tbody></table>';
    $ccp=trim((string)($b['ccp_baja']??''));
    $ccpHtml=$ccp!==''?'<div class="ccp"><strong class="ccp-label">C. c. p.</strong><div class="ccp-value">'.$esc($ccp).'</div></div>':'';
    $content='<p class="year-legend">&quot;2026. Año del Humanismo Mexicano en el Estado de México&quot;</p><div class="institutional-address"><div><strong>Secretaría de Cultura y Turismo</strong></div><div><strong>Unidad de Información, Planeación, Programación y Evaluación</strong></div><div><strong>Departamento de Tecnologías de la Información</strong></div><p class="document-date">Toluca, Estado de México a '.$esc($dateLong).'.</p></div>
      <div class="document-title"><div>FORMATO</div><div>&quot;BAJA DE RESGUARDANTE DE EQUIPOS DE CÓMPUTO&quot;</div></div>
      <p class="intro">Anteponiendo un cordial saludo, me es grato dirigirme a usted, con la finalidad de informarle que con fecha de <strong class="document-date-inline">'.$esc($date).'</strong>, el personal del Departamento de Tecnologías de la Información adscrito a la Unidad de Información, Planeación, Programación y Evaluación de esta Secretaría, llevó a cabo el retiro del siguiente equipo de cómputo:</p>
      '.$table.'
      <p class="body-copy">Lo anterior, con el objeto de dar por <strong>concluido el periodo de resguardante</strong> del equipo de cómputo en mención con el servidor público '.$person.', toda vez que a partir de esta fecha el equipo de cómputo estará resguardado en el Departamento de Tecnologías de la Información hasta que se determine su reasignación.</p>
      <p class="observations-intro"><strong>OBSERVACIONES:</strong></p>'.$observationsHtml.'
      <p class="closing">Sin otro particular por el momento, me reitero a sus apreciables órdenes.</p>
      <table class="signatures"><tbody><tr><td><strong>RECIBE</strong><span class="signature-space">&nbsp;</span><strong>FIRMA DEL PERSONAL ADSCRITO AL<br>DEPARTAMENTO DE TECNOLOGÍAS DE LA INFORMACIÓN</strong></td><td><strong>ENTREGA</strong><span class="signature-space">&nbsp;</span><strong>FIRMA DE CONFORMIDAD<br>DEL SERVIDOR PÚBLICO<br>QUE ENTREGA EL EQUIPO</strong><br>'.$person.'</td></tr></tbody></table>
      '.$ccpHtml.'
      <div class="institutional-footer">Bulevar Jesús Reyes Heroles número 302, delegación San Buenaventura, C. P. 50110, Toluca, Estado de México.<br>Teléfonos: 722 274 13 15 y 722 274 11 77.</div>';
    return self::baseInstitucional($content);
  }

  private static function fechaCorta($value):string
  { $date=trim((string)$value);if($date===''||strtotime($date)===false)return '';return date('d/m/Y',strtotime($date)); }

  private static function fechaInstitucional($value):string
  { $date=trim((string)$value);if($date===''||strtotime($date)===false)return '[Fecha pendiente de captura]';$months=[1=>'enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];$timestamp=strtotime($date);return date('j',$timestamp).' de '.$months[(int)date('n',$timestamp)].' de '.date('Y',$timestamp); }

  private static function baseInstitucional(string $body):string
  {
    $background=self::imageData('FondoMembretado.jpeg');
    $style='<style>
      *{box-sizing:border-box}
      @page{size:Letter;margin:0}
      html,body{margin:0;padding:0;font-family:Arial,Helvetica,sans-serif;color:#2d3748;font-size:8.4pt;line-height:1.28}
      .page-background{position:fixed;top:0;left:12.95mm;width:190mm;height:246.5mm;z-index:0}
      .institutional-page{position:relative;z-index:1;margin:25mm 30mm;padding:10mm 0 0}
      .year-legend{text-align:center;margin:0 0 4mm;font-size:10pt;font-weight:normal}
      .institutional-address{width:72%;margin:0 0 6mm auto;text-align:right;font-size:8.4pt;overflow-wrap:break-word;word-wrap:break-word}
      .document-date{margin:2mm 0 0;font-weight:normal}
      .document-title{text-align:center;color:#1e293b;font-size:9pt;font-weight:bold;line-height:1.25;margin:3mm 0 6mm}
      .document-title div{margin:0}
      .intro,.body-copy,.observations-intro,.closing{margin:0 0 3mm;text-align:justify}
      .document-date-inline{color:#000;font-weight:bold}
      .equipment{width:100%;table-layout:fixed;border-collapse:collapse;margin:4mm 0 5mm;font-size:6pt;line-height:1.13;overflow-wrap:break-word;background:transparent}
      .equipment th{background:#1e293b;color:#fff;font-weight:bold;text-align:center;padding:1.3mm .65mm;border:.35pt solid #64748b;vertical-align:middle;overflow-wrap:break-word}
      .equipment td{background:transparent;color:#2d3748;text-align:center;padding:1.4mm .65mm;border:.35pt solid #94a3b8;vertical-align:middle;overflow-wrap:break-word}
      .equipment tr{page-break-inside:avoid}
      .observations-intro{margin:2mm 0 1.5mm}
      .observations-box{width:100%;min-height:23mm;border:.8pt solid #64748b;background:transparent;padding:3mm;text-align:left;white-space:pre-wrap;overflow-wrap:break-word}
      .observations-space{min-height:23mm}
      .editable-orange{color:#c2410c;font-weight:bold}
      .editable-purple{color:#6b21a8;font-weight:bold}
      .signatures{width:100%;table-layout:fixed;border-collapse:collapse;border:none;margin:5mm 0 2mm}
      .signatures td{width:50%;border:none;text-align:center;vertical-align:top;padding:0 4mm;font-size:7.8pt}
      .signature-space{display:block;height:14mm}
      .ccp{font-size:8pt;margin:4mm 0 0;text-align:left}.ccp-value{white-space:pre-line;margin-left:12mm}
      .institutional-footer{position:fixed;left:30mm;right:30mm;bottom:25mm;text-align:center;font-size:7pt;line-height:1.3;margin:0}
      .institutional-page table{page-break-inside:avoid}
    </style>';
    return $style.'<img class="page-background" src="'.$background.'" alt=""><main class="institutional-page">'.$body.'</main>';
  }

  private static function imageData(string $filename):string
  {
    $path=dirname(dirname(__DIR__)).DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'images'.DIRECTORY_SEPARATOR.$filename;
    $bytes=is_file($path)?file_get_contents($path):false;if($bytes===false)throw new RuntimeException('No se encontró o no se pudo leer el membrete assets/images/'.$filename.'.');
    return 'data:image/jpeg;base64,'.base64_encode($bytes);
  }
}
