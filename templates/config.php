<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
date_default_timezone_set('America/Recife');

/** --- Firebase (para o front injetar) --- */
$firebaseConfig = [
  'apiKey'     => '',
  'authDomain' => '',
  'projectId'  => '',
  // 'appId' => '',
  // 'storageBucket' => '',
];
$appId       = 'cehab-portal';
$customToken = null; // se tiver JWT de custom token, coloque aqui

/** --- Bancos de dados --- */
$local = [
  'host' => 'localhost',
  'user' => 'root',
  'pass' => '',
  'name' => 'sistema_acompanhamento',
  'port' => 3306,
];

$remoto = [
  'host' => '172.19.16.15',
  'user' => 'siscreche',
  'pass' => 'Cehab@123_',
  'name' => 'cehab_online',
  'port' => 3306,
];

$connLocal = new mysqli($local['host'], $local['user'], $local['pass'], $local['name'], $local['port']);
if ($connLocal->connect_error) {
  die('Falha na conexão (local): ' . $connLocal->connect_error);
}
$connLocal->set_charset('utf8mb4');

$connRemoto = new mysqli($remoto['host'], $remoto['user'], $remoto['pass'], $remoto['name'], $remoto['port']);
if ($connRemoto->connect_error) {
  die('Falha na conexão (remota): ' . $connRemoto->connect_error);
}
$connRemoto->set_charset('utf8mb4');

$conn     = $connLocal;
$conexao  = $connLocal;
$conexao2 = $connRemoto;
