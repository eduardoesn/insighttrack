<?php
declare(strict_types=1);

/**
 * Cria uma conexão PDO por solicitação usando a configuração do Compose.
 * Os valores padrão servem apenas para o ambiente local de demonstração.
 */
function database(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = getenv('DB_HOST') ?: 'db';
    $port = getenv('DB_PORT') ?: '5432';
    $name = getenv('DB_NAME') ?: 'insighttrack';
    $user = getenv('DB_USER') ?: 'insighttrack';
    $password = getenv('DB_PASSWORD') ?: 'insighttrack_dev';
    $pdo = new PDO("pgsql:host={$host};port={$port};dbname={$name}", $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}
