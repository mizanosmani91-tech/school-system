<?php
// ================================================================
// migrate_unique_codes.php
// পুরানো students, teachers ও staff দের unique code দেওয়ার জন্য
// ব্যবহার: একবার run করুন, তারপর এই ফাইলটি DELETE করুন!
// ================================================================

require_once 'includes/functions.php';
$db = getDB();

$types = [
    ['table' => 'students', 'type' => 'student'],
    ['table' => 'teachers', 'type' => 'teacher'],
    ['table' => 'staff',    'type' => 'staff'],
];

echo '<!DOCTYPE html>
<html><head><meta charset="UTF-8">
<title>Unique Code Migration</title>
<style>
body { font-family: Arial, sans-serif; padding: 30px; background: #f5f5f5; }
.box { background: #fff; border-radius: 10px; padding: 24px; max-width: 700px; margin: auto; box-shadow: 0 2px 10px rgba(0,0,0,.1); }
h2 { color: #2c3e50; }
.ok  { color: #27ae60; }
.warn{ color: #e67e22; }
.done{ background:#eafaf1; border:1px solid #27ae60; border-radius:8px; padding:12px; margin-top:20px; font-weight:bold; color:#27ae60; }
</style></head><body><div class="box">
<h2>🔄 Unique Code Migration</h2>';

$total = 0;

foreach ($types as $t) {
    try {
        $rows = $db->query("SELECT id FROM {$t['table']} WHERE unique_code IS NULL OR unique_code = ''")->fetchAll();

        if (empty($rows)) {
            echo "<p class='warn'>⚠️ <b>{$t['table']}</b> — কোনো খালি রেকর্ড নেই।</p>";
            continue;
        }

        echo "<p><b>{$t['table']}</b> — {$rows[0]} টি রেকর্ড পাওয়া গেছে...</p>";

        foreach ($rows as $row) {
            $code = generateUniqueCode($db, $t['type']);
            $db->prepare("UPDATE {$t['table']} SET unique_code = ? WHERE id = ?")
               ->execute([$code, $row['id']]);
            echo "<p class='ok'>✅ {$t['table']} ID {$row['id']} → <b>$code</b></p>";
            $total++;
        }

    } catch (Exception $e) {
        echo "<p class='warn'>⚠️ <b>{$t['table']}</b> table নেই বা error: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
}

echo "<div class='done'>✅ মোট $total টি রেকর্ডে Unique Code দেওয়া হয়েছে!<br>⚠️ এখনই এই ফাইলটি সার্ভার থেকে DELETE করুন।</div>";
echo '</div></body></html>';
?>
