<?php
// ============================================================================
// ATOMIX DEV_CONSOLE v2.026 - EMBEDDED BACKEND PHP ENGINE
// ============================================================================
if (isset($_GET['action']) || isset($_POST['action_type'])) {
    error_reporting(0);
    ini_set('display_errors', 0);
    
    header("Content-Type: application/json; charset=UTF-8");

    $host = "localhost";
    $db_name = "atomix_db";
    $username = "root";
    $password = "";

    try {
        $conn = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => "DB Connection Failed: " . $e->getMessage()]);
        exit();
    }

    $action = $_GET['action'] ?? $_POST['action_type'] ?? '';

    // ------------------------------------------------------------------------
    // 1. FETCH BADGES (Returns both badge_ids and criteria_keys)
    // ------------------------------------------------------------------------
    if ($action === 'get_student_badges') {
        $student_id = $_GET['student_id'] ?? null;
        if (!$student_id) {
            echo json_encode(["status" => "success", "keys" => [], "ids" => []]);
            exit();
        }

        try {
            $query = "SELECT bd.badge_id, bd.criteria_key 
                      FROM student_badges sb
                      INNER JOIN badge_definitions bd ON sb.badge_id = bd.badge_id
                      WHERE sb.student_id = :student_id";

            $stmt = $conn->prepare($query);
            $stmt->bindParam(":student_id", $student_id, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll();

            $keys = array_column($rows, 'criteria_key');
            $ids  = array_map('intval', array_column($rows, 'badge_id'));

            // Fallback: Query student_badges directly if INNER JOIN fails
            if (empty($ids)) {
                $rawQuery = "SELECT DISTINCT badge_id FROM student_badges WHERE student_id = :student_id";
                $rawStmt = $conn->prepare($rawQuery);
                $rawStmt->bindParam(":student_id", $student_id, PDO::PARAM_INT);
                $rawStmt->execute();
                $ids = array_map('intval', $rawStmt->fetchAll(PDO::FETCH_COLUMN));
            }

            echo json_encode([
                "status" => "success",
                "keys"   => $keys,
                "ids"    => $ids
            ]);
        } catch (PDOException $e) {
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        }
        exit();
    }

    // ------------------------------------------------------------------------
    // 2. GRANT BADGE
    // ------------------------------------------------------------------------
    if ($action === 'grant_badge') {
        $student_id = $_POST['student_id'] ?? null;
        $criteria_key = $_POST['criteria_key'] ?? null;

        if (!$student_id || !$criteria_key) {
            echo json_encode(["status" => "error", "message" => "Missing student_id or criteria_key"]);
            exit();
        }

        try {
            $findBadge = $conn->prepare("SELECT badge_id FROM badge_definitions WHERE criteria_key = :criteria_key LIMIT 1");
            $findBadge->bindParam(":criteria_key", $criteria_key);
            $findBadge->execute();
            $badge = $findBadge->fetch();

            if (!$badge) {
                echo json_encode(["status" => "error", "message" => "Criteria key not found in badge_definitions"]);
                exit();
            }

            $badge_id = $badge['badge_id'];

            $stmt = $conn->prepare("
                INSERT INTO student_badges (student_id, badge_id, unlocked_at) 
                VALUES (:student_id, :badge_id, NOW())
                ON DUPLICATE KEY UPDATE unlocked_at = NOW()
            ");
            $stmt->bindParam(":student_id", $student_id, PDO::PARAM_INT);
            $stmt->bindParam(":badge_id", $badge_id, PDO::PARAM_INT);
            $stmt->execute();

            echo json_encode(["status" => "success", "message" => "Granted '$criteria_key' to Student $student_id"]);
        } catch (PDOException $e) {
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        }
        exit();
    }

    // ------------------------------------------------------------------------
    // 3. REVOKE SINGLE BADGE
    // ------------------------------------------------------------------------
    if ($action === 'revoke_badge') {
        $student_id = $_POST['student_id'] ?? null;
        $criteria_key = $_POST['criteria_key'] ?? null;

        if (!$student_id || !$criteria_key) {
            echo json_encode(["status" => "error", "message" => "Missing parameters for revocation"]);
            exit();
        }

        try {
            $stmt = $conn->prepare("
                DELETE sb FROM student_badges sb
                INNER JOIN badge_definitions bd ON sb.badge_id = bd.badge_id
                WHERE sb.student_id = :student_id 
                AND bd.criteria_key = :criteria_key
            ");
            $stmt->bindParam(":student_id", $student_id, PDO::PARAM_INT);
            $stmt->bindParam(":criteria_key", $criteria_key);
            $stmt->execute();

            echo json_encode(["status" => "success", "message" => "Revoked '$criteria_key' from Student $student_id"]);
        } catch (PDOException $e) {
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        }
        exit();
    }

    // ------------------------------------------------------------------------
    // 4. RESET ALL BADGES FOR A STUDENT
    // ------------------------------------------------------------------------
    if ($action === 'reset_all_badges') {
        $student_id = $_POST['student_id'] ?? null;

        if (!$student_id) {
            echo json_encode(["status" => "error", "message" => "Missing student_id parameter"]);
            exit();
        }

        try {
            $stmt = $conn->prepare("DELETE FROM student_badges WHERE student_id = :student_id");
            $stmt->bindParam(":student_id", $student_id, PDO::PARAM_INT);
            $stmt->execute();

            echo json_encode(["status" => "success", "message" => "All badges reset for Student $student_id"]);
        } catch (PDOException $e) {
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        }
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ATOMIX // DEV_CORE v2.026</title>
  
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <style>
    :root {
      --bg: #030712;
      --card-bg: #0b1329;
      --accent-cyan: #00f3ff;
      --accent-green: #00ff66;
      --accent-magenta: #ff0055;
      --accent-amber: #ffaa00;
      --text: #e2e8f0;
      --text-dim: #64748b;
      --border: #1e293b;
      --font-mono: 'Courier New', Courier, monospace;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }
    
    body {
      background-color: var(--bg);
      color: var(--text);
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      padding: 20px;
      background-image: 
        radial-gradient(circle at 50% 0%, rgba(0, 243, 255, 0.05), transparent 70%),
        linear-gradient(rgba(255, 255, 255, 0.02) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255, 255, 255, 0.02) 1px, transparent 1px);
      background-size: 100% 100%, 20px 20px, 20px 20px;
    }

    header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding-bottom: 20px;
      border-bottom: 1px solid var(--accent-cyan);
      margin-bottom: 25px;
      box-shadow: 0 1px 15px rgba(0, 243, 255, 0.2);
    }

    h1 {
      font-family: var(--font-mono);
      font-size: 1.5rem;
      letter-spacing: 2px;
      color: var(--accent-cyan);
      text-transform: uppercase;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .pulse-dot {
      width: 10px;
      height: 10px;
      background: var(--accent-green);
      border-radius: 50%;
      box-shadow: 0 0 10px var(--accent-green);
      animation: blink 1.5s infinite;
    }

    @keyframes blink { 50% { opacity: 0.3; } }

    .grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(360px, 1fr));
      gap: 20px;
    }

    .card {
      background: var(--card-bg);
      border: 1px solid var(--border);
      border-radius: 6px;
      padding: 20px;
      position: relative;
      overflow: hidden;
      display: flex;
      flex-direction: column;
    }

    .card.badge-card::before { 
      content: '';
      position: absolute;
      top: 0; left: 0; width: 4px; height: 100%;
      background: var(--accent-amber); 
    }

    h2 {
      font-family: var(--font-mono);
      font-size: 0.95rem;
      margin-bottom: 15px;
      text-transform: uppercase;
      color: #94a3b8;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .table-container {
      max-height: 480px;
      overflow-y: auto;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.85rem;
      text-align: left;
    }

    th, td {
      padding: 10px;
      border-bottom: 1px solid var(--border);
    }

    th { 
      color: var(--text-dim); 
      font-family: var(--font-mono); 
      position: sticky;
      top: 0;
      background: var(--card-bg);
      z-index: 10;
    }

    .btn {
      background: transparent;
      border: 1px solid var(--accent-cyan);
      color: var(--accent-cyan);
      padding: 8px 14px;
      border-radius: 4px;
      cursor: pointer;
      font-family: var(--font-mono);
      font-size: 0.8rem;
      transition: all 0.2s;
    }

    .btn:hover {
      background: var(--accent-cyan);
      color: #000;
      box-shadow: 0 0 10px var(--accent-cyan);
    }

    .btn-danger {
      border-color: var(--accent-magenta);
      color: var(--accent-magenta);
    }

    .btn-danger:hover {
      background: var(--accent-magenta);
      color: #fff;
      box-shadow: 0 0 10px var(--accent-magenta);
    }

    .btn-warning {
      border-color: var(--accent-amber);
      color: var(--accent-amber);
    }

    .btn-warning:hover {
      background: var(--accent-amber);
      color: #000;
      box-shadow: 0 0 10px var(--accent-amber);
    }

    .btn-group {
      display: flex;
      gap: 8px;
      margin-top: 10px;
    }

    select {
      width: 100%;
      padding: 10px;
      background: #020617;
      border: 1px solid var(--border);
      color: var(--text);
      border-radius: 4px;
      margin-bottom: 12px;
      font-family: var(--font-mono);
    }

    .section-title {
      font-family: var(--font-mono);
      font-size: 0.75rem;
      color: var(--accent-cyan);
      margin: 12px 0 6px 0;
      text-transform: uppercase;
      letter-spacing: 1px;
    }

    .checkbox-group {
      background: #020617;
      border: 1px solid var(--border);
      padding: 10px;
      border-radius: 4px;
      max-height: 180px;
      overflow-y: auto;
      margin-bottom: 10px;
    }

    .checkbox-item {
      display: flex;
      align-items: center;
      gap: 10px;
      font-size: 0.8rem;
      margin-bottom: 6px;
      color: var(--text);
      cursor: pointer;
    }

    .checkbox-item input[type="checkbox"] {
      accent-color: var(--accent-magenta);
      cursor: pointer;
    }

    .badge-status {
      font-family: var(--font-mono);
      font-size: 0.7rem;
      padding: 2px 6px;
      border-radius: 3px;
      margin-left: auto;
    }

    .status-unlocked { background: rgba(0, 255, 102, 0.2); color: var(--accent-green); border: 1px solid var(--accent-green); }
    .status-locked { background: rgba(100, 116, 139, 0.2); color: var(--text-dim); border: 1px solid var(--text-dim); }

    .terminal-log {
      background: #000;
      border: 1px solid var(--border);
      padding: 10px;
      font-family: var(--font-mono);
      font-size: 0.75rem;
      color: var(--accent-green);
      height: 130px;
      overflow-y: auto;
      margin-top: 15px;
    }

    .swal2-popup {
      background: #0b1329 !important;
      border: 1px solid var(--accent-cyan) !important;
      color: var(--text) !important;
      font-family: var(--font-mono) !important;
    }
    .swal2-title { color: var(--accent-cyan) !important; }
    .swal2-html-container { color: var(--text) !important; }
  </style>
</head>
<body>

  <header>
    <h1><span class="pulse-dot"></span> ATOMIX // GODMODE_CONSOLE</h1>
    <div style="font-family: var(--font-mono); font-size: 0.8rem; color: var(--text-dim);">
      DB: atomix_db | SERVER: EMBEDDED_ACTIVE
    </div>
  </header>

  <div class="grid">
    
    <!-- Student Directory Card -->
    <div class="card">
      <h2>Student Directory <span>[NEWEST → OLDEST]</span></h2>
      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th>Student ID</th>
              <th>User ID</th>
              <th>Name</th>
              <th>Gender</th>
            </tr>
          </thead>
          <tbody>
            <tr><td>101</td><td>160</td><td>Tristan Salvador</td><td>male</td></tr>
            <tr><td>100</td><td>159</td><td>Maria Leonard</td><td>female</td></tr>
            <tr><td>99</td><td>158</td><td>Jazel Mae Apdua</td><td>female</td></tr>
            <tr><td>98</td><td>148</td><td>Anna Leah aasad</td><td>female</td></tr>
            <tr><td>97</td><td>147</td><td>Anna Zayas</td><td>female</td></tr>
            <tr><td>96</td><td>142</td><td>karl dave</td><td>male</td></tr>
            <tr><td>95</td><td>140</td><td>John Alvarez</td><td>male</td></tr>
            <tr><td>94</td><td>139</td><td>Shem Smith</td><td>female</td></tr>
            <tr><td>93</td><td>138</td><td>Nino Doe</td><td>male</td></tr>
            <tr><td>92</td><td>136</td><td>Jane2 Smith</td><td>female</td></tr>
            <tr><td>91</td><td>135</td><td>John1 Doe</td><td>male</td></tr>
            <tr><td>88</td><td>130</td><td>Nino Olarita</td><td>male</td></tr>
            <tr><td>62</td><td>77</td><td>Anna Leah bugo</td><td>female</td></tr>
            <tr><td>58</td><td>73</td><td>Anna Zayas</td><td>male</td></tr>
            <tr><td>43</td><td>55</td><td>Shiela Mae</td><td>female</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Badge Control & Reset Console -->
    <div class="card badge-card">
      <h2>Badge Management & Reset <span>[RESET_CORE]</span></h2>

      <label style="font-size: 0.75rem; color: var(--text-dim);">ACTIVE STUDENT FOCUS:</label>
      <select id="badge-student-select" onchange="syncSelectedStudent(this.value)">
        <option value="101">Tristan Salvador (ID: 101)</option>
        <option value="100">Maria Leonard (ID: 100)</option>
        <option value="99">Jazel Mae Apdua (ID: 99)</option>
        <option value="98">Anna Leah aasad (ID: 98)</option>
        <option value="97">Anna Zayas (ID: 97)</option>
        <option value="96">karl dave (ID: 96)</option>
        <option value="95">John Alvarez (ID: 95)</option>
        <option value="94">Shem Smith (ID: 94)</option>
        <option value="93">Nino Doe (ID: 93)</option>
        <option value="92">Jane2 Smith (ID: 92)</option>
        <option value="91">John1 Doe (ID: 91)</option>
        <option value="88">Nino Olarita (ID: 88)</option>
        <option value="62">Anna Leah bugo (ID: 62)</option>
        <option value="58">Anna Zayas (ID: 58)</option>
        <option value="43">Shiela Mae (ID: 43)</option>
      </select>

      <div class="section-title">Master Catalog Badges</div>
      <div class="checkbox-group" id="badge-list-container">
        <label class="checkbox-item">
          <input type="checkbox" class="badge-reset-check" value="GREENHOUSE_CLEARED"> 
          Greenhouse Hero (ID: 1)
          <span class="badge-status status-locked" id="status-GREENHOUSE_CLEARED">LOCKED</span>
        </label>
        <label class="checkbox-item">
          <input type="checkbox" class="badge-reset-check" value="CH1_SCIENCE_COMPLETE"> 
          Scientific Pioneer (ID: 2)
          <span class="badge-status status-locked" id="status-CH1_SCIENCE_COMPLETE">LOCKED</span>
        </label>
        <label class="checkbox-item">
          <input type="checkbox" class="badge-reset-check" value="CH2_MIXTURES_COMPLETE"> 
          Mixture Master (ID: 3)
          <span class="badge-status status-locked" id="status-CH2_MIXTURES_COMPLETE">LOCKED</span>
        </label>
        <label class="checkbox-item">
          <input type="checkbox" class="badge-reset-check" value="CH3_SEPARATE_COMPLETE"> 
          Separation Expert (ID: 4)
          <span class="badge-status status-locked" id="status-CH3_SEPARATE_COMPLETE">LOCKED</span>
        </label>
        <label class="checkbox-item">
          <input type="checkbox" class="badge-reset-check" value="CH4_BODY_COMPLETE"> 
          Systems Analyst (ID: 5)
          <span class="badge-status status-locked" id="status-CH4_BODY_COMPLETE">LOCKED</span>
        </label>
      </div>

      <div class="btn-group">
        <button class="btn" style="flex: 1;" onclick="grantSelectedBadges()">Grant Selected</button>
        <button class="btn btn-warning" style="flex: 1;" onclick="revokeSelectedBadges()">Revoke Selected</button>
      </div>

      <button class="btn btn-danger" style="margin-top: 10px;" onclick="resetAllBadges()">RESET ALL BADGES (TARGET)</button>
    </div>

  </div>

  <div class="terminal-log" id="console-output">
    > ATOMIX DEV_CORE INITIALIZED...<br>
    > DB SYNC: EMBEDDED (atomix_db)<br>
    > READY FOR COMMANDS.
  </div>

  <script>
    const currentFile = window.location.pathname;

    function log(message) {
      const consoleBox = document.getElementById('console-output');
      const time = new Date().toLocaleTimeString();
      consoleBox.innerHTML += `<br>> [${time}] ${message}`;
      consoleBox.scrollTop = consoleBox.scrollHeight;
    }

    function syncSelectedStudent(studentId) {
      log(`TARGET CHANGED: Active Student ID set to ${studentId}`);
      fetchStudentBadgeStatus(studentId);
    }

    function updateBadgeUI(key, isUnlocked) {
      const statusSpan = document.getElementById(`status-${key}`);
      if (statusSpan) {
        if (isUnlocked) {
          statusSpan.textContent = 'UNLOCKED';
          statusSpan.className = 'badge-status status-unlocked';
        } else {
          statusSpan.textContent = 'LOCKED';
          statusSpan.className = 'badge-status status-locked';
        }
      }
    }

    function fetchStudentBadgeStatus(studentId) {
      log(`API FETCH: Synchronizing badges for Student ID ${studentId}...`);
      
      document.querySelectorAll('.badge-status').forEach(span => {
        span.textContent = 'LOCKED';
        span.className = 'badge-status status-locked';
      });

      fetch(`${currentFile}?action=get_student_badges&student_id=${studentId}`)
        .then(async res => {
          const rawText = await res.text();
          try {
            return JSON.parse(rawText);
          } catch (e) {
            console.error("RAW PHP ERROR:", rawText);
            throw new Error("Invalid JSON returned from server.");
          }
        })
        .then(data => {
          if (data.status === 'error') {
            throw new Error(data.message);
          }

          const unlockedKeys = data.keys || [];
          const unlockedIds = data.ids || [];

          const idToKeyMap = {
            1: "GREENHOUSE_CLEARED",
            2: "CH1_SCIENCE_COMPLETE",
            3: "CH2_MIXTURES_COMPLETE",
            4: "CH3_SEPARATE_COMPLETE",
            5: "CH4_BODY_COMPLETE"
          };

          document.querySelectorAll('.badge-reset-check').forEach(cb => {
            const key = cb.value;
            let isUnlocked = unlockedKeys.includes(key);

            if (!isUnlocked) {
              for (const [badgeId, mappedKey] of Object.entries(idToKeyMap)) {
                if (mappedKey === key && unlockedIds.includes(parseInt(badgeId))) {
                  isUnlocked = true;
                  break;
                }
              }
            }

            updateBadgeUI(key, isUnlocked);
          });

          log(`SYNC COMPLETE: Student ID ${studentId} loaded successfully.`);
        })
        .catch(err => {
          log(`WARN: Backend error - ${err.message}`);
        });
    }

    function grantSelectedBadges() {
      const studentId = document.getElementById('badge-student-select').value;
      const selectedBoxes = Array.from(document.querySelectorAll('.badge-reset-check:checked'));

      if (selectedBoxes.length === 0) {
        Swal.fire({
          icon: 'warning',
          title: 'No Selection',
          text: 'Please select at least one badge to grant.',
          background: '#0b1329',
          confirmButtonColor: '#ffaa00'
        });
        return;
      }

      Swal.fire({
        title: 'Grant Badges?',
        text: `Are you sure you want to grant ${selectedBoxes.length} badge(s) to Student ID ${studentId}?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#00ff66',
        cancelButtonColor: '#ff0055',
        confirmButtonText: 'Yes, Grant Them',
        background: '#0b1329'
      }).then((result) => {
        if (result.isConfirmed) {
          let promises = selectedBoxes.map(cb => {
            const key = cb.value;

            const formData = new FormData();
            formData.append('action_type', 'grant_badge');
            formData.append('student_id', studentId);
            formData.append('criteria_key', key);

            return fetch(currentFile, { method: 'POST', body: formData })
              .then(res => res.json())
              .then(data => {
                if (data.status === 'success') {
                  log(`+ DB CONFIRMED: Granted '${key}' to Student ${studentId}.`);
                  updateBadgeUI(key, true);
                } else {
                  log(`- ERROR: ${data.message}`);
                }
                cb.checked = false;
              });
          });

          Promise.all(promises).then(() => {
            Swal.fire({
              icon: 'success',
              title: 'Badges Granted!',
              text: `Selected badges have been saved for Student ID ${studentId}.`,
              background: '#0b1329',
              confirmButtonColor: '#00f3ff'
            });
            fetchStudentBadgeStatus(studentId);
          });
        }
      });
    }

    function revokeSelectedBadges() {
      const studentId = document.getElementById('badge-student-select').value;
      const selectedBoxes = Array.from(document.querySelectorAll('.badge-reset-check:checked'));

      if (selectedBoxes.length === 0) {
        Swal.fire({
          icon: 'warning',
          title: 'No Selection',
          text: 'Please select at least one badge to revoke.',
          background: '#0b1329',
          confirmButtonColor: '#ffaa00'
        });
        return;
      }

      Swal.fire({
        title: 'Revoke Badges?',
        text: `Revoke ${selectedBoxes.length} badge(s) from Student ID ${studentId}?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ffaa00',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, Revoke',
        background: '#0b1329'
      }).then((result) => {
        if (result.isConfirmed) {
          let promises = selectedBoxes.map(cb => {
            const key = cb.value;

            const formData = new FormData();
            formData.append('action_type', 'revoke_badge');
            formData.append('student_id', studentId);
            formData.append('criteria_key', key);

            return fetch(currentFile, { method: 'POST', body: formData })
              .then(res => res.json())
              .then(data => {
                if (data.status === 'success') {
                  log(`- DB CONFIRMED: Revoked '${key}' from Student ${studentId}.`);
                  updateBadgeUI(key, false);
                }
                cb.checked = false;
              });
          });

          Promise.all(promises).then(() => {
            Swal.fire({
              icon: 'success',
              title: 'Badges Revoked',
              text: `Selected badges were deleted for Student ID ${studentId}.`,
              background: '#0b1329',
              confirmButtonColor: '#00f3ff'
            });
            fetchStudentBadgeStatus(studentId);
          });
        }
      });
    }

    function resetAllBadges() {
      const studentId = document.getElementById('badge-student-select').value;

      Swal.fire({
        title: 'DANGER: Reset All Badges?',
        text: `This will permanently clear ALL badges for Student ID ${studentId} from the database!`,
        icon: 'error',
        showCancelButton: true,
        confirmButtonColor: '#ff0055',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, DELETE ALL',
        background: '#0b1329'
      }).then((result) => {
        if (result.isConfirmed) {
          const formData = new FormData();
          formData.append('action_type', 'reset_all_badges');
          formData.append('student_id', studentId);

          fetch(currentFile, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
              if (data.status === 'success') {
                log(`RESET COMPLETE: All badges cleared for Student ${studentId}.`);
                document.querySelectorAll('.badge-reset-check').forEach(cb => {
                  updateBadgeUI(cb.value, false);
                  cb.checked = false;
                });
                Swal.fire({
                  icon: 'success',
                  title: 'Reset Complete',
                  text: `All badges have been cleared for Student ID ${studentId}.`,
                  background: '#0b1329',
                  confirmButtonColor: '#00f3ff'
                });
              } else {
                Swal.fire({
                  icon: 'error',
                  title: 'Reset Failed',
                  text: data.message,
                  background: '#0b1329'
                });
              }
            });
        }
      });
    }

    window.onload = () => {
      fetchStudentBadgeStatus(101);
    };
  </script>
</body>
</html>