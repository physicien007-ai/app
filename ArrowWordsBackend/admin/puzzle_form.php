<?php
declare(strict_types=1);
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/../db.php';

$packId = $_GET['pack_id'] ?? '';
$id = $_GET['id'] ?? '';
if ($packId === '') {
    die('pack_id مطلوب');
}

$packStmt = $pdo->prepare('SELECT * FROM packs WHERE id = :id');
$packStmt->execute([':id' => $packId]);
$pack = $packStmt->fetch();
if (!$pack) {
    http_response_code(404);
    die('Pack not found');
}

$puzzle = ['id' => '', 'title' => '', 'rows' => 15, 'cols' => 13, 'words' => [], 'refImage' => ''];
if ($id !== '') {
    $stmt = $pdo->prepare('SELECT * FROM puzzles WHERE id = :id AND pack_id = :pack_id');
    $stmt->execute([':id' => $id, ':pack_id' => $packId]);
    $row = $stmt->fetch();
    if (!$row) {
        http_response_code(404);
        die('Puzzle not found');
    }
    $decoded = json_decode($row['words_json'], true) ?? [];
    $puzzle = ['id' => $row['id'], 'title' => $row['title'], 'rows' => (int)$row['rows'], 'cols' => (int)$row['cols'], 'words' => $decoded, 'refImage' => ''];
}

$serverError = $_GET['error'] ?? null;
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>محرر اللغز — كلمات مسهمة</title>
<link rel="stylesheet" href="assets/admin.css">
<style>
  .editor-grid{ display:flex; gap:16px; flex-wrap:wrap; align-items:flex-start; }
  .col{ flex:1; min-width:320px; }
  .word-table th, .word-table td{ padding:4px; font-size:0.78rem; }
  .word-table input, .word-table select{ padding:4px 6px; font-size:0.78rem; }
  .word-table td.answer input{ width:90px; }
  .word-table td.rc input{ width:52px; }
  .remove-btn{ background:#a5453a; color:#fff; border:none; padding:4px 8px; cursor:pointer; font-size:0.75rem; }
  #refImageBox img{ max-width:100%; border:1px solid #ccc; margin-top:8px; }
  #preview{ display:grid; gap:1px; background:#111; border:2px solid #111; margin-top:10px; width:100%; max-width:480px; }
  .pcell{ aspect-ratio:1; display:flex; align-items:center; justify-content:center; background:#fdfcf8; font-size:11px; font-weight:700; position:relative; }
  .pcell.clue{ background:#111; color:#fdfcf8; font-size:8px; }
  #previewError{ color:#a5453a; font-size:0.8rem; margin-top:8px; white-space:pre-wrap; }
  #previewOk{ color:#4c7a5d; font-size:0.8rem; margin-top:8px; }
</style>
</head>
<body>
<div class="topbar">
  <div class="brand">كلمات مسهمة — لوحة التحكم</div>
  <nav><a href="pack_form.php?id=<?= urlencode($packId) ?>">« <?= htmlspecialchars($pack['title']) ?></a><a href="logout.php">خروج</a></nav>
</div>
<div class="wrap">
  <div class="card">
    <h1><?= $id !== '' ? 'تعديل اللغز' : 'لغز جديد' ?></h1>
    <?php if ($serverError): ?><div class="error"><?= htmlspecialchars($serverError) ?></div><?php endif; ?>

    <form method="post" action="puzzle_save.php" id="puzzleForm">
      <input type="hidden" name="pack_id" value="<?= htmlspecialchars($packId) ?>">
      <input type="hidden" name="original_id" value="<?= htmlspecialchars($id) ?>">
      <input type="hidden" name="words_json" id="wordsJsonField">

      <div class="row">
        <div>
          <label>معرف اللغز (id)</label>
          <input name="id" id="puzzleId" value="<?= htmlspecialchars($puzzle['id']) ?>" required pattern="[a-zA-Z0-9\-_]+">
        </div>
        <div>
          <label>العنوان</label>
          <input name="title" id="puzzleTitle" value="<?= htmlspecialchars($puzzle['title']) ?>" required>
        </div>
      </div>
      <div class="row">
        <div>
          <label>عدد الأعمدة (cols)</label>
          <input type="number" name="cols" id="cols" value="<?= (int)$puzzle['cols'] ?>" min="1" required>
        </div>
        <div>
          <label>عدد الصفوف (rows)</label>
          <input type="number" name="rows" id="rows" value="<?= (int)$puzzle['rows'] ?>" min="1" required>
        </div>
      </div>

      <div class="editor-grid" style="margin-top:16px;">
        <div class="col">
          <h2>الكلمات</h2>
          <table class="word-table">
            <thead><tr><th>الاتجاه</th><th>صف</th><th>عمود</th><th>الإجابة</th><th>نص الدليل</th><th></th></tr></thead>
            <tbody id="wordRows"></tbody>
          </table>
          <button type="button" class="btn small secondary" id="addWordBtn" style="margin-top:8px;">+ إضافة كلمة</button>

          <h2 style="margin-top:20px;">صورة مرجعية (اختياري)</h2>
          <p class="muted">ارفع صورة القصاصة من الجريدة لتستخدمها كمرجع أثناء تفريغ الكلمات — لا تُحلَّل تلقائياً، فقط تُعرض بجانبك.</p>
          <input type="file" id="refImageInput" accept="image/*">
          <div id="refImageBox"></div>
        </div>

        <div class="col">
          <h2>المعاينة الحية</h2>
          <p class="muted">تُبنى من نفس منطق التحقق المستخدم عند الحفظ — إذا ظهرت هنا بدون أخطاء فستُحفظ بنجاح.</p>
          <div id="preview"></div>
          <div id="previewError"></div>
          <div id="previewOk"></div>
        </div>
      </div>

      <button class="btn" type="submit" style="margin-top:20px;" id="saveBtn" disabled>حفظ اللغز</button>
      <p class="muted">زر الحفظ يُفعَّل فقط عندما تكون المعاينة صحيحة بالكامل (بدون أخطاء وبدون خانات فارغة).</p>
    </form>
  </div>
</div>

<script>
const DIRECTIONS = ['RTL','LTR','DOWN','UP'];
const ARROW = {RTL:'←', LTR:'→', DOWN:'↓', UP:'↑'};

let words = <?= json_encode($puzzle['words'], JSON_UNESCAPED_UNICODE) ?>;
if (!Array.isArray(words)) words = [];

const wordRowsEl = document.getElementById('wordRows');
const colsInput = document.getElementById('cols');
const rowsInput = document.getElementById('rows');
const saveBtn = document.getElementById('saveBtn');
const wordsJsonField = document.getElementById('wordsJsonField');

function uid() { return 'w' + Math.random().toString(36).slice(2, 9); }

function renderWordRows() {
  wordRowsEl.innerHTML = '';
  words.forEach((w, i) => {
    const tr = document.createElement('tr');

    const dirTd = document.createElement('td');
    const sel = document.createElement('select');
    DIRECTIONS.forEach(d => {
      const opt = document.createElement('option');
      opt.value = d; opt.textContent = `${ARROW[d]} ${d}`;
      if (w.direction === d) opt.selected = true;
      sel.appendChild(opt);
    });
    sel.onchange = () => { w.direction = sel.value; schedulePreview(); };
    dirTd.appendChild(sel);

    const rowTd = document.createElement('td'); rowTd.className = 'rc';
    const rowInput = document.createElement('input'); rowInput.type = 'number'; rowInput.value = w.startRow ?? 0;
    rowInput.oninput = () => { w.startRow = parseInt(rowInput.value || '0', 10); schedulePreview(); };
    rowTd.appendChild(rowInput);

    const colTd = document.createElement('td'); colTd.className = 'rc';
    const colInput = document.createElement('input'); colInput.type = 'number'; colInput.value = w.startCol ?? 0;
    colInput.oninput = () => { w.startCol = parseInt(colInput.value || '0', 10); schedulePreview(); };
    colTd.appendChild(colInput);

    const ansTd = document.createElement('td'); ansTd.className = 'answer';
    const ansInput = document.createElement('input'); ansInput.value = w.answer ?? ''; ansInput.dir = 'rtl';
    ansInput.oninput = () => { w.answer = ansInput.value; schedulePreview(); };
    ansTd.appendChild(ansInput);

    const clueTd = document.createElement('td');
    const clueInput = document.createElement('input'); clueInput.value = w.clueText ?? ''; clueInput.dir = 'rtl'; clueInput.style.width = '100%';
    clueInput.oninput = () => { w.clueText = clueInput.value; schedulePreview(); };
    clueTd.appendChild(clueInput);

    const rmTd = document.createElement('td');
    const rmBtn = document.createElement('button'); rmBtn.type = 'button'; rmBtn.className = 'remove-btn'; rmBtn.textContent = 'حذف';
    rmBtn.onclick = () => { words.splice(i, 1); renderWordRows(); schedulePreview(); };
    rmTd.appendChild(rmBtn);

    tr.append(dirTd, rowTd, colTd, ansTd, clueTd, rmTd);
    wordRowsEl.appendChild(tr);
  });
}

document.getElementById('addWordBtn').onclick = () => {
  words.push({ id: uid(), direction: 'RTL', startRow: 0, startCol: 0, answer: '', clueText: '' });
  renderWordRows();
  schedulePreview();
};

colsInput.oninput = schedulePreview;
rowsInput.oninput = schedulePreview;

let previewTimer = null;
function schedulePreview() {
  clearTimeout(previewTimer);
  previewTimer = setTimeout(runPreview, 350);
}

async function runPreview() {
  // assign missing ids so new rows don't collide
  words.forEach(w => { if (!w.id) w.id = uid(); });

  const payload = {
    id: document.getElementById('puzzleId').value || 'preview',
    rows: parseInt(rowsInput.value || '0', 10),
    cols: parseInt(colsInput.value || '0', 10),
    words: words,
  };

  const previewEl = document.getElementById('preview');
  const errEl = document.getElementById('previewError');
  const okEl = document.getElementById('previewOk');

  try {
    const res = await fetch('ajax_preview.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
    const data = await res.json();

    if (!data.ok) {
      errEl.textContent = data.error;
      okEl.textContent = '';
      previewEl.style.gridTemplateColumns = '';
      previewEl.innerHTML = '';
      saveBtn.disabled = true;
      return;
    }

    errEl.textContent = '';
    okEl.textContent = 'صحيح — كل خانة معرّفة، بدون تعارضات.';
    saveBtn.disabled = false;

    previewEl.style.gridTemplateColumns = `repeat(${payload.cols}, 1fr)`;
    previewEl.innerHTML = '';
    data.grid.forEach(row => {
      row.forEach(cell => {
        const div = document.createElement('div');
        if (cell.type === 'clue') {
          div.className = 'pcell clue';
          div.textContent = cell.directions.map(d => ARROW[d]).join(' ');
        } else {
          div.className = 'pcell';
          div.textContent = cell.letter;
        }
        previewEl.appendChild(div);
      });
    });
  } catch (e) {
    errEl.textContent = 'تعذّر الاتصال بخادم المعاينة';
    saveBtn.disabled = true;
  }
}

document.getElementById('puzzleForm').addEventListener('submit', () => {
  wordsJsonField.value = JSON.stringify(words);
});

// --- reference image upload (display-only, no auto-recognition) ---
document.getElementById('refImageInput').addEventListener('change', async (e) => {
  const file = e.target.files[0];
  if (!file) return;
  const fd = new FormData();
  fd.append('image', file);
  const box = document.getElementById('refImageBox');
  box.innerHTML = '<p class="muted">جارٍ الرفع...</p>';
  try {
    const res = await fetch('upload_image.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.ok) {
      box.innerHTML = '';
      const img = document.createElement('img');
      img.src = data.url;
      box.appendChild(img);
    } else {
      box.innerHTML = '<p class="error">' + data.error + '</p>';
    }
  } catch (err) {
    box.innerHTML = '<p class="error">فشل الرفع</p>';
  }
});

renderWordRows();
runPreview();
</script>
</body>
</html>
