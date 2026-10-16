<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$required = [
  'app/config.php','app/bootstrap.php','app/db.php','app/functions.php','app/schema.sql',
  'public/index.php','public/setup.php','public/assets/app.css','public/assets/app.js','.env.example'
];
$fail = [];
foreach ($required as $file) {
  if (!is_file($root.'/'.$file)) $fail[] = "Missing: $file";
}
if (PHP_VERSION_ID < 80100) $fail[] = 'PHP 8.1 or later is required.';
foreach (['pdo_mysql','curl','json','mbstring'] as $ext) {
  if (!extension_loaded($ext)) $fail[] = "PHP extension missing: $ext";
}
if ($fail) {
  fwrite(STDERR, "JobPilot smoke test FAILED:\n- ".implode("\n- ",$fail)."\n");
  exit(1);
}
echo "JobPilot smoke test passed. This checks files/runtime extensions only; run the manual functional checklist in README.md.\n";
