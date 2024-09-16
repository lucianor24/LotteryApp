<?php
// Mostrar todos los errores para depuración
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Función para obtener los datos del feed RSS
function obtener_datos_rss($url) {
    // Verificamos si el feed se carga correctamente
    $rss = simplexml_load_file($url);
    if (!$rss) {
        die("Error: No se pudo cargar el feed RSS desde la URL $url");
    }

    $datos = [];

    foreach ($rss->channel->item as $item) {
        $titulo = (string) $item->title;
        $pubDate = (string) $item->pubDate;

        // Extraer los números que aparecen después de ":" en el título
        if (preg_match('/: (\d+(?:-\d+)+)/', $titulo, $coincidencias)) {
            $numeros = explode('-', $coincidencias[1]);
            // Incluir el título completo, los números extraídos y la fecha
            $datos[] = ['titulo' => $titulo, 'numeros' => $numeros, 'pubDate' => $pubDate];
        }
    }

    return $datos;
}

// Función para guardar los datos en el archivo especificado
function guardar_datos($datos, $ruta_archivo) {
    // Verificar si el archivo ya existe y es escribible
    if (!file_exists($ruta_archivo)) {
        die("Error: El archivo $ruta_archivo no existe.");
    }

    if (!is_writable($ruta_archivo)) {
        die("Error: No se puede escribir en el archivo $ruta_archivo.");
    }

    // Leer los datos existentes en el archivo JSON
    $datos_existentes = json_decode(file_get_contents($ruta_archivo), true) ?? [];

    foreach ($datos as $dato) {
        // Verificar si ya existe un registro con el mismo pubDate
        $existe = false;
        foreach ($datos_existentes as $registro) {
            if ($registro['pubDate'] == $dato['pubDate']) {
                $existe = true;
                break;
            }
        }
        // Si no existe, agregarlo a los datos
        if (!$existe) {
            $datos_existentes[] = $dato;
        }
    }

    // Guardar los datos en el archivo
    if (!file_put_contents($ruta_archivo, json_encode($datos_existentes, JSON_PRETTY_PRINT))) {
        die("Error: No se pudo escribir en el archivo $ruta_archivo.");
    }
}

// Lista de URLs de feeds RSS
$feeds_rss = [
    'https://enloteria.com/rss/florida-tarde',
    'https://enloteria.com/rss/new-york-tarde',
    'https://enloteria.com/rss/florida-noche',
    'https://enloteria.com/rss/new-york-noche',
    // Agrega más URLs de feeds aquí
];
// Archivo único para guardar todos los datos
$archivo_unico = __DIR__ . '/numeros-americanas.json';

// Iterar sobre cada URL del feed RSS
foreach ($feeds_rss as $feed_url) {
    // Extraer todos los datos del feed RSS
    $datos_todos = obtener_datos_rss($feed_url);

    // Guardar los datos en el archivo único
    guardar_datos($datos_todos, $archivo_unico);

    echo "Datos del feed $feed_url guardados correctamente en $archivo_unico.\n";
}
?>