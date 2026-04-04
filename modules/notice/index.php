<?php
require_once '../../includes/functions.php';
requireLogin();
$pageTitle = 'নোটিশ বোর্ড';
$db = getDB();

// Add notice
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_notice'])) {
    if (!verifyCsrf($_POST['csrf'] ?? '')) die('CSRF');
    $title = trim($_POST['title'] ?? '');
    $titleBn = trim($_POST['title_bn'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $type = $_POST['notice_type'] ?? 'general';
    $audience = $_POST['target_audience'] ?? 'all';
    $publishDate = $_POST['publish_date'] ?? date('Y-m-d');

    if ($title) {
        $stmt = $db->prepare("INSERT INTO notices (title, title_bn, content, notice_type, target_audience, is_published, publish_date, created_by) VALUES (?,?,?,?,?,1,?,?)");
        $stmt->execute([$title, $titleBn, $content, $type, $audience, $publishDate, $_SESSION['user_id']]);
        setFlash('success', 'নোটিশ সফলভাবে প্রকাশিত হয়েছে।');
        header('Location: index.php');
        exit;
    }
}

$notices = $db->query("SELECT n.*, u.name_bn as added_by FROM notices n LEFT JOIN users u ON n.created_by=u.id ORDER BY n.created_at DESC")->fetchAll();

require_once '../../includes/header.php';
?>
<div class="section-header">
    <h2 class="section-title"><i class="fas fa-bullhorn"></i> নোটিশ বোর্ড</h2>
    <button onclick="openModal('addNoticeModal')" class="btn btn-primary btn-sm">
        <i class="fas fa-plus"></i> নতুন নোটিশ
    </button>
</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>নোটিশ</th><th>ধরন</th><th>প্রকাশের তারিখ</th><th>লক্ষ্যমাত্রা</th><th>অ্যাকশন</th></tr>
            </thead>
            <tbody>
                <?php if (empty($notices)): ?>
                <tr><td colspan="5" style="text-align:center;padding:30px;color:#718096;">কোনো নোটিশ নেই</td></tr>
                <?php else: foreach ($notices as $n): ?>
                <tr>
                    <td>
                        <div style="font-weight:700;font-size:14px;"><?= e($n['title_bn'] ?? $n['title']) ?></div>
                        <div style="font-size:12px;color:#718096;margin-top:2px;"><?= e(mb_substr($n['content'] ?? '', 0, 80)) ?>...</div>
                    </td>
                    <td>
                        <span class="badge badge-<?= $n['notice_type']==='urgent'?'danger':($n['notice_type']==='exam'?'warning':'info') ?>">
                            <?= e($n['notice_type']) ?>
                        </span>
                    </td>
                    <td style="font-size:13px;"><?= banglaDate($n['publish_date']) ?></td>
                    <td style="font-size:13px;"><?= e($n['target_audience']) ?></td>
                    <td>
                        <a href="delete.php?id=<?= $n['id'] ?>" onclick="return confirm('নোটিশটি মুছবেন?')" class="btn btn-danger btn-xs">
                            <i class="fas fa-trash"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Notice Modal -->
<div class="modal-overlay" id="addNoticeModal">
    <div class="modal-box">
        <div class="modal-header">
            <span style="font-weight:700;">নতুন নোটিশ যোগ করুন</span>
            <button onclick="closeModal('addNoticeModal')" class="btn btn-outline btn-xs">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf" value="<?= getCsrfToken() ?>">
            <input type="hidden" name="add_notice" value="1">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label>শিরোনাম (বাংলায়) <span style="color:red;">*</span></label>
                        <input type="text" name="title_bn" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>শিরোনাম (ইংরেজিতে)</label>
                        <input type="text" name="title" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>ধরন</label>
                        <select name="notice_type" class="form-control">
                            <option value="general">সাধারণ</option>
                            <option value="academic">একাডেমিক</option>
                            <option value="exam">পরীক্ষা</option>
                            <option value="fee">ফি</option>
                            <option value="holiday">ছুটি</option>
                            <option value="urgent">জরুরি</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>লক্ষ্যমাত্রা</label>
                        <select name="target_audience" class="form-control">
                            <option value="all">সবাই</option>
                            <option value="students">ছাত্রছাত্রী</option>
                            <option value="parents">অভিভাবক</option>
                            <option value="teachers">শিক্ষক</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>প্রকাশের তারিখ</label>
                        <input type="date" name="publish_date" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                </div>
                <div class="form-group mt-16">
                    <label>নোটিশের বিস্তারিত</label>
                    <textarea name="content" class="form-control" rows="4"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="closeModal('addNoticeModal')" class="btn btn-outline">বাতিল</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> প্রকাশ করুন</button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
