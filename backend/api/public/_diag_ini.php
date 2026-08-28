<?php
// Diagnostico temporal: confirma que los limites de subida se aplicaron
// en el contenedor de Render. Se borra apenas se confirma (ver commit
// siguiente) -- no toca datos ni queda expuesto permanentemente.
header('Content-Type: application/json');
echo json_encode([
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'max_file_uploads' => ini_get('max_file_uploads'),
    'memory_limit' => ini_get('memory_limit'),
]);
