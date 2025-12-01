<?php
session_start();
include '../connection.php';

$response = ['success' => false, 'message' => ''];

if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'Unauthorized access';
    echo json_encode($response);
    exit();
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle profile photo upload
    if (isset($_POST['update_profile_photo']) && isset($_FILES['profile_photo'])) {
        $upload_dir = '../uploads/profile_photos/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 2 * 1024 * 1024; // 2MB
        $file = $_FILES['profile_photo'];
        
        if (in_array($file['type'], $allowed_types) && $file['size'] <= $max_size) {
            $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $file_name = 'user_' . $user_id . '_' . time() . '.' . $file_ext;
            $file_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($file['tmp_name'], $file_path)) {
                // Get current photo to delete later
                $sql = "SELECT profile_photo FROM users WHERE user_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();
                $stmt->close();
                
                // Delete old photo if it exists
                if (!empty($user['profile_photo']) && file_exists($user['profile_photo'])) {
                    unlink($user['profile_photo']);
                }
                
                // Update database
                $sql = "UPDATE users SET profile_photo = ? WHERE user_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("si", $file_path, $user_id);
                
                if ($stmt->execute()) {
                    $response['success'] = true;
                    $response['message'] = 'Profile photo updated successfully';
                    $response['profile_photo'] = $file_path;
                    $_SESSION['profile_photo'] = $file_path;
                } else {
                    $response['message'] = 'Database error: ' . $stmt->error;
                    unlink($file_path); // Remove uploaded file if DB update fails
                }
                $stmt->close();
            } else {
                $response['message'] = 'Error moving uploaded file';
            }
        } else {
            $response['message'] = 'Invalid file type or size (max 2MB allowed)';
        }
    }
    // Handle profile information update
    elseif (isset($_POST['update_profile'])) {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $contact = trim($_POST['contact']);
        $address = trim($_POST['address'] ?? '');
        
        if (!empty($name) && !empty($email) && !empty($contact)) {
            $profile_photo = $_POST['existing_photo'] ?? ''; // Keep existing photo by default
            
            $sql = "UPDATE users SET name = ?, email = ?, contact = ?, address = ? WHERE user_id = ?";
            $stmt = $conn->prepare($sql);
            
            if ($stmt) {
                $stmt->bind_param("ssssi", $name, $email, $contact, $address, $user_id);
                if ($stmt->execute()) {
                    $_SESSION['user_name'] = $name;
                    $response['success'] = true;
                    $response['message'] = 'Profile updated successfully!';
                    $response['name'] = $name;
                } else {
                    $response['message'] = 'Error: ' . $stmt->error;
                }
                $stmt->close();
            } else {
                $response['message'] = 'Error preparing statement: ' . $conn->error;
            }
        } else {
            $response['message'] = 'Name, email and contact are required fields.';
        }
    }
    // Handle password change
    elseif (isset($_POST['change_password'])) {
        $current_password = trim($_POST['current_password']);
        $new_password = trim($_POST['new_password']);
        $confirm_password = trim($_POST['confirm_password']);
        
        if (!empty($current_password) && !empty($new_password) && !empty($confirm_password)) {
            if (strlen($new_password) < 8) {
                $response['message'] = 'Password must be at least 8 characters long.';
            } elseif ($new_password !== $confirm_password) {
                $response['message'] = 'New passwords do not match.';
            } else {
                // Verify current password
                $sql = "SELECT password FROM users WHERE user_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $user_data = $result->fetch_assoc();
                $stmt->close();
                
                if (password_verify($current_password, $user_data['password'])) {
                    // Update password
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $sql = "UPDATE users SET password = ? WHERE user_id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("si", $hashed_password, $user_id);
                    
                    if ($stmt->execute()) {
                        $response['success'] = true;
                        $response['message'] = 'Password updated successfully!';
                    } else {
                        $response['message'] = 'Error updating password: ' . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    $response['message'] = 'Current password is incorrect.';
                }
            }
        } else {
            $response['message'] = 'All password fields are required.';
        }
    }
    // Handle username change
    elseif (isset($_POST['change_username'])) {
        $new_username = trim($_POST['new_username']);
        $current_password = trim($_POST['username_password']);
        
        if (!empty($new_username) && !empty($current_password)) {
            if (strlen($new_username) < 4) {
                $response['message'] = 'Username must be at least 4 characters long.';
            } else {
                // Verify current password first
                $sql = "SELECT password FROM users WHERE user_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $user_data = $result->fetch_assoc();
                $stmt->close();
                
                if (password_verify($current_password, $user_data['password'])) {
                    // Check if username already exists
                    $sql = "SELECT user_id FROM users WHERE username = ? AND user_id != ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("si", $new_username, $user_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows === 0) {
                        // Update username
                        $sql = "UPDATE users SET username = ? WHERE user_id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("si", $new_username, $user_id);
                        
                        if ($stmt->execute()) {
                            $_SESSION['user_name'] = $new_username;
                            $response['success'] = true;
                            $response['message'] = 'Username updated successfully!';
                            $response['username'] = $new_username;
                        } else {
                            $response['message'] = 'Error updating username: ' . $stmt->error;
                        }
                        $stmt->close();
                    } else {
                        $response['message'] = 'Username already exists. Please choose a different one.';
                    }
                } else {
                    $response['message'] = 'Current password is incorrect.';
                }
            }
        } else {
            $response['message'] = 'Both fields are required.';
        }
    }
    // Handle 2FA toggle
    elseif (isset($_POST['toggle_2fa'])) {
        // In a real implementation, you would set up actual 2FA here
        // For this example, we'll just store a preference in the session
        $_SESSION['2fa_enabled'] = !isset($_SESSION['2fa_enabled']) || !$_SESSION['2fa_enabled'];
        
        $response['success'] = true;
        $response['message'] = 'Two-factor authentication has been ' . ($_SESSION['2fa_enabled'] ? 'enabled' : 'disabled') . '.';
        $response['enabled'] = $_SESSION['2fa_enabled'];
    }
}

echo json_encode($response);
?>