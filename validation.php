<?php

function validateEmailFormat(string $value): ?string
{
    return filter_var($value, FILTER_VALIDATE_EMAIL)
        ? null
        : "Enter a valid email address.";
}

function validateRequired(string $value, string $label): ?string
{
    return trim($value) === ''
        ? "$label is required."
        : null;
}

function validateStrongPassword(string $password): ?string
{
    if (strlen($password) < 8) {
        return "Password is too basic. Use at least 8 characters.";
    }

    if (!preg_match('/[A-Z]/', $password)) {
        return "Password must contain at least one uppercase letter (A-Z).";
    }

    if (!preg_match('/[a-z]/', $password)) {
        return "Password must contain at least one lowercase letter (a-z).";
    }

    if (!preg_match('/[0-9]/', $password)) {
        return "Password must contain at least one number (0-9).";
    }

    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        return "Password must contain at least one special character (e.g. !, @, #, $).";
    }

    $weakPasswords = [
        'password',
        'password123',
        '12345678',
        '123456789',
        'qwerty123',
        'admin123',
        'letmein123'
    ];

    if (in_array(strtolower($password), $weakPasswords, true)) {
        return "That password is too common. Please create a stronger password.";
    }

    return null;
}

function validateStudentInput(array $post): array
{
    $username = trim($post['username'] ?? '');
    $email    = trim($post['email'] ?? '');
    $password = $post['password'] ?? '';

    $errors = array_filter([
        validateRequired($username, 'Username'),
        validateRequired($email, 'Email'),
        validateEmailFormat($email),
        validateRequired($password, 'Password'),
        validateStrongPassword($password)
    ]);

    return [
        'errors' => $errors,
        'data'   => [
            'username' => $username,
            'email'    => $email,
            'password' => $password
        ]
    ];
}

function validateLoginInput(array $post): array
{
    $username = trim($post['username'] ?? '');
    $password = $post['password'] ?? '';

    $errors = array_filter([
        validateRequired($username, 'Username'),
        validateRequired($password, 'Password')
    ]);

    return [
        'errors' => $errors,
        'data'   => [
            'username' => $username,
            'password' => $password
        ]
    ];
}
?>