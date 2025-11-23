<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== "Cashier") exit();
include "db.php";
?>

<?php if (isset($_SESSION['cart']) && count($_SESSION['cart']) > 0): ?>
<div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-700 bg-gray-900 text-gray-200 rounded-xl">
        <thead class="bg-gray-800">
            <tr>
                <th class="px-4 py-2 text-left">Product</th>
                <th class="px-4 py-2 text-left">Price</th>
                <th class="px-4 py-2 text-left">Quantity</th>
                <th class="px-4 py-2 text-left">Subtotal</th>
                <th class="px-4 py-2 text-left">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $total = 0;
            foreach ($_SESSION['cart'] as $item):
                $subtotal = $item['price'] * $item['quantity'];
                $total += $subtotal;
            ?>
            <tr class="hover:bg-gray-800 transition">
                <td class="px-4 py-2"><?= htmlspecialchars($item['productName']) ?></td>
                <td class="px-4 py-2 text-green-400 font-semibold">₱<?= number_format($item['price'], 2) ?></td>
                <td class="px-4 py-2">
                    <form class="update_qty_form flex items-center gap-2" method="POST">
                        <input type="hidden" name="update_qty" value="1">
                        <input type="hidden" name="updateID" value="<?= $item['productID'] ?>">
                        <input type="number" name="newQty" value="<?= $item['quantity'] ?>" min="1" max="<?= $item['stockQuantity'] ?>" class="w-16 p-1 rounded-lg text-center bg-gray-800 border border-gray-700 text-gray-200">
                        <button type="submit" class="px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-semibold">Update</button>
                    </form>
                </td>
                <td class="px-4 py-2 font-semibold">₱<?= number_format($subtotal, 2) ?></td>
                <td class="px-4 py-2">
                    <form class="remove_cart_form" method="POST">
                        <input type="hidden" name="remove_cart" value="1">
                        <input type="hidden" name="removeID" value="<?= $item['productID'] ?>">
                        <button type="submit" class="px-3 py-1 bg-red-600 hover:bg-red-700 text-white rounded-lg font-semibold">Remove</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            <tr class="bg-gray-800 font-bold">
                <td colspan="3" class="px-4 py-2 text-right">Total:</td>
                <td colspan="2" class="px-4 py-2">₱<?= number_format($total, 2) ?></td>
            </tr>
        </tbody>
    </table>
</div>

<div class="mt-6 bg-gray-900 p-6 rounded-2xl shadow-lg text-gray-200">
    <form id="submitSaleForm" class="space-y-4">
        <input type="hidden" name="totalAmount" value="<?= $total ?>">
        <input type="hidden" name="cashier" value="<?= htmlspecialchars($_SESSION['username']) ?>">
        <input type="hidden" name="paymentType" value="cash">

        <h3 class="text-xl font-bold text-white">Client Information</h3>

        <div class="flex flex-col gap-4">
            <input type="text" name="clientName" placeholder="Client Name" required class="input-style w-full p-3 rounded-xl">
            <input type="text" name="contactNumber" placeholder="09XXXXXXXXX" required class="input-style w-full p-3 rounded-xl">
            <input type="email" name="email" placeholder="client@example.com" required class="input-style w-full p-3 rounded-xl">
            <input type="text" name="address" placeholder="Enter address" required class="input-style w-full p-3 rounded-xl">

            <select name="salesAccount" required class="input-style w-full p-3 rounded-xl">
                <?php
                $sales = $conn->query("SELECT username FROM usermanagement WHERE LOWER(role)='sales'");
                if ($sales && $sales->num_rows > 0):
                    while ($s = $sales->fetch_assoc()):
                        echo "<option value='".htmlspecialchars($s['username'])."'>".htmlspecialchars($s['username'])."</option>";
                    endwhile;
                else:
                    echo "<option disabled>No sales accounts found</option>";
                endif;
                ?>
            </select>

            <button type="submit" class="w-full px-6 py-3 bg-red-600 hover:bg-red-700 text-white rounded-xl font-semibold shadow-lg transition">
                Submit Sale & Generate Receipt
            </button>
        </div>
    </form>
</div>

<?php else: ?>
<p class="text-gray-400">Cart is empty.</p>
<?php endif; ?>

<script>
$("#submitSaleForm").submit(function(e) {
    e.preventDefault();

    $.ajax({
        url: 'cashier-items.php',
        type: 'POST',
        data: $(this).serialize() + '&submit_sale=1',
        dataType: 'json',
        success: function(data) {
            if (data.status === 'submitted') {
                alert("✅ Sale submitted successfully! Sale ID: " + data.saleID);
                window.open('generate-receipt.php?saleID=' + data.saleID, '_blank');
                loadCart();
                updateBadge();
            } else if (data.status === 'empty') {
                alert("❌ Cart is empty.");
            } else {
                alert("❌ " + (data.message || "An error occurred."));
            }
        },
        error: function(xhr, status, error) {
            console.error("AJAX Error:", status, error);
            alert("❌ Invalid server response or network error.");
        }
    });
});

function loadCart(){ $.get('get_cart.php', data => $('#cartContent').html(data)); }
function updateBadge(){ $.get('get_count.php', function(count){ $('#cartCountBadge').text(count); }); }
</script>
