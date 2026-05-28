<?php
/**
 * includes/evaluacion_backend.php
 * --------------------------------
 * Cerebro de la "Evaluación Preventiva".
 * Lee el último resultado de cada cuestionario, aplica reglas de detección
 * y devuelve un puntaje (0-100) + factores + nivel (bajo/moderado/elevado).
 *
 * Cómo está organizado el archivo:
 *   1) Lectura de datos        → consultas a la BD
 *   2) Reglas de detección     → una función por factor (detectarXxx)
 *   3) Cálculo y persistencia  → calcular, guardar, recargar
 *   4) Helpers de presentación → títulos, colores, textos para la vista
 *
 * Para agregar un nuevo factor:
 *   a) Crea una función detectarMiFactor($porcentajes, $respuestas) que devuelva el arreglo o null.
 *   b) Agrégala a la lista dentro de detectarFactores().
 */

const EVAL_SLUGS = ['sueno','cansancio','hidratacion','dolor','energia','habitos','riesgos']; // los 7 cuestionarios que entran en la evaluación

// =====================================================================
// 1) LECTURA DE DATOS
// =====================================================================

/** Último porcentaje (0-100) que el usuario sacó en CADA cuestionario.
 *  Devuelve ['porcentajes' => [...], 'faltantes' => [...]].
 *  Si nunca contestó un cuestionario, su slug va a "faltantes". */
function obtenerUltimosPorcentajes(PDO $pdo, int $usuarioId) {
    $porcentajes = [];                                          // aquí guardaremos slug => porcentaje
    foreach (EVAL_SLUGS as $slug) {                             // recorremos los 7 slugs
        $porcentajes[$slug] = null;                             // arrancamos todos en null = "sin datos"
    }
    $faltantes = [];                                            // lista de slugs sin resultado guardado

    $sql = "SELECT r.porcentaje
              FROM resultados r
              JOIN cuestionarios c ON c.id = r.cuestionario_id
             WHERE r.usuario_id = ? AND c.slug = ?
             ORDER BY r.creado_en DESC LIMIT 1";                // trae el último % de un cuestionario específico
    $consulta = $pdo->prepare($sql);                            // preparamos la query UNA sola vez

    foreach (EVAL_SLUGS as $slug) {                             // ejecutamos la misma query por cada cuestionario
        $consulta->execute([$usuarioId, $slug]);                // pasamos id del usuario y el slug actual
        $fila = $consulta->fetch();                             // traemos la fila (o false si no existe)
        if ($fila === false) {                                  // si no hay resultado guardado para este slug
            $faltantes[] = $slug;                               // lo agregamos a la lista de faltantes
        } else {
            $porcentajes[$slug] = (float)$fila['porcentaje'];   // si hay, guardamos el % como número decimal
        }
    }
    return [                                                    // devolvemos las dos listas con claves claras
        'porcentajes' => $porcentajes,
        'faltantes'   => $faltantes,
    ];
}

/** Respuestas individuales del último cuestionario "riesgos".
 *  Devuelve [orden_pregunta => valor], ej. [1=>4, 2=>5, 3=>2, ...]. */
function obtenerRespuestasRiesgos(PDO $pdo, int $usuarioId) {
    $sql = "SELECT id FROM resultados r
             WHERE usuario_id = ?
               AND cuestionario_id = (SELECT id FROM cuestionarios WHERE slug = 'riesgos')
             ORDER BY creado_en DESC LIMIT 1";                  // PASO 1: busca el id del último resultado de "riesgos"
    $consulta = $pdo->prepare($sql);                            // prepara
    $consulta->execute([$usuarioId]);                           // ejecuta con el id del usuario
    $resultadoId = $consulta->fetchColumn();                    // toma SOLO el valor de la 1ra columna
    if (!$resultadoId) {                                        // si no encontró nada
        return [];                                              // devuelve arreglo vacío y termina
    }

    $sql = "SELECT p.orden, rs.valor
              FROM respuestas rs
              JOIN preguntas p ON p.id = rs.pregunta_id
             WHERE rs.resultado_id = ?
             ORDER BY p.orden ASC";                             // PASO 2: trae las respuestas detalladas de ese resultado
    $consulta = $pdo->prepare($sql);                            // preparamos la query
    $consulta->execute([(int)$resultadoId]);                    // pasamos el id que encontramos arriba

    $resultado = [];                                            // aquí construiremos [orden => valor]
    foreach ($consulta->fetchAll() as $fila) {                  // recorremos cada respuesta traída
        $numeroPregunta = (int)$fila['orden'];                  // número de pregunta (1..7)
        $valor          = (int)$fila['valor'];                  // respuesta del usuario (1..5)
        $resultado[$numeroPregunta] = $valor;                   // la clave es el orden de la pregunta
    }
    return $resultado;                                          // devolvemos el mapa de respuestas
}

// =====================================================================
// 2) REGLAS DE DETECCIÓN — una función por factor.
//    Cada detector recibe los datos que necesita y devuelve:
//      - el arreglo del factor si la regla aplica
//      - null si no aplica
//    $porcentajes = porcentajes por slug (sueno/cansancio/...)
//    $respuestas  = respuestas del cuestionario "riesgos" indexadas 1..7
// =====================================================================

/** Diabetes tipo 2: antecedentes/azúcar + falta de control/peso. */
function detectarDiabetes(array $respuestas) {
    $razones = [];                                              // aquí juntamos las razones específicas que dispararon el factor
    if ($respuestas[1] <= 2) { $razones[] = 'Tienes familiares cercanos con diabetes.'; }                        // si la respuesta a la pregunta 1 fue mala
    if ($respuestas[3] <= 2) { $razones[] = 'Consumes refrescos o bebidas azucaradas con alta frecuencia.'; }    // (mismo patrón para las demás)
    if ($respuestas[5] <= 2) { $razones[] = 'No te has medido la glucosa o presión en mucho tiempo.'; }
    if ($respuestas[7] <= 2) { $razones[] = 'Has notado cambios bruscos de peso sin razón aparente.'; }

    $grupoA = ($respuestas[1] <= 2 || $respuestas[3] <= 2);     // grupo A: antecedente familiar o azúcar
    $grupoB = ($respuestas[5] <= 2 || $respuestas[7] <= 2);     // grupo B: sin control o peso inestable
    if (!($grupoA && $grupoB)) {                                // necesita disparar AMBOS grupos para contar como riesgo
        return null;                                            // si no, no aplica el factor
    }

    return [                                                    // si aplica, devolvemos el factor con todos sus datos
        'id'              => 'diabetes',                        // identificador único del factor (para CSS/BD)
        'titulo'          => 'Posible riesgo de Diabetes tipo 2', // texto que ve el usuario
        'puntaje'         => 25,                                // puntos que suma al puntaje total (0-100)
        'que_significa'   => 'La diabetes tipo 2 es una condición donde el cuerpo no procesa bien el azúcar en sangre. Tener familiares con diabetes y hábitos de alto consumo de azúcar son factores de riesgo conocidos, aunque no determinantes.',
        'detectado_por'   => $razones,                          // las razones que pusimos arriba
        'recomendaciones' => [                                  // lista de consejos para el usuario
            'Solicita una prueba de glucosa en ayuno con tu médico.',
            'Reduce refrescos y bebidas azucaradas.',
            'Aumenta el consumo de agua y fibra.',
            'Mantén un peso estable con actividad física regular.',
        ],
    ];
}

/** Hipertensión: antecedentes cardiovasculares + (sal alta o sin control).
 *  Estructura idéntica a detectarDiabetes. */
function detectarHipertension(array $respuestas) {
    $razones = [];
    if ($respuestas[2] <= 2) { $razones[] = 'Tienes familiares cercanos con presión alta o problemas del corazón.'; }
    if ($respuestas[4] <= 2) { $razones[] = 'Consumes comida muy salada o frituras con alta frecuencia.'; }
    if ($respuestas[5] <= 2) { $razones[] = 'No te has medido la presión en mucho tiempo.'; }

    $tieneAntecedente    = ($respuestas[2] <= 2);               // antecedente cardiovascular
    $tieneSalOSinControl = ($respuestas[4] <= 2 || $respuestas[5] <= 2); // sal alta o sin control reciente
    if (!($tieneAntecedente && $tieneSalOSinControl)) {         // pide antecedente + uno de los otros dos
        return null;
    }

    return [
        'id'              => 'hipertension',
        'titulo'          => 'Posible riesgo de Hipertensión',
        'puntaje'         => 20,
        'que_significa'   => 'La hipertensión es una presión arterial elevada que con el tiempo puede afectar el corazón y los riñones. El consumo alto de sodio y los antecedentes familiares son factores de riesgo prevenibles.',
        'detectado_por'   => $razones,
        'recomendaciones' => [
            'Mide tu presión arterial en cualquier farmacia.',
            'Reduce el consumo de sal y alimentos ultraprocesados.',
            'Aumenta actividad física moderada (caminatas).',
            'Consulta a tu médico si no te has medido en más de un año.',
        ],
    ];
}

/** Insomnio crónico: mal sueño + mucho cansancio + poca energía.
 *  Este detector usa $porcentajes (generales), no $respuestas de "riesgos". */
function detectarInsomnio(array $porcentajes) {
    if (!($porcentajes['sueno'] < 50 && $porcentajes['cansancio'] < 50 && $porcentajes['energia'] < 50)) { // los TRES tienen que estar por debajo del 50%
        return null;
    }

    return [
        'id'              => 'insomnio',
        'titulo'          => 'Posible riesgo de Insomnio crónico',
        'puntaje'         => 20,
        'que_significa'   => 'El insomnio crónico afecta la calidad de vida, la concentración y el sistema inmune. En muchos casos está relacionado con hábitos nocturnos que se pueden corregir.',
        'detectado_por'   => [                                  // razones construidas en el momento con los % del usuario
            'Tu calidad de sueño es baja (' . round($porcentajes['sueno']) . '%).',
            'Tu nivel de cansancio diario es alto (' . round($porcentajes['cansancio']) . '% de bienestar).',
            'Tu nivel de energía es bajo (' . round($porcentajes['energia']) . '%).',
        ],
        'recomendaciones' => [
            'Establece un horario fijo para dormir y despertar.',
            'Evita pantallas al menos 30 min antes de dormir.',
            'Evita cafeína después de las 5 pm.',
            'Si persiste más de 3 semanas, consulta a tu médico.',
        ],
    ];
}

/** Sedentarismo: pocos hábitos + poca energía + dolores frecuentes. */
function detectarSedentarismo(array $porcentajes) {
    if (!($porcentajes['habitos'] < 50 && $porcentajes['energia'] < 50 && $porcentajes['dolor'] < 60)) {
        return null;
    }

    return [
        'id'              => 'sedentarismo',
        'titulo'          => 'Posible riesgo por Sedentarismo',
        'puntaje'         => 15,
        'que_significa'   => 'El sedentarismo es uno de los principales factores de riesgo para enfermedades cardiovasculares, diabetes y problemas musculoesqueléticos. La buena noticia es que es completamente reversible.',
        'detectado_por'   => [
            'Tus hábitos de actividad física son bajos (' . round($porcentajes['habitos']) . '%).',
            'Tu nivel de energía es bajo (' . round($porcentajes['energia']) . '%).',
            'Reportas molestias físicas frecuentes (' . round($porcentajes['dolor']) . '% de bienestar).',
        ],
        'recomendaciones' => [
            'Empieza con 15-20 min de caminata diaria.',
            'Haz pausas de 5 min cada hora si trabajas sentado.',
            'Sube escaleras en vez de usar elevador.',
            'Busca una actividad que disfrutes: bici, baile, natación.',
        ],
    ];
}

/** Deshidratación crónica: baja hidratación + dolores frecuentes. */
function detectarDeshidratacion(array $porcentajes) {
    if (!($porcentajes['hidratacion'] < 45 && $porcentajes['dolor'] < 60)) {
        return null;
    }

    return [
        'id'              => 'deshidratacion',
        'titulo'          => 'Posible riesgo de Deshidratación crónica',
        'puntaje'         => 10,
        'que_significa'   => 'La deshidratación crónica leve afecta la concentración, el funcionamiento renal y puede causar dolores de cabeza frecuentes. Es uno de los factores más fáciles de corregir.',
        'detectado_por'   => [
            'Tu hidratación diaria es baja (' . round($porcentajes['hidratacion']) . '%).',
            'Reportas dolores frecuentes (' . round($porcentajes['dolor']) . '% de bienestar).',
        ],
        'recomendaciones' => [
            'Toma al menos 8 vasos de agua al día.',
            'Carga una botella contigo como recordatorio.',
            'Reduce refrescos y jugos artificiales.',
            'Come frutas con alto contenido de agua (sandía, pepino).',
        ],
    ];
}

/** Estrés crónico: poca energía + mal sueño + pocos hábitos de autocuidado. */
function detectarEstres(array $porcentajes) {
    if (!($porcentajes['energia'] < 45 && $porcentajes['sueno'] < 50 && $porcentajes['habitos'] < 50)) {
        return null;
    }

    return [
        'id'              => 'estres',
        'titulo'          => 'Posible riesgo de Estrés crónico',
        'puntaje'         => 10,
        'que_significa'   => 'El estrés crónico tiene impacto directo en el sistema inmune, el sueño, la presión arterial y el estado anímico. Detectarlo a tiempo permite tomar medidas preventivas antes de que escale.',
        'detectado_por'   => [
            'Tu nivel de energía es muy bajo (' . round($porcentajes['energia']) . '%).',
            'Tu calidad de sueño es baja (' . round($porcentajes['sueno']) . '%).',
            'Tus hábitos de autocuidado son bajos (' . round($porcentajes['habitos']) . '%).',
        ],
        'recomendaciones' => [
            'Dedica al menos 20 min al día a una actividad que disfrutes.',
            'Practica respiración profunda o meditación básica.',
            'Habla con alguien de confianza sobre lo que te preocupa.',
            'Si sientes que no puedes manejarlo solo, un profesional puede ayudarte.',
        ],
    ];
}

/** Corre los 6 detectores y devuelve solo los factores que aplican. */
function detectarFactores(array $porcentajes, array $respuestas) {
    $factores = [];                                                            // aquí juntamos los factores que sí aplicaron

    $factor = detectarDiabetes($respuestas);        if ($factor !== null) { $factores[] = $factor; } // probamos cada detector y, si devolvió algo, lo metemos
    $factor = detectarHipertension($respuestas);    if ($factor !== null) { $factores[] = $factor; } // (los siguientes funcionan igual)
    $factor = detectarInsomnio($porcentajes);       if ($factor !== null) { $factores[] = $factor; }
    $factor = detectarSedentarismo($porcentajes);   if ($factor !== null) { $factores[] = $factor; }
    $factor = detectarDeshidratacion($porcentajes); if ($factor !== null) { $factores[] = $factor; }
    $factor = detectarEstres($porcentajes);         if ($factor !== null) { $factores[] = $factor; }

    return $factores;                                                          // lista final de factores detectados
}

// =====================================================================
// 3) CÁLCULO Y PERSISTENCIA
// =====================================================================

/** Convierte el puntaje (0-100) en nivel (bajo/moderado/elevado). */
function clasificarNivel(int $puntaje) {
    if ($puntaje <= 30) { return 'bajo'; }                      // 0-30  → verde, sin alertas
    if ($puntaje <= 60) { return 'moderado'; }                  // 31-60 → amarillo, atención
    return 'elevado';                                           // 61+   → rojo, ver médico
}

/** Función PRINCIPAL: calcula la evaluación completa para un usuario. */
function calcularEvaluacion(PDO $pdo, int $usuarioId) {
    // 1) Leer datos de la BD.
    $datos       = obtenerUltimosPorcentajes($pdo, $usuarioId); // devuelve ['porcentajes' => ..., 'faltantes' => ...]
    $porcentajes = $datos['porcentajes'];                       // mapa slug => % (puede tener nulls)
    $faltantes   = $datos['faltantes'];                         // slugs sin resultado
    $respuestas  = obtenerRespuestasRiesgos($pdo, $usuarioId);  // respuestas pregunta-por-pregunta del cuestionario "riesgos"

    // 2) Rellenar lo que falte con valores "todo bien" para que las reglas
    //    no disparen falsos positivos cuando faltan datos.
    //    (El aviso amarillo "te falta X" lo muestra la vista por separado.)
    foreach (EVAL_SLUGS as $slug) {                             // recorremos los 7 slugs
        if ($porcentajes[$slug] === null) {                     // si quedó en null (no tenía resultado)
            $porcentajes[$slug] = 100;                          // lo asumimos como 100% (sin riesgo)
        }
    }
    for ($numeroPregunta = 1; $numeroPregunta <= 7; $numeroPregunta++) { // pregunta 1 a la 7 del cuestionario "riesgos"
        if (!isset($respuestas[$numeroPregunta])) {             // si no contestó esa pregunta
            $respuestas[$numeroPregunta] = 5;                   // ponemos 5 = mejor respuesta posible
        }
    }

    // 3) Detectar factores y sumar puntajes (tope 100).
    $factores = detectarFactores($porcentajes, $respuestas);    // corre las 6 reglas
    $puntaje  = 0;                                              // arranca en cero
    foreach ($factores as $factor) {                            // por cada factor detectado
        $puntaje += $factor['puntaje'];                         // sumamos sus puntos
    }
    if ($puntaje > 100) {                                       // si nos pasamos
        $puntaje = 100;                                         // lo dejamos en 100 (tope)
    }
    $nivel = clasificarNivel($puntaje);                         // convertimos a bajo/moderado/elevado

    return [                                                    // empaquetamos todo y lo devolvemos
        'puntaje'                 => $puntaje,                  // 0-100 (suma topeada de los factores)
        'nivel'                   => $nivel,                    // 'bajo' / 'moderado' / 'elevado'
        'factores'                => $factores,                 // lista de factores detectados
        'cuestionarios_faltantes' => $faltantes,                // slugs que el usuario no ha contestado
    ];
}

/** Guarda una evaluación en sus 3 tablas dentro de una transacción.
 *  Si algo falla, no queda evaluación a medias. */
function guardarEvaluacion(PDO $pdo, int $usuarioId, array $evaluacion) {
    $pdo->beginTransaction();                                   // abrimos transacción: todo o nada
    try {
        // 1) Cabecera de la evaluación.
        $sql = "INSERT INTO evaluaciones_preventivas (usuario_id, puntaje, nivel)
                VALUES (?, ?, ?)";                              // inserta la fila principal
        $consulta = $pdo->prepare($sql);                        // preparamos
        $consulta->execute([$usuarioId, (int)$evaluacion['puntaje'], $evaluacion['nivel']]); // ejecutamos con los 3 valores
        $evaluacionId = (int)$pdo->lastInsertId();              // id que MariaDB le asignó a esta fila nueva

        // Statements reutilizables para los siguientes inserts (preparamos una vez, ejecutamos muchas).
        $sqlFactor  = "INSERT INTO evaluacion_factores
                         (evaluacion_id, factor_id, titulo, puntaje, que_significa)
                       VALUES (?, ?, ?, ?, ?)";                 // 1 fila por cada factor detectado
        $sqlDetalle = "INSERT INTO evaluacion_detalles (factor_id, tipo, texto, orden)
                       VALUES (?, ?, ?, ?)";                    // 1 fila por cada razón o recomendación
        $consultaFactor  = $pdo->prepare($sqlFactor);           // preparamos query de factores
        $consultaDetalle = $pdo->prepare($sqlDetalle);          // preparamos query de detalles

        // 2) Cada factor con sus dos listas de detalles.
        foreach ($evaluacion['factores'] as $factor) {          // recorremos los factores detectados
            $consultaFactor->execute([                          // insertamos el factor con sus 5 columnas
                $evaluacionId,                                  // a qué evaluación pertenece
                $factor['id'],                                  // slug del factor (diabetes, insomnio, ...)
                $factor['titulo'],                              // texto que ve el usuario
                (int)$factor['puntaje'],                        // puntos que aporta (5/10/15/20/25)
                $factor['que_significa'],                       // explicación clínica
            ]);
            $factorDbId = (int)$pdo->lastInsertId();            // id que tomó esta fila en la tabla evaluacion_factores

            // Lista 1: razones por las que se detectó.
            $orden = 0;                                         // contador de orden, empieza en 0
            foreach ($factor['detectado_por'] as $texto) {      // por cada razón
                $consultaDetalle->execute([$factorDbId, 'detectado_por', $texto, $orden]); // inserta como tipo "detectado_por"
                $orden++;                                       // siguiente posición
            }

            // Lista 2: recomendaciones (mismo patrón, solo cambia el "tipo").
            $orden = 0;                                         // reiniciamos el contador
            foreach ($factor['recomendaciones'] as $texto) {    // por cada recomendación
                $consultaDetalle->execute([$factorDbId, 'recomendacion', $texto, $orden]); // inserta como tipo "recomendacion"
                $orden++;                                       // siguiente posición
            }
        }

        $pdo->commit();                                         // todo bien → confirmar cambios
        return $evaluacionId;                                   // devolvemos el id para redirigir
    } catch (Exception $e) {                                    // si algo explotó
        $pdo->rollBack();                                       // deshacemos todo lo insertado
        throw $e;                                               // relanzamos el error para que se vea
    }
}

/** Recompone los factores guardados con la MISMA forma que devuelve detectarFactores(),
 *  para que la vista no distinga entre "vivo" y "leído de BD". */
function cargarFactoresEvaluacion(PDO $pdo, int $evaluacionId) {
    // 1) Traer los factores de esa evaluación.
    $sql = "SELECT id, factor_id, titulo, puntaje, que_significa
              FROM evaluacion_factores
             WHERE evaluacion_id = ?
             ORDER BY id ASC";                                  // todos los factores de esta evaluación, en orden
    $consulta = $pdo->prepare($sql);                            // preparamos la query
    $consulta->execute([$evaluacionId]);                        // ejecutamos con el id de la evaluación
    $factores = $consulta->fetchAll();                          // arreglo de filas, una por factor

    // 2) Por cada factor, traer sus detalles y separarlos en dos listas.
    $sqlDetalle = "SELECT tipo, texto FROM evaluacion_detalles
                    WHERE factor_id = ? ORDER BY orden ASC, id ASC"; // razones + recomendaciones en una sola query
    $consultaDetalle = $pdo->prepare($sqlDetalle);              // preparamos UNA vez, ejecutamos por cada factor

    $lista = [];                                                // aquí construimos el resultado final
    foreach ($factores as $factor) {                            // recorremos cada factor
        $consultaDetalle->execute([(int)$factor['id']]);        // traemos sus detalles pasando el id del factor
        $detectadoPor    = [];                                  // razones del factor
        $recomendaciones = [];                                  // consejos del factor
        foreach ($consultaDetalle->fetchAll() as $detalle) {    // recorremos los detalles
            if ($detalle['tipo'] === 'detectado_por') {         // si la fila es del tipo "razón"
                $detectadoPor[] = $detalle['texto'];            // va a la lista de razones
            } else {                                            // si no, es "recomendacion"
                $recomendaciones[] = $detalle['texto'];         // va a la lista de consejos
            }
        }
        $lista[] = [                                            // armamos el factor con la MISMA forma que en memoria
            'id'              => $factor['factor_id'],          // slug del factor (diabetes, insomnio, ...)
            'titulo'          => $factor['titulo'],             // texto que ve el usuario
            'puntaje'         => (int)$factor['puntaje'],       // puntos que aporta
            'que_significa'   => $factor['que_significa'],      // explicación clínica
            'detectado_por'   => $detectadoPor,                 // lista de razones (textos)
            'recomendaciones' => $recomendaciones,              // lista de consejos (textos)
        ];
    }
    return $lista;                                              // lista de factores lista para la vista
}

// =====================================================================
// 4) HELPERS DE PRESENTACIÓN (usados por la vista)
// =====================================================================

/** Convierte un slug ("sueno") en un título legible ("Calidad de sueño"). */
function tituloCuestionario(string $slug) {
    $map = [                                                    // tabla de traducción slug → título bonito
        'sueno'       => 'Calidad de sueño',
        'cansancio'   => 'Nivel de cansancio',
        'hidratacion' => 'Hidratación',
        'dolor'       => 'Dolores físicos',
        'energia'     => 'Nivel de energía',
        'habitos'     => 'Hábitos saludables',
        'riesgos'     => 'Riesgos generales de salud',
    ];
    if (isset($map[$slug])) {                                   // si el slug está en la tabla
        return $map[$slug];                                     // devolvemos el título
    }
    return ucfirst($slug);                                      // fallback: el slug con la 1ra letra en mayúscula
}

/** Clases CSS del borde de la card de un factor (más puntos = color más fuerte). */
function bordeFactor(int $puntaje) {
    if ($puntaje >= 25) { return 'border-l-4 border-clay-600'; } // grave → rojo intenso
    if ($puntaje >= 20) { return 'border-l-4 border-clay-400'; } // intermedio
    return 'border-l-4 border-amber-400';                        // leve → ámbar
}

/** Clases CSS del badge según nivel (bajo/moderado/elevado). */
function badgeNivelEval(string $nivel) {
    if ($nivel === 'bajo')     { return 'bg-sage-100 text-sage-700'; }   // verde calmo
    if ($nivel === 'moderado') { return 'bg-amber-100 text-amber-700'; } // amarillo
    if ($nivel === 'elevado')  { return 'bg-clay-200 text-clay-600'; }   // rojo arcilla
    return 'bg-sage-100 text-sage-700';                                  // por defecto (mismo que "bajo")
}

/** Texto interpretativo que acompaña al puntaje, según nivel. */
function textoNivelEval(string $nivel) {
    if ($nivel === 'bajo') {                                    // verde: todo bien
        return 'Tu evaluación no detectó factores de riesgo significativos. Sigue manteniendo tus hábitos saludables.';
    }
    if ($nivel === 'moderado') {                                // amarillo: atención
        return 'Se detectaron algunos factores asociados a posibles riesgos de salud. Aunque esto no representa un diagnóstico médico, se recomienda realizar una evaluación clínica preventiva.';
    }
    if ($nivel === 'elevado') {                                 // rojo: ver médico
        return 'Se detectaron múltiples factores que ameritan atención. Te recomendamos consultar a un profesional de la salud a la brevedad.';
    }
    return '';                                                  // si llega algo raro, texto vacío
}
