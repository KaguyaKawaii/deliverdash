<?php
session_start();
session_regenerate_id(true);

include '../../connection_api.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: user_login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Retrieve the delivery_id from the query string
if (isset($_GET['delivery_id'])) {
    $delivery_id = $_GET['delivery_id'];
} else {
    header("Location: user_delivery.php");
    exit();
}

// Get delivery details from the database
$sql = "SELECT * FROM deliveries WHERE delivery_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $delivery_id);
$stmt->execute();
$result = $stmt->get_result();
$delivery = $result->fetch_assoc();
$stmt->close();

// Display delivery details
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Confirmation</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href='https://fonts.googleapis.com/css?family=Montserrat' rel='stylesheet'>
</head>
<body class="bg-gray-100">
    <div class="max-w-lg mx-auto mt-10 bg-white p-6 rounded-lg shadow-lg">
        <h2 class="text-xl font-bold mb-4">Confirm Your Delivery</h2>

        <form action="payment_processing.php" method="POST" class="space-y-4">
            <h3>Delivery Information</h3>
            <p><strong>Delivery Option:</strong> <?php echo htmlspecialchars($delivery['delivery_option']); ?></p>
            <p><strong>Product Name:</strong> <?php echo htmlspecialchars($delivery['product_name']); ?></p>
            <p><strong>Weight:</strong> <?php echo htmlspecialchars($delivery['weight']); ?> kg</p>
            <p><strong>Box Size:</strong> <?php echo htmlspecialchars($delivery['box_size']); ?></p>
            <p><strong>Category:</strong> <?php echo htmlspecialchars($delivery['category']); ?></p>

            <h3>Payment Information</h3>
            <label for="payment_method" class="block">Payment Method</label>
            <select name="payment_method" id="payment_method" class="w-full p-2 border rounded-md">
                <option value="credit_card">Credit Card</option>
                <option value="cash_on_delivery">Cash on Delivery</option>
            </select>

            <input type="hidden" name="delivery_id" value="<?php echo $delivery_id; ?>">
            <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
            <input type="hidden" name="amount" value="<?php echo $delivery['box_price']; ?>">

            <button type="submit" class="w-full bg-blue-600 text-white p-2 rounded-md hover:bg-blue-700">Proceed to Payment</button>
        </form>
    </div>
</body>
</html>
