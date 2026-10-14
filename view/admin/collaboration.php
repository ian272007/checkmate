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

// ---------- all collaboration requests ----------
// who asked (requester), who was asked (receiver), and the question they are collaborating on
$sql = "SELECT c.collaboration_id AS id,
               c.status,
               c.create_at,
               q.question AS topic,
               q.subject,
               q.question_course AS course,
               TRIM(CONCAT(COALESCE(ur.first_name, ''), ' ', COALESCE(ur.last_name, ''))) AS requester,
               TRIM(CONCAT(COALESCE(ue.first_name, ''), ' ', COALESCE(ue.last_name, ''))) AS receiver
        FROM collaborations c
        LEFT JOIN questions q ON q.question_id = c.question_id
        LEFT JOIN users ur ON ur.id = c.requester_id
        LEFT JOIN users ue ON ue.id = c.receiver_id
        ORDER BY c.create_at DESC, c.collaboration_id DESC";

$requests = [];
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {

    $row["id"] = (int) $row["id"];

    if ($row["requester"] === "") { $row["requester"] = "Unknown"; }
    if ($row["receiver"] === "")  { $row["receiver"]  = "Unknown"; }
    if ($row["topic"] === null)   { $row["topic"] = "(question was deleted)"; }

    // nice date + time for the table
    $time = strtotime($row["create_at"]);
    $row["date"] = date("M j, Y", $time);
    $row["time"] = date("h:i A", $time);
    unset($row["create_at"]);

    $requests[] = $row;
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

    <title>CHECKMATE - Collaborations</title>

    <link rel="stylesheet" href="../../asset/css/admin_layout.css?v=<?= filemtime(__DIR__ . '/../../asset/css/admin_layout.css') ?>">
    <link rel="stylesheet" href="../../asset/css/collaboration.css?v=<?= filemtime(__DIR__ . '/../../asset/css/collaboration.css') ?>">
</head>

<body>

<?php
// shared sidebar + top bar (partials/admin_shell.php)
$activePage      = "collaborations";
$pageTitle       = "Collaborations";
$pageSub         = "Monitor collaboration requests and active student partnerships.";
$sidebarStudents = $studentCount;
$sidebarPending  = $pendingReports;

require __DIR__ . "/partials/admin_shell.php";
?>

        <section class="content">

            <!-- Summary cards (click one to jump to the matching view) -->
            <div class="stat-row">

                <div class="stat-card" data-jump="all">
                    <div class="stat-icon blue">▤</div>
                    <div class="stat-info">
                        <div class="stat-label">Total Requests</div>
                        <div class="stat-number" id="statTotal">0</div>
                    </div>
                    <div class="stat-arrow">→</div>
                </div>

                <div class="stat-card" data-jump="Pending">
                    <div class="stat-icon">◔</div>
                    <div class="stat-info">
                        <div class="stat-label">Pending Requests</div>
                        <div class="stat-number" id="statPending">0</div>
                    </div>
                    <div class="stat-arrow">→</div>
                </div>

                <div class="stat-card" data-jump="active">
                    <div class="stat-icon green">♞</div>
                    <div class="stat-info">
                        <div class="stat-label">Active Collaborations</div>
                        <div class="stat-number" id="statActive">0</div>
                    </div>
                    <div class="stat-arrow">→</div>
                </div>

            </div>


            <!-- Tabs -->
            <div class="tabs">
                <button class="tab active" data-tab="requests">▤ Requests</button>
                <button class="tab" data-tab="active">♞ Active Collaborations</button>
            </div>


            <!-- Collaboration requests table -->
            <div class="panel" id="requestsPanel">

                <div class="panel-head">
                    <div class="panel-title">Collaboration Requests</div>
                    <div class="panel-sub" id="requestsSub">All collaboration requests sent between students.</div>
                </div>

                <div class="table-wrap">

                    <table class="collab-table">

                        <thead>
                            <tr>
                                <th>Request ID</th>
                                <th colspan="2">Requested By</th>
                                <th colspan="2">Requested To</th>
                                <th>Collaboration Topic</th>
                                <th>Requested On</th>
                                <th>Status</th>
                            </tr>
                        </thead>

                        <!-- rows are filled in by collaboration.js -->
                        <tbody id="requestsBody"></tbody>

                    </table>

                </div>

                <div class="panel-footer">
                    <div class="showing-text" id="showingText"></div>
                    <div class="pagination" id="requestsPagination"></div>
                </div>

            </div>


            <!-- Active collaborations -->
            <div class="panel" id="activePanel">

                <div class="panel-head">
                    <div class="panel-title">Active Collaborations</div>
                    <div class="panel-sub">Currently ongoing student partnerships (accepted requests).</div>
                </div>

                <div class="active-grid" id="activeGrid"></div>

                <div class="panel-footer">
                    <div class="showing-text" id="activeShowing"></div>
                    <div class="pagination" id="activePagination"></div>
                </div>

            </div>

        </section>

    </main>


    <div class="toast" id="toast"></div>

    <!-- data from the database, used by collaboration.js -->
    <script>
        var requests = <?= json_encode($requests, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
        var pendingReports = <?= $pendingReports ?>;
    </script>

    <script src="../../asset/js/collaboration.js"></script>

</body>
</html>