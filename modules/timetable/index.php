<?php
require_once '../../includes/functions.php';
requireLogin();
$pageTitle = 'রুটিন ব্যবস্থাপনা';
$db = getDB();

$divisionId = (int)($_GET['division_id'] ?? 0);
$divisions  = $db->query("SELECT * FROM divisions WHERE is_active=1 ORDER BY sort_order, id")->fetchAll();
if ($divisionId) {
    $clsStmt = $db->prepare("SELECT c.*, d.division_name_bn FROM classes c LEFT JOIN divisions d ON c.division_id=d.id WHERE c.is_active=1 AND c.division_id=? ORDER BY c.class_numeric");
    $clsStmt->execute([$divisionId]);
    $classes = $clsStmt->fetchAll();
} else {
    $classes = $db->query("SELECT c.*, d.division_name_bn FROM classes c LEFT JOIN divisions d ON c.division_id=d.id WHERE c.is_active=1 ORDER BY d.sort_order, c.class_numeric")->fetchAll();
}
$subjects = $db->query("SELECT * FROM subjects WHERE is_active=1 ORDER BY subject_name_bn")->fetchAll();
$teachers = $db->query("SELECT * FROM teachers WHERE is_active=1 ORDER BY name_bn")->fetchAll();

$days = [0=>'রবিবার',1=>'সোমবার',2=>'মঙ্গলবার',3=>'বুধবার',4=>'বৃহস্পতিবার',5=>'শুক্রবার',6=>'শনিবার'];
$workDays = [0,1,2,3,4]; // Sun-Thu (Bangladesh)

// Save timetable entry
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_timetable'])) {
    if (!verifyCsrf($_POST['csrf']??'')) die('CSRF');
    $classId   = (int)$_POST['class_id'];
    $subjectId = (int)$_POST['subject_id'];
    $teacherId = (int)($_POST['teacher_id']??0) ?: null;
    $day       = (int)$_POST['day_of_week'];
    $startTime = $_POST['start_time'];
    $endTime   = $_POST['end_time'];
    $room      = trim($_POST['room']??'');
    $year      = date('Y');

    // Check conflict
    $conflict = $db->prepare("SELECT id FROM timetable WHERE class_id=? AND day_of_week=? AND academic_year=?
        AND ((start_time < ? AND end_time > ?) OR (start_time < ? AND end_time > ?) OR (start_time >= ? AND end_time <= ?))");
    $conflict->execute([$classId,$day,$year,$endTime,$startTime,$startTime,$startTime,$startTime,$endTime]);

    if ($conflict->fetch()) {
        setFlash('danger','এই সময়ে ইতিমধ্যে একটি ক্লাস নির্ধারিত আছে।');
    } else {
        $db->prepare("INSERT INTO timetable (class_id,subject_id,teacher_id,day_of_week,start_time,end_time,room,academic_year)
            VALUES (?,?,?,?,?,?,?,?)")
           ->execute([$classId,$subjectId,$teacherId,$day,$startTime,$endTime,$room,$year]);
        setFlash('success','রুটিন সংরক্ষিত হয়েছে।');
    }
    header('Location: index.php?division_id='.$divisionId.'&class_id='.$classId); exit;
}

// Delete
if (isset($_GET['delete']) && in_array($_SESSION['role_slug'],['super_admin','principal'])) {
    $db->prepare("DELETE FROM timetable WHERE id=?")->execute([(int)$_GET['delete']]);
    setFlash('success','মুছে ফেলা হয়েছে।');
    header('Location: index.php'); exit;
}

$selectedClass = (int)($_GET['class_id']??0);
$timetable = [];

if ($selectedClass) {
    $stmt = $db->prepare("SELECT tt.*, s.subject_name_bn, t.name_bn as teacher_name
        FROM timetable tt
        LEFT JOIN subjects s ON tt.subject_id=s.id
        LEFT JOIN teachers t ON tt.teacher_id=t.id
        WHERE tt.class_id=? AND tt.academic_year=?
        ORDER BY tt.day_of_week, tt.start_time");
    $stmt->execute([$selectedClass, date('Y')]);
    $rows = $stmt->fetchAll();
    foreach ($rows as $r) {
        $timetable[$r['day_of_week']][] = $r;
    }
}

require_once '../../includes/header.php';
?>

<div class="section-header">
    <h2 class="section-title"><i class="fas fa-calendar-alt"></i> ক্লাস রুটিন</h2>
    <div style="display:flex;gap:8px;">
        <?php if(in_array($_SESSION['role_slug'],['super_admin','principal'])): ?>
        <button onclick="openModal('addTimetableModal')" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> পিরিয়ড যোগ করুন</button>
        <?php endif; ?>
        <button onclick="window.print()" class="btn btn-outline btn-sm no-print"><i class="fas fa-print"></i> প্রিন্ট</button>
    </div>
</div>

<!-- Class selector -->
<div class="card mb-16 no-print">
    <div class="card-body" style="padding:12px 20px;">
        <form method="GET" id="ttFilter" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
            <input type="hidden" name="division_id" id="ttDivId" value="<?=$divisionId?>">
            <div class="form-group" style="margin:0;flex:1;min-width:130px;">
                <label style="font-size:12px;font-weight:600;">বিভাগ</label>
                <select class="form-control" style="padding:7px;" onchange="document.getElementById('ttDivId').value=this.value;document.querySelector('select[name=class_id]').value='';document.getElementById('ttFilter').submit()">
                    <option value="">সব বিভাগ</option>
                    <?php foreach($divisions as $dv): ?>
                    <option value="<?=$dv['id']?>" <?=$divisionId==$dv['id']?'selected':''?>><?=e($dv['division_name_bn'])?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;flex:1;max-width:300px;">
                <label style="font-size:12px;">শ্রেণী নির্বাচন করুন</label>
                <select name="class_id" class="form-control" style="padding:7px;" onchange="this.form.submit()">
                    <option value="">শ্রেণী নির্বাচন করুন</option>
                    <?php foreach($classes as $c): ?>
                    <option value="<?=$c['id']?>" <?=$selectedClass==$c['id']?'selected':''?>>
                        <?php if(!$divisionId): ?><?=e($c['division_name_bn']??'')?> → <?php endif; ?>
                        <?=e($c['class_name_bn'])?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>

<?php if ($selectedClass && !empty($timetable)): ?>
<!-- Timetable Grid -->
<div class="card">
    <div class="card-header">
        <span class="card-title">
            <?php foreach($classes as $c) if($c['id']==$selectedClass) echo e($c['class_name_bn']); ?> — রুটিন <?=toBanglaNumber(date('Y'))?>
        </span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th style="width:100px;">দিন</th>
                    <th>সময়</th>
                    <th>বিষয়</th>
                    <th>শিক্ষক</th>
                    <th>কক্ষ</th>
                    <?php if(in_array($_SESSION['role_slug'],['super_admin','principal'])): ?>
                    <th class="no-print">অ্যাকশন</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach($workDays as $dayNum):
                    $dayRows = $timetable[$dayNum] ?? [];
                    if (empty($dayRows)) continue;
                    $first = true;
                    foreach ($dayRows as $row):
                ?>
                <tr>
                    <?php if($first): ?>
                    <td rowspan="<?=count($dayRows)?>" style="font-weight:700;background:#ebf5fb;text-align:center;vertical-align:middle;font-size:14px;">
                        <?=$days[$dayNum]?>
                    </td>
                    <?php $first=false; endif; ?>
                    <td style="font-size:13px;white-space:nowrap;">
                        <?=toBanglaNumber(date('h:i',strtotime($row['start_time'])))?>
                        — <?=toBanglaNumber(date('h:i A',strtotime($row['end_time'])))?>
                    </td>
                    <td style="font-weight:600;"><?=e($row['subject_name_bn']??'')?></td>
                    <td style="font-size:13px;"><?=e($row['teacher_name']??'-')?></td>
                    <td style="font-size:13px;"><?=e($row['room']??'-')?></td>
                    <?php if(in_array($_SESSION['role_slug'],['super_admin','principal'])): ?>
                    <td class="no-print">
                        <a href="?delete=<?=$row['id']?>&class_id=<?=$selectedClass?>" onclick="return confirm('মুছবেন?')" class="btn btn-danger btn-xs">
                            <i class="fas fa-trash"></i>
                        </a>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif($selectedClass): ?>
<div class="card"><div class="card-body" style="text-align:center;padding:40px;color:var(--text-muted);">
    <i class="fas fa-calendar-times" style="font-size:48px;margin-bottom:16px;"></i>
    <p>এই শ্রেণীর জন্য কোনো রুটিন নির্ধারিত নেই।</p>
    <?php if(in_array($_SESSION['role_slug'],['super_admin','principal'])): ?>
    <button onclick="openModal('addTimetableModal')" class="btn btn-primary" style="margin-top:12px;">
        <i class="fas fa-plus"></i> রুটিন যোগ করুন
    </button>
    <?php endif; ?>
</div></div>

<?php else: ?>
<div class="card"><div class="card-body" style="text-align:center;padding:48px;color:var(--text-muted);">
    <i class="fas fa-hand-point-up" style="font-size:48px;margin-bottom:16px;"></i>
    <p style="font-size:16px;">উপরে শ্রেণী নির্বাচন করুন</p>
</div></div>
<?php endif; ?>

<!-- Add Timetable Modal -->
<?php if(in_array($_SESSION['role_slug'],['super_admin','principal'])): ?>
<div class="modal-overlay" id="addTimetableModal">
    <div class="modal-box" style="max-width:560px;">
        <div class="modal-header">
            <span style="font-weight:700;"><i class="fas fa-plus"></i> পিরিয়ড যোগ করুন</span>
            <button onclick="closeModal('addTimetableModal')" class="btn btn-outline btn-xs">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf" value="<?=getCsrfToken()?>">
            <input type="hidden" name="save_timetable" value="1">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label>শ্রেণী <span style="color:red;">*</span></label>
                        <select name="class_id" class="form-control" required>
                            <option value="">নির্বাচন করুন</option>
                            <?php foreach($classes as $c): ?>
                            <option value="<?=$c['id']?>" <?=$selectedClass==$c['id']?'selected':''?>><?=e($c['class_name_bn'])?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>দিন <span style="color:red;">*</span></label>
                        <select name="day_of_week" class="form-control" required>
                            <?php foreach($workDays as $d): ?>
                            <option value="<?=$d?>"><?=$days[$d]?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>বিষয় <span style="color:red;">*</span></label>
                        <select name="subject_id" class="form-control" required>
                            <option value="">নির্বাচন করুন</option>
                            <?php foreach($subjects as $s): ?>
                            <option value="<?=$s['id']?>"><?=e($s['subject_name_bn'])?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>শিক্ষক</label>
                        <select name="teacher_id" class="form-control">
                            <option value="">নির্বাচন করুন</option>
                            <?php foreach($teachers as $t): ?>
                            <option value="<?=$t['id']?>"><?=e($t['name_bn'])?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>শুরুর সময় <span style="color:red;">*</span></label>
                        <input type="time" name="start_time" class="form-control" required value="08:00">
                    </div>
                    <div class="form-group">
                        <label>শেষের সময় <span style="color:red;">*</span></label>
                        <input type="time" name="end_time" class="form-control" required value="08:45">
                    </div>
                    <div class="form-group" style="grid-column:1/-1;">
                        <label>কক্ষ নম্বর</label>
                        <input type="text" name="room" class="form-control" placeholder="যেমন: কক্ষ ১০১">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="closeModal('addTimetableModal')" class="btn btn-outline">বাতিল</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> সংরক্ষণ করুন</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>
