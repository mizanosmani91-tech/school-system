<?php
require_once '../../includes/functions.php';
requireLogin(['super_admin','principal']);
$pageTitle = 'আইডি কার্ড জেনারেটর';
$db = getDB();

$classes  = $db->query("SELECT * FROM classes WHERE is_active=1 ORDER BY class_numeric")->fetchAll();
$filterClass = (int)($_GET['class_id'] ?? 0);
$filterIds   = $_GET['ids'] ?? ''; // comma separated student ids
$design      = $_GET['design'] ?? 'modern'; // modern, classic, green
$type        = $_GET['type'] ?? 'student'; // student, teacher, staff
$printMode   = isset($_GET['print']);

// ছাত্র লোড
$students = [];
if ($filterIds) {
    $ids = array_map('intval', explode(',', $filterIds));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare("SELECT s.*, c.class_name_bn, c.class_name, sec.section_name
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN sections sec ON s.section_id = sec.id
        WHERE s.id IN ($placeholders) AND s.status='active'
        ORDER BY s.roll_number");
    $stmt->execute($ids);
    $students = $stmt->fetchAll();
} elseif ($filterClass) {
    $stmt = $db->prepare("SELECT s.*, c.class_name_bn, c.class_name, sec.section_name
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN sections sec ON s.section_id = sec.id
        WHERE s.class_id=? AND s.status='active'
        ORDER BY s.roll_number");
    $stmt->execute([$filterClass]);
    $students = $stmt->fetchAll();
}

$instituteName   = getSetting('institute_name', 'আন নাজাহ তাহফিজুল কুরআন মাদরাসা');
$instituteNameEn = getSetting('institute_name_en', 'An Nazah Tahfizul Quran Madrasah');
$instituteAddress = getSetting('address', 'পান্ধোয়া বাজার, আশুলিয়া, সাভার, ঢাকা');
$institutePhone  = getSetting('phone', '01715-821661');
$instituteWeb    = getSetting('website', 'www.annazah.com');
$logoPath        = getSetting('logo', '');

require_once '../../includes/header.php';
?>

<?php if (!$printMode): ?>
<!-- ===== কন্ট্রোল প্যানেল ===== -->
<div class="section-header no-print">
    <h2 class="section-title"><i class="fas fa-id-card"></i> আইডি কার্ড জেনারেটর</h2>
    <?php if (!empty($students)): ?>
    <button onclick="printCards()" class="btn btn-primary"><i class="fas fa-print"></i> প্রিন্ট / PDF ডাউনলোড</button>
    <?php endif; ?>
</div>

<!-- ফিল্টার -->
<div class="card mb-16 no-print">
    <div class="card-body" style="padding:16px 20px;">
        <form method="GET" id="filterForm">
            <div style="display:flex;flex-wrap:wrap;gap:14px;align-items:flex-end;">
                <div class="form-group" style="margin:0;flex:1;min-width:160px;">
                    <label style="font-size:12px;">ধরন</label>
                    <select name="type" class="form-control" style="padding:8px;">
                        <option value="student" <?= $type==='student'?'selected':'' ?>>ছাত্র</option>
                        <option value="teacher" <?= $type==='teacher'?'selected':'' ?>>শিক্ষক</option>
                    </select>
                </div>
                <div class="form-group" style="margin:0;flex:1;min-width:160px;">
                    <label style="font-size:12px;">শ্রেণী</label>
                    <select name="class_id" class="form-control" style="padding:8px;" onchange="this.form.submit()">
                        <option value="">সব শ্রেণী</option>
                        <?php foreach($classes as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $filterClass==$c['id']?'selected':'' ?>><?= e($c['class_name_bn']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin:0;flex:1;min-width:160px;">
                    <label style="font-size:12px;">ডিজাইন</label>
                    <select name="design" class="form-control" style="padding:8px;" onchange="this.form.submit()">
                        <option value="modern"  <?= $design==='modern'?'selected':'' ?>>🔵 মডার্ন (নীল-কমলা)</option>
                        <option value="green"   <?= $design==='green'?'selected':'' ?>>🟢 গ্রিন (সবুজ-সাদা)</option>
                        <option value="classic" <?= $design==='classic'?'selected':'' ?>>⚫ ক্লাসিক (গাঢ় নীল)</option>
                        <option value="maroon"  <?= $design==='maroon'?'selected':'' ?>>🔴 মেরুন (ঐতিহ্যবাহী)</option>
                    </select>
                </div>
            </div>
        </form>

        <?php if ($filterClass && !empty($students)): ?>
        <div style="margin-top:12px;display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
            <span style="font-size:13px;color:var(--text-muted);">নির্বাচন করুন:</span>
            <button onclick="selectAll()" class="btn btn-outline btn-sm">সবাই</button>
            <button onclick="selectNone()" class="btn btn-outline btn-sm">কেউ না</button>
            <button onclick="generateSelected()" class="btn btn-primary btn-sm"><i class="fas fa-id-card"></i> নির্বাচিতদের কার্ড দেখুন</button>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ছাত্র চেকবক্স তালিকা -->
<?php if ($filterClass && !empty($students)): ?>
<div class="card mb-16 no-print">
    <div class="card-header">
        <span class="card-title">মোট <?= toBanglaNumber(count($students)) ?> জন ছাত্র</span>
    </div>
    <div class="card-body" style="padding:12px 20px;">
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:8px;">
            <?php foreach($students as $s): ?>
            <label style="display:flex;align-items:center;gap:8px;padding:8px;border:1px solid var(--border);border-radius:8px;cursor:pointer;">
                <input type="checkbox" class="student-check" value="<?= $s['id'] ?>" checked>
                <div>
                    <div style="font-size:13px;font-weight:600;"><?= e($s['name_bn']?:$s['name']) ?></div>
                    <div style="font-size:11px;color:var(--text-muted);">রোল: <?= e($s['roll_number']) ?> | <?= e($s['student_id']) ?></div>
                </div>
            </label>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<!-- ===== আইডি কার্ড ===== -->
<?php if (!empty($students)): ?>
<div id="cardContainer" style="<?= $printMode ? '' : 'margin-top:24px;' ?>">
    <?php foreach($students as $s):
        $name        = $s['name_bn'] ?: $s['name'];
        $nameEn      = $s['name'] ?? '';
        $nameParts   = explode(' ', $nameEn, 2);
        $firstNameEn = $nameParts[0] ?? '';
        $lastNameEn  = $nameParts[1] ?? '';
        $photoUrl    = $s['photo'] ? BASE_URL.'/'.$s['photo'] : '';
        $classNameBn = $s['class_name_bn'] ?? '';
        $section     = $s['section_name'] ?? '';
        $roll        = $s['roll_number'] ?? '';
        $blood       = $s['blood_group'] ?? '';
        $stuId       = $s['student_id'] ?? '';
        $father      = $s['father_name_bn'] ?? '';
        $phone       = $s['father_phone'] ?? $s['guardian_phone'] ?? '';
    ?>

    <div class="id-card-pair">

        <!-- ===== পেছনের দিক (Back) ===== -->
        <div class="id-card card-back">
            <div class="back-watermark"><i class="fas fa-mosque"></i></div>
            <div class="back-content">
                <h3 class="back-title">Terms and Condition</h3>
                <p class="back-text">This ID card must be brought and worn whenever the student attends the madrasah. If this card is lost, the student or guardian must inform the office immediately. If anyone finds this card, please return it to An Nazah Tahfizul Quran Madrasah. Misuse, lending, or altering this card in any way is strictly prohibited.</p>

                <div class="back-bottom">
                    <div class="back-qr">
                        <!-- QR placeholder -->
                        <div class="qr-box">
                            <svg viewBox="0 0 100 100" width="60" height="60" xmlns="http://www.w3.org/2000/svg">
                                <rect x="5" y="5" width="35" height="35" rx="3" fill="none" stroke="#e67e22" stroke-width="4"/>
                                <rect x="12" y="12" width="21" height="21" rx="1" fill="#e67e22"/>
                                <rect x="60" y="5" width="35" height="35" rx="3" fill="none" stroke="#e67e22" stroke-width="4"/>
                                <rect x="67" y="12" width="21" height="21" rx="1" fill="#e67e22"/>
                                <rect x="5" y="60" width="35" height="35" rx="3" fill="none" stroke="#e67e22" stroke-width="4"/>
                                <rect x="12" y="67" width="21" height="21" rx="1" fill="#e67e22"/>
                                <rect x="55" y="55" width="8" height="8" fill="#333"/>
                                <rect x="67" y="55" width="8" height="8" fill="#333"/>
                                <rect x="79" y="55" width="8" height="8" fill="#333"/>
                                <rect x="91" y="55" width="8" height="8" fill="#333"/>
                                <rect x="55" y="67" width="8" height="8" fill="#333"/>
                                <rect x="79" y="67" width="8" height="8" fill="#333"/>
                                <rect x="55" y="79" width="8" height="8" fill="#333"/>
                                <rect x="67" y="79" width="8" height="8" fill="#333"/>
                                <rect x="91" y="79" width="8" height="8" fill="#333"/>
                                <rect x="55" y="91" width="8" height="8" fill="#333"/>
                                <rect x="79" y="91" width="8" height="8" fill="#333"/>
                            </svg>
                        </div>
                        <div class="back-sig">
                            <div class="sig-line"></div>
                            <div class="sig-text">Principal's Signature</div>
                        </div>
                    </div>
                    <div class="back-address">
                        <p><?= e($instituteAddress) ?></p>
                        <p style="margin-top:5px;font-weight:700;">Mobile: <?= e($institutePhone) ?></p>
                        <p style="font-weight:700;"><?= e($instituteWeb) ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== সামনের দিক (Front) ===== -->
        <div class="id-card card-front">
            <!-- বাম পাশের সবুজ-কমলা diagonal strip -->
            <div class="front-strip">
                <div class="strip-green"></div>
                <div class="strip-orange"></div>
                <div class="strip-label">STUDENT ID CARD</div>
            </div>

            <!-- মূল কন্টেন্ট -->
            <div class="front-body">
                <!-- লোগো ও নাম -->
                <div class="front-header">
                    <?php if($logoPath): ?>
                    <img src="<?= BASE_URL.'/'.$logoPath ?>" class="front-logo" alt="logo">
                    <?php else: ?>
                    <div class="front-logo-placeholder"><i class="fas fa-mosque"></i></div>
                    <?php endif; ?>
                    <div class="front-institute">
                        <div class="front-institute-arabic">مدرسة النجاح لتحفيظ القرآن</div>
                        <div class="front-institute-bn"><?= e($instituteName) ?></div>
                    </div>
                </div>

                <!-- ছাত্রের ছবি -->
                <div class="front-photo-wrap">
                    <?php if($photoUrl): ?>
                    <img src="<?= $photoUrl ?>" class="front-photo" alt="photo">
                    <?php else: ?>
                    <div class="front-photo-avatar"><?= mb_substr($name, 0, 1) ?></div>
                    <?php endif; ?>
                </div>

                <!-- নাম ও তথ্য -->
                <div class="front-name">
                    <span class="name-first"><?= e($firstNameEn ?: $name) ?></span>
                    <?php if($lastNameEn): ?>
                    <span class="name-last"> <?= e($lastNameEn) ?></span>
                    <?php endif; ?>
                </div>
                <div class="front-id">ID: <?= e($stuId) ?></div>

                <div class="front-table">
                    <div class="front-row"><span class="fr-label">Class</span><span class="fr-val">:<?= e($classNameBn) ?></span></div>
                    <?php if($section): ?>
                    <div class="front-row"><span class="fr-label">Group</span><span class="fr-val">:<?= e($section) ?></span></div>
                    <?php endif; ?>
                    <div class="front-row"><span class="fr-label">Roll</span><span class="fr-val">:<?= e($roll) ?></span></div>
                    <div class="front-row"><span class="fr-label">Blood</span><span class="fr-val">:<?= e($blood ?: 'N/A') ?></span></div>
                </div>
            </div>
        </div>

    </div>
    <?php endforeach; ?>
</div>
<?php elseif (!$printMode): ?>
<div class="card"><div class="card-body" style="text-align:center;padding:48px;color:var(--text-muted);">
    <i class="fas fa-id-card" style="font-size:48px;margin-bottom:16px;opacity:.3;"></i>
    <p style="font-size:16px;">শ্রেণী নির্বাচন করুন অথবা ছাত্র বেছে নিন</p>
</div></div>
<?php endif; ?>

    <?php if ($design === 'modern'): ?>
    <!-- ===== ডিজাইন ১: মডার্ন ===== -->
    <div class="id-card-wrap">
        <div class="id-card modern-card">
            <!-- সামনে -->
            <div class="card-front">
                <!-- শীর্ষ ব্যানার -->
                <div class="modern-header">
                    <div class="modern-diagonal"></div>
                    <div class="modern-header-content">
                        <?php if($logoPath): ?>
                        <img src="<?= BASE_URL.'/'.$logoPath ?>" alt="logo" class="modern-logo">
                        <?php else: ?>
                        <div class="modern-logo-placeholder"><i class="fas fa-mosque"></i></div>
                        <?php endif; ?>
                        <div class="modern-institute">
                            <div class="modern-institute-bn"><?= e($instituteName) ?></div>
                            <div class="modern-institute-en"><?= e($instituteNameEn) ?></div>
                        </div>
                    </div>
                    <div class="modern-card-label">STUDENT ID CARD</div>
                </div>

                <!-- ফোটো ও তথ্য -->
                <div class="modern-body">
                    <div class="modern-photo-wrap">
                        <?php if($photoUrl): ?>
                        <img src="<?= $photoUrl ?>" alt="photo" class="modern-photo">
                        <?php else: ?>
                        <div class="modern-photo-avatar"><?= mb_substr($name, 0, 1) ?></div>
                        <?php endif; ?>
                        <div class="modern-blood"><?= e($blood ?: 'N/A') ?></div>
                    </div>
                    <div class="modern-info">
                        <div class="modern-name"><?= e($name) ?></div>
                        <div class="modern-name-en"><?= e($nameEn) ?></div>
                        <table class="modern-table">
                            <tr><td>ID</td><td>: <?= e($stuId) ?></td></tr>
                            <tr><td>শ্রেণী</td><td>: <?= e($classNameBn) ?><?= $section?" ($section)":'' ?></td></tr>
                            <tr><td>রোল</td><td>: <?= e($roll) ?></td></tr>
                            <tr><td>পিতা</td><td>: <?= e($father ?: '-') ?></td></tr>
                        </table>
                    </div>
                </div>

                <!-- ফুটার -->
                <div class="modern-footer">
                    <div class="modern-footer-info">
                        <span><i class="fas fa-map-marker-alt"></i> <?= e($instituteAddress) ?></span>
                    </div>
                    <div class="modern-footer-right">
                        <div class="modern-sig-line"></div>
                        <div style="font-size:6px;text-align:center;">Principal's Signature</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php elseif ($design === 'green'): ?>
    <!-- ===== ডিজাইন ২: গ্রিন ===== -->
    <div class="id-card-wrap">
        <div class="id-card green-card">
            <div class="green-top-bar"></div>
            <div class="green-side-bar"></div>
            <div class="green-content">
                <div class="green-header">
                    <?php if($logoPath): ?>
                    <img src="<?= BASE_URL.'/'.$logoPath ?>" alt="logo" style="width:36px;height:36px;object-fit:contain;">
                    <?php else: ?>
                    <div style="width:36px;height:36px;background:#27ae60;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-size:16px;"><i class="fas fa-mosque"></i></div>
                    <?php endif; ?>
                    <div>
                        <div style="font-size:8px;font-weight:700;color:#1a5276;line-height:1.3;"><?= e($instituteName) ?></div>
                        <div style="font-size:6px;color:#27ae60;font-weight:600;">STUDENT ID CARD</div>
                    </div>
                </div>
                <div style="display:flex;gap:10px;margin:8px 0;">
                    <div>
                        <?php if($photoUrl): ?>
                        <img src="<?= $photoUrl ?>" style="width:52px;height:64px;object-fit:cover;border-radius:4px;border:2px solid #27ae60;">
                        <?php else: ?>
                        <div style="width:52px;height:64px;background:#e8f8f0;border-radius:4px;border:2px solid #27ae60;display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:700;color:#27ae60;"><?= mb_substr($name,0,1) ?></div>
                        <?php endif; ?>
                        <?php if($blood): ?>
                        <div style="background:#e74c3c;color:#fff;font-size:7px;font-weight:700;text-align:center;border-radius:3px;padding:1px 3px;margin-top:3px;"><?= e($blood) ?></div>
                        <?php endif; ?>
                    </div>
                    <div style="flex:1;">
                        <div style="font-size:10px;font-weight:700;color:#1a5276;margin-bottom:4px;"><?= e($name) ?></div>
                        <div style="font-size:7px;color:#555;margin-bottom:6px;"><?= e($nameEn) ?></div>
                        <div style="font-size:7px;line-height:1.8;">
                            <div><b>ID:</b> <?= e($stuId) ?></div>
                            <div><b>শ্রেণী:</b> <?= e($classNameBn) ?></div>
                            <div><b>রোল:</b> <?= e($roll) ?></div>
                            <div><b>পিতা:</b> <?= e($father ?: '-') ?></div>
                        </div>
                    </div>
                </div>
                <div style="border-top:1px solid #27ae60;padding-top:5px;display:flex;justify-content:space-between;align-items:flex-end;">
                    <div style="font-size:6px;color:#666;"><?= e($instituteAddress) ?><br><?= e($phone) ?></div>
                    <div style="text-align:center;">
                        <div style="width:50px;border-top:1px solid #333;margin-bottom:2px;"></div>
                        <div style="font-size:5px;">Principal</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php elseif ($design === 'classic'): ?>
    <!-- ===== ডিজাইন ৩: ক্লাসিক ===== -->
    <div class="id-card-wrap">
        <div class="id-card classic-card">
            <div class="classic-header">
                <div style="display:flex;align-items:center;gap:6px;">
                    <?php if($logoPath): ?>
                    <img src="<?= BASE_URL.'/'.$logoPath ?>" alt="logo" style="width:28px;height:28px;object-fit:contain;">
                    <?php else: ?>
                    <i class="fas fa-mosque" style="color:#fff;font-size:20px;"></i>
                    <?php endif; ?>
                    <div>
                        <div style="font-size:7px;font-weight:700;color:#fff;line-height:1.3;"><?= e($instituteName) ?></div>
                        <div style="font-size:5.5px;color:rgba(255,255,255,.8);"><?= e($instituteNameEn) ?></div>
                    </div>
                </div>
                <div class="classic-label">STUDENT ID CARD</div>
            </div>
            <div class="classic-body">
                <div class="classic-photo-section">
                    <?php if($photoUrl): ?>
                    <img src="<?= $photoUrl ?>" class="classic-photo">
                    <?php else: ?>
                    <div class="classic-photo-avatar"><?= mb_substr($name,0,1) ?></div>
                    <?php endif; ?>
                </div>
                <div class="classic-info">
                    <div class="classic-name"><?= e($name) ?></div>
                    <div class="classic-name-en"><?= e($nameEn) ?></div>
                    <div class="classic-divider"></div>
                    <table class="classic-table">
                        <tr><td>আইডি নং</td><td>: <?= e($stuId) ?></td></tr>
                        <tr><td>শ্রেণী</td><td>: <?= e($classNameBn) ?></td></tr>
                        <tr><td>রোল নং</td><td>: <?= e($roll) ?></td></tr>
                        <tr><td>রক্তের গ্রুপ</td><td>: <?= e($blood ?: 'N/A') ?></td></tr>
                        <tr><td>পিতার নাম</td><td>: <?= e($father ?: '-') ?></td></tr>
                    </table>
                </div>
            </div>
            <div class="classic-footer">
                <div style="font-size:6px;color:rgba(255,255,255,.9);"><?= e($instituteAddress) ?> | <?= e($phone) ?></div>
                <div style="text-align:right;">
                    <div style="width:55px;border-top:1px solid rgba(255,255,255,.7);margin-bottom:2px;margin-left:auto;"></div>
                    <div style="font-size:5.5px;color:rgba(255,255,255,.9);">Principal's Signature</div>
                </div>
            </div>
        </div>
    </div>

    <?php elseif ($design === 'maroon'): ?>
    <!-- ===== ডিজাইন ৪: মেরুন ===== -->
    <div class="id-card-wrap">
        <div class="id-card maroon-card">
            <div class="maroon-left-bar">
                <div class="maroon-rotated-text">STUDENT ID CARD</div>
            </div>
            <div class="maroon-right">
                <div class="maroon-header">
                    <?php if($logoPath): ?>
                    <img src="<?= BASE_URL.'/'.$logoPath ?>" alt="logo" style="width:30px;height:30px;object-fit:contain;">
                    <?php endif; ?>
                    <div>
                        <div style="font-size:7.5px;font-weight:700;color:#7b1d1d;line-height:1.3;"><?= e($instituteName) ?></div>
                        <div style="font-size:5.5px;color:#c0392b;"><?= e($instituteNameEn) ?></div>
                    </div>
                </div>
                <div style="display:flex;gap:8px;padding:0 8px 6px;">
                    <?php if($photoUrl): ?>
                    <img src="<?= $photoUrl ?>" style="width:50px;height:62px;object-fit:cover;border-radius:4px;border:2px solid #c0392b;">
                    <?php else: ?>
                    <div style="width:50px;height:62px;background:#fdf0f0;border-radius:4px;border:2px solid #c0392b;display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:700;color:#c0392b;"><?= mb_substr($name,0,1) ?></div>
                    <?php endif; ?>
                    <div style="flex:1;">
                        <div style="font-size:9.5px;font-weight:700;color:#7b1d1d;"><?= e($name) ?></div>
                        <div style="font-size:6.5px;color:#888;margin-bottom:5px;"><?= e($nameEn) ?></div>
                        <div style="font-size:7px;line-height:1.7;color:#333;">
                            <div><span style="color:#c0392b;font-weight:600;">ID:</span> <?= e($stuId) ?></div>
                            <div><span style="color:#c0392b;font-weight:600;">শ্রেণী:</span> <?= e($classNameBn) ?></div>
                            <div><span style="color:#c0392b;font-weight:600;">রোল:</span> <?= e($roll) ?></div>
                            <?php if($blood): ?><div><span style="color:#c0392b;font-weight:600;">রক্ত:</span> <?= e($blood) ?></div><?php endif; ?>
                        </div>
                    </div>
                </div>
                <div style="background:#7b1d1d;margin:0 0 0 0;padding:4px 8px;display:flex;justify-content:space-between;align-items:center;">
                    <div style="font-size:5.5px;color:rgba(255,255,255,.9);"><?= e($phone) ?></div>
                    <div style="text-align:center;">
                        <div style="width:45px;border-top:1px solid rgba(255,255,255,.6);margin-bottom:1px;"></div>
                        <div style="font-size:5px;color:rgba(255,255,255,.9);">Principal</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php endforeach; ?>
</div>
<?php elseif (!$printMode): ?>
<div class="card"><div class="card-body" style="text-align:center;padding:48px;color:var(--text-muted);">
    <i class="fas fa-id-card" style="font-size:48px;margin-bottom:16px;opacity:.3;"></i>
    <p style="font-size:16px;">শ্রেণী নির্বাচন করুন অথবা ছাত্র বেছে নিন</p>
</div></div>
<?php endif; ?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;600;700&family=Libre+Baskerville:wght@400;700&display=swap');

/* ===== CARD PAIR (Front + Back side by side) ===== */
.id-card-pair {
    display: inline-flex;
    gap: 12px;
    margin: 10px;
    vertical-align: top;
}

/* CR80 Portrait: 54mm × 85.6mm = 204px × 323px at 96dpi */
.id-card {
    width: 204px;
    height: 323px;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 6px 24px rgba(0,0,0,.18);
    position: relative;
    font-family: 'Hind Siliguri', sans-serif;
}

/* ===== FRONT ===== */
.card-front {
    background: #fff;
    display: flex;
}

/* বাম strip */
.front-strip {
    width: 30px;
    position: relative;
    flex-shrink: 0;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
}
.strip-green {
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 55%;
    background: #1a8a3c;
    clip-path: polygon(0 0, 100% 0, 100% 85%, 0 100%);
}
.strip-orange {
    position: absolute;
    bottom: 0; left: 0; right: 0;
    height: 55%;
    background: #e67e22;
    clip-path: polygon(0 15%, 100% 0, 100% 100%, 0 100%);
}
.strip-label {
    position: relative;
    z-index: 2;
    color: #fff;
    font-size: 7px;
    font-weight: 700;
    letter-spacing: 1.5px;
    writing-mode: vertical-rl;
    text-orientation: mixed;
    transform: rotate(180deg);
    white-space: nowrap;
    text-shadow: 0 1px 3px rgba(0,0,0,.4);
}

/* মূল কন্টেন্ট */
.front-body {
    flex: 1;
    display: flex;
    flex-direction: column;
    padding: 8px 8px 8px 6px;
}
.front-header {
    display: flex;
    align-items: center;
    gap: 5px;
    border-bottom: 2px solid #1a8a3c;
    padding-bottom: 5px;
    margin-bottom: 6px;
}
.front-logo {
    width: 32px; height: 32px;
    object-fit: contain; flex-shrink: 0;
}
.front-logo-placeholder {
    width: 32px; height: 32px;
    background: linear-gradient(135deg,#1a8a3c,#e67e22);
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: 15px; flex-shrink: 0;
}
.front-institute { flex: 1; }
.front-institute-arabic {
    font-size: 7px; color: #1a5276; font-weight: 600;
    line-height: 1.2; direction: rtl;
}
.front-institute-bn {
    font-size: 6.5px; color: #1a8a3c; font-weight: 700;
    line-height: 1.3;
}

/* ছবি */
.front-photo-wrap {
    text-align: center;
    margin: 4px 0;
}
.front-photo {
    width: 80px; height: 95px;
    object-fit: cover;
    border: 3px solid #e67e22;
    border-radius: 4px;
    display: inline-block;
}
.front-photo-avatar {
    width: 80px; height: 95px;
    background: #f0f8f0;
    border: 3px solid #e67e22;
    border-radius: 4px;
    display: inline-flex;
    align-items: center; justify-content: center;
    font-size: 32px; font-weight: 700; color: #1a8a3c;
}

/* নাম */
.front-name {
    text-align: center;
    margin-top: 6px;
    line-height: 1.2;
}
.name-first {
    font-size: 14px; font-weight: 700; color: #1a8a3c;
    font-family: 'Libre Baskerville', serif;
}
.name-last {
    font-size: 14px; font-weight: 400; color: #333;
    font-family: 'Libre Baskerville', serif;
}
.front-id {
    text-align: center;
    font-size: 8.5px; font-weight: 700; color: #555;
    margin: 2px 0 5px;
    letter-spacing: 0.5px;
}

/* তথ্য টেবিল */
.front-table {
    border-top: 1px dashed #1a8a3c;
    padding-top: 5px;
}
.front-row {
    display: flex;
    font-size: 8px;
    line-height: 1.8;
    color: #333;
}
.fr-label {
    width: 38px;
    color: #1a5276;
    font-weight: 600;
}
.fr-val { flex: 1; }

/* ===== BACK ===== */
.card-back {
    background: #fff;
    border: 1px solid #ddd;
    display: flex;
    flex-direction: column;
    position: relative;
    overflow: hidden;
}
.back-watermark {
    position: absolute;
    top: 50%; left: 50%;
    transform: translate(-50%,-50%);
    font-size: 90px;
    color: rgba(26,138,60,.06);
    pointer-events: none;
}
.back-content {
    padding: 12px 10px 8px;
    display: flex;
    flex-direction: column;
    height: 100%;
    position: relative;
    z-index: 1;
}
.back-title {
    font-size: 10px;
    font-weight: 700;
    color: #1a5276;
    text-align: center;
    margin-bottom: 7px;
    font-family: 'Libre Baskerville', serif;
}
.back-text {
    font-size: 6.5px;
    color: #444;
    line-height: 1.7;
    text-align: justify;
    flex: 1;
}
.back-bottom {
    margin-top: 8px;
    border-top: 1px solid #e67e22;
    padding-top: 7px;
    display: flex;
    flex-direction: column;
    gap: 5px;
}
.back-qr {
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.qr-box {
    background: #fff8f0;
    border: 1px solid #e67e22;
    border-radius: 4px;
    padding: 3px;
}
.back-sig { text-align: center; }
.sig-line {
    width: 60px;
    border-top: 1px solid #333;
    margin: 0 auto 2px;
}
.sig-text {
    font-size: 6px;
    color: #555;
}
.back-address {
    font-size: 6.5px;
    color: #444;
    text-align: center;
    line-height: 1.6;
}

/* ===== PRINT ===== */
@media print {
    .no-print { display: none !important; }
    body { margin: 0; padding: 0; background: #fff; }
    #cardContainer {
        display: flex;
        flex-wrap: wrap;
        gap: 5mm;
        padding: 5mm;
    }
    .id-card-pair { margin: 0; }
    .id-card {
        box-shadow: none;
        border: 1px solid #ccc;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    .sidebar, .topbar, header, nav { display: none !important; }
    .main-wrapper { margin-left: 0 !important; }
    .content { padding: 0 !important; }
}

@page {
    size: A4 portrait;
    margin: 8mm;
}
</style>

<script>
function selectAll() {
    document.querySelectorAll('.student-check').forEach(c => c.checked = true);
}
function selectNone() {
    document.querySelectorAll('.student-check').forEach(c => c.checked = false);
}
function generateSelected() {
    const ids = [...document.querySelectorAll('.student-check:checked')].map(c => c.value);
    if (!ids.length) { alert('কমপক্ষে একজন ছাত্র নির্বাচন করুন।'); return; }
    const params = new URLSearchParams(window.location.search);
    params.set('ids', ids.join(','));
    params.delete('class_id');
    window.location.href = '?' + params.toString();
}
function printCards() {
    window.print();
}
</script>

<?php require_once '../../includes/footer.php'; ?>
