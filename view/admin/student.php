<?php
session_start();

// if not logged in as admin, go to the login page
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] != "admin") {
    header("Location: /checkmate/authentication/Login/login.php");
    exit();
}

// connect to the database (gives us the $conn variable)
require_once "../../config/config.php";


/* =========================================
   SMALL HELPER FUNCTIONS
   ========================================= */

// makes text safe to print inside HTML
function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

// runs a "SELECT COUNT(*)" query and returns the number
function getCount($conn, $sql)
{
    $result = $conn->query($sql);

    // if the query failed, just show 0 instead of crashing the page
    if (!$result) {
        return 0;
    }

    $row = $result->fetch_row();
    return (int)$row[0];
}


/* =========================================
   GET THE STUDENTS FROM THE DATABASE
   ========================================= */

// role = 'student' means admins are never listed on this page
$sql = "SELECT id, first_name, last_name, student_id, email, course, year_level
        FROM users
        WHERE role = 'student'
        ORDER BY id ASC";

$result = $conn->query($sql);

$students = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
}

$total_students = count($students);

// make the Course dropdown from the courses the students really have
$courses = [];

foreach ($students as $s) {
    if (!in_array($s["course"], $courses)) {
        $courses[] = $s["course"];
    }
}

sort($courses);

// pending reports number for the bell and the Reports dot
$pending_reports = 0;
$check = $conn->query("SHOW TABLES LIKE 'reports'");

if ($check && $check->num_rows > 0) {
    $pending_reports = getCount($conn, "SELECT COUNT(*) FROM reports WHERE status = 'Pending'");
}

// we have everything we need, so close the connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>CHECKMATE - Students</title>

    <!-- filemtime() is the time the file was last saved, so the link changes whenever you edit the file
         (this stops the browser from showing an old cached copy) -->
    <link rel="stylesheet" href="../../asset/css/admin_layout.css?v=<?= filemtime(__DIR__ . '/../../asset/css/admin_layout.css') ?>">
    <link rel="stylesheet" href="../../asset/css/student.css?v=<?= filemtime(__DIR__ . '/../../asset/css/student.css') ?>">
</head>

<body>

<?php
// shared sidebar + top bar (partials/admin_shell.php)
$activePage      = "students";
$pageTitle       = "Students";
$pageSub         = "";
$sidebarStudents = $total_students;
$sidebarPending  = $pending_reports;

require __DIR__ . "/partials/admin_shell.php";
?>

        <!-- PAGE CONTENT -->
        <section class="content">

            <!-- Search and filters -->
            <div class="toolbar">

                <div class="search-box">
                    <span class="search-icon">⌕</span>
                    <input type="text" id="searchInput" class="search-input"
                           placeholder="Search by name, student ID, or email...">
                </div>

                <select id="courseFilter" class="filter-select">
                    <option value="all">All Courses</option>

                    <?php foreach ($courses as $course): ?>
                        <option value="<?= e($course) ?>"><?= e($course) ?></option>
                    <?php endforeach; ?>
                </select>

                <select id="yearFilter" class="filter-select">
                    <option value="all">All Year Levels</option>
                    <option value="1st Year">1st Year</option>
                    <option value="2nd Year">2nd Year</option>
                    <option value="3rd Year">3rd Year</option>
                    <option value="4th Year">4th Year</option>
                </select>

            </div>


            <!-- Students table -->
            <div class="table-wrap">

                <table class="students-table">

                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Full Name</th>
                            <th>Student ID</th>
                            <th>Email</th>
                            <th>Course</th>
                            <th>Year Level</th>
                        </tr>
                    </thead>

                    <tbody id="studentsBody">

                        <?php if ($total_students == 0): ?>

                            <!-- no student accounts in the database yet -->
                            <tr class="empty-row">
                                <td colspan="6">No students have registered yet.</td>
                            </tr>

                        <?php else: ?>

                            <?php foreach ($students as $s): ?>

                                <?php
                                // join first and last name (some students have no last name)
                                $full_name = trim($s["first_name"] . " " . $s["last_name"]);
                                ?>

                                <!-- the data-* attributes are what the JavaScript uses to filter -->
                                <tr class="student-row"
                                    data-name="<?= e($full_name) ?>"
                                    data-studentid="<?= e($s["student_id"]) ?>"
                                    data-email="<?= e($s["email"]) ?>"
                                    data-course="<?= e($s["course"]) ?>"
                                    data-year="<?= e($s["year_level"]) ?>">

                                    <td><span class="cell-id"><?= (int)$s["id"] ?></span></td>
                                    <td><?= e($full_name) ?></td>
                                    <td><?= e($s["student_id"]) ?></td>
                                    <td class="cell-muted"><?= e($s["email"]) ?></td>
                                    <td><?= e($s["course"]) ?></td>
                                    <td><?= e($s["year_level"]) ?></td>

                                </tr>

                            <?php endforeach; ?>

                            <!-- shown by the JavaScript when the search/filters match nobody -->
                            <tr class="empty-row" id="noMatchRow" style="display: none;">
                                <td colspan="6">No students found. Try a different search or filter.</td>
                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>


            <!-- Count + pagination -->
            <div class="table-footer">

                <div class="showing-text" id="showingText">
                    Showing <?= $total_students ?> of <?= $total_students ?> students
                </div>

                <!-- the page buttons (‹ 1 2 3 ›) are made by student.js, 10 students per page -->
                <div class="pagination" id="pagination"></div>

            </div>

        </section>

    </main>


    <!-- TOAST MESSAGE -->
    <div class="toast" id="toast"></div>


    <!-- =========================================
         JAVASCRIPT
         ========================================= -->
    <script src="../../asset/js/student.js?v=<?= filemtime(__DIR__ . '/../../asset/js/student.js') ?>"></script>

</body>
</html>