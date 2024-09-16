<?php

// Configuración de la zona horaria a República Dominicana
date_default_timezone_set('America/Santo_Domingo');

function obtener_predicciones_guardadas($archivo_predicciones) {
    if (!file_exists($archivo_predicciones)) {
        return [];
    }
    $contenido = file_get_contents($archivo_predicciones);
    if ($contenido === false) {
        throw new Exception("No se pudo leer el archivo de predicciones.");
    }
    $predicciones = json_decode($contenido, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Error al decodificar el archivo JSON: " . json_last_error_msg());
    }
    return $predicciones;
}

function guardar_prediccion($archivo_predicciones, $fechas_prediccion, $prediccion) {
    $predicciones = obtener_predicciones_guardadas($archivo_predicciones);
    
    // Convertir todos los números a cadenas de dos dígitos para mantener el formato correcto
    $prediccion_normalizada = array_map(function($numero) {
        return sprintf('%02d', $numero);
    }, $prediccion);

    // Verificar si ya existe un grupo de fechas con la misma predicción
    foreach ($predicciones as $prediccion_guardada) {
        if ($prediccion_guardada['fechas'] === $fechas_prediccion && 
            $prediccion_guardada['prediccion'] === $prediccion_normalizada) {
            // Si ya existe la predicción con las mismas fechas, no se duplica
            return;
        }
    }

    // Si no existe, agregar la nueva predicción
    $predicciones[] = [
        'fechas' => $fechas_prediccion,
        'prediccion' => $prediccion_normalizada
    ];

    $resultado = file_put_contents($archivo_predicciones, json_encode($predicciones, JSON_PRETTY_PRINT));
    if ($resultado === false) {
        throw new Exception("No se pudo escribir en el archivo de predicciones.");
    }
}

function convertir_fecha_a_formato_corto($fecha_larga) {
    // Convierte la fecha del formato "Sun, 01 Sep 2024 09:08:30 -0400" al formato "d-m-Y"
    return date('d-m-Y', strtotime($fecha_larga));
}

function calcular_estadisticas($archivo_json, $archivo_predicciones) {
    if (!file_exists($archivo_json)) {
        throw new Exception("El archivo de números no existe.");
    }

    $contenido_json = file_get_contents($archivo_json);
    if ($contenido_json === false) {
        throw new Exception("No se pudo leer el archivo de números.");
    }

    $datos = json_decode($contenido_json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Error al decodificar el archivo JSON: " . json_last_error_msg());
    }

    $conteo_numeros = [];
    $numeros_totales = [];
    $aciertos_totales = 0;
    $conteo_aciertos = 0;

    // Obtener la fecha actual de internet usando la zona horaria de República Dominicana
    $fecha_actual = date('d-m-Y');
    
    // Generar las fechas de predicción para los próximos tres días
    $fechas_prediccion = [
        date('d-m-Y', strtotime('+1 day')),  // Día 1 después de la fecha actual
        date('d-m-Y', strtotime('+2 days')), // Día 2 después de la fecha actual
        date('d-m-Y', strtotime('+3 days'))  // Día 3 después de la fecha actual
    ];

    // Calcular la predicción para los próximos tres días
    foreach ($datos as $registro) {
        $primer_numero = sprintf('%02d', $registro['numeros'][0]);
        if (!isset($conteo_numeros[$primer_numero])) {
            $conteo_numeros[$primer_numero] = 0;
        }
        $conteo_numeros[$primer_numero]++;
        $numeros_totales[] = $primer_numero;
    }

    arsort($conteo_numeros);

    $indice_prediccion = calcular_indice_prediccion($conteo_numeros, $numeros_totales);
    arsort($indice_prediccion);
    $prediccion_tres_dias = array_slice($indice_prediccion, 0, 5, true);

    // Guardar o actualizar la predicción con el rango de fechas correcto
    guardar_prediccion($archivo_predicciones, $fechas_prediccion, array_keys($prediccion_tres_dias));

    // Obtener las predicciones guardadas
    $predicciones_guardadas = obtener_predicciones_guardadas($archivo_predicciones);

    // Comparar números reales con predicciones guardadas
    foreach ($datos as $registro) {
        // Convertir la fecha de publicación al formato corto
        $pubDate_formato_corto = convertir_fecha_a_formato_corto($registro['pubDate']);
        
        // Obtener el primer número de los tres
        $numero_en_primera_posicion = sprintf('%02d', $registro['numeros'][0]);
        
        foreach ($predicciones_guardadas as $prediccion_guardada) {
            // Verificar si la fecha coincide
            if (in_array($pubDate_formato_corto, $prediccion_guardada['fechas'])) {
                $conteo_aciertos++;
                // Verificar si el primer número coincide con la predicción
                if (in_array($numero_en_primera_posicion, $prediccion_guardada['prediccion'])) {
                    $aciertos_totales++;
                }
            }
        }
    }

    $porcentaje_aciertos = ($conteo_aciertos > 0) ? ($aciertos_totales / $conteo_aciertos) * 100 : 0;

    return [
        'calientes' => array_slice($conteo_numeros, 0, 10, true),
        'frios' => array_slice($conteo_numeros, -10, 10, true),
        'media' => array_sum($numeros_totales) / count($numeros_totales),
        'mediana' => calcular_mediana($numeros_totales),
        'moda' => calcular_moda($numeros_totales),
        'desviacion_estandar' => calcular_desviacion_estandar($numeros_totales, array_sum($numeros_totales) / count($numeros_totales)),
        'varianza' => calcular_desviacion_estandar($numeros_totales, array_sum($numeros_totales) / count($numeros_totales)) ** 2,
        'rango' => max($numeros_totales) - min($numeros_totales),
        'coeficiente_variacion' => (array_sum($numeros_totales) / count($numeros_totales) != 0) ? calcular_desviacion_estandar($numeros_totales, array_sum($numeros_totales) / count($numeros_totales)) / (array_sum($numeros_totales) / count($numeros_totales)) : 0,
        'prediccion_tres_dias' => $prediccion_tres_dias,
        'porcentaje_aciertos' => $porcentaje_aciertos,
        'conteo_aciertos' => $aciertos_totales,
        'fechas_prediccion' => $fechas_prediccion // Mostramos el rango de fechas correcto
    ];
}

function calcular_mediana($valores) {
    $cuenta = count($valores);
    $mitad = floor($cuenta / 2);
    return $cuenta % 2 ? $valores[$mitad] : ($valores[$mitad - 1] + $valores[$mitad]) / 2;
}

function calcular_moda($valores) {
    $frecuencias = array_count_values($valores);
    arsort($frecuencias);
    $moda = array_keys($frecuencias, max($frecuencias));
    return $moda[0];
}

function calcular_desviacion_estandar($valores, $media) {
    $sumatoria = array_reduce($valores, function($carry, $valor) use ($media) {
        return $carry + pow($valor - $media, 2);
    });
    return sqrt($sumatoria / count($valores));
}

function calcular_indice_prediccion($conteo_numeros, $numeros_totales) {
    $indice_prediccion = [];
    $media = array_sum($numeros_totales) / count($numeros_totales);
    $desviacion_estandar = calcular_desviacion_estandar($numeros_totales, $media);
    $moda = calcular_moda($numeros_totales);
    $coeficiente_variacion = ($media != 0) ? $desviacion_estandar / $media : 0;

    foreach ($conteo_numeros as $numero => $frecuencia) {
        // Incrementar el índice de predicción basado en múltiples factores
        $indice_prediccion[$numero] = $frecuencia + 
            (0.3 * ($numero == $moda ? 1 : 0)) + 
            (0.2 * abs($numero - $media)) + 
            (0.2 * (1 / ($desviacion_estandar + 1))) + 
            (0.1 * (1 / ($coeficiente_variacion + 1)));
    }

    return $indice_prediccion;
}

// Inicio del procesamiento principal con manejo de excepciones
try {
    header('Content-Type: application/json');

    $archivo_json = 'numeros-americanas.json';
    $archivo_predicciones = 'predicciones-americanas.json';
    $estadisticas = calcular_estadisticas($archivo_json, $archivo_predicciones);

    echo json_encode($estadisticas, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    // Capturar cualquier excepción y mostrar el mensaje de error
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

?>