<?php
require_once 'db.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

$selected_date = $_GET['date'] ?? date('Y-m-d');
$current_year = date('Y');

// 曜日判定ロジック
$dayOfWeek = date('w', strtotime($selected_date));
$isWeekday = ($dayOfWeek >= 1 && $dayOfWeek <= 5);
$isWeekend = ($dayOfWeek == 0 || $dayOfWeek == 6);

/* ===== Xử lý thống kê (Statistics Logic) ===== */
// 1. Thống kê theo từng tháng trong năm hiện tại
$stats_year_sql = "SELECT MONTH(task_date) as month, COUNT(*) as total FROM tasks 
                   WHERE YEAR(task_date) = :year AND status = 'done' 
                   GROUP BY MONTH(task_date) ORDER BY month";
$stmt_year = $pdo->prepare($stats_year_sql);
$stmt_year->execute([':year' => $current_year]);
$yearly_data = $stmt_year->fetchAll(PDO::FETCH_KEY_PAIR);
// Tạo mảng đủ 12 tháng
$months_display = [];
for ($m = 1; $m <= 12; $m++) {
    $months_display[$m] = $yearly_data[$m] ?? 0;
}
$max_tasks = max($months_display) ?: 1; // Để tính chiều cao cột biểu đồ

/* ===== POST処理 (データ操作) ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add' && !empty($_POST['title'])) {
        $stmt = $pdo->prepare("INSERT INTO tasks (title, time_limit, task_date, recurrence_type) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_POST['title'], $_POST['time'] ?: null, $selected_date, $_POST['recurrence_type'] ?? 'once']);
    }
    if ($action === 'toggle') {
        $stmt = $pdo->prepare("UPDATE tasks SET status = IF(status='done','todo','done') WHERE id = ?");
        $stmt->execute([$_POST['id']]);
    }
    if ($action === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ?");
        $stmt->execute([$_POST['id']]);
    }
    if ($action === 'reschedule') {
        $stmt = $pdo->prepare("UPDATE tasks SET task_date = DATE_ADD(task_date, INTERVAL 1 DAY), reschedule_count = reschedule_count + 1 WHERE id = ?");
        $stmt->execute([$_POST['id']]);
    }
    header("Location: index.php?date=".$selected_date);
    exit;
}

/* ===== タスク取得 ===== */
$sql = "SELECT * FROM tasks WHERE (recurrence_type = 'once' AND task_date = :date) OR (recurrence_type = 'daily') OR (recurrence_type = 'weekdays' AND :isWeekday = 1) OR (recurrence_type = 'weekends' AND :isWeekend = 1) ORDER BY status ASC, id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([':date' => $selected_date, ':isWeekday' => $isWeekday ? 1 : 0, ':isWeekend' => $isWeekend ? 1 : 0]);
$tasks = $stmt->fetchAll();

$total = count($tasks);
$done  = count(array_filter($tasks, fn($t)=>$t['status']==='done'));
$percent = $total ? round($done/$total*100) : 0;

$badges = [
    'once' => ['一度のみ', 'bg-slate-100 text-slate-500'],
    'daily' => ['毎日', 'bg-orange-100 text-orange-600'],
    'weekdays' => ['平日', 'bg-blue-100 text-blue-600'],
    'weekends' => ['週末', 'bg-indigo-100 text-indigo-600'],
];
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>デイリープランナー</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;800&display=swap" rel="stylesheet">
    <style>
        body { background: #FDFDFD; color: #1E293B; font-family: 'Plus Jakarta Sans', sans-serif; }
        .cb-custom { width: 24px; height: 24px; border: 2px solid #E2E8F0; border-radius: 7px; appearance: none; cursor: pointer; transition: all 0.2s; position: relative; }
        .cb-custom:checked { background: #FF7D1F; border-color: #FF7D1F; }
        .cb-custom:checked::after { content: "✓"; color: white; position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); font-weight: bold; font-size: 14px; }
        .task-card { transition: transform 0.2s, box-shadow 0.2s; border: 1px solid #F1F5F9; }
        .task-card:hover { transform: translateY(-2px); box-shadow: 0 10px 20px -5px rgba(0,0,0,0.05); }
        .task-done { opacity: 0.5; filter: grayscale(0.5); text-decoration: line-through; }
        .modal-overlay { transition: all 0.3s ease; }
    </style>
</head>
<body class="min-h-screen flex justify-center p-6 md:p-12">

    <div class="w-full max-w-2xl">
        <header class="flex justify-between items-center mb-12">
            <div class="flex gap-2">
                <button onclick="openFocus()" class="flex items-center gap-2 bg-slate-900 text-white px-4 py-2 rounded-2xl font-bold text-xs hover:bg-orange-600 transition-all shadow-md">
                    <i data-lucide="timer" class="w-4 h-4"></i>
                    <span>集中</span>
                </button>
                <button onclick="openStats()" class="flex items-center gap-2 bg-white border border-slate-200 px-4 py-2 rounded-2xl font-bold text-xs hover:border-orange-500 hover:text-orange-500 transition-all shadow-sm">
                    <i data-lucide="bar-chart-3" class="w-4 h-4"></i>
                    <span>分析モード</span>
                </button>
            </div>
            <div class="text-right flex flex-col items-end">
                <div class="relative w-12 h-12 flex items-center justify-center">
                    <svg class="w-full h-full transform -rotate-90">
                        <circle cx="24" cy="24" r="20" stroke="#F1F5F9" stroke-width="4" fill="transparent" />
                        <circle cx="24" cy="24" r="20" stroke="#FF7D1F" stroke-width="4" fill="transparent" 
                            stroke-dasharray="125.6" stroke-dashoffset="<?= 125.6 * (1 - $percent/100) ?>" stroke-linecap="round" />
                    </svg>
                    <span class="absolute text-[10px] font-black"><?= $percent ?>%</span>
                </div>
            </div>
        </header>

        <div class="mb-10">
            <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight italic">スケジュール</h1>
            <form method="GET" id="dateForm" class="mt-1">
                <input type="date" name="date" value="<?= $selected_date ?>" onchange="this.form.submit()"
                    class="bg-transparent font-semibold text-orange-500 outline-none cursor-pointer text-sm">
            </form>
        </div>

        <form method="POST" class="bg-white p-4 rounded-[28px] shadow-xl shadow-slate-100/50 border border-slate-100 mb-10">
            <input type="hidden" name="action" value="add">
            <input name="title" type="text" placeholder="新しいタスクを追加..." required class="w-full bg-transparent px-4 py-2 outline-none font-medium text-lg placeholder:text-slate-300">
            <div class="flex items-center justify-between border-t border-slate-50 pt-4 px-2 mt-4">
                <div class="flex gap-2">
                    <select name="recurrence_type" class="bg-slate-50 text-slate-600 px-3 py-1.5 rounded-xl text-[10px] font-bold outline-none border-none">
                        <option value="once">一度のみ</option><option value="daily">毎日</option>
                        <option value="weekdays">平日</option><option value="weekends">週末</option>
                    </select>
                    <input type="time" name="time" class="bg-slate-50 text-slate-600 px-3 py-1.5 rounded-xl text-[10px] font-bold outline-none border-none">
                </div>
                <button type="submit" class="bg-orange-500 text-white p-2.5 rounded-xl hover:bg-orange-600 shadow-lg shadow-orange-200">
                    <i data-lucide="plus" class="w-5 h-5"></i>
                </button>
            </div>
        </form>

        <div class="space-y-3 mb-12">
            <?php foreach ($tasks as $t): ?>
            <div class="task-card flex items-center gap-4 bg-white p-5 rounded-[24px] <?= $t['status']==='done' ? 'task-done' : '' ?>">
                <form method="POST"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $t['id'] ?>">
                    <input type="checkbox" class="cb-custom" onchange="this.form.submit()" <?= $t['status']==='done'?'checked':'' ?>>
                </form>
                <div class="flex-1">
                    <h3 class="font-bold text-slate-800 tracking-tight"><?= htmlspecialchars($t['title']) ?></h3>
                    <span class="text-slate-400 text-[11px] font-bold uppercase tracking-wide"><?= $t['time_limit'] ?: '終日' ?></span>
                </div>
                <div class="flex items-center gap-1">
                    <form method="POST"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $t['id'] ?>">
                        <button class="p-2 text-slate-200 hover:text-red-500 transition-colors"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="bg-white p-8 rounded-[40px] shadow-sm border border-slate-50">
            <div class="flex justify-between items-center mb-4">
                <div class="font-bold text-slate-900">今日の達成度</div>
                <div class="font-black text-orange-500 text-2xl"><?= $percent ?>%</div>
            </div>
            <div class="w-full bg-slate-100 h-3 rounded-full overflow-hidden">
                <div class="bg-orange-500 h-full rounded-full transition-all duration-1000" style="width: <?= $percent ?>%"></div>
            </div>
        </div>
    </div>

    <div id="statsOverlay" class="fixed inset-0 bg-slate-900/60 backdrop-blur-md z-[2000] invisible opacity-0 flex items-center justify-center p-4">
        <div class="bg-white w-full max-w-2xl rounded-[40px] p-8 shadow-2xl relative max-h-[90vh] overflow-y-auto">
            <button onclick="closeStats()" class="absolute top-6 right-6 text-slate-300 hover:text-slate-900"><i data-lucide="x-circle" class="w-8 h-8"></i></button>
            
            <h2 class="text-2xl font-black text-slate-900 mb-2">パフォーマンス分析</h2>
            <p class="text-slate-400 text-sm mb-8"><?= $current_year ?>年の活動統計</p>

            <div class="bg-slate-50 rounded-[32px] p-6 mb-8">
                <h3 class="font-bold text-sm mb-6 flex items-center gap-2">
                    <i data-lucide="calendar" class="w-4 h-4 text-orange-500"></i> 月間完了タスク数 (Số task hoàn thành theo tháng)
                </h3>
                <div class="flex items-end justify-between h-48 gap-2 px-2">
                    <?php foreach ($months_display as $m => $val): 
                        $height = ($val / $max_tasks) * 100;
                    ?>
                    <div class="flex-1 flex flex-col items-center gap-2 group relative">
                        <div class="absolute -top-8 bg-slate-800 text-white text-[10px] px-2 py-1 rounded opacity-0 group-hover:opacity-100 transition-opacity">
                            <?= $val ?> tasks
                        </div>
                        <div class="w-full bg-orange-500 rounded-t-lg transition-all duration-500 ease-out" style="height: <?= $height ?>%; min-height: 4px; opacity: <?= 0.3 + ($height/100) ?>;"></div>
                        <span class="text-[9px] font-bold text-slate-400"><?= $m ?>月</span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="bg-orange-50 p-6 rounded-[28px] border border-orange-100">
                    <span class="text-orange-600 text-[10px] font-black uppercase tracking-widest">Most Busy</span>
                    <div class="text-2xl font-black text-slate-900 mt-1">
                        <?= array_search(max($months_display), $months_display) ?>月
                    </div>
                    <p class="text-slate-500 text-[11px] mt-1">最も忙しい月</p>
                </div>
                <div class="bg-blue-50 p-6 rounded-[28px] border border-blue-100">
                    <span class="text-blue-600 text-[10px] font-black uppercase tracking-widest">Yearly Total</span>
                    <div class="text-2xl font-black text-slate-900 mt-1"><?= array_sum($months_display) ?></div>
                    <p class="text-slate-500 text-[11px] mt-1">今年の合計タスク数</p>
                </div>
            </div>
        </div>
    </div>

    <div id="focusOverlay" class="fixed inset-0 bg-slate-900/40 backdrop-blur-md z-[1000] invisible opacity-0 flex items-center justify-center p-6">
        <div class="bg-white w-full max-w-md rounded-[48px] p-10 flex flex-col items-center relative">
            <button onclick="closeFocus()" class="absolute top-8 right-8 text-slate-300 hover:text-slate-900"><i data-lucide="circle-x" class="w-8 h-8"></i></button>
            <div id="timerDisplay" class="text-6xl font-black text-slate-900 my-12">25:00</div>
            <button id="timerBtn" onclick="toggleTimer()" class="w-full bg-orange-500 text-white py-4 rounded-3xl font-black">START</button>
        </div>
    </div>

    <script>
        lucide.createIcons();
        function openFocus() { document.getElementById('focusOverlay').classList.remove('invisible', 'opacity-0'); }
        function closeFocus() { document.getElementById('focusOverlay').classList.add('invisible', 'opacity-0'); }
        function openStats() { document.getElementById('statsOverlay').classList.remove('invisible', 'opacity-0'); }
        function closeStats() { document.getElementById('statsOverlay').classList.add('invisible', 'opacity-0'); }

        // Pomodoro Timer Logic (Đã có từ trước)
        let timeLeft = 25 * 60; let timerId = null;
        function updateTimer() {
            const mins = Math.floor(timeLeft / 60); const secs = timeLeft % 60;
            document.getElementById('timerDisplay').innerText = `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
            if (timeLeft <= 0) { clearInterval(timerId); alert("Time up!"); }
            timeLeft--;
        }
        function toggleTimer() {
            if (timerId) { clearInterval(timerId); timerId = null; }
            else { timerId = setInterval(updateTimer, 1000); }
        }
    </script>
</body>
</html>