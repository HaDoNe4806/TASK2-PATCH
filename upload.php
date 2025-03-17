<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

// Kết nối database
$host = "localhost";
$user = "root";
$pass = "";
$db = "webfu";
$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$username = $_SESSION['user'];

// Xử lý upload file
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $target_dir = "uploads/";

    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    $file_name = basename($_FILES["avatar"]["name"]);
    $target_file = $target_dir . $file_name;
    $file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    $allowed_types = ["jpg", "jpeg", "png", "gif"];

    // Kiểm tra MIME type thực sự của file
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $_FILES["avatar"]["tmp_name"]);
    finfo_close($finfo);
    $valid_mime_types = ["image/jpeg", "image/png", "image/gif"];

    // Kiểm tra file hợp lệ
    if (!in_array($file_type, $allowed_types) || !in_array($mime_type, $valid_mime_types)) {
        $upload_message = "<p class='error'> Invalid file type. Only JPG, PNG, and GIF are allowed.</p>";
    } elseif (move_uploaded_file($_FILES["avatar"]["tmp_name"], $target_file)) {
        // Lấy user_id từ username
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();
        $stmt->bind_result($user_id);
        $stmt->fetch();
        $stmt->close();

        // Cập nhật avatar mới vào DB
        $stmt = $conn->prepare("INSERT INTO avatars(user_id, file_path) VALUES (?, ?)");
        $stmt->bind_param("is", $user_id, $target_file);
        $stmt->execute();
        $stmt->close();

        $upload_message = "<p class='success'>The file " . htmlspecialchars($file_name) . " has been uploaded.</p>";
    } else {
        $upload_message = "<p class='error'>Sorry, there was an error uploading your file.</p>";
    }
    $filename = basename($_FILES["avatar"]["name"]);

    // Ngăn chặn file .htaccess 
    $forbidden_files = [".htaccess"];
    if (in_array(strtolower($filename), $forbidden_files)) {
        die("<p class='error'>This file type is not allowed for security reasons.</p>");
    }

}

// Lấy avatar mới nhất
$stmt = $conn->prepare("SELECT file_path FROM avatars JOIN users ON avatars.user_id = users.id WHERE users.username = ? ORDER BY avatars.id DESC LIMIT 1");
$stmt->bind_param("s", $username);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($avatar_path);
$stmt->fetch();
$stmt->close();

$conn->close();

$default_avatar = "uploads/default.jpg";
$avatar_url = $avatar_path ? $avatar_path : $default_avatar;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Avatar</title>
    <style>
        .upload-container {
            width: 300px;
            margin: auto;
            text-align: center;
            padding: 20px;
            border: 1px solid #ccc;
            border-radius: 10px;
            box-shadow: 2px 2px 10px rgba(0,0,0,0.1);
        }
        .avatar-preview img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            border: 2px solid #ddd;
            margin-bottom: 10px;
        }
        .input-group {
            margin-bottom: 10px;
        }
        .btn-upload, .btn-reset {
            padding: 5px 10px;
            margin: 5px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="upload-container">
        <h2>Upload New Avatar</h2>

        <div class="avatar-preview">
            <img src="<?= htmlspecialchars($avatar_url) ?>" alt="Avatar" class="avatar-img">
        </div>

        <?= isset($upload_message) ? $upload_message : '' ?>

        <form action="upload.php" method="post" enctype="multipart/form-data">
            <div class="input-group">
                <input type="file" name="avatar" required>
            </div>
            <button type="submit" class="btn-upload">Upload</button>
            <button type="reset" class="btn-reset">Reset</button>
        </form>
    </div>
</body>
</html>
