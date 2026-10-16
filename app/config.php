<?php
declare(strict_types=1);

function load_env_file(string $path): void {
    if (!is_file($path)) return;
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim(trim($value), "\"'");
        if ($key !== '' && getenv($key) === false) putenv($key . '=' . $value);
    }
}
load_env_file(dirname(__DIR__) . '/.env');

return [
    'app_name' => getenv('APP_NAME') ?: 'JobPilot',
    'app_url' => rtrim(getenv('APP_URL') ?: 'http://localhost/jobpilot/public', '/'),
    'app_secret' => getenv('APP_SECRET') ?: 'unsafe-development-secret-change-me',
    'db' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => getenv('DB_PORT') ?: '3306',
        'name' => getenv('DB_NAME') ?: 'jobpilot',
        'user' => getenv('DB_USER') ?: 'root',
        'pass' => getenv('DB_PASS') ?: '',
    ],
    'ai' => [
        'provider' => strtolower(getenv('AI_PROVIDER') ?: 'none'),
        'base_url' => rtrim(getenv('AI_BASE_URL') ?: 'https://api.openai.com/v1', '/'),
        'api_key' => getenv('AI_API_KEY') ?: '',
        'model' => getenv('AI_MODEL') ?: '',
    ],
    // Add public board identifiers only. These are not portal login credentials.
    'greenhouse_boards' => [],
    'lever_sites' => [],
    'allowed_resume_extensions' => ['pdf', 'doc', 'docx', 'txt'],
];
