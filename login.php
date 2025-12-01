<?php

session_start();
include 'connection.php'; // Include the database connection

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user = trim($_POST["username"]);
    $pass = $_POST["password"];

    if (!empty($user) && !empty($pass)) {
        // Prepare statement
        $stmt = $conn->prepare("SELECT * FROM admins WHERE username = ?");
        $stmt->bind_param("s", $user);
        $stmt->execute();
        $result = $stmt->get_result();
        $admin = $result->fetch_assoc();

        if ($admin && password_verify($pass, $admin["password"])) {
            // Set session variables
            $_SESSION["admin_id"] = $admin["id"];
            $_SESSION["admin_username"] = $admin["username"];

            // Redirect to dashboard
            header("Location: dashboard/admin_dashboard.php");
            exit();
        } else {
            $error = "Invalid username or password.";
        }
    } else {
        $error = "Please fill in all fields.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DeliverDash - Admin Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href='https://fonts.googleapis.com/css?family=Montserrat' rel='stylesheet'>
   
    
</head>
<body class="flex justify-center items-center h-screen bg-[#545454]">
    <main class="flex justify-center items-center ">
        <div class="bg-[#363B40] backdrop-blur-lg border border-white/20 h-[750px] w-[500px] rounded-2xl shadow-xl flex flex-col justify-center items-center p-8">  
            <form action="" method="POST" class="w-full">
                <div class="flex flex-col gap-4 w-full">

                    <div class="w-[300px] flex justify-center items-center w-full">
                        <img src="picture/icon/logo.png" alt="">

                    </div>


                    <div class="flex flex-col justify-center items-center gap-4 p-5">   
                        <h1 class="font-montserrat text-2xl md:text-3xl font-bold text-white text-center">
                            Administrator Login
                        </h1>
                    </div>

                    <?php if ($error): ?>
                        <p class="text-red-500 text-center bg-red-100 p-2 rounded-lg"> <?= $error; ?> </p>
                    <?php endif; ?>

                    <input class="bg-white/20 text-white placeholder-gray-300 h-[50px] w-full p-4 rounded-lg shadow-md outline-0 focus:ring-2 focus:ring-green-400" type="text" name="username" placeholder="Username" required>
                    <input class="bg-white/20 text-white placeholder-gray-300 h-[50px] w-full p-4 rounded-lg shadow-md outline-0 focus:ring-2 focus:ring-green-400" type="password" name="password" placeholder="Password" required>

                    <div class="flex justify-end text-white font-montserrat">
                        <a class="underline underline-offset-2 hover:text-green-300 transition duration-200" href="#">Forgot Password?</a>
                    </div>

                    <div class="w-full flex justify-center">
                        <button type="submit" class="bg-green-400 w-full h-[50px] rounded-full text-white font-bold text-lg cursor-pointer hover:bg-green-500 transition duration-200 ease-in-out shadow-lg">
                            Login
                        </button>
                    </div>

                    <!-- <div class="flex justify-center items-center text-white font-montserrat">
                        <p>Don't have an account?</p>
                        <a class="text-green-300 pl-2 hover:text-green-400 transition duration-200" href="register.php">Sign up!</a>
                    </div> -->
                </div>
            </form>
        </div>
    </main>
</body>
</html>