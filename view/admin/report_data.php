<?php
// Shared by report.php (first load) and report_api.php (live updates)

function loadReports(mysqli $conn): array
{
    $sql = "SELECT r.report_id AS id,
                   r.reason,
                   r.details,
                   r.image,
                   r.status,
                   r.create_at,
                   r.question_id,
                   q.question,
                   q.subject,
                   q.question_course AS course,
                   q.question_type AS type,
                   q.attachment,
                   TRIM(CONCAT(COALESCE(ur.first_name, ''), ' ', COALESCE(ur.last_name, ''))) AS reporter,
                   TRIM(CONCAT(COALESCE(uq.first_name, ''), ' ', COALESCE(uq.last_name, ''))) AS student,
                   uq.id AS student_uid,
                   COALESCE(uq.status, 'Active') AS student_status
            FROM reports r
            LEFT JOIN users ur ON ur.id = r.reporter_id
            LEFT JOIN questions q ON q.question_id = r.question_id
            LEFT JOIN users uq ON uq.id = q.user_id
            ORDER BY r.create_at DESC, r.report_id DESC";

    $reports = [];
    $reasons = [];

    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {

        $row["id"] = (int) $row["id"];
        $row["student_uid"] = $row["student_uid"] !== null ? (int) $row["student_uid"] : null;

        if ($row["reporter"] === "") { $row["reporter"] = "Unknown"; }
        if ($row["student"] === "")  { $row["student"]  = "Unknown"; }

        // the question was deleted, so the report no longer points to it
        $row["question_deleted"] = ($row["question"] === null);
        if ($row["question"] === null) {
            $row["question"] = "(question was deleted)";
        }

        if ($row["attachment"] !== null) {
            $row["attachment"] = basename($row["attachment"]);
        }

        // NEW: only the file name of the reporter's image
        if ($row["image"] !== null) {
            $row["image"] = basename($row["image"]);
        }

        // nice date + time
        $time = strtotime($row["create_at"]);
        $row["date"] = date("M j, Y", $time);
        $row["time"] = date("h:i A", $time);
        unset($row["create_at"]);

        if (!in_array($row["reason"], $reasons, true)) {
            $reasons[] = $row["reason"];
        }

        $reports[] = $row;
    }

    sort($reasons);

    return [$reports, $reasons];
}