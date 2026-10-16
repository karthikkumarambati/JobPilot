<?php
require_once dirname(__DIR__).'/app/bootstrap.php';
$done = false; $error = '';
try {
    // Create tables automatically on first visit, after the database itself exists.
    $schema = file_get_contents(dirname(__DIR__).'/app/schema.sql');
    foreach (array_filter(array_map('trim', explode(';', (string)$schema))) as $statement) {
        db()->exec($statement);
    }
    $count = (int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($count > 0) $done = true;
} catch (Throwable $e) {
    $error = 'Database connection error. Create the jobpilot database and check .env credentials. Details: '.e($e->getMessage());
}
if ($_SERVER['REQUEST_METHOD']==='POST' && !$done && !$error) {
    csrf_check();
    $email = filter_var(trim((string)($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
    $pass = (string)($_POST['password'] ?? '');
    if (!$email || strlen($pass)<12) $error = 'Enter a valid email and a password of at least 12 characters.';
    else {
        try {
            $sql = file_get_contents(dirname(__DIR__).'/app/schema.sql');
            foreach (array_filter(array_map('trim', explode(';',$sql))) as $statement) db()->exec($statement);
            $stmt=db()->prepare('INSERT INTO users(email,password_hash) VALUES(?,?)');
            $stmt->execute([$email,password_hash($pass,PASSWORD_DEFAULT)]);
            $done=true;
        } catch(Throwable $e) { $error='Setup failed: '.e($e->getMessage()); }
    }
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>JobPilot setup</title><link rel="stylesheet" href="assets/app.css"></head><body><main class="auth"><h1>JobPilot setup</h1>
<?php if($done): ?><p class="notice">Setup complete or already initialized. Delete or rename <code>public/setup.php</code> now, then open the dashboard.</p><a class="button" href="index.php">Open dashboard</a>
<?php else: ?><p>Create your first local administrator account.</p><?php if($error): ?><p class="error"><?= $error ?></p><?php endif; ?><form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><label>Email<input type="email" name="email" required></label><label>Password (12+ characters)<input type="password" name="password" minlength="12" required></label><button>Create admin account</button></form><?php endif; ?></main></body></html>
