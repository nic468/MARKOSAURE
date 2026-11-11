<?php
/**
 * Conexão com o banco de dados via PDO
 * Ajuste as constantes abaixo conforme seu ambiente (XAMPP por padrão)
 */

declare(strict_types=1);

const DB_HOST = "localhost";
const DB_NAME = "u517376960_BJ3vm";
const DB_USER = "u517376960_9mU2q";
const DB_PASS = "NIubOrl?aa>:YR88";
const DB_CHARSET = 'utf8mb4';

function getConnection(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'sucesso' => false,
                'erro'    => 'Falha na conexão com o banco de dados: ' . $e->getMessage(),
            ]);
            exit;
        }
    }

    return $pdo;
}
