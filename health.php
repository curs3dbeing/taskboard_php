<?php
/**
 * Health check endpoint для Railway
 * Railway автоматически проверяет этот endpoint
 * Этот файл НЕ требует подключения к БД
 */
http_response_code(200);
header('Content-Type: text/plain');
echo "OK";

