<?php
session_start();

// ✅ Protect page
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

require 'db.php';

// ✅ Insert product
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_product'])) {
    $productName   = $_POST['productName']; //tanginamo
    $category      = $_POST['category'];
    $price         = $_POST['price'];
    $stockQuantity = $_POST['stockQuantity'];
    $supplierID    = $_POST['supplierID'];

    // Handle image upload
    $productsImg = "";
    if (isset($_FILES['productsImg']) && $_FILES['productsImg']['error'] == 0) {
        $targetDir = "uploads/";
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
        $productsImg = $targetDir . basename($_FILES["productsImg"]["name"]);
        move_uploaded_file($_FILES["productsImg"]["tmp_name"], $productsImg);
    }

    $stmt = $conn->prepare("INSERT INTO products (productsImg, productName, category, price, stockQuantity, supplierID, dateAdded) 
                        VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("sssdis", $productsImg, $productName, $category, $price, $stockQuantity, $supplierID);
    $stmt->execute();


    header("Location: products.php");
    exit();
}

// ✅ Delete product
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_product'])) {
    $deleteID = intval($_POST['deleteID']);

    $stmt = $conn->prepare("DELETE FROM products WHERE productID = ?");
    $stmt->bind_param("i", $deleteID);

    if ($stmt->execute()) {
    echo "success";
    } else {
        echo "error";
    }
    $stmt->close();
    exit();
    

}


// ✅ Fetch products (oldest first)
$result = $conn->query("SELECT * FROM products ORDER BY dateAdded ASC");
   

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update_product'])) {
    $updateID      = intval($_POST['updateID']);
    $productName   = $_POST['updateName'];
    $category      = $_POST['updateCategory'];
    $price         = $_POST['updatePrice'];
    $stockQuantity = $_POST['updateStock'];
    $supplierID    = $_POST['updateSupplier'];

    // Check if ID exists
    $check = $conn->prepare("SELECT productID FROM products WHERE productID = ?");
    $check->bind_param("i", $updateID);
    $check->execute();
    $check->store_result();

    if ($check->num_rows === 0) {
        echo "<script>alert('❌ Product ID does not exist!'); window.location='products.php';</script>";
        $check->close();
        exit;
    }
    $check->close();

    // Handle image upload
    $productsImg = "";
    if (isset($_FILES['updateImg']) && $_FILES['updateImg']['error'] == 0) {
        $targetDir = "uploads/";
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
        $productsImg = $targetDir . basename($_FILES["updateImg"]["name"]);
        move_uploaded_file($_FILES["updat4eImg"]["tmp_name"], $productsImg);
    }

    if (!empty($productsImg)) {
        $stmt = $conn->prepare("UPDATE products 
                                SET productsImg=?, productName=?, category=?, price=?, stockQuantity=?, supplierID=? 
                                WHERE productID=?");
        $stmt->bind_param("sssdisi", $productsImg, $productName, $category, $price, $stockQuantity, $supplierID, $updateID);
    } else {
        $stmt = $conn->prepare("UPDATE products 
                                SET productName=?, category=?, price=?, stockQuantity=?, supplierID=? 
                                WHERE productID=?");
        $stmt->bind_param("ssdisi", $productName, $category, $price, $stockQuantity, $supplierID, $updateID);
    }

    if ($stmt->execute()) {
        echo "<script>alert('✅ Product updated successfully!'); window.location='products.php';</script>";
    } else {
        echo "<script>alert('❌ Error updating product.'); window.location='products.php';</script>";
    }

    $stmt->close();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Products</title>
    <link rel="stylesheet" type="text/css" href="css/products.css">
    
</head>

<!--DELETE TO   --->
<script>
function confirmDelete(productID, event) {
    if (!confirm("Are you sure you want to delete this product?")) return;

    fetch('products.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            delete_product: '1',
            deleteID: productID
        })
    })
    .then(response => response.text())
    .then(data => {
        if (data.trim() === "success") {
            alert("🗑️ Product deleted successfully!");
            const row = event.target.closest('tr');
            row.style.opacity = '0';
            setTimeout(() => row.remove(), 300);
        } else {
            alert("❌ Error deleting product.");
            console.log("Server response:", data);
        }
    })
    .catch(error => {
        alert("⚠️ Error connecting to server.");
        console.error(error);
    });
}
</script>

<body>
    
    <?php
            include("sidebar.php")
    ?>

    <!-- Main Content -->
    <div class="main-content">
        <header class="topbar">
            <h1>📦 Products</h1>

            <div class="settings-menu">
                <button class="settings-btn">&#9776;</button>
                <div class="settings-dropdown">
                    <button class="add-btn" onclick="document.getElementById('modal').style.display='flex'">+ Add Product</button>  
                </div>
        </header>
            <div style="margin: 15px 0;">
                <input type="text" id="searchInput" placeholder="🔍 Search by name or category..." 
                    style="padding:8px; width:300px; border:1px solid #ccc; border-radius:5px;">

                    <!-- Category Filter -->
                <select id="categoryFilter" style="padding:6px; border:1px solid #ccc; border-radius:5px;">
                    <option value="">Categories</option>
                    <option value="Engine & Transmission">Engine & Transmission</option>
                    <option value="Braking System">Braking System</option>
                    <option value="Suspension & Steering">Suspension & Steering</option>
                    <option value="Electrical & Lighting">Electrical & Lighting</option>
                    <option value="Tires & Wheels">Tires & Wheels</option>
                </select>
            </div>


                    <script>
            // ✅ Filter products by Name or Category
            document.getElementById("searchInput").addEventListener("keyup", function() {
                let filter = this.value.toLowerCase();
                let rows = document.querySelectorAll(".products-table tbody tr");

                rows.forEach(row => {
                    let name = row.cells[2].textContent.toLowerCase();     // Product Name
                    let category = row.cells[3].textContent.toLowerCase(); // Category

                    if (name.includes(filter) || category.includes(filter)) {
                        row.style.display = "";
                    } else {
                        row.style.display = "none";
                            }
                        });
                    });
                    </script>

        <!-- Products Table -->
        <table class="products-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Image</th>
                    <th>ProductName</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>QTY</th>
                    <th>Sup.ID</th>
                    <th>DateAdded</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= $row['productID']; ?></td>
                    <td>
                        <?php if(!empty($row['productsImg'])): ?>
                            <img src="<?= $row['productsImg']; ?>" style="width:60px; height:60px; object-fit:cover;">
                        <?php else: ?>
                            <span style="color:#999;">No Image</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($row['productName']); ?></td>
                    <td><?= htmlspecialchars($row['category']); ?></td>
                    <td>₱<?= number_format($row['price'], 2); ?></td>
                    <td><?= $row['stockQuantity']; ?></td>
                    <td><?= $row['supplierID']; ?></td>
                    <td><?= $row['dateAdded']; ?></td>
                    <td>
                        <div class="action">
                            
                        <button class="update-btn"
                            onclick="openUpdateModal(
                                '<?= $row['productID']; ?>',
                                '<?= htmlspecialchars($row['productName']); ?>',
                                '<?= htmlspecialchars($row['category']); ?>',
                                '<?= $row['price']; ?>',
                                '<?= $row['stockQuantity']; ?>',
                                '<?= $row['supplierID']; ?>'
                                )"> Update
                            </button>
                            <button class="delete-btn" onclick="confirmDelete(<?= $row['productID']; ?>, event)"> Delete</button>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>

        </table>
    </div>



    <!-- Add Product Modal -->
    <div class="modal" id="modal">
        <div class="modal-content">
            <h3>Add New Product</h3>
            <form method="POST" action="products.php" enctype="multipart/form-data">
                <label>Product Image:</label>
                <input type="file" name="productsImg" accept="image/*" required><br>

                <label>Product Name:</label>
                <input type="text" name="productName" required><br>

                <label>Category:</label>
                <select name="category" required>
                    <option value="">-- Select Category --</option>
                    <option value="Engine & Transmission">Engine & Transmission</option>
                    <option value="Braking System">Braking System</option>
                    <option value="Suspension & Steering">Suspension & Steering</option>
                    <option value="Electrical & Lighting">Electrical & Lighting</option>
                    <option value="Tires & Wheels">Tires & Wheels</option>
                </select><br>

                <label>Price:</label>
                <input type="number" step="0.01" name="price" required><br>

                <label>Stock Quantity:</label>
                <input type="number" name="stockQuantity" required><br>

                <label>Supplier ID:</label>
                <input type="number" name="supplierID"><br>

                <button type="submit" name="add_product">Save</button>
                <button type="button" onclick="document.getElementById('modal').style.display='none'">Cancel</button>
            </form>
        </div>
    </div>

    
    <!-- UPDATE PRODUCT MODAL -->
    <div class="modal" id="updateModal">
    <div class="modal-content">
        <h3>Update Product</h3>

        <form method="POST" action="products.php" enctype="multipart/form-data">
        <input type="hidden" name="updateID" id="updateID">

        <label>Product Image:</label>
        <input type="file" name="updateImg" accept="image/*"><br>

        <label>Product Name:</label>
        <input type="text" name="updateName" id="updateName" required><br>

        <label>Category:</label>
        <select name="updateCategory" id="updateCategory" required>
            <option value="">-- Select Category --</option>
            <option value="Engine & Transmission">Engine & Transmission</option>
            <option value="Braking System">Braking System</option>
            <option value="Suspension & Steering">Suspension & Steering</option>
            <option value="Electrical & Lighting">Electrical & Lighting</option>
            <option value="Tires & Wheels">Tires & Wheels</option>
        </select><br>

        <label>Price:</label>
        <input type="number" step="0.01" name="updatePrice" id="updatePrice" required><br>

        <label>Stock Quantity:</label>
        <input type="number" name="updateStock" id="updateStock" required><br>

        <label>Supplier ID:</label>
        <input type="number" name="updateSupplier" id="updateSupplier"><br>

        <div style="margin-top:10px;">
            <button type="submit" name="update_product">Update</button>
            <button type="button" onclick="document.getElementById('updateModal').style.display='none'">Cancel</button>
        </div>
        </form>
    </div>
    </div>



            
        <script>
            // Toggle settings dropdown
            document.querySelector(".settings-btn").addEventListener("click", function() {
                document.querySelector(".settings-menu").classList.toggle("show");
            });
      
        // ✅ Search Filter (name + category)
        document.getElementById("searchInput").addEventListener("keyup", function() {
            filterTable();
        });

        // ✅ Category Filter (dropdown only)
        document.getElementById("categoryFilter").addEventListener("change", function() {
            filterTable();
        });

        // ✅ Function to filter table
        function filterTable() {
        let search = document.getElementById("searchInput").value.toLowerCase();
        let categoryFilter = document.getElementById("categoryFilter").value.toLowerCase();
        let rows = document.querySelectorAll(".products-table tbody tr");

        rows.forEach(row => {
            let name = row.cells[2].textContent.toLowerCase();     // Product Name
            let category = row.cells[3].textContent.toLowerCase(); // Category

            let matchSearch = name.includes(search) || category.includes(search);
            let matchCategory = categoryFilter === "" || category === categoryFilter;

            if (matchSearch && matchCategory) {
                row.style.display = "";
            } else {
                row.style.display = "none";
            }
        });
    }   
            
    //SA IUPDATE TO
    function openUpdateModal(productID, name, category, price, qty, supplier) {
        document.getElementById('updateModal').style.display = 'flex';
        document.getElementById('updateID').value = productID;
        document.getElementById('updateName').value = name;
        document.getElementById('updateCategory').value = category;
        document.getElementById('updatePrice').value = price;
        document.getElementById('updateStock').value = qty;
        document.getElementById('updateSupplier').value = supplier;
    }
                               
    </script>
</body>
</html>
