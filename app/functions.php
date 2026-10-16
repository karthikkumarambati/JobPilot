<?php
declare(strict_types=1);

function e(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_check(): void {
    $token = $_POST['csrf'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf'] ?? '', $token)) {
        http_response_code(419); exit('Invalid or expired form token. Refresh the page and try again.');
    }
}
function logged_in(): bool { return isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id']); }
function require_login(): void {
    if (!logged_in()) { header('Location: index.php'); exit; }
}
function flash(string $message, string $type='success'): void { $_SESSION['flash'] = ['message'=>$message, 'type'=>$type]; }
function take_flash(): ?array { $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f; }
function clean_url(string $url): string {
    $url = trim($url);
    if (!filter_var($url, FILTER_VALIDATE_URL)) return '';
    $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
    return in_array($scheme, ['http','https'], true) ? $url : '';
}
function profile_data(): array {
    $stmt = db()->query('SELECT * FROM profile WHERE id=1');
    return $stmt->fetch() ?: ['full_name'=>'','target_role'=>'WordPress Developer','locations'=>'Hyderabad, India; Remote','skills'=>'WordPress, PHP, MySQL, JavaScript, WooCommerce','experience_summary'=>'','portfolio_url'=>''];
}
function score_job(string $title, string $description, string $location, array $profile): int {
    $text = strtolower($title . ' ' . $description);
    $skills = array_filter(array_map('trim', explode(',', (string)$profile['skills'])));
    $hits = 0;
    foreach ($skills as $skill) {
        $skill = strtolower($skill);
        if ($skill !== '' && str_contains($text, $skill)) $hits++;
    }
    $skillScore = count($skills) ? min(65, (int)round(($hits / count($skills)) * 65)) : 0;
    $titleLower = strtolower($title);
    $roleScore = (str_contains($titleLower, 'wordpress') ? 20 : 0)
        + ((str_contains($titleLower, 'web developer') || str_contains($titleLower, 'php developer')) ? 8 : 0);
    $loc = strtolower($location . ' ' . (string)$profile['locations']);
    $locationScore = (str_contains(strtolower($location), 'hyderabad') || str_contains(strtolower($location), 'remote') || str_contains(strtolower($location), 'india')) ? 7 : 0;
    return min(100, $skillScore + min(28, $roleScore) + $locationScore);
}
function save_job(array $data): array {
    $company = trim((string)($data['company'] ?? ''));
    $title = trim((string)($data['title'] ?? ''));
    $url = clean_url((string)($data['job_url'] ?? ''));
    if ($company === '' || $title === '' || $url === '') return ['ok'=>false,'message'=>'Company, job title, and a valid http/https job URL are required.'];
    $description = trim((string)($data['description'] ?? ''));
    $location = trim((string)($data['location'] ?? ''));
    $source = trim((string)($data['source'] ?? 'Manual'));
    $profile = profile_data();
    $score = score_job($title, $description, $location, $profile);
    try {
        $stmt = db()->prepare('INSERT INTO jobs (source,company,title,location,job_url,description,posted_at,match_score,status,notes) VALUES (?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([$source,$company,$title,$location,$url,$description,trim((string)($data['posted_at'] ?? '')),$score,'Saved','']);
        return ['ok'=>true,'message'=>'Job saved. Match score: '.$score.'%'];
    } catch (PDOException $ex) {
        if ((string)$ex->getCode() === '23000') return ['ok'=>false,'message'=>'This job URL is already saved.'];
        throw $ex;
    }
}
function career_search_links(): array {
    $q = rawurlencode('"WordPress Developer"');
    $loc = rawurlencode('Hyderabad OR Remote');
    return [
        'LinkedIn' => 'https://www.linkedin.com/jobs/search/?keywords='.$q.'&location='.rawurlencode('Hyderabad, Telangana, India'),
        'Naukri' => 'https://www.naukri.com/wordpress-developer-jobs-in-hyderabad',
        'Indeed' => 'https://in.indeed.com/jobs?q=WordPress+Developer&l=Hyderabad%2C+Telangana',
        'Glassdoor' => 'https://www.glassdoor.co.in/Job/hyderabad-wordpress-developer-jobs-SRCH_IL.0,9_IC2865319_KO10,28.htm',
        'Remote OK' => 'https://remoteok.com/remote-wordpress-jobs',
        'We Work Remotely' => 'https://weworkremotely.com/remote-jobs/search?term=wordpress',
        'Company career pages' => 'https://www.google.com/search?q=WordPress+Developer+careers+Hyderabad+remote',
    ];
}
function http_json(string $url): ?array {
    if (!function_exists('curl_init')) return null;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3,
        CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_TIMEOUT => 18,
        CURLOPT_USERAGENT => 'JobPilotLocal/1.0 (public career board integration)',
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    $body = curl_exec($ch); $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if (!is_string($body) || $status < 200 || $status >= 300) return null;
    $decoded = json_decode($body, true);
    return is_array($decoded) ? $decoded : null;
}
function search_public_boards(): array {
    global $config;
    $added = 0; $errors = [];
    foreach (($config['greenhouse_boards'] ?? []) as $board) {
        $board = trim((string)$board);
        if ($board === '') continue;
        $items = http_json('https://boards-api.greenhouse.io/v1/boards/'.rawurlencode($board).'/jobs?content=true');
        if (!$items || !isset($items['jobs'])) { $errors[] = 'Greenhouse board unavailable: '.$board; continue; }
        foreach ($items['jobs'] as $j) {
            $loc = (string)($j['location']['name'] ?? '');
            $title = (string)($j['title'] ?? '');
            $desc = strip_tags((string)($j['content'] ?? ''));
            if (!preg_match('/wordpress|php|web developer|woocommerce/i', $title.' '.$desc)) continue;
            $r = save_job(['source'=>'Greenhouse','company'=>$board,'title'=>$title,'location'=>$loc,'job_url'=>$j['absolute_url'] ?? '', 'description'=>mb_substr($desc,0,30000),'posted_at'=>$j['updated_at'] ?? '']);
            if ($r['ok']) $added++;
        }
    }
    foreach (($config['lever_sites'] ?? []) as $site) {
        $site = trim((string)$site);
        if ($site === '') continue;
        $items = http_json('https://api.lever.co/v0/postings/'.rawurlencode($site).'?mode=json');
        if (!$items) { $errors[] = 'Lever site unavailable: '.$site; continue; }
        foreach ($items as $j) {
            if (!is_array($j)) continue;
            $title = (string)($j['text'] ?? '');
            $desc = strip_tags((string)($j['descriptionPlain'] ?? ''));
            if (!preg_match('/wordpress|php|web developer|woocommerce/i', $title.' '.$desc)) continue;
            $cats = $j['categories'] ?? [];
            $loc = (string)($cats['location'] ?? '');
            $r = save_job(['source'=>'Lever','company'=>$site,'title'=>$title,'location'=>$loc,'job_url'=>$j['hostedUrl'] ?? '', 'description'=>mb_substr($desc,0,30000),'posted_at'=>$j['createdAt'] ?? '']);
            if ($r['ok']) $added++;
        }
    }
    return ['added'=>$added,'errors'=>$errors,'configured'=>count($config['greenhouse_boards'] ?? []) + count($config['lever_sites'] ?? [])];
}
function generate_draft(array $job, array $profile): array {
    global $config;
    $prompt = "Create a truthful job application draft. Do not invent employers, credentials, achievements, metrics, or experience. If details are missing, use neutral wording or mark [CUSTOMIZE]. Return JSON with keys cover_letter and cv_bullets (array of 3-5 suggestions). Keep the cover letter under 300 words.\n\nCANDIDATE PROFILE:\nName: {$profile['full_name']}\nTarget role: {$profile['target_role']}\nSkills: {$profile['skills']}\nExperience: {$profile['experience_summary']}\nPortfolio: {$profile['portfolio_url']}\n\nJOB:\nCompany: {$job['company']}\nTitle: {$job['title']}\nLocation: {$job['location']}\nDescription:\n".mb_substr((string)$job['description'],0,12000);
    $provider = $config['ai']['provider'];
    if ($provider === 'none' || $config['ai']['model'] === '') {
        return ['cover_letter'=>"Dear Hiring Team,\n\nI am interested in the {$job['title']} opportunity at {$job['company']}. My background includes WordPress development, PHP, MySQL, responsive front-end development, and website maintenance. I would welcome the opportunity to discuss how my experience aligns with your requirements.\n\nPortfolio: {$profile['portfolio_url']}\n\nPlease review and customize this draft with specific, verified examples before sending.\n\nRegards,\n".($profile['full_name'] ?: '[Your name]'),
            'cv_bullets'=>[
                'Developed and maintained responsive WordPress websites using PHP, HTML, CSS, and JavaScript. [Verify and tailor to your actual projects.]',
                'Customized WordPress themes and plugins to meet project requirements. [Add only verified examples.]',
                'Worked with MySQL and website performance/SEO improvements. [Include concrete results only if documented.]'
            ], 'provider'=>'local template'];
    }
    if (!function_exists('curl_init')) throw new RuntimeException('PHP cURL extension is required for AI integration.');
    $base = $config['ai']['base_url'];
    $payload = json_encode(['model'=>$config['ai']['model'],'messages'=>[
        ['role'=>'system','content'=>'You are a careful job application writing assistant. Never fabricate facts. Return valid JSON only.'],
        ['role'=>'user','content'=>$prompt]
    ],'temperature'=>0.3,'response_format'=>['type'=>'json_object']], JSON_UNESCAPED_SLASHES);
    $headers = ['Content-Type: application/json'];
    if ($config['ai']['api_key'] !== '') $headers[] = 'Authorization: Bearer '.$config['ai']['api_key'];
    $ch = curl_init($base.'/chat/completions');
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$payload,CURLOPT_HTTPHEADER=>$headers,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>60]);
    $body = curl_exec($ch); $status = (int)curl_getinfo($ch,CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
    if (!is_string($body) || $status < 200 || $status >= 300) throw new RuntimeException('AI request failed (HTTP '.$status.'). '.$err);
    $response = json_decode($body,true);
    $content = $response['choices'][0]['message']['content'] ?? '';
    $result = json_decode((string)$content,true);
    if (!is_array($result)) throw new RuntimeException('AI returned invalid JSON. Try another model or use the local template.');
    $bullets = $result['cv_bullets'] ?? [];
    if (is_string($bullets)) $bullets = [$bullets];
    return ['cover_letter'=>(string)($result['cover_letter'] ?? ''),'cv_bullets'=>array_values(array_map('strval',is_array($bullets)?$bullets:[])),'provider'=>$provider];
}
