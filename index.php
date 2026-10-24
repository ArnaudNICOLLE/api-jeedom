<?php
header('Content-Type: application/json; charset=utf-8');

// --- Configuration via variables d'environnement (définies dans Coolify) ---
$API_KEY = getenv('API_KEY');
$DB_HOST = getenv('DB_HOST');
$DB_PORT = getenv('DB_PORT') ?: '3306';
$DB_NAME = getenv('DB_NAME');
$DB_USER = getenv('DB_USER');
$DB_PASS = getenv('DB_PASS');

function reponse($code, $data) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

// --- Méthode ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    reponse(405, ['ok' => false, 'erreur' => 'POST uniquement']);
}

// --- Authentification par clé secrète ---
$cle = $_SERVER['HTTP_X_API_KEY'] ?? '';
if (!$API_KEY || !hash_equals($API_KEY, $cle)) {
    reponse(401, ['ok' => false, 'erreur' => 'Cle API invalide']);
}

// --- Lecture du JSON ---
$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) {
    reponse(400, ['ok' => false, 'erreur' => 'JSON invalide']);
}

$batiment = trim($in['batiment'] ?? '');
$parametre = trim($in['parametre'] ?? '');
if ($batiment === '' || $parametre === '') {
    reponse(400, ['ok' => false, 'erreur' => 'batiment et parametre obligatoires']);
}

// Valeur numerique OU texte
$valeur_chiffre = (isset($in['valeur_chiffre']) && is_numeric($in['valeur_chiffre']))
    ? $in['valeur_chiffre'] : null;
$valeur_texte = (isset($in['valeur_texte']) && $in['valeur_texte'] !== '')
    ? (string)$in['valeur_texte'] : null;

// Horodatage : celui fourni, sinon heure du serveur
$horodatage = $in['horodatage'] ?? date('Y-m-d H:i:s');

// --- Insertion ---
try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER, $DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
    );
    $stmt = $pdo->prepare(
        'INSERT INTO tablededonnees (Horodatage, Batiment, Parametre, Valeur_chiffre, Valeur_texte)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$horodatage, $batiment, $parametre, $valeur_chiffre, $valeur_texte]);
    reponse(200, ['ok' => true, 'id' => $pdo->lastInsertId()]);
} catch (Exception $e) {
    error_log($e->getMessage());
    reponse(500, ['ok' => false, 'erreur' => 'Erreur base de donnees']);
}
