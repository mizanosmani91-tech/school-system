<?php
require_once '../../includes/functions.php';
requireLogin(['super_admin','principal','teacher']);
$pageTitle = 'অভিভাবক বার্তাসমূহ';
$db = getDB();

// Reply
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['reply_message'])) {
    if (!verifyCsrf($_POST['csrf']??'')) die('CSRF');
    $msgId = (int)$_POST['msg_id'];
    $reply = trim($_POST['reply']??'');
    if ($reply && $msgId) {
        $db->prepare("UPDATE parent_messages SET reply=?, status='replied', replied_at=NOW(), replied_by=? WHERE id=?")
            ->execute([$reply, $_SESSION['user_id'], $msgId]);
        setFlash('success','উত্তর পাঠানো হয়েছে।');
    }
    header('Location: messages.php'); exit;
}

$messages = $db->query("SELECT pm.*, s.name_bn as sname, s.student_id as sid, c.class_name_bn
    FROM parent_messages pm
    JOIN students s ON pm.student_id=s.id
    LEFT JOIN classes c ON s.class_id=c.id
    ORDER BY pm.created_at DESC")->fetchAll();

require_once '../../includes/header.php';
?>
<div class="section-header">
    <h2 class="section-title"><i class="fas fa-comments"></i> অভিভাবক বার্তাসমূহ</h2>
</div>
<div class="card">
    <div class="table-wrap">
        <table>
            <thead><tr><th>ছাত্র</th><th>অভিভাবকের বার্তা</th><th>তারিখ</th><th>অবস্থা</th><th>অ্যাকশন</th></tr></thead>
            <tbody>
                <?php if(empty($messages)): ?>
                <tr><td colspan="5" style="text-align:center;padding:30px;color:#718096;">কোনো বার্তা নেই</td></tr>
                <?php else: foreach($messages as $m): ?>
                <tr style="<?=$m['status']==='unread'?'background:#ebf5fb':''?>">
                    <td>
                        <div style="font-weight:600;font-size:13px;"><?=e($m['sname']??'')?></div>
                        <div style="font-size:11px;color:var(--text-muted);"><?=e($m['class_name_bn']??'')?> &bull; <?=e($m['sid']??'')?></div>
                    </td>
                    <td style="max-width:250px;">
                        <div style="font-size:13px;"><?=e(mb_substr($m['message'],0,100))?>...</div>
                        <?php if($m['reply']): ?>
                        <div style="margin-top:6px;background:var(--bg);border-radius:6px;padding:6px 8px;font-size:12px;color:var(--text-muted);">
                            <strong>উত্তর:</strong> <?=e(mb_substr($m['reply'],0,80))?>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:12px;"><?=banglaDate($m['created_at'])?></td>
                    <td>
                        <span class="badge badge-<?=$m['status']==='unread'?'warning':($m['status']==='replied'?'success':'info')?>">
                            <?=['unread'=>'অপঠিত','read'=>'পঠিত','replied'=>'উত্তর দেওয়া'][$m['status']]??e($m['status'])?>
                        </span>
                    </td>
                    <td>
                        <button onclick="openReply(<?=$m['id']?>,<?=json_encode($m['message'])?>)" class="btn btn-primary btn-xs">
                            <i class="fas fa-reply"></i> উত্তর
                        </button>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Reply Modal -->
<div class="modal-overlay" id="replyModal">
    <div class="modal-box">
        <div class="modal-header">
            <span style="font-weight:700;"><i class="fas fa-reply"></i> বার্তার উত্তর</span>
            <button onclick="closeModal('replyModal')" class="btn btn-outline btn-xs">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf" value="<?=getCsrfToken()?>">
            <input type="hidden" name="reply_message" value="1">
            <input type="hidden" name="msg_id" id="replyMsgId">
            <div class="modal-body">
                <div style="background:var(--bg);border-radius:8px;padding:12px;margin-bottom:16px;font-size:13px;" id="origMsg"></div>
                <div class="form-group">
                    <label>আপনার উত্তর</label>
                    <textarea name="reply" class="form-control" rows="4" required placeholder="অভিভাবককে উত্তর লিখুন..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="closeModal('replyModal')" class="btn btn-outline">বাতিল</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> উত্তর পাঠান</button>
            </div>
        </form>
    </div>
</div>
<script>
function openReply(id, msg) {
    document.getElementById('replyMsgId').value = id;
    document.getElementById('origMsg').textContent = 'অভিভাবকের বার্তা: ' + msg;
    openModal('replyModal');
}
</script>
<?php require_once '../../includes/footer.php'; ?>
