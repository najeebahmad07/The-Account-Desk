<?php
// File: includes/auth.php
// No whitespace before this line

function authenticateUser($username, $password) {
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("SELECT id, name, username, email, password, role, center_id, status FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        if ($user['status'] === 'blocked') {
            $stmt->close();
            $conn->close();
            return ['success' => false, 'message' => 'Your account has been blocked. Contact Super Admin.'];
        }
        
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['center_id'] = $user['center_id'];
            $_SESSION['last_activity'] = time();
            
            logActivity($user['id'], 'Login', 'Successful login');
            
            $stmt->close();
            $conn->close();
            return ['success' => true, 'role' => $user['role']];
        }
    }
    
    $stmt->close();
    $conn->close();
    return ['success' => false, 'message' => 'Invalid username or password.'];
}

function createUser($name, $username, $email, $password, $role, $centerId = null) {
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $stmt->close();
        $conn->close();
        return ['success' => false, 'message' => 'Username already exists.'];
    }
    $stmt->close();
    
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("INSERT INTO users (name, username, email, password, role, center_id, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
    $stmt->bind_param("sssssi", $name, $username, $email, $hashedPassword, $role, $centerId);
    
    if ($stmt->execute()) {
        $userId = $conn->insert_id;
        $stmt->close();
        $conn->close();
        return ['success' => true, 'user_id' => $userId];
    }
    
    $error = $stmt->error;
    $stmt->close();
    $conn->close();
    return ['success' => false, 'message' => 'Failed to create user: ' . $error];
}

function changePassword($userId, $newPassword) {
    $conn = getDBConnection();
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->bind_param("si", $hashedPassword, $userId);
    $success = $stmt->execute();
    $stmt->close();
    $conn->close();
    return $success;
}