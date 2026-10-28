<?php
// PDO connection - update constants as needed for your environment
declare(strict_types=1);
// Never display raw errors/warnings to visitors — they leak absolute server
// file paths and stack traces (confirmed live). Belt-and-suspenders with the
// .htaccess override, since not every file requires this one before running.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
// SameSite=Strict stops the browser from attaching this session cookie to ANY
// cross-site request — including the plain GET links used throughout the
// admin/director actions (approve, decline, delete) — which is what actually
// closes CSRF on those, short of rewriting every action to use tokenized POSTs.
// HttpOnly blocks an XSS payload from reading the cookie to steal a session.
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'samesite' => 'Strict',
        'httponly' => true,
        'secure' => false, // set true once this is served over HTTPS
    ]);
}
session_start();
const DB_HOST = '127.0.0.1';
const DB_NAME = 'fprs';
const DB_USER = 'root';
const DB_PASS = '';
// Default password for seeded accounts (change after initial login)
const DEFAULT_USER_PASSWORD = '1234';
// Base path of the application (use the folder name under htdocs). Example: '/fprs'
const BASE_PATH = '/fprs';

function pdo_connect(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4';
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    }
    return $pdo;
}
