<?php
session_start();

// ✅ Protect page: Only Cashier, Sales, or Admin
if (!isset($_SESSION['username']) || 
    ($_SESSION['role'] !== "Cashier" && $_SESSION['role'] !== "sales" && $_SESSION['role'] !== "Admin")) {
    header("Location: login.php");
    exit();
}

include "db.php";
require('fpdf/fpdf.php');

// --- 1. Check for Sale ID ---
if (!isset($_GET['saleID']) || empty($_GET['saleID'])) {
    die("Error: Sale ID not specified.");
}

$saleID = intval($_GET['saleID']);

// --- 2. Retrieve Sale Header Data ---
$sql_header = "
SELECT 
    s.saleID, 
    s.customerName, 
    s.totalAmount, 
    s.saleDate,
    uc.username AS cashierName
FROM sales s
LEFT JOIN usermanagement uc ON s.cashierID = uc.userID
WHERE s.saleID = ?
";

$stmt_header = $conn->prepare($sql_header);
$stmt_header->bind_param("i", $saleID);
$stmt_header->execute();
$result_header = $stmt_header->get_result();

if ($result_header->num_rows === 0) {
    die("Error: Sale record not found.");
}
$sale = $result_header->fetch_assoc();
$stmt_header->close();

// --- 3. Retrieve Sale Items Data (snapshot columns) ---
$sql_items = "
SELECT 
    quantity, 
    unitPrice, 
    lineTotal, 
    productName, 
    category
FROM sale_items
WHERE saleID = ?
";

$stmt_items = $conn->prepare($sql_items);
$stmt_items->bind_param("i", $saleID);
$stmt_items->execute();
$items_result = $stmt_items->get_result();

// --- 4. Generate PDF Receipt ---
$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetTitle('Receipt #' . $saleID);

// --- Header ---
$pdf->SetFont('Arial', 'B', 18);
$pdf->Cell(0, 10, '1-GARAGE Official Receipt', 0, 1, 'C');
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 6, 'Rizal Technology, Mandaluyong City', 0, 1, 'C');
$pdf->Cell(0, 6, 'Contact: 0912-345-6789 | Email: support@1garage.com ', 0, 1, 'C');
$pdf->Ln(10);

// --- Sale Details ---
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(50, 8, 'Sale ID:', 0, 0);
$pdf->Cell(0, 8, $sale['saleID'], 0, 1);
$pdf->Cell(50, 8, 'Customer:', 0, 0);
$pdf->Cell(0, 8, utf8_decode($sale['customerName']), 0, 1);
$pdf->Cell(50, 8, 'Cashier:', 0, 0);
$pdf->Cell(0, 8, utf8_decode($sale['cashierName']), 0, 1);
$pdf->Cell(50, 8, 'Payment Type:', 0, 0);
$pdf->Cell(0, 8, 'Cash', 0, 1);
$pdf->Cell(50, 8, 'Date:', 0, 0);
$pdf->Cell(0, 8, date('F j, Y g:i A', strtotime($sale['saleDate'])), 0, 1);
$pdf->Ln(5);

// --- Items Table Header ---
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetFillColor(230, 230, 230);
$pdf->Cell(50, 8, 'Item Name', 1, 0, 'L', true);
$pdf->Cell(45, 8, 'Category', 1, 0, 'L', true);
$pdf->Cell(30, 8, 'Unit Price', 1, 0, 'C', true);
$pdf->Cell(20, 8, 'Qty', 1, 0, 'C', true);
$pdf->Cell(40, 8, 'Subtotal', 1, 1, 'R', true);

// --- Items Table Rows ---
$pdf->SetFont('Arial', '', 11);
while ($item = $items_result->fetch_assoc()) {
    $pdf->Cell(50, 7, utf8_decode($item['productName']), 1, 0, 'L');
    $pdf->Cell(45, 7, utf8_decode($item['category']), 1, 0, 'L');
    $pdf->Cell(30, 7, number_format($item['unitPrice'], 2), 1, 0, 'R');
    $pdf->Cell(20, 7, $item['quantity'], 1, 0, 'C');
    $pdf->Cell(40, 7, number_format($item['lineTotal'], 2), 1, 1, 'R');
}
$stmt_items->close();

// --- Total Footer ---
$pdf->SetFont('Arial', 'B', 13);
$pdf->Cell(145, 10, 'TOTAL AMOUNT:', 1, 0, 'R', true);
$pdf->Cell(40, 10, 'P' . number_format($sale['totalAmount'], 2), 1, 1, 'R', true);
$pdf->Ln(10);

// --- Footer ---
$pdf->SetFont('Arial', 'I', 10);
$pdf->Cell(0, 10, 'Thank you for shopping at 1-GARAGE!', 0, 1, 'C');


// --- Output PDF ---
$pdf->Output('I', 'receipt_' . $saleID . '.pdf');
exit();
?>
