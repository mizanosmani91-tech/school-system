<?php
require_once '../../includes/functions.php';
requireLogin();
$pageTitle = 'AI সহকারী';
$currentUser = getCurrentUser();

// Handle AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ai_message'])) {
    header('Content-Type: application/json');
    if (!verifyCsrf($_POST['csrf'] ?? '')) {
        echo json_encode(['error' => 'CSRF Error']);
        exit;
    }

    $userMsg = trim($_POST['ai_message']);
    $context = $_POST['context'] ?? 'general';
    $history = json_decode($_POST['history'] ?? '[]', true);

    $instituteName = getSetting('institute_name', 'স্কুল');

    $systemPrompt = "তুমি {$instituteName}-এর একজন বুদ্ধিমান শিক্ষা ব্যবস্থাপনা সহকারী। তুমি বাংলায় কথা বলো। তুমি নিচের বিষয়গুলোতে সাহায্য করতে পারো:
- শিক্ষার্থীদের পড়াশোনা ও মূল্যায়ন পরামর্শ
- শিক্ষকদের পাঠ পরিকল্পনা তৈরিতে সাহায্য
- ইসলামী শিক্ষার বিষয়বস্তু ব্যাখ্যা (কুরআন, হাদিস, ফিকহ)
- বাংলাদেশের শিক্ষাবোর্ড সংক্রান্ত তথ্য (দাখিল, আলিম ইত্যাদি)
- অভিভাবক যোগাযোগের জন্য চিঠি/বার্তা রচনা
- স্কুল/মাদ্রাসা পরিচালনা সংক্রান্ত পরামর্শ
- পরীক্ষার প্রশ্নপত্র তৈরিতে সাহায্য
- SMS বার্তা/নোটিশ রচনা

বর্তমান ব্যবহারকারী: {$currentUser['name_bn']} ({$currentUser['role_name']})
আজকের তারিখ: " . banglaDate() . "

সংক্ষিপ্ত, স্পষ্ট এবং সহায়ক উত্তর দাও।";

    $result = callAI($userMsg, $systemPrompt, $history);

    // Save log
    try {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO ai_chat_logs (user_id, session_id, message, response, context) VALUES (?,?,?,?,?)");
        $stmt->execute([$currentUser['id'], session_id(), $userMsg, $result['text'] ?? '', $context]);
    } catch(Exception $e){}

    echo json_encode($result);
    exit;
}

require_once '../../includes/header.php';
?>
<div class="section-header">
    <h2 class="section-title"><i class="fas fa-robot"></i> AI সহকারী</h2>
    <button onclick="clearChat()" class="btn btn-outline btn-sm"><i class="fas fa-trash"></i> চ্যাট মুছুন</button>
</div>

<div class="grid-2">
    <!-- Chat Interface -->
    <div class="card" style="grid-column:1/-1;">
        <div class="card-header">
            <span class="card-title">
                <span style="width:8px;height:8px;background:var(--success);border-radius:50%;display:inline-block;margin-right:6px;"></span>
                AI সহকারী সক্রিয়
            </span>
            <select id="aiContext" class="form-control" style="width:auto;padding:5px 10px;font-size:13px;">
                <option value="general">সাধারণ সহায়তা</option>
                <option value="lesson_plan">পাঠ পরিকল্পনা</option>
                <option value="question_paper">প্রশ্নপত্র তৈরি</option>
                <option value="notice">নোটিশ/চিঠি রচনা</option>
                <option value="sms">SMS বার্তা</option>
                <option value="islamic">ইসলামী শিক্ষা</option>
                <option value="parent_msg">অভিভাবক বার্তা</option>
            </select>
        </div>

        <div class="ai-chat-box">
            <div class="ai-messages" id="aiMessages">
                <div class="ai-msg bot">
                    <strong>🤖 AI সহকারী:</strong><br>
                    আসসালামু আলাইকুম! আমি আপনার স্কুল/মাদ্রাসা পরিচালনায় সাহায্য করতে প্রস্তুত।<br><br>
                    আমি যা করতে পারি:
                    <ul style="margin-top:8px;padding-left:20px;line-height:2;">
                        <li>পাঠ পরিকল্পনা ও প্রশ্নপত্র তৈরি</li>
                        <li>অভিভাবকদের জন্য SMS/চিঠি লেখা</li>
                        <li>ইসলামী বিষয়বস্তু ব্যাখ্যা</li>
                        <li>নোটিশ ও বিজ্ঞপ্তি রচনা</li>
                        <li>শিক্ষার্থী মূল্যায়ন পরামর্শ</li>
                    </ul>
                    <br>কীভাবে সাহায্য করতে পারি?
                </div>
            </div>
            <div class="ai-input-row">
                <textarea id="aiInput" class="form-control" rows="2" placeholder="আপনার প্রশ্ন বা অনুরোধ লিখুন... (Shift+Enter = নতুন লাইন, Enter = পাঠান)"
                    style="resize:none;"></textarea>
                <button id="aiSendBtn" onclick="sendMessage()" class="btn btn-primary" style="align-self:flex-end;">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Quick Prompts -->
<div class="card mt-16">
    <div class="card-header"><span class="card-title"><i class="fas fa-bolt"></i> দ্রুত প্রম্পট</span></div>
    <div class="card-body">
        <div style="display:flex;flex-wrap:wrap;gap:8px;">
            <?php
            $prompts = [
                ['📋 পাঠ পরিকল্পনা', 'পঞ্চম শ্রেণীর গণিতের জন্য ৪৫ মিনিটের একটি পাঠ পরিকল্পনা তৈরি করো।'],
                ['📝 MCQ প্রশ্ন', 'আরবি ব্যাকরণের উপর ১০টি MCQ প্রশ্ন তৈরি করো (দাখিল স্তরের জন্য)।'],
                ['📱 অনুপস্থিতি SMS', 'অভিভাবকদের জন্য ছাত্রের অনুপস্থিতির একটি SMS বার্তা লেখো।'],
                ['🕌 হাদিস ব্যাখ্যা', 'নিয়তের হাদিসটি সহজ বাংলায় ব্যাখ্যা করো।'],
                ['📢 পরীক্ষার নোটিশ', 'বার্ষিক পরীক্ষার জন্য একটি নোটিশ লেখো।'],
                ['✉️ অভিভাবক চিঠি', 'ছাত্রের পড়াশোনায় অমনোযোগিতার বিষয়ে অভিভাবককে একটি চিঠি লেখো।'],
                ['📊 রিপোর্ট কার্ড', 'একটি ছাত্রের প্রগতি রিপোর্টের মন্তব্য লেখো (ভালো ফলাফলের জন্য)।'],
                ['🤲 দু\'আ', 'পরীক্ষার আগে পড়ার জন্য একটি মাসনুন দু\'আ লেখো।'],
            ];
            foreach ($prompts as [$label, $prompt]):
            ?>
            <button onclick="setPrompt(<?= json_encode($prompt) ?>)" class="btn btn-outline btn-sm"><?= $label ?></button>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
let chatHistory = [];
const csrf = '<?= getCsrfToken() ?>';

function setPrompt(text) {
    document.getElementById('aiInput').value = text;
    document.getElementById('aiInput').focus();
}

function sendMessage() {
    const input = document.getElementById('aiInput');
    const msg = input.value.trim();
    if (!msg) return;

    addMessage(msg, 'user');
    input.value = '';
    input.style.height = 'auto';

    const sendBtn = document.getElementById('aiSendBtn');
    sendBtn.disabled = true;
    sendBtn.innerHTML = '<div class="spinner" style="width:18px;height:18px;border-width:2px;"></div>';

    const context = document.getElementById('aiContext').value;

    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            csrf: csrf,
            ai_message: msg,
            context: context,
            history: JSON.stringify(chatHistory.slice(-10))
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.error) {
            addMessage('ত্রুটি: ' + data.error, 'bot', true);
        } else {
            addMessage(data.text, 'bot');
            chatHistory.push({ role: 'user', content: msg });
            chatHistory.push({ role: 'assistant', content: data.text });
        }
    })
    .catch(() => addMessage('সংযোগ ব্যর্থ হয়েছে।', 'bot', true))
    .finally(() => {
        sendBtn.disabled = false;
        sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i>';
    });
}

function addMessage(text, role, isError = false) {
    const container = document.getElementById('aiMessages');
    const div = document.createElement('div');
    div.className = 'ai-msg ' + role;
    if (isError) div.style.background = '#fff5f5';
    // Convert markdown-ish formatting
    const formatted = text
        .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
        .replace(/\*(.*?)\*/g, '<em>$1</em>')
        .replace(/\n/g, '<br>');
    div.innerHTML = role === 'bot' ? '<strong>🤖 AI:</strong><br>' + formatted : text;
    container.appendChild(div);
    container.scrollTop = container.scrollHeight;
}

function clearChat() {
    if (confirm('চ্যাট ইতিহাস মুছে ফেলবেন?')) {
        chatHistory = [];
        const container = document.getElementById('aiMessages');
        container.innerHTML = '<div class="ai-msg bot">চ্যাট পরিষ্কার করা হয়েছে।</div>';
    }
}

// Enter to send
document.getElementById('aiInput').addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>
