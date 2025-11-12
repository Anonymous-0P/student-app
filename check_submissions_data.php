<?php
require_once 'config/config.php';

echo "=== CHECKING SUBMISSIONS DATA ===\n\n";

// Get current moderator (you'll need to replace this with actual moderator_id)
echo "Available moderators:\n";
$mods = $conn->query("SELECT id, name, email, role FROM users WHERE role = 'moderator'");
while ($row = $mods->fetch_assoc()) {
    echo "  ID: {$row['id']} - {$row['name']} ({$row['email']})\n";
}

echo "\n\nAvailable evaluators:\n";
$evals = $conn->query("SELECT id, name, email, moderator_id FROM users WHERE role = 'evaluator'");
while ($row = $evals->fetch_assoc()) {
    echo "  ID: {$row['id']} - {$row['name']} (Moderator ID: {$row['moderator_id']})\n";
}

echo "\n\nAll submissions:\n";
$subs = $conn->query("SELECT id, student_id, evaluator_id, moderator_id, status, submission_title, created_at FROM submissions ORDER BY created_at DESC LIMIT 10");
while ($row = $subs->fetch_assoc()) {
    echo "  ID: {$row['id']} - Student: {$row['student_id']}, Evaluator: {$row['evaluator_id']}, Moderator: {$row['moderator_id']}, Status: {$row['status']}\n";
    echo "    Title: {$row['submission_title']}, Created: {$row['created_at']}\n";
}

echo "\n\nSubmissions with assignment details:\n";
$query = "SELECT s.id, s.moderator_id, s.evaluator_id, s.status, 
          a.id as assignment_id, a.subject_id,
          subj.name as subject_name
          FROM submissions s
          LEFT JOIN assignments a ON s.assignment_id = a.id
          LEFT JOIN subjects subj ON a.subject_id = subj.id
          ORDER BY s.created_at DESC LIMIT 10";
$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    echo "  Submission {$row['id']}: Mod={$row['moderator_id']}, Eval={$row['evaluator_id']}, Status={$row['status']}\n";
    echo "    Assignment: {$row['assignment_id']}, Subject: {$row['subject_name']} (ID: {$row['subject_id']})\n";
}

echo "\n\nModerator-Subject relationships:\n";
$ms = $conn->query("SELECT moderator_id, subject_id, is_active FROM moderator_subjects");
while ($row = $ms->fetch_assoc()) {
    echo "  Moderator {$row['moderator_id']} -> Subject {$row['subject_id']} (Active: {$row['is_active']})\n";
}
