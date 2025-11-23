<?php
session_start();

// ✅ Protect page
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

require 'db.php';

// ✅ AJAX endpoint: Create User
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['create_user'])) {
    $username    = trim($_POST['username']);
    $password    = $_POST['password'];
    $role        = $_POST['role'];
    $fullName    = trim($_POST['fullName']);
    $email       = trim($_POST['email']);
    $status      = $_POST['status'];
    $dateCreated = date("Y-m-d H:i:s");

    // Password requirements
    $hasLower = preg_match('/[a-z]/', $password);
    $hasUpper = preg_match('/[A-Z]/', $password);
    $hasNumber= preg_match('/\d/', $password);
    $isLong   = strlen($password) >= 8;

    if (!$hasLower || !$hasUpper || !$hasNumber || !$isLong) {
        echo json_encode(['status'=>'error', 'message'=>'Password does not meet the requirements.']);
        exit;
    }

    $sql = "INSERT INTO usermanagement 
            (username, password, role, fullName, email, status, dateCreated) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssss", $username, $password, $role, $fullName, $email, $status, $dateCreated);

    if ($stmt->execute()) {
        echo json_encode(['status'=>'success', 'message'=>'User account created successfully!']);
    } else {
        echo json_encode(['status'=>'error', 'message'=>'Error creating account: '.$conn->error]);
    }
    $stmt->close();
    exit;
}

// ✅ Fetch users by role (AJAX)
if (isset($_GET['role']) && !isset($_GET['for_delete'])) {
    $role = $_GET['role'];
    $stmt = $conn->prepare("SELECT username, role, fullName, email, status, dateCreated FROM usermanagement WHERE role=?");
    $stmt->bind_param("s", $role);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo "<table class='user-table'>
                <tr>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Full Name</th>
                    <th>Email</th>
                    <th>Status</th>
                    <th>Date Created</th>
                </tr>";
        while($row = $result->fetch_assoc()) {
            echo "<tr>
                    <td>{$row['username']}</td>
                    <td>{$row['role']}</td>
                    <td>{$row['fullName']}</td>
                    <td>{$row['email']}</td>
                    <td>{$row['status']}</td>
                    <td>{$row['dateCreated']}</td>
                  </tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No users found for role: <strong>$role</strong></p>";
    }
    exit;
}

// ✅ AJAX endpoint: Return username options for deletion
if (isset($_GET['for_delete']) && isset($_GET['role'])) {
    $role = $_GET['role'];
    if (!in_array($role, ['Admin', 'sales', 'Cashier'])) {
        echo "<option value=''>-- Invalid role --</option>";
        exit;
    }

    $stmt = $conn->prepare("SELECT username FROM usermanagement WHERE role=? AND status='Active' ORDER BY username");
    $stmt->bind_param("s", $role);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo "<option value=''>-- Select Username --</option>";
        while ($row = $result->fetch_assoc()) {
            $u = htmlspecialchars($row['username'], ENT_QUOTES);
            echo "<option value=\"{$u}\">{$u}</option>";
        }
    } else {
        echo "<option value=''>-- No users found --</option>";
    }
    exit;
}

$userCountResult = $conn->query("SELECT COUNT(*) AS total FROM usermanagement");
$userCount = $userCountResult ? (int)$userCountResult->fetch_assoc()['total'] : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>User Management</title>
<link rel="stylesheet" type="text/css" href="css/Usermanagement.css">
<script src="https://cdn.tailwindcss.com" defer></script>
<script src="https://unpkg.com/lucide@latest"></script>
<style>
.input-style {
    background-color: #1f2937;
    color: #d1d5db;
    border: 2px solid #374151;
    transition: all 0.3s ease;
    width: 100%;
}
.input-style:focus {
    outline: none;
    border-color: #ef4444;
}
.valid { color: #10b981; font-weight: 600; }
.invalid { color: #f87171; font-weight: 600; }
button[disabled] { opacity: 0.5; cursor: not-allowed; }
</style>
</head>
<body class="app-bg text-gray-100 min-h-screen">
<div class="flex w-full min-h-screen">
    <?php include "admin-sidebar.php"; ?>
    <main class="flex-1 md:ml-64 p-6 md:p-10 w-full space-y-8">
        <header class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between border-b border-gray-800 pb-4">
            <div class="flex items-center space-x-3">
                <i data-lucide="users" class="text-red-500 w-10 h-10"></i>
                <div>
                    <p class="text-sm uppercase tracking-[0.3em] text-gray-500">Administrative Hub</p>
                    <h1 class="text-4xl font-bold tracking-tight text-white">User Management</h1>
                </div>
            </div>
            <div class="text-gray-400 text-sm">Total Users: <?= $userCount ?></div>
        </header>

        <section class="bg-gray-900 p-6 rounded-2xl shadow-2xl">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <p class="text-sm uppercase tracking-widest text-gray-500">Create</p>
                    <h2 class="text-2xl font-semibold text-white">New User</h2>
                </div>
            </div>
            <form id="createUserForm" class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="block text-sm text-gray-400 mb-1">Username</label>
                    <input type="text" name="username" required class="input-style p-3 rounded-xl">
                </div>
                <div>
                    <label class="block text-sm text-gray-400 mb-1">Role</label>
                    <select name="role" required class="input-style p-3 rounded-xl cursor-pointer">
                        <option value="">-- Select Role --</option>
                        <option value="Admin">Admin</option>
                        <option value="sales">Sales</option>
                        <option value="Cashier">Cashier</option>
                    </select>
                </div>

               <div class="relative">
    <label class="block text-sm text-gray-400 mb-1">Password</label>
    <input type="password" id="password" name="password" required 
           class="input-style p-3 rounded-xl w-full pr-16">

    <button type="button"
        onclick="togglePassword('password', this)"
        class="absolute right-3 top-10 text-gray-400 text-sm">
        Show
    </button>
</div>

<div class="relative mt-3">
    <label class="block text-sm text-gray-400 mb-1">Confirm Password</label>
    <input type="password" id="confirm_pass" name="confirm_pass" required 
           class="input-style p-3 rounded-xl w-full pr-16">

    <button type="button"
        onclick="togglePassword('confirm_pass', this)"
        class="absolute right-3 top-10 text-gray-400 text-sm">
        Show
    </button>

    <div id="confirmError" style="display:none" 
         class="text-red-400 text-sm font-semibold mt-2">
         ❌ Passwords do not match!
    </div>
</div>

<script>
function togglePassword(id, btn) {
    const input = document.getElementById(id);

    if (input.type === "password") {
        input.type = "text";
        btn.textContent = "Hide";
    } else {
        input.type = "password";
        btn.textContent = "Show";
    }
}
</script>
                <div class="md:col-span-2">
                    <div id="message" style="display:none" class="border border-gray-700 bg-gray-800/50 rounded-xl p-4 text-sm text-gray-300">
                        <p class="mb-2 text-gray-400">Password must contain:</p>
                        <p id="letter" class="invalid">• At least <b>one lowercase letter</b></p>
                        <p id="capital" class="invalid">• At least <b>one capital letter</b></p>
                        <p id="number" class="invalid">• At least <b>one number</b></p>
                        <p id="length" class="invalid">• Be at least <b>8 characters</b></p>
                    </div>
                </div>

                <div>
                    <label class="block text-sm text-gray-400 mb-1">Full Name</label>
                    <input type="text" name="fullName" required class="input-style p-3 rounded-xl">
                </div>
                <div>
                    <label class="block text-sm text-gray-400 mb-1">Email</label>
                    <input type="email" name="email" required class="input-style p-3 rounded-xl">
                </div>
                <div>
                    <label class="block text-sm text-gray-400 mb-1">Status</label>
                    <select name="status" required class="input-style p-3 rounded-xl cursor-pointer">
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm text-gray-400 mb-1">Date Created</label>
                    <input type="text" name="dateCreated" value="<?php echo date('Y-m-d H:i:s'); ?>" readonly class="input-style p-3 rounded-xl text-gray-500">
                </div>

                <div class="md:col-span-2 flex justify-end">
                    <button type="submit" name="create_user" class="px-6 py-3 bg-red-600 hover:bg-red-700 text-white font-semibold rounded-xl shadow-lg transition flex items-center space-x-2">
                        <i data-lucide="user-plus" class="w-5 h-5"></i>
                        <span>Create User</span>
                    </button>
                </div>
            </form>
        </section>

        <section class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 bg-gray-900 p-6 rounded-2xl shadow-2xl">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <p class="text-sm uppercase tracking-widest text-gray-500">Directory</p>
                        <h2 class="text-2xl font-semibold text-white">View Users</h2>
                    </div>
                    <select onchange="filterUsers(this.value)" class="input-style p-2 rounded-xl w-48">
                        <option value="">-- Select Role --</option>
                        <option value="Admin">Admin</option>
                        <option value="sales">Sales</option>
                        <option value="Cashier">Cashier</option>
                    </select>
                </div>
                <div id="userList" class="overflow-x-auto text-sm text-gray-300 bg-gray-800/40 rounded-xl p-4"></div>
            </div>

            <div class="bg-gray-900 p-6 rounded-2xl shadow-2xl">
                <p class="text-sm uppercase tracking-widest text-gray-500">Danger Zone</p>
                <h2 class="text-2xl font-semibold text-white mb-4">Delete User</h2>
                <label class="block text-sm text-gray-400 mb-1">Role</label>
                <select id="deleteRoleSelect" class="input-style p-3 rounded-xl mb-4">
                    <option value="">-- Select Role --</option>
                    <option value="Admin">Admin</option>
                    <option value="sales">Sales</option>
                    <option value="Cashier">Cashier</option>
                </select>

                <form id="deleteUserForm" class="space-y-4">
                    <div>
                        <label class="block text-sm text-gray-400 mb-1">Username</label>
                        <select id="deleteUserSelect" name="delete_username" required class="input-style p-3 rounded-xl">
                            <option value="">-- Select Role First --</option>
                        </select>
                    </div>
                    <button type="submit" id="deleteBtn" disabled class="w-full px-4 py-3 bg-red-900/60 border border-red-500 text-red-200 font-semibold rounded-xl transition">Delete User</button>
                </form>
            </div>
        </section>
    </main>
</div>

<script>
// -------------------- Filter Users --------------------
function filterUsers(role) {
    if(role === "") { document.getElementById("userList").innerHTML = ""; return; }
    fetch("<?php echo $_SERVER['PHP_SELF']; ?>?role=" + role)
        .then(r => r.text())
        .then(data => { document.getElementById("userList").innerHTML = data; });
}

// -------------------- Delete User AJAX --------------------
const deleteRoleSelect = document.getElementById('deleteRoleSelect');
const deleteUserSelect = document.getElementById('deleteUserSelect');
const deleteBtn = document.getElementById('deleteBtn');

deleteRoleSelect.addEventListener('change', function() {
    const role = this.value;
    deleteUserSelect.innerHTML = "<option value=''>Loading...</option>";
    deleteBtn.disabled = true;

    if (!role) { deleteUserSelect.innerHTML = "<option value=''>-- Select Role First --</option>"; return; }

    fetch("<?php echo $_SERVER['PHP_SELF']; ?>?for_delete=1&role=" + encodeURIComponent(role))
        .then(r => r.text())
        .then(html => { deleteUserSelect.innerHTML = html; deleteBtn.disabled = true; });
});

deleteUserSelect.addEventListener('change', function() { deleteBtn.disabled = !this.value; });

document.getElementById('deleteUserForm').addEventListener('submit', async function(e){
    e.preventDefault();
    const username = deleteUserSelect.value;
    const role = deleteRoleSelect.value;
    if(!username) return alert('Please select a username first.');
    if(!confirm(`Are you sure you want to delete ${username}?`)) return;

    try {
        const res = await fetch('delete_user.php', {
            method:'POST',
            headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:`delete_username=${encodeURIComponent(username)}`
        });
        const data = await res.json();
        alert(data.status === 'success' ? '✅ '+data.message : '❌ '+data.message);
        deleteRoleSelect.dispatchEvent(new Event('change'));
        if(role) filterUsers(role);
    } catch(e){ alert('⚠️ Something went wrong.'); console.error(e);}
});

// -------------------- Create User AJAX --------------------
const createForm = document.getElementById("createUserForm");
const passwordField = document.getElementById("password");
const confirmField = document.getElementById("confirm_pass");
const confirmError = document.getElementById("confirmError");
const letter = document.getElementById("letter");
const capital = document.getElementById("capital");
const number = document.getElementById("number");
const lengthEl = document.getElementById("length");
const messageBox = document.getElementById("message");

function checkRequirement(el, regexOrBool, val){
    let ok = typeof regexOrBool === 'boolean' ? regexOrBool : regexOrBool.test(val);
    el.className = ok ? 'valid':'invalid';
    el.style.color = ok ? '#00b300':'#ff6666';
}

passwordField.onfocus = ()=> messageBox.style.display='block';
passwordField.onblur = ()=> messageBox.style.display='none';

passwordField.onkeyup = ()=>{
    const val = passwordField.value;
    checkRequirement(letter, /[a-z]/, val);
    checkRequirement(capital, /[A-Z]/, val);
    checkRequirement(number, /\d/, val);
    checkRequirement(lengthEl, val.length>=8, val);
    if(confirmField.value!="") confirmError.style.display=(val===confirmField.value?'none':'block');
}

confirmField.onkeyup = ()=> confirmError.style.display=(passwordField.value===confirmField.value?'none':'block');

createForm.onsubmit = async function(e){
    e.preventDefault();
    const formData = new FormData(createForm);
    if(passwordField.value!==confirmField.value){ confirmError.style.display='block'; alert("Passwords do not match!"); return; }
    formData.append("create_user", "1");

const res = await fetch("<?php echo $_SERVER['PHP_SELF']; ?>", { 
    method:'POST', 
    body: formData
});
    const data = await res.json();
    alert(data.status==='success'?'✅ '+data.message:'❌ '+data.message);
    if(data.status==='success'){ createForm.reset(); messageBox.style.display='none'; }
    // Refresh user list if role selected
    const role = formData.get('role');
    if(role) filterUsers(role);
};

lucide.createIcons();
</script>
</body>
</html>
