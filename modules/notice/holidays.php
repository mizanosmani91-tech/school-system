<?php
require_once '../../includes/functions.php';
requireLogin(['super_admin','principal']);
$pageTitle = 'ছুটির তালিকা';
$db = getDB();

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_holiday'])) {
    if (!verifyCsrf($_POST['csrf']??'')) die('CSRF');
    $name = trim($_POST['holiday_name_bn']??'');
    $type = $_POST['holiday_type']??'national';
    $start = $_POST['start_date']??''; $end = $_POST['end_date']??$start;
    if ($name && $start) {
        $db->prepare("INSERT INTO holidays (holiday_name, holiday_name_bn, holiday_type, start_date, end_date) VALUES (?,?,?,?,?)")
            ->execute([$name,$name,$type,$start,$end]);
        setFlash('success','ছুটি যোগ করা হয়েছে।');
    }
    header('Location: holidays.php'); exit;
}
if (isset($_GET['delete'])) {
    $db->prepare("DELETE FROM holidays WHERE id=?")->execute([(int)$_GET['delete']]);
    setFlash('success','ছুটি মুছে ফেলা হয়েছে।');
    header('Location: holidays.php'); exit;
}

$holidays = $db->query("SELECT * FROM holidays ORDER BY start_date")->fetchAll();
require_once '../../includes/header.php';
?>
<div class="section-header">
    <h2 class="section-title"><i class="fas fa-calendar-times"></i> ছুটির তালিকা</h2>
    <button onclick="openModal('addHolidayModal')" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> ছুটি যোগ করুন</button>
</div>
<div class="card">
    <div class="table-wrap">
        <table>
            <thead><tr><th>ছুটির নাম</th><th>ধরন</th><th>শুরু</th><th>শেষ</th><th>অ্যাকশন</th></tr></thead>
            <tbody>
                <?php foreach($holidays as $h): ?>
                <tr>
                    <td style="font-weight:600;"><?=e($h['holiday_name_bn']??$h['holiday_name'])?></td>
                    <td><span class="badge badge-<?=['national'=>'primary','religious'=>'success','school'=>'info','exam'=>'warning'][$h['holiday_type']]??'secondary'?>">
                        <?=['national'=>'জাতীয়','religious'=>'ধর্মীয়','school'=>'বিদ্যালয়','exam'=>'পরীক্ষা'][$h['holiday_type']]??$h['holiday_type']?>
                    </span></td>
                    <td><?=banglaDate($h['start_date'])?></td>
                    <td><?=$h['end_date']?banglaDate($h['end_date']):'-'?></td>
                    <td><a href="?delete=<?=$h['id']?>" onclick="return confirm('মুছবেন?')" class="btn btn-danger btn-xs"><i class="fas fa-trash"></i></a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="addHolidayModal">
    <div class="modal-box">
        <div class="modal-header">
            <span style="font-weight:700;">ছুটি যোগ করুন</span>
            <button onclick="closeModal('addHolidayModal')" class="btn btn-outline btn-xs">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf" value="<?=getCsrfToken()?>">
            <input type="hidden" name="add_holiday" value="1">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group" style="grid-column:1/-1;"><label>ছুটির নাম *</label>
                        <input type="text" name="holiday_name_bn" class="form-control" required placeholder="ঈদুল ফিতর"></div>
                    <div class="form-group"><label>ধরন</label>
                        <select name="holiday_type" class="form-control">
                            <option value="national">জাতীয়</option>
                            <option value="religious">ধর্মীয়</option>
                            <option value="school">বিদ্যালয়</option>
                            <option value="exam">পরীক্ষা</option>
                        </select></div>
                    <div class="form-group"><label>শুরুর তারিখ *</label>
                        <input type="date" name="start_date" class="form-control" required></div>
                    <div class="form-group"><label>শেষের তারিখ</label>
                        <input type="date" name="end_date" class="form-control"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="closeModal('addHolidayModal')" class="btn btn-outline">বাতিল</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> যোগ করুন</button>
            </div>
        </form>
    </div>
</div>
<?php require_once '../../includes/footer.php'; ?>
