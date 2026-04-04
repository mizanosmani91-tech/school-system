<?php
require_once '../../includes/functions.php';
requireLogin(['teacher']);
$pageTitle = 'শিক্ষক ড্যাশবোর্ড';
$db = getDB();
$userId = $_SESSION['user_id'];

// শিক্ষকের তথ্য
$teacher = $db->prepare("SELECT * FROM teachers WHERE user_id=?");
$teacher->execute([$userId]);
$teacher = $teacher->fetch();

if (!$teacher) {
    setFlash('danger', 'শিক্ষক প্রোফাইল পাওয়া যায়নি।');
    header('Location: ' . BASE_URL . '/logout.php');
    exit;
}

$today = date('Y-m-d');

// আজকের চেক ইন স্ট্যাটাস
$checkIn = $db->prepare("SELECT * FROM teacher_attendance WHERE teacher_id=? AND date=?");
$checkIn->execute([$teacher['id'], $today]);
$todayAttendance = $checkIn->fetch();

// এই মাসে কতদিন উপস্থিত
$monthAttendance = $db->prepare("SELECT COUNT(*) FROM teacher_attendance WHERE teacher_id=? AND DATE_FORMAT(date,'%Y-%m')=? AND status='present'");
$monthAttendance->execute([$teacher['id'], date('Y-m')]);
$presentDays = $monthAttendance->fetchColumn();

// নোটিশ
try {
    $notices = $db->query("SELECT * FROM notices ORDER BY created_at DESC LIMIT 5")->fetchAll();
} catch(Exception $e) { $notices = []; }

require_once '../../includes/teacher_header.php';
?>

<div class="section-header">
    <h2 class="section-title"><i class="fas fa-tachometer-alt"></i> আমার ড্যাশবোর্ড</h2>
    <span style="font-size:13px;color:var(--text-muted);"><?= banglaDate() ?></span>
</div>

<!-- স্বাগত কার্ড -->
<div class="card mb-24" style="background:linear-gradient(135deg,var(--primary),var(--primary-light));color:#fff;border:none;">
    <div class="card-body" style="display:flex;align-items:center;gap:20px;padding:24px;">
        <div style="width:60px;height:60px;background:rgba(255,255,255,.2);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:26px;font-weight:700;flex-shrink:0;">
            <?= mb_substr($teacher['name_bn'] ?? $teacher['name'], 0, 1) ?>
        </div>
        <div>
            <div style="font-size:20px;font-weight:700;">আস-সালামু আলাইকুম, <?= e($teacher['name_bn'] ?? $teacher['name']) ?>!</div>
            <div style="opacity:.85;font-size:14px;margin-top:4px;"><?= e($teacher['designation_bn'] ?? 'শিক্ষক') ?> | ID: <?= e($teacher['teacher_id_no']) ?></div>
        </div>
    </div>
</div>

<!-- স্ট্যাট কার্ড -->
<div class="stat-grid mb-24">
    <div class="stat-card <?= $todayAttendance && $todayAttendance['check_in'] ? 'green' : 'red' ?>">
        <div class="stat-icon"><i class="fas fa-fingerprint"></i></div>
        <div>
            <div class="stat-value"><?= $todayAttendance && $todayAttendance['check_in'] ? 'উপস্থিত' : 'অনুপস্থিত' ?></div>
            <div class="stat-label">আজকের অবস্থা</div>
        </div>
    </div>
    <div class="stat-card blue">
        <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
        <div>
            <div class="stat-value"><?= toBanglaNumber($presentDays) ?></div>
            <div class="stat-label">এই মাসে উপস্থিতি</div>
        </div>
    </div>
    <?php if($todayAttendance && $todayAttendance['check_in']): ?>
    <div class="stat-card green">
        <div class="stat-icon"><i class="fas fa-sign-in-alt"></i></div>
        <div>
            <div class="stat-value"><?= toBanglaNumber(date('h:i', strtotime($todayAttendance['check_in']))) ?></div>
            <div class="stat-label">আজ চেক ইন</div>
        </div>
    </div>
    <?php endif; ?>
    <?php if($todayAttendance && $todayAttendance['check_out']): ?>
    <div class="stat-card orange">
        <div class="stat-icon"><i class="fas fa-sign-out-alt"></i></div>
        <div>
            <div class="stat-value"><?= toBanglaNumber(date('h:i', strtotime($todayAttendance['check_out']))) ?></div>
            <div class="stat-label">আজ চেক আউট</div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- দ্রুত অ্যাকশন -->
<div class="card mb-24">
    <div class="card-header"><span class="card-title"><i class="fas fa-bolt"></i> দ্রুত অ্যাকশন</span></div>
    <div class="card-body">
        <div style="display:flex;flex-wrap:wrap;gap:12px;">
            <a href="<?= BASE_URL ?>/modules/attendance/checkin.php" class="btn btn-success" style="padding:12px 20px;">
                <i class="fas fa-fingerprint"></i> চেক ইন / চেক আউট
            </a>
            <a href="<?= BASE_URL ?>/modules/attendance/index.php" class="btn btn-primary" style="padding:12px 20px;">
                <i class="fas fa-clipboard-check"></i> ছাত্র উপস্থিতি
            </a>
            <a href="<?= BASE_URL ?>/modules/exam/marks.php" class="btn btn-warning" style="padding:12px 20px;">
                <i class="fas fa-pen"></i> মার্ক এন্ট্রি
            </a>
            <a href="<?= BASE_URL ?>/modules/teacher/diary.php" class="btn btn-outline" style="padding:12px 20px;">
                <i class="fas fa-book-open"></i> ডায়েরি
            </a>
            <a href="<?= BASE_URL ?>/modules/exam/model_test.php" class="btn btn-outline" style="padding:12px 20px;">
                <i class="fas fa-file-alt"></i> মডেল টেস্ট
            </a>
            <a href="<?= BASE_URL ?>/modules/teacher/profile.php" class="btn btn-outline" style="padding:12px 20px;">
                <i class="fas fa-user-circle"></i> আমার প্রোফাইল
            </a>
        </div>
    </div>
</div>

<!-- নোটিশ বোর্ড -->
<?php if(!empty($notices)): ?>
<div class="card">
    <div class="card-header"><span class="card-title"><i class="fas fa-bullhorn"></i> নোটিশ বোর্ড</span></div>
    <div class="card-body" style="padding:12px 20px;">
        <?php foreach($notices as $n): ?>
        <div style="padding:12px 0;border-bottom:1px solid var(--border);display:flex;align-items:flex-start;gap:12px;">
            <div style="width:8px;height:8px;background:var(--primary);border-radius:50%;margin-top:6px;flex-shrink:0;"></div>
            <div>
                <div style="font-weight:600;font-size:14px;"><?= e($n['title'] ?? '') ?></div>
                <div style="font-size:12px;color:var(--text-muted);margin-top:2px;"><?= banglaDate($n['created_at'] ?? '') ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>
