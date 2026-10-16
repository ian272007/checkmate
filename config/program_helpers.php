<?php
/* =========================================================
   Shared helpers for "program" (stored in question_courses /
   users.course) and its subjects (course_subjects).
   One copy, used by:
     view/student/questions.php
     view/student/student_home.php
     view/student/question_post_api.php
     view/student/question_manage_api.php
     view/admin/subject_course.php
     view/admin/subject_course_api.php
   Put this file in: config/program_helpers.php
   ========================================================= */

// Allowed values for the optional curriculum placement.
// year_level matches what users.year_level already stores.
// Edit these lists if your school uses different names.
const PROGRAM_YEAR_LEVELS = ["1st Year", "2nd Year", "3rd Year", "4th Year"];
const PROGRAM_SEMESTERS   = ["1st Semester", "2nd Semester", "Summer"];

/*
 * State of a student's registered program:
 *   "ok"       registered and Active
 *   "missing"  the account has no program saved
 *   "unknown"  the saved program is not in question_courses
 *   "inactive" the program exists but is archived
 */
function checkStudentProgram(mysqli $conn, string $program): string
{
    $program = trim($program);

    if ($program === "") {
        return "missing";
    }

    $stmt = $conn->prepare(
        "SELECT status FROM question_courses WHERE course_name = ? LIMIT 1"
    );

    if (!$stmt) {
        return "unknown";
    }

    $stmt->bind_param("s", $program);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return "unknown";
    }

    return $row["status"] === "Active" ? "ok" : "inactive";
}

function programProblemMessage(string $state): string
{
    if ($state === "missing") {
        return "Your account has no registered program. Please contact the administrator.";
    }

    return "Your registered program is not available right now. Please contact the administrator.";
}

/*
 * Names of the ACTIVE subjects linked to one program.
 * course_subjects has a primary key of (course_id, subject_id), so a
 * subject can never appear twice for the same program.
 * Ordered by year level, then semester, then name.
 */
function loadSubjectsForProgram(mysqli $conn, string $program): array
{
    $options = [];

    $stmt = $conn->prepare(
        "SELECT s.subject_name AS option_name
         FROM question_subjects s
         INNER JOIN course_subjects cs ON cs.subject_id = s.id
         INNER JOIN question_courses c ON c.id = cs.course_id
         WHERE c.course_name = ?
           AND c.status = 'Active'
           AND s.status = 'Active'
         ORDER BY (cs.year_level IS NULL), cs.year_level ASC,
                  (cs.semester IS NULL), cs.semester ASC,
                  s.subject_name ASC"
    );

    if (!$stmt) {
        return $options;
    }

    $stmt->bind_param("s", $program);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $options[] = $row["option_name"];
    }

    $stmt->close();

    return $options;
}