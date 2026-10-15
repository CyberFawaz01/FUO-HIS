<?php
require_once __DIR__ . '/../../config/database.php';
session_set_cookie_params(['httponly'=>true,'samesite'=>'Strict']);
if (session_status()===PHP_SESSION_NONE) session_start();
if (!empty($_SESSION['patient_id'])) { header('Location: '.APP_URL.'/views/patient/dashboard.php'); exit; }
$db = Database::getInstance();
$error = '';
$registered = false;
$newPatientId = $newPatientName = '';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $f = [
        'full_name'         => trim($_POST['full_name']         ?? ''),
        'date_of_birth'     => $_POST['date_of_birth']          ?? '',
        'gender'            => $_POST['gender']                 ?? '',
        'blood_group'       => $_POST['blood_group']            ?? '',
        'phone'             => trim($_POST['phone']             ?? ''),
        'email'             => trim($_POST['email']             ?? ''),
        'address'           => trim($_POST['address']           ?? ''),
        'next_of_kin'       => trim($_POST['next_of_kin']       ?? ''),
        'next_of_kin_phone' => trim($_POST['next_of_kin_phone'] ?? ''),
        'password'          => $_POST['password']               ?? '',
        'confirm_password'  => $_POST['confirm_password']       ?? '',
    ];
    if (empty($f['full_name']))          $error = 'Full name is required.';
    elseif (empty($f['date_of_birth']))  $error = 'Date of birth is required.';
    elseif (empty($f['gender']))         $error = 'Gender is required.';
    elseif (empty($f['blood_group']))    $error = 'Blood group is required.';
    elseif (empty($f['phone']))          $error = 'Phone number is required.';
    elseif (empty($f['address']))        $error = 'Home address is required.';
    elseif (empty($f['next_of_kin']))    $error = 'Next of kin name is required.';
    elseif (empty($f['next_of_kin_phone'])) $error = 'Next of kin phone is required.';
    elseif (strlen($f['password']) < 6)  $error = 'Password must be at least 6 characters.';
    elseif ($f['password'] !== $f['confirm_password']) $error = 'Passwords do not match.';
    elseif (!empty($f['email']) && !filter_var($f['email'], FILTER_VALIDATE_EMAIL)) $error = 'Invalid email.';
    else {
        if (!empty($f['email'])) {
            $ex = $db->fetchOne("SELECT id FROM patients WHERE email=?", [$f['email']]);
            if ($ex) $error = 'Email already registered.';
        }
        if (!$error) {
            $last = $db->fetchOne("SELECT patient_id FROM patients ORDER BY id DESC LIMIT 1");
            $num  = 0;
            if ($last) { preg_match('/(\d+)$/', $last['patient_id'], $m); $num = isset($m[1])?(int)$m[1]:0; }
            $newPatientId   = 'PAT' . str_pad($num + 1, 3, '0', STR_PAD_LEFT);
            $newPatientName = $f['full_name'];
            $hash           = password_hash($f['password'], PASSWORD_BCRYPT);
            $db->query(
                "INSERT INTO patients (patient_id,full_name,date_of_birth,gender,blood_group,phone,email,address,next_of_kin,next_of_kin_phone,password_hash,admission_status,sensitivity_level) VALUES (?,?,?,?,?,?,?,?,?,?,?,'outpatient','Low')",
                [$newPatientId,$f['full_name'],$f['date_of_birth'],$f['gender'],$f['blood_group'],$f['phone'],$f['email']?:null,$f['address'],$f['next_of_kin'],$f['next_of_kin_phone'],$hash]
            );
            $_SESSION['reg_success']    = true;
            $_SESSION['reg_patient_id'] = $newPatientId;
            $_SESSION['reg_name']       = $newPatientName;
            header('Location: '.APP_URL.'/views/patient/register.php'); exit;
        }
    }
}
if (!empty($_SESSION['reg_success'])) {
    $registered     = true;
    $newPatientId   = $_SESSION['reg_patient_id'];
    $newPatientName = $_SESSION['reg_name'];
    unset($_SESSION['reg_success'],$_SESSION['reg_patient_id'],$_SESSION['reg_name']);
}
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Patient Registration — CA-RBAC HIS</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
*{box-sizing:border-box;margin:0;padding:0}
:root{--gd:#0a4a2e;--gm:#0f6b42;--gl:#22c55e;--gp:#f0fdf4;--gb:#bbf7d0;--w:#fff;--g50:#f8fafc;--g100:#f1f5f9;--g200:#e2e8f0;--g400:#94a3b8;--g500:#64748b;--g600:#475569;--g800:#1e293b;--red:#ef4444;--amb:#f59e0b}
body{font-family:'Inter',sans-serif;background:var(--g50);min-height:100vh;padding:24px 16px}
.wrap{max-width:780px;margin:0 auto}
/* Success */
.suc-page{background:white;border-radius:20px;border:1px solid var(--g200);padding:48px 40px;text-align:center;box-shadow:0 4px 20px rgba(0,0,0,0.06)}
.suc-icon{width:80px;height:80px;background:var(--gp);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 24px;border:3px solid var(--gb)}
.suc-icon i{font-size:36px;color:var(--gm)}
.suc-page h2{font-size:24px;font-weight:800;color:var(--gd);margin-bottom:8px}
.suc-page p{font-size:14px;color:var(--g500);margin-bottom:28px}
.pid-box{background:var(--gp);border:2px dashed #86efac;border-radius:14px;padding:24px;margin-bottom:28px}
.pid-box .lbl{font-size:12px;font-weight:600;color:var(--gm);text-transform:uppercase;letter-spacing:1px;margin-bottom:8px}
.pid-box .pid{font-size:42px;font-weight:800;color:var(--gd);letter-spacing:2px}
.pid-box .nm{font-size:14px;color:var(--g500);margin-top:6px}
.warn-box{background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:14px 18px;margin-bottom:24px;font-size:13px;color:#92400e;display:flex;align-items:flex-start;gap:10px;text-align:left}
.copy-btn{background:var(--gm);color:white;border:none;border-radius:8px;padding:8px 18px;font-family:'Inter',sans-serif;font-size:13px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:6px;margin-bottom:16px}
.btn-go{display:inline-flex;align-items:center;gap:8px;padding:12px 28px;background:linear-gradient(135deg,var(--gm),#1a8a56);color:white;border:none;border-radius:10px;font-family:'Inter',sans-serif;font-size:14px;font-weight:700;cursor:pointer;text-decoration:none}
/* Form */
.hdr{background:linear-gradient(135deg,var(--gd),#1a8a56);border-radius:20px;padding:28px 36px;margin-bottom:24px;display:flex;align-items:center;gap:20px}
.hdr-icon{width:56px;height:56px;background:rgba(255,255,255,0.15);border-radius:16px;display:flex;align-items:center;justify-content:center;color:#86efac;font-size:26px;flex-shrink:0}
.hdr h1{color:white;font-size:22px;font-weight:700;margin-bottom:4px}.hdr p{color:rgba(255,255,255,0.6);font-size:13px}
.card{background:white;border-radius:16px;border:1px solid var(--g200);box-shadow:0 1px 4px rgba(0,0,0,0.06);margin-bottom:16px;overflow:hidden}
.ch{padding:16px 24px;border-bottom:1px solid var(--g100);background:var(--g50);display:flex;align-items:center;gap:10px}
.si{width:32px;height:32px;border-radius:8px;background:var(--gp);display:flex;align-items:center;justify-content:center;color:var(--gm);font-size:15px}
.ch span{font-size:14px;font-weight:600;color:var(--g800)}
.cb{padding:24px}
.fg{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.fg3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px}
.fg1{display:grid;grid-template-columns:1fr;gap:16px}
@media(max-width:600px){.fg,.fg3{grid-template-columns:1fr}}
.fm{margin-bottom:16px;display:flex;flex-direction:column;gap:5px}
.fl{font-size:12px;font-weight:600;color:var(--g600);letter-spacing:0.3px;text-transform:uppercase}
.req{color:var(--red);margin-left:2px}
.iw{position:relative}
.iw .fi{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--g400);font-size:14px;pointer-events:none;z-index:1}
.fi-ta{position:absolute;left:12px;top:14px;color:var(--g400);font-size:14px;pointer-events:none;z-index:1}
.fc,.fs{width:100%;padding:10px 14px 10px 36px;border:1.5px solid var(--g200);border-radius:8px;font-family:'Inter',sans-serif;font-size:14px;color:var(--g800);background:white;outline:none;transition:.2s;appearance:none}
.fc:focus,.fs:focus{border-color:var(--gl);box-shadow:0 0 0 3px rgba(34,197,94,0.1)}
textarea.fc{padding-top:10px;resize:vertical;min-height:80px}
.pw{position:relative}
.pw .fc{padding-right:42px}
.et{position:absolute;right:12px;top:50%;transform:translateY(-50%);border:none;background:none;cursor:pointer;color:var(--g400);font-size:15px;padding:0;z-index:2}
.alert{padding:12px 16px;border-radius:8px;font-size:13px;margin-bottom:16px;display:flex;align-items:center;gap:8px}
.alert-danger{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
.btn-reg{width:100%;padding:13px;background:linear-gradient(135deg,var(--gm),#1a8a56);color:white;border:none;border-radius:10px;font-family:'Inter',sans-serif;font-size:15px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;margin-bottom:16px}
.ll{text-align:center;font-size:13px;color:var(--g400)}.ll a{color:var(--gm);font-weight:600;text-decoration:none}
.pb{height:4px;border-radius:2px;background:var(--g200);overflow:hidden;margin-top:5px}
.pbf{height:100%;border-radius:2px;transition:.3s;width:0%}
.pl{font-size:11px;color:var(--g400);margin-top:3px}
.ib{background:var(--gp);border:1px solid var(--gb);border-radius:8px;padding:10px 14px;font-size:12px;color:#166534;margin-bottom:16px;display:flex;align-items:flex-start;gap:8px}
</style></head><body>
<div class="wrap">
<?php if ($registered): ?>
<div class="suc-page">
  <div class="suc-icon"><i class="bi bi-person-check-fill"></i></div>
  <h2>Registration Successful!</h2>
  <p>Welcome, <?= htmlspecialchars($newPatientName) ?>. Your patient account has been created.</p>
  <div class="pid-box">
    <div class="lbl">Your Patient ID</div>
    <div class="pid" id="pidDisplay"><?= htmlspecialchars($newPatientId) ?></div>
    <div class="nm"><?= htmlspecialchars($newPatientName) ?></div>
  </div>
  <div class="warn-box">
    <i class="bi bi-exclamation-triangle-fill" style="color:var(--amb);flex-shrink:0;margin-top:1px"></i>
    <div><strong>Important — Save your Patient ID!</strong><br>You need this ID every time you log in. Write it down or take a screenshot now.</div>
  </div>
  <button class="copy-btn" onclick="copyId()">
    <i class="bi bi-clipboard" id="copyIcon"></i><span id="copyText">Copy Patient ID</span>
  </button><br>
  <a href="<?= APP_URL ?>/index.php" class="btn-go"><i class="bi bi-box-arrow-in-right"></i> Proceed to Login</a>
</div>
<?php else: ?>
<div class="hdr">
  <div class="hdr-icon"><i class="bi bi-person-plus-fill"></i></div>
  <div><h1>Patient Registration</h1><p>Create your account to access your health records</p></div>
</div>
<?php if ($error): ?>
<div class="alert alert-danger"><i class="bi bi-x-circle-fill"></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<div class="ib"><i class="bi bi-info-circle-fill" style="flex-shrink:0;margin-top:1px"></i><div>After registration, you will be given a <strong>Patient ID</strong>. Save it — you will use it to log in.</div></div>
<form method="POST" action="">
<!-- Personal -->
<div class="card"><div class="ch"><div class="si"><i class="bi bi-person-fill"></i></div><span>Personal Information</span></div><div class="cb">
<div class="fg1" style="margin-bottom:16px"><div class="fm"><label class="fl">Full Name <span class="req">*</span></label><div class="iw"><i class="bi bi-person fi"></i><input type="text" class="fc" name="full_name" required placeholder="Enter your full name"></div></div></div>
<div class="fg3">
<div class="fm"><label class="fl">Date of Birth <span class="req">*</span></label><div class="iw"><i class="bi bi-calendar3 fi"></i><input type="date" class="fc" name="date_of_birth" required></div></div>
<div class="fm"><label class="fl">Gender <span class="req">*</span></label><div class="iw"><i class="bi bi-gender-ambiguous fi"></i><select class="fs" name="gender" required><option value="">Select</option><option>Male</option><option>Female</option><option>Other</option></select></div></div>
<div class="fm"><label class="fl">Blood Group <span class="req">*</span></label><div class="iw"><i class="bi bi-droplet-fill fi"></i><select class="fs" name="blood_group" required><option value="">Select</option><?php foreach(['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bg): ?><option><?= $bg ?></option><?php endforeach; ?></select></div></div>
</div></div></div>
<!-- Contact -->
<div class="card"><div class="ch"><div class="si"><i class="bi bi-telephone-fill"></i></div><span>Contact Information</span></div><div class="cb">
<div class="fg" style="margin-bottom:16px">
<div class="fm"><label class="fl">Phone Number <span class="req">*</span></label><div class="iw"><i class="bi bi-telephone fi"></i><input type="tel" class="fc" name="phone" required placeholder="e.g. 08012345678"></div></div>
<div class="fm"><label class="fl">Email Address</label><div class="iw"><i class="bi bi-envelope fi"></i><input type="email" class="fc" name="email" placeholder="Optional"></div></div>
</div>
<div class="fm"><label class="fl">Home Address <span class="req">*</span></label><div class="iw"><i class="bi bi-house fi-ta"></i><textarea class="fc" name="address" required placeholder="Enter your full home address" style="padding-left:36px"></textarea></div></div>
</div></div>
<!-- Next of Kin -->
<div class="card"><div class="ch"><div class="si"><i class="bi bi-people-fill"></i></div><span>Next of Kin</span></div><div class="cb"><div class="fg">
<div class="fm"><label class="fl">Full Name <span class="req">*</span></label><div class="iw"><i class="bi bi-person-heart fi"></i><input type="text" class="fc" name="next_of_kin" required placeholder="Full name"></div></div>
<div class="fm"><label class="fl">Phone Number <span class="req">*</span></label><div class="iw"><i class="bi bi-telephone-forward fi"></i><input type="tel" class="fc" name="next_of_kin_phone" required placeholder="e.g. 08087654321"></div></div>
</div></div></div>
<!-- Security -->
<div class="card"><div class="ch"><div class="si"><i class="bi bi-shield-lock-fill"></i></div><span>Account Security</span></div><div class="cb"><div class="fg">
<div class="fm"><label class="fl">Password <span class="req">*</span></label>
<div class="pw"><input type="password" class="fc" name="password" id="pwd1" required minlength="6" placeholder="Minimum 6 characters" oninput="chkStr(this.value)"><button type="button" class="et" onclick="tgl('pwd1','e1')"><i class="bi bi-eye" id="e1"></i></button></div>
<div><div class="pb"><div class="pbf" id="pb"></div></div><div class="pl" id="pl">Enter a password</div></div></div>
<div class="fm"><label class="fl">Confirm Password <span class="req">*</span></label>
<div class="pw"><input type="password" class="fc" name="confirm_password" id="pwd2" required minlength="6" placeholder="Re-enter password"><button type="button" class="et" onclick="tgl('pwd2','e2')"><i class="bi bi-eye" id="e2"></i></button></div></div>
</div></div></div>
<button type="submit" class="btn-reg"><i class="bi bi-person-check-fill"></i> Create My Account</button>
<div class="ll">Already have an account? <a href="<?= APP_URL ?>/index.php">Sign In</a></div>
</form>
<?php endif; ?>
</div>
<script>
function tgl(i,e){const inp=document.getElementById(i),ico=document.getElementById(e);inp.type=inp.type==='password'?'text':'password';ico.className=inp.type==='text'?'bi bi-eye-slash':'bi bi-eye'}
function chkStr(v){const b=document.getElementById('pb'),l=document.getElementById('pl');if(!b)return;let s=0;if(v.length>=6)s++;if(v.length>=10)s++;if(/[A-Z]/.test(v))s++;if(/[0-9]/.test(v))s++;if(/[^A-Za-z0-9]/.test(v))s++;const lv=[{p:'20%',c:'#ef4444',t:'Very Weak'},{p:'40%',c:'#f97316',t:'Weak'},{p:'60%',c:'#f59e0b',t:'Fair'},{p:'80%',c:'#84cc16',t:'Strong'},{p:'100%',c:'#22c55e',t:'Very Strong'}][Math.min(s,4)];b.style.width=lv.p;b.style.background=lv.c;l.textContent=lv.t;l.style.color=lv.c}
function copyId(){const p=document.getElementById('pidDisplay').textContent.trim(),b=document.getElementById('copyText'),i=document.getElementById('copyIcon');navigator.clipboard.writeText(p).then(()=>{b.textContent='Copied!';i.className='bi bi-check-lg';setTimeout(()=>{b.textContent='Copy Patient ID';i.className='bi bi-clipboard'},2000)})}
</script>
</body></html>
