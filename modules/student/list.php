<?php
require_once '../../includes/functions.php';
requireLogin();
$pageTitle = 'ছাত্র তালিকা';
$db = getDB();

// Filters
$divisionFilter = (int)($_GET['division_id'] ?? 0);
$classFilter    = (int)($_GET['class_id'] ?? 0);
// FIXED: search থেকে ?page=X বা &page=X সরিয়ে দাও
$search         = trim(preg_replace('/[?&]page=\d+/', '', $_GET['search'] ?? ''));
$status         = $_GET['status'] ?? 'active';
$page           = max(1, (int)($_GET['page'] ?? 1));
$perPage        = 20;
$offset         = ($page - 1) * $perPage;

$where  = ["s.academic_year = '" . date('Y') . "'"];
$params = [];

if ($divisionFilter) {
    $where[]  = 's.division_id = ?';
    $params[] = $divisionFilter;
}
if ($classFilter) {
    $where[]  = 's.class_id = ?';
    $params[] = $classFilter;
}
if ($status) {
    $where[]  = 's.status = ?';
    $params[] = $status;
}
if ($search) {
    $where[]  = '(s.name LIKE ? OR s.name_bn LIKE ? OR s.student_id LIKE ? OR s.father_phone LIKE ?)';
    $s        = "%$search%";
    $params   = array_merge($params, [$s, $s, $s, $s]);
}
$whereStr = implode(' AND ', $where);

// মোট সংখ্যা
$totalStmt = $db->prepare("SELECT COUNT(*) FROM students s WHERE $whereStr");
$totalStmt->execute($params);
$total = $totalStmt->fetchColumn();

// ছাত্র তালিকা
$stmt = $db->prepare("
    SELECT s.*, c.class_name_bn, sec.section_name, d.division_name_bn
    FROM students s
    LEFT JOIN classes c   ON s.class_id    = c.id
    LEFT JOIN sections sec ON s.section_id = sec.id
    LEFT JOIN divisions d  ON s.division_id = d.id
    WHERE $whereStr
    ORDER BY d.sort_order, c.class_numeric, s.roll_number
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$students = $stmt->fetchAll();

// সব বিভাগ
$divisions = $db->query("SELECT * FROM divisions WHERE is_active=1 ORDER BY sort_order, id")->fetchAll();

// শ্রেণী
if ($divisionFilter) {
    $clsStmt = $db->prepare("SELECT c.*, d.division_name_bn FROM classes c LEFT JOIN divisions d ON c.division_id=d.id WHERE c.is_active=1 AND c.division_id=? ORDER BY c.class_numeric");
    $clsStmt->execute([$divisionFilter]);
    $classes = $clsStmt->fetchAll();
} else {
    $classes = $db->query("SELECT c.*, d.division_name_bn FROM classes c LEFT JOIN divisions d ON c.division_id=d.id WHERE c.is_active=1 ORDER BY d.sort_order, c.class_numeric")->fetchAll();
}

// FIXED: Pagination base URL — & দিয়ে জুড়বে, ? দিয়ে নয়
$paginateBase = 'list.php?' . http_build_query([
    'division_id' => $divisionFilter,
    'class_id'    => $classFilter,
    'status'      => $status,
    'search'      => $search,
]);

require_once '../../includes/header.php';
?>

<div class="section-header">
    <h2 class="section-title"><i class="fas fa-user-graduate"></i> ছাত্র তালিকা</h2>
    <div style="display:flex;gap:8px;">
        <a href="admission.php" class="btn btn-primary btn-sm"><i class="fas fa-user-plus"></i> নতুন ভর্তি</a>
        <button onclick="window.print()" class="btn btn-outline btn-sm no-print"><i class="fas fa-print"></i> প্রিন্ট</button>
    </div>
</div>

<!-- ===== Filter ===== -->
<div class="card mb-16 no-print">
    <div class="card-body" style="padding:14px 20px;">
        <form method="GET" id="filterForm" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">

            <!-- বিভাগ -->
            <div class="form-group" style="flex:1;min-width:160px;margin:0;">
                <label style="font-size:12px;font-weight:600;">বিভাগ</label>
                <select name="division_id" id="divisionFilter" class="form-control" style="padding:7px 10px;" onchange="onDivisionChange(this.value)">
                    <option value="">সব বিভাগ</option>
                    <?php foreach ($divisions as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= $divisionFilter == $d['id'] ? 'selected' : '' ?>>
                        <?= e($d['division_name_bn']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- শ্রেণী -->
            <div class="form-group" style="flex:1;min-width:160px;margin:0;">
                <label style="font-size:12px;font-weight:600;">শ্রেণী</label>
                <select name="class_id" id="classFilter" class="form-control" style="padding:7px 10px;" onchange="this.form.submit()">
                    <option value="">সব শ্রেণী</option>
                    <?php foreach ($classes as $c): ?>
                    <option value="<?= $c['id'] ?>"
                        data-div="<?= $c['division_id'] ?>"
                        <?= $classFilter == $c['id'] ? 'selected' : '' ?>>
                        <?php if (!$divisionFilter): ?>
                            <?= e($c['division_name_bn']) ?> →
                        <?php endif; ?>
                        <?= e($c['class_name_bn']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- অবস্থা -->
            <div class="form-group" style="flex:1;min-width:140px;margin:0;">
                <label style="font-size:12px;font-weight:600;">অবস্থা</label>
                <select name="status" class="form-control" style="padding:7px 10px;" onchange="this.form.submit()">
                    <option value="active"   <?= $status == 'active'   ? 'selected' : '' ?>>সক্রিয়</option>
                    <option value="inactive" <?= $status == 'inactive' ? 'selected' : '' ?>>নিষ্ক্রিয়</option>
                    <option value=""         <?= $status == ''         ? 'selected' : '' ?>>সবাই</option>
                </select>
            </div>

            <!-- অনুসন্ধান -->
            <div class="form-group" style="flex:2;min-width:220px;margin:0;">
                <label style="font-size:12px;font-weight:600;">অনুসন্ধান</label>
                <input type="text" name="search" id="searchInput" class="form-control"
                    style="padding:7px 10px;"
                    placeholder="নাম, ID, ফোন নম্বর..."
                    value="<?= e($search) ?>">
            </div>

            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> খুঁজুন</button>
            <a href="list.php" class="btn btn-outline btn-sm"><i class="fas fa-redo"></i> রিসেট</a>
        </form>
    </div>
</div>

<!-- ===== বিভাগ ট্যাব (quick filter) ===== -->
<div class="no-print" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;">
    <a href="list.php?status=<?= urlencode($status) ?>&search=<?= urlencode($search) ?>"
       class="btn btn-sm <?= !$divisionFilter ? 'btn-primary' : 'btn-outline' ?>">
        <i class="fas fa-layer-group"></i> সব বিভাগ
    </a>
    <?php foreach ($divisions as $d): ?>
    <a href="list.php?division_id=<?= $d['id'] ?>&status=<?= urlencode($status) ?>&search=<?= urlencode($search) ?>"
       class="btn btn-sm <?= $divisionFilter == $d['id'] ? 'btn-primary' : 'btn-outline' ?>">
        <?= e($d['division_name_bn']) ?>
    </a>
    <?php endforeach; ?>
</div>

<!-- ===== টেবিল ===== -->
<div class="card">
    <div class="card-header">
        <span class="card-title">
            মোট <?= toBanglaNumber($total) ?> জন ছাত্র
            <?php if ($divisionFilter): ?>
                <?php foreach ($divisions as $d): if ($d['id'] == $divisionFilter): ?>
                <span style="font-size:12px;color:var(--text-muted);font-weight:400;">
                    — <?= e($d['division_name_bn']) ?>
                </span>
                <?php endif; endforeach; ?>
            <?php endif; ?>
        </span>
        <a href="<?= BASE_URL ?>/api/export.php?type=students&division_id=<?= $divisionFilter ?>&class_id=<?= $classFilter ?>"
           class="btn btn-outline btn-sm no-print">
            <i class="fas fa-file-excel"></i> এক্সপোর্ট
        </a>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>ছাত্রের তথ্য</th>
                    <th>বিভাগ / শ্রেণী / শাখা</th>
                    <th>পিতার নাম</th>
                    <th>ফোন</th>
                    <th>অবস্থা</th>
                    <th class="no-print">অ্যাকশন</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($students)): ?>
                <tr>
                    <td colspan="7" style="text-align:center;padding:30px;color:#718096;">
                        কোনো ছাত্র পাওয়া যায়নি
                    </td>
                </tr>
                <?php else: foreach ($students as $i => $s): ?>
                <tr>
                    <td style="font-size:13px;color:var(--text-muted);">
                        <?= toBanglaNumber($offset + $i + 1) ?>
                    </td>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <?php
                            $p    = $s['photo'] ?? '';
                            $pUrl = $p ? ((strpos($p, 'http') === 0) ? $p : UPLOAD_URL . e($p)) : '';
                            ?>
                            <?php if ($pUrl): ?>
                            <img src="<?= $pUrl ?>" style="width:36px;height:36px;border-radius:8px;object-fit:cover;">
                            <?php else: ?>
                            <div class="avatar"><?= mb_substr($s['name_bn'] ?? $s['name'], 0, 1) ?></div>
                            <?php endif; ?>
                            <div>
                                <div style="font-weight:600;font-size:14px;"><?= e($s['name_bn'] ?? $s['name']) ?></div>
                                <div style="font-size:11px;color:var(--text-muted);">
                                    ID: <?= e($s['student_id']) ?> &bull; রোল: <?= toBanglaNumber($s['roll_number']) ?>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <?php if ($s['division_name_bn']): ?>
                        <div style="font-size:11px;color:var(--primary);font-weight:700;margin-bottom:2px;">
                            <?= e($s['division_name_bn']) ?>
                        </div>
                        <?php endif; ?>
                        <div style="font-size:13px;font-weight:600;"><?= e($s['class_name_bn'] ?? '') ?></div>
                        <div style="font-size:11px;color:var(--text-muted);">শাখা: <?= e($s['section_name'] ?? 'নেই') ?></div>
                    </td>
                    <td style="font-size:13px;"><?= e($s['father_name'] ?? '-') ?></td>
                    <td style="font-size:13px;"><?= e($s['father_phone'] ?? '-') ?></td>
                    <td>
                        <span class="badge badge-<?= $s['status'] === 'active' ? 'success' : 'secondary' ?>">
                            <?= $s['status'] === 'active' ? 'সক্রিয়' : e($s['status']) ?>
                        </span>
                    </td>
                    <td class="no-print">
                        <div style="display:flex;gap:4px;">
                            <a href="view.php?id=<?= $s['id'] ?>"
                               class="btn btn-info btn-xs" title="দেখুন"><i class="fas fa-eye"></i></a>
                            <a href="edit.php?id=<?= $s['id'] ?>"
                               class="btn btn-warning btn-xs" title="সম্পাদনা"><i class="fas fa-edit"></i></a>
                            <a href="<?= BASE_URL ?>/modules/fees/student.php?id=<?= $s['id'] ?>"
                               class="btn btn-success btn-xs" title="ফি"><i class="fas fa-money-bill"></i></a>
                            <a href="<?= BASE_URL ?>/modules/exam/result.php?student_id=<?= $s['id'] ?>"
                               class="btn btn-primary btn-xs" title="ফলাফল"><i class="fas fa-file-alt"></i></a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($total > $perPage): ?>
    <div class="card-footer no-print">
        <?= paginate($total, $perPage, $page, $paginateBase) ?>
    </div>
    <?php endif; ?>
</div>

<script>
function onDivisionChange(divId) {
    const classSel = document.getElementById('classFilter');
    classSel.value = '';
    document.getElementById('filterForm').submit();
}

(function(){
    var t;
    document.getElementById('searchInput').addEventListener('input', function(){
        clearTimeout(t);
        t = setTimeout(function(){ document.getElementById('filterForm').submit(); }, 500);
    });
})();
</script>

<?php require_once '../../includes/footer.php'; ?>
