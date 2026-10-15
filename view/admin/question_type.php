<?php
session_start();
require "../../config/config.php";   // your connection file

// make mysqli throw real errors and read text correctly
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset("utf8mb4");

// only admins can open this page
// (CHANGE 'role' and the login path if yours are different)
if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../../authentication/Login/login.php");
    exit;
}

// token used to protect add / edit / delete
if (empty($_SESSION["csrf"])) {
    $_SESSION["csrf"] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION["csrf"];

// ---------- question types (with how many questions use each one) ----------
$types = [];
$result = $conn->query(
    "SELECT t.id, t.type_name AS name, t.status,
            (SELECT COUNT(*) FROM questions q WHERE q.question_type = t.type_name) AS used
     FROM question_types t
     ORDER BY t.id"
);
while ($row = $result->fetch_assoc()) {
    $row["id"] = (int) $row["id"];
    $row["used"] = (int) $row["used"];
    $types[] = $row;
}

// ---------- sidebar numbers ----------
$studentCount = (int) $conn->query("SELECT COUNT(*) AS c FROM users WHERE role = 'student'")->fetch_assoc()["c"];
$pendingReports = (int) $conn->query("SELECT COUNT(*) AS c FROM reports WHERE status = 'Pending'")->fetch_assoc()["c"];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>CHECKMATE - Question Types</title>

    <link rel="stylesheet" href="../../asset/css/admin_layout.css?v=<?= filemtime(__DIR__ . '/../../asset/css/admin_layout.css') ?>">
    <link rel="stylesheet" href="../../asset/css/question_type.css?v=<?= filemtime(__DIR__ . '/../../asset/css/question_type.css') ?>">
    <link rel="stylesheet" href="../../asset/css/cm_confirm.css?v=<?= filemtime(__DIR__ . '/../../asset/css/cm_confirm.css') ?>">
</head>

<body>

<?php
// shared sidebar + top bar (partials/admin_shell.php)
$activePage      = "types";
$pageTitle       = "Question Types";
$pageSub         = "Manage the different types of questions available.";
$sidebarStudents = $studentCount;
$sidebarPending  = $pendingReports;

require __DIR__ . "/partials/admin_shell.php";
?>

        <section class="content">

            <!-- Summary cards (click one to filter the table) -->
            <div class="stat-row">

                <div class="stat-card" data-jump="all">
                    <div class="stat-icon">≡</div>
                    <div class="stat-info">
                        <div class="stat-label">Total Types</div>
                        <div class="stat-number" id="statTotal">0</div>
                    </div>
                    <div class="stat-arrow">→</div>
                </div>

                <div class="stat-card" data-jump="Active">
                    <div class="stat-icon green">✓</div>
                    <div class="stat-info">
                        <div class="stat-label">Active Types</div>
                        <div class="stat-number" id="statActive">0</div>
                    </div>
                    <div class="stat-arrow">→</div>
                </div>

                <div class="stat-card" data-jump="Archived">
                    <div class="stat-icon archived"></div>
                    <div class="stat-info">
                        <div class="stat-label">Archived Types</div>
                        <div class="stat-number" id="statArchived">0</div>
                    </div>
                    <div class="stat-arrow">→</div>
                </div>

            </div>


            <!-- Search, status filter and add button -->
            <div class="toolbar">

                <div class="search-box">
                    <span class="search-icon">⌕</span>
                    <input type="text" id="searchInput" class="search-input"
                           placeholder="Search question types...">
                </div>

                <select id="statusFilter" class="filter-select">
                    <option value="all">All Status</option>
                    <option value="Active">Active</option>
                    <option value="Archived">Archived</option>
                </select>

                <button class="btn-gold" id="addButton">+ Add Type</button>

            </div>


            <!-- Table -->
            <div class="panel">

                <div class="table-wrap">

                    <table class="data-table">

                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Type Name</th>
                                <th>Questions</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <!-- rows are filled in by question_type.js -->
                        <tbody id="tableBody"></tbody>

                    </table>

                </div>

                <div class="panel-footer">
                    <div class="showing-text" id="showingText"></div>
                    <div class="pagination" id="pagination"></div>
                </div>

            </div>

        </section>

    </main>


    <!-- =========================================
         ADD POPUP
         ========================================= -->

    <div class="modal-overlay" id="formModal">

        <div class="modal">

            <div class="modal-label">QUESTION TYPES</div>
            <h2 class="modal-title" id="modalTitle">Add type</h2>

            <div class="form-group">
                <label for="formName">Type name</label>
                <input type="text" id="formName" class="form-input" maxlength="100" placeholder="Assignment Help">
            </div>

            <div class="form-error" id="formError"></div>

            <div class="modal-buttons">
                <button class="btn-outline" id="cancelForm">Cancel</button>
                <button class="btn-gold" id="saveForm">Add Type</button>
            </div>

        </div>

    </div>


    <div class="toast" id="toast"></div>

    <!-- data from the database, used by question_type.js -->
    <script>
        var types = <?= json_encode($types, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
        var csrfToken = <?= json_encode($csrf) ?>;
        var pendingReports = <?= $pendingReports ?>;
    </script>

    <!-- styled confirm dialog (must load BEFORE question_type.js) -->
    <script src="../../asset/js/cm_confirm.js?v=<?= filemtime(__DIR__ . '/../../asset/js/cm_confirm.js') ?>"></script>
    <script src="../../asset/js/question_type.js?v=<?= filemtime(__DIR__ . '/../../asset/js/question_type.js') ?>"></script>

</body>
</html>