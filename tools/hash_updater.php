<?php
// Run this ONCE to encrypt all current plain-text passwords
require_once '../config/db.php';
$users = $pdo->query("SELECT id_usuario, login_password FROM usuarios")->fetchAll();
foreach ($users as $u) {
    // Only hash if it doesn't look like a hash already (length check)
    if (strlen($u['login_password']) < 60) { 
        $newHash = password_hash($u['login_password'], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE usuarios SET login_password = ? WHERE id_usuario = ?");
        $stmt->execute([$newHash, $u['id_usuario']]);
        echo "Updated User ID: " . $u['id_usuario'] . "<br>";
    }
}
echo "Migration Complete.";
?>