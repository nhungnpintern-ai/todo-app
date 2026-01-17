<?php
$host = "mysql323.phy.lolipop.lan";
$dbname = "LAA1606342-intership";
$user = "LAA1606342";
$pass = "ITpass2024";
$charset = "utf8mb4";

$dsn = "mysql:host=$host;dbname=$dbname";

$options = [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
  $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
  exit("DB接続エラー: " . $e->getMessage());
}
