<?php

require 'config.php';

$pdo = getConnection();
$sql = "SELECT id, username, email, role, created_at FROM user ORDER BY id ASC";
$stmt = $pdo->query($sql);
$students = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>All Users</title>
</head>
<body>

    <h1>All Users</h1>

    <p>
        <a href="index.php">← Back to Homepage</a> | 
        <a href="index.php">Register a new user</a>
    </p>

    <?php if (empty($students)): ?>
        <p>No users found in database.</p>
    <?php else: ?>
        <table border="1" cellpadding="8" cellspacing="0">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Created At</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($students as $student): ?>
                    <tr>
                        <td><?= htmlspecialchars($student['id']) ?></td>
                        <td><?= htmlspecialchars($student['username']) ?></td>
                        <td><?= htmlspecialchars($student['email']) ?></td>
                        <td><?= htmlspecialchars($student['role']) ?></td>
                        <td><?= htmlspecialchars($student['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

</body>
</html>