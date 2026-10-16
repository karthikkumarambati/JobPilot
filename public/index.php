<?php
require_once dirname(__DIR__).'/app/bootstrap.php';
$action = (string)($_POST['action'] ?? '');
$error = '';
if (!logged_in() && $_SERVER['REQUEST_METHOD']==='POST' && $action==='login') {
    csrf_check();
    $email = trim((string)($_POST['email'] ?? '')); $password=(string)($_POST['password'] ?? '');
    $stmt=db()->prepare('SELECT id,password_hash FROM users WHERE email=?'); $stmt->execute([$email]); $user=$stmt->fetch();
    if ($user && password_verify($password,$user['password_hash'])) {
        session_regenerate_id(true); $_SESSION['user_id']=(int)$user['id']; $_SESSION['csrf']=bin2hex(random_bytes(32));
        header('Location: index.php'); exit;
    }
    $error='Invalid email or password.';
}
if (logged_in() && $_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    try {
        if ($action==='add_job') {
            $r=save_job($_POST); flash($r['message'],$r['ok']?'success':'error');
        } elseif ($action==='update_status') {
            $allowed=['Saved','To review','Draft ready','Applied','Interview','Rejected','Offer','Not a fit'];
            $status=(string)($_POST['status']??'Saved');
            if (!in_array($status,$allowed,true)) throw new RuntimeException('Invalid status.');
            $stmt=db()->prepare('UPDATE jobs SET status=?,notes=? WHERE id=?');
            $stmt->execute([$status,trim((string)($_POST['notes']??'')),(int)$_POST['job_id']]);
            flash('Job updated.');
        } elseif ($action==='save_profile') {
            $stmt=db()->prepare('UPDATE profile SET full_name=?,target_role=?,locations=?,skills=?,experience_summary=?,portfolio_url=? WHERE id=1');
            $stmt->execute([trim((string)$_POST['full_name']),trim((string)$_POST['target_role']),trim((string)$_POST['locations']),trim((string)$_POST['skills']),trim((string)$_POST['experience_summary']),clean_url((string)$_POST['portfolio_url'])]);
            $jobs=db()->query('SELECT id,title,description,location FROM jobs')->fetchAll(); $p=profile_data();
            $upd=db()->prepare('UPDATE jobs SET match_score=? WHERE id=?');
            foreach($jobs as $j) $upd->execute([score_job($j['title'],$j['description'],$j['location'],$p),$j['id']]);
            flash('Profile saved and scores refreshed.');
        } elseif ($action==='search_boards') {
            $r=search_public_boards(); flash('Career-board search complete. New jobs imported: '.$r['added'].'. Configured boards: '.$r['configured'].'. '.implode(' ',$r['errors']), 'success');
        } elseif ($action==='generate_draft') {
            $id=(int)($_POST['job_id']??0); $stmt=db()->prepare('SELECT * FROM jobs WHERE id=?'); $stmt->execute([$id]); $job=$stmt->fetch();
            if (!$job) throw new RuntimeException('Job not found.');
            $draft=generate_draft($job,profile_data());
            $stmt=db()->prepare('INSERT INTO drafts(job_id,cover_letter,cv_bullets) VALUES(?,?,?)');
            $stmt->execute([$id,$draft['cover_letter'],json_encode($draft['cv_bullets'],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)]);
            db()->prepare("UPDATE jobs SET status='Draft ready' WHERE id=?")->execute([$id]);
            flash('Application draft generated using '.$draft['provider'].'. Review every claim before use.');
        }
        if (isset($_POST['redirect_to'])) { header('Location: index.php#'.preg_replace('/[^a-z0-9_-]/i','',(string)$_POST['redirect_to'])); exit; }
    } catch(Throwable $e) { flash('Action failed: '.$e->getMessage(),'error'); }
    header('Location: index.php'); exit;
}
if (!logged_in()) {
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>JobPilot — Sign in</title><link rel="stylesheet" href="assets/app.css"></head><body><main class="auth"><div class="brand-mark">JP</div><h1>JobPilot</h1><p>Personal AI job search and application tracker</p><?php if($error):?><p class="error"><?=e($error)?></p><?php endif;?><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="login"><label>Email<input name="email" type="email" required autocomplete="username"></label><label>Password<input name="password" type="password" required autocomplete="current-password"></label><button type="submit">Sign in</button></form><p class="muted">First run? Complete <a href="setup.php">local setup</a>.</p></main></body></html><?php exit; }

$p=profile_data(); $flash=take_flash();
$search=(string)($_GET['q']??''); $statusFilter=(string)($_GET['status']??'');
$sql='SELECT * FROM jobs WHERE (company LIKE ? OR title LIKE ? OR location LIKE ? OR description LIKE ?)'; $params=array_fill(0,4,'%'.$search.'%');
if ($statusFilter!=='') { $sql.=' AND status=?'; $params[]=$statusFilter; }
$sql.=' ORDER BY match_score DESC, created_at DESC LIMIT 250';
$stmt=db()->prepare($sql); $stmt->execute($params); $jobs=$stmt->fetchAll();
$stats=db()->query("SELECT COUNT(*) total, SUM(status='Applied') applied, SUM(status='Interview') interviews, SUM(status='Draft ready') drafts FROM jobs")->fetch();
$links=career_search_links();
if (isset($_GET['export']) && $_GET['export']==='csv') {
    header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="jobpilot-applications.csv"');
    $out=fopen('php://output','w'); fputcsv($out,['Company','Title','Location','Source','URL','Match score','Status','Created']);
    foreach($jobs as $j) fputcsv($out,[$j['company'],$j['title'],$j['location'],$j['source'],$j['job_url'],$j['match_score'],$j['status'],$j['created_at']]);
    fclose($out); exit;
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>JobPilot dashboard</title><link rel="stylesheet" href="assets/app.css"><script defer src="assets/app.js"></script></head><body>
<header class="topbar"><a class="brand" href="index.php"><span class="brand-mark">JP</span> JobPilot</a><nav><a href="#jobs">Jobs</a><a href="#profile">Profile</a><a href="#sources">Search sources</a><a href="logout.php">Sign out</a></nav></header>
<main class="wrap"><section class="hero"><div><p class="eyebrow">LOCAL-FIRST JOB COPILOT</p><h1>Find your next role.<br><span>Apply with intention.</span></h1><p>WordPress Developer · Hyderabad · Remote opportunities</p></div><form method="post" class="inline-form"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="search_boards"><button type="submit">Search public career boards</button></form></section>
<?php if($flash):?><div class="notice <?=e($flash['type'])?>"><?=e($flash['message'])?></div><?php endif;?>
<section class="stats"><article><span>Saved jobs</span><strong><?= (int)$stats['total']?></strong></article><article><span>Applications</span><strong><?= (int)$stats['applied']?></strong></article><article><span>Interviews</span><strong><?= (int)$stats['interviews']?></strong></article><article><span>Drafts ready</span><strong><?= (int)$stats['drafts']?></strong></article></section>
<section id="sources" class="panel"><div class="section-heading"><div><p class="eyebrow">DISCOVER</p><h2>Search job sources</h2></div><span class="muted">Opens each site's search page</span></div><div class="source-grid"><?php foreach($links as $name=>$url):?><a class="source-link" href="<?=e($url)?>" target="_blank" rel="noopener noreferrer"><span><?=e($name)?></span><span>↗</span></a><?php endforeach;?></div><p class="hint">Portal search shortcuts are user-driven. Only configured public Greenhouse/Lever boards are imported automatically; this app does not bypass login walls, CAPTCHA, or portal restrictions.</p></section>
<section class="panel"><div class="section-heading"><div><p class="eyebrow">ADD A LISTING</p><h2>Save a job</h2></div></div><form method="post" class="job-form"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="add_job"><label>Company<input name="company" required maxlength="190" placeholder="Company name"></label><label>Job title<input name="title" required maxlength="255" placeholder="WordPress Developer"></label><label>Location<input name="location" placeholder="Hyderabad / Remote"></label><label>Source<select name="source"><option>Manual</option><option>LinkedIn</option><option>Naukri</option><option>Indeed</option><option>Glassdoor</option><option>Company career page</option><option>Remote job board</option></select></label><label class="wide">Job URL<input name="job_url" type="url" required placeholder="https://..."></label><label class="wide">Job description<textarea name="description" rows="4" placeholder="Paste the public job description for better matching..."></textarea></label><button type="submit">Save and score job</button></form></section>
<section id="jobs" class="panel"><div class="section-heading"><div><p class="eyebrow">PIPELINE</p><h2>Your jobs</h2></div><a class="button secondary" href="?export=csv">Export CSV</a></div><form class="filters" method="get"><input name="q" value="<?=e($search)?>" placeholder="Search title, company, skills..."><select name="status"><option value="">All statuses</option><?php foreach(['Saved','To review','Draft ready','Applied','Interview','Rejected','Offer','Not a fit'] as $s):?><option <?= $statusFilter===$s?'selected':''?>><?=e($s)?></option><?php endforeach;?></select><button>Filter</button></form>
<?php if(!$jobs):?><div class="empty"><strong>No jobs yet</strong><p>Search a portal above, or save a listing URL to start building your pipeline.</p></div><?php else:?><div class="job-list"><?php foreach($jobs as $j):?><article class="job-card"><div class="job-main"><div class="job-title-row"><h3><?=e($j['title'])?></h3><span class="score <?=((int)$j['match_score']>=70?'high':((int)$j['match_score']>=45?'mid':'low'))?>"><?= (int)$j['match_score']?>% match</span></div><p class="company"><?=e($j['company'])?> <span>·</span> <?=e($j['location'] ?: 'Location not listed')?> <span>·</span> <?=e($j['source'])?></p><p class="description"><?=e(mb_substr($j['description'],0,260))?><?=mb_strlen($j['description'])>260?'…':''?></p><div class="job-actions"><a href="<?=e($j['job_url'])?>" target="_blank" rel="noopener noreferrer">Open original listing ↗</a><form method="post" class="inline-form"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="generate_draft"><input type="hidden" name="job_id" value="<?=(int)$j['id']?>"><button class="small" type="submit">Generate application draft</button></form><button type="button" class="small secondary" data-toggle="details-<?= (int)$j['id']?>">Update status</button></div><div class="details hidden" id="details-<?= (int)$j['id']?>"><form method="post" class="status-form"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="update_status"><input type="hidden" name="job_id" value="<?=(int)$j['id']?>"><select name="status"><?php foreach(['Saved','To review','Draft ready','Applied','Interview','Rejected','Offer','Not a fit'] as $s):?><option <?= $j['status']===$s?'selected':''?>><?=e($s)?></option><?php endforeach;?></select><input name="notes" value="<?=e($j['notes'])?>" placeholder="Follow-up notes"><button class="small">Save</button></form></div><?php $ds=db()->prepare('SELECT * FROM drafts WHERE job_id=? ORDER BY id DESC LIMIT 1');$ds->execute([$j['id']]);$draft=$ds->fetch(); if($draft):?><details class="draft"><summary>Latest application draft</summary><h4>Cover letter</h4><pre><?=e($draft['cover_letter'])?></pre><button type="button" class="small secondary" data-copy="draft-cover-<?= (int)$draft['id']?>">Copy cover letter</button><textarea class="copy-source hidden" id="draft-cover-<?= (int)$draft['id']?>"><?=e($draft['cover_letter'])?></textarea><h4>Suggested CV bullets</h4><pre><?=e($draft['cv_bullets'])?></pre></details><?php endif;?></div></article><?php endforeach;?></div><?php endif;?></section>
<section id="profile" class="panel"><div class="section-heading"><div><p class="eyebrow">PERSONALIZATION</p><h2>Candidate profile</h2></div><span class="muted">Used for matching and AI drafts</span></div><form method="post" class="job-form"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="save_profile"><label>Full name<input name="full_name" value="<?=e($p['full_name'])?>"></label><label>Target role<input name="target_role" value="<?=e($p['target_role'])?>" required></label><label class="wide">Locations<input name="locations" value="<?=e($p['locations'])?>"></label><label class="wide">Skills (comma-separated)<textarea name="skills" rows="3"><?=e($p['skills'])?></textarea></label><label class="wide">Experience summary<textarea name="experience_summary" rows="4"><?=e($p['experience_summary'])?></textarea></label><label class="wide">Portfolio URL<input type="url" name="portfolio_url" value="<?=e($p['portfolio_url'])?>"></label><button>Save profile and refresh match scores</button></form></section>
<footer><p>JobPilot · Local-first prototype · Review every AI-generated draft before sending.</p></footer></main></body></html>
