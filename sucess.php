<?php

require 'config.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    header('Location: index.php');
    exit;
}

$pdo = getConnection();
$sql = "SELECT id, username, email, role, created_at FROM user WHERE id = :id";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':id', $id, PDO::PARAM_INT);
$stmt->execute();
$student = $stmt->fetch();

if (!$student) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Created</title>
</head>
<body>

    <h1>User Registered Successfully!</h1>

    <p><strong>ID:</strong> <?= htmlspecialchars($student['id']) ?></p>
    <p><strong>Username:</strong> <?= htmlspecialchars($student['username']) ?></p>
    <p><strong>Email:</strong> <?= htmlspecialchars($student['email']) ?></p>
    <p><strong>Role:</strong> <?= htmlspecialchars($student['role']) ?></p>
    <p><strong>Registered Date:</strong> <?= htmlspecialchars($student['created_at']) ?></p>

    <p>
        <a href="index.php">← Back to Homepage</a> | 
        <a href="student.php">View All Users</a>
    </p>

</body>
</html>