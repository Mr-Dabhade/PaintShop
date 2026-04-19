<?php
// backend/controllers/ReportController.php - CORRECTED

require_once '../config/Database.php';
require_once '../core/Response.php';
require_once '../lib/fpdf/fpdf.php';

class ReportController {
    private $conn;

    public function __construct() {
        $db = new Database();
        $this->conn = $db->connect();
    }

    // ============================================
    // INWARD BILL REPORT
    // ============================================
    public function inwardBillReport() {
        $data = json_decode(file_get_contents('php://input'), true);
        $inward_id = $data['inward_id'] ?? null;

        if (!$inward_id) {
            Response::error('Inward ID is required', 400);
        }

        try {
            $query = "SELECT im.*, c.name as company_name, c.phone, c.address FROM inwardmaster im 
                      LEFT JOIN companies c ON im.company_id = c.id 
                      WHERE im.id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param('i', $inward_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                Response::error('Inward record not found', 404);
            }

            $inward = $result->fetch_assoc();

            $childQuery = "SELECT ic.*, p.name, p.brand FROM inwardchild ic 
                          LEFT JOIN products p ON ic.product_id = p.id 
                          WHERE ic.inward_id = ?";
            $childStmt = $this->conn->prepare($childQuery);
            $childStmt->bind_param('i', $inward_id);
            $childStmt->execute();
            $childResult = $childStmt->get_result();

            $pdf = new FPDF('L', 'mm', 'A4');
            $pdf->AddPage();
            $pdf->SetFont('Arial', 'B', 16);
            $pdf->Cell(0, 10, 'PURCHASE INWARD BILL', 0, 1, 'C');
            
            $pdf->SetFont('Arial', '', 10);
            $pdf->Cell(0, 5, 'Bill No: ' . $inward['bill_no'] . ' | Date: ' . $inward['bill_date'], 0, 1);
            $pdf->Cell(0, 5, 'Supplier: ' . $inward['company_name'], 0, 1);
            $pdf->Cell(0, 5, 'Phone: ' . $inward['phone'] . ' | Address: ' . $inward['address'], 0, 1);
            $pdf->Ln(5);

            // Table Header
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->SetFillColor(200, 200, 200);
            $pdf->Cell(50, 7, 'Product', 1, 0, 'C', true);
            $pdf->Cell(30, 7, 'Brand', 1, 0, 'C', true);
            $pdf->Cell(20, 7, 'Qty', 1, 0, 'C', true);
            $pdf->Cell(20, 7, 'MRP', 1, 0, 'C', true);
            $pdf->Cell(25, 7, 'Discount', 1, 0, 'C', true);
            $pdf->Cell(20, 7, 'GST', 1, 0, 'C', true);
            $pdf->Cell(30, 7, 'Total', 1, 1, 'C', true);

            // Table Body
            $pdf->SetFont('Arial', '', 8);
            $pdf->SetFillColor(255, 255, 255);
            
            while ($item = $childResult->fetch_assoc()) {
                $pdf->Cell(50, 6, substr($item['name'], 0, 15), 1, 0, 'L');
                $pdf->Cell(30, 6, substr($item['brand'], 0, 10), 1, 0, 'L');
                $pdf->Cell(20, 6, $item['quantity'], 1, 0, 'C');
                $pdf->Cell(20, 6, '₹' . number_format($item['mrp'], 2), 1, 0, 'R');
                $pdf->Cell(25, 6, '₹' . number_format($item['discount'], 2), 1, 0, 'R');
                $pdf->Cell(20, 6, '₹' . number_format($item['gst_amount'], 2), 1, 0, 'R');
                $pdf->Cell(30, 6, '₹' . number_format($item['total'], 2), 1, 1, 'R');
            }

            // Totals
            $pdf->Ln(5);
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(150, 6, 'Total GST:', 0, 0, 'R');
            $pdf->Cell(0, 6, '₹' . number_format($inward['total_gst'], 2), 0, 1, 'R');
            $pdf->Cell(150, 6, 'Total Amount:', 0, 0, 'R');
            $pdf->Cell(0, 6, '₹' . number_format($inward['total_amount'], 2), 0, 1, 'R');

            $pdf->Output('D', 'Inward_Bill_' . $inward['bill_no'] . '_' . date('Ymd') . '.pdf');
            exit;

        } catch (Exception $e) {
            Response::error('Error generating report: ' . $e->getMessage(), 400);
        }
    }

    // ============================================
    // STOCK REPORT
    // ============================================
    public function stockReport() {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $report_date = $data['report_date'] ?? date('Y-m-d');

            $query = "SELECT id, barcode, name, brand, quantity, min_stock, sale_rate FROM products ORDER BY name";
            $result = $this->conn->query($query);

            if (!$result) {
                Response::error('Query failed: ' . $this->conn->error, 400);
            }

            $pdf = new FPDF('P', 'mm', 'A4');
            $pdf->AddPage();
            $pdf->SetFont('Arial', 'B', 14);
            $pdf->Cell(0, 10, 'STOCK REPORT', 0, 1, 'C');
            
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(0, 5, 'Report Date: ' . $report_date, 0, 1);
            $pdf->Cell(0, 5, 'Generated on: ' . date('d-m-Y H:i:s'), 0, 1);
            $pdf->Ln(5);

            // Table Header
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->SetFillColor(200, 200, 200);
            $pdf->Cell(25, 7, 'Barcode', 1, 0, 'C', true);
            $pdf->Cell(60, 7, 'Product', 1, 0, 'C', true);
            $pdf->Cell(40, 7, 'Brand', 1, 0, 'C', true);
            $pdf->Cell(20, 7, 'Qty', 1, 0, 'C', true);
            $pdf->Cell(15, 7, 'Min', 1, 0, 'C', true);
            $pdf->Cell(20, 7, 'Status', 1, 1, 'C', true);

            // Table Body
            $pdf->SetFont('Arial', '', 8);
            $totalProducts = 0;
            $lowStockCount = 0;

            while ($product = $result->fetch_assoc()) {
                $status = ($product['quantity'] < $product['min_stock']) ? 'LOW' : 'OK';
                if ($product['quantity'] < $product['min_stock']) {
                    $lowStockCount++;
                }
                $totalProducts++;

                $pdf->Cell(25, 6, substr($product['barcode'] ?? 'N/A', 0, 12), 1, 0, 'C');
                $pdf->Cell(60, 6, substr($product['name'], 0, 25), 1, 0, 'L');
                $pdf->Cell(40, 6, substr($product['brand'], 0, 15), 1, 0, 'L');
                $pdf->Cell(20, 6, $product['quantity'], 1, 0, 'C');
                $pdf->Cell(15, 6, $product['min_stock'], 1, 0, 'C');
                $pdf->Cell(20, 6, $status, 1, 1, 'C');
            }

            // Summary
            $pdf->Ln(10);
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(100, 6, 'Total Products: ' . $totalProducts, 0, 1);
            $pdf->Cell(100, 6, 'Low Stock Items: ' . $lowStockCount, 0, 1);

            $pdf->Output('D', 'Stock_Report_' . date('Ymd') . '.pdf');
            exit;

        } catch (Exception $e) {
            Response::error('Error generating report: ' . $e->getMessage(), 400);
        }
    }

    // ============================================
    // SALARY SLIP
    // ============================================
    public function salarySilp() {
        $data = json_decode(file_get_contents('php://input'), true);
        $salary_id = $data['salary_id'] ?? null;

        if (!$salary_id) {
            Response::error('Salary ID is required', 400);
        }

        try {
            $query = "SELECT s.*, e.name, e.phone, e.address FROM salaries s 
                      LEFT JOIN employees e ON s.employee_id = e.id 
                      WHERE s.id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param('i', $salary_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                Response::error('Salary record not found', 404);
            }

            $salary = $result->fetch_assoc();

            $pdf = new FPDF('P', 'mm', 'A4');
            $pdf->AddPage();
            $pdf->SetFont('Arial', 'B', 14);
            $pdf->Cell(0, 10, 'SALARY SLIP', 0, 1, 'C');
            
            $pdf->SetFont('Arial', '', 10);
            $pdf->Cell(0, 5, 'Employee: ' . $salary['name'], 0, 1);
            $pdf->Cell(0, 5, 'Month: ' . date('F Y', strtotime($salary['month_year'])), 0, 1);
            $pdf->Cell(0, 5, 'Phone: ' . $salary['phone'], 0, 1);
            $pdf->Ln(5);

            // Earnings Section
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(0, 6, 'EARNINGS', 0, 1);
            $pdf->SetFont('Arial', '', 9);
            
            $pdf->Cell(100, 5, 'Basic Salary', 0, 0);
            $pdf->Cell(0, 5, '₹' . number_format($salary['basic_salary'], 2), 0, 1, 'R');
            
            $pdf->Cell(100, 5, 'Dearness Allowance (DA)', 0, 0);
            $pdf->Cell(0, 5, '₹' . number_format($salary['da'], 2), 0, 1, 'R');
            
            $pdf->Cell(100, 5, 'Other Allowances', 0, 0);
            $pdf->Cell(0, 5, '₹' . number_format($salary['allowances'], 2), 0, 1, 'R');

            $gross = $salary['basic_salary'] + $salary['da'] + $salary['allowances'];
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(100, 5, 'Gross Salary', 0, 0);
            $pdf->Cell(0, 5, '₹' . number_format($gross, 2), 0, 1, 'R');

            // Deductions Section
            $pdf->Ln(5);
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(0, 6, 'DEDUCTIONS', 0, 1);
            $pdf->SetFont('Arial', '', 9);
            
            $pdf->Cell(100, 5, 'Advance', 0, 0);
            $pdf->Cell(0, 5, '₹' . number_format($salary['advance'], 2), 0, 1, 'R');
            
            $pdf->Cell(100, 5, 'Other Deductions', 0, 0);
            $pdf->Cell(0, 5, '₹' . number_format($salary['deductions'], 2), 0, 1, 'R');

            // Net Salary
            $pdf->Ln(5);
            $pdf->SetFont('Arial', 'B', 11);
            $pdf->Cell(100, 7, 'NET SALARY', 0, 0);
            $pdf->Cell(0, 7, '₹' . number_format($salary['net_salary'], 2), 0, 1, 'R');

            $pdf->Output('D', 'Salary_Slip_' . $salary['employee_id'] . '_' . date('Ymd', strtotime($salary['month_year'])) . '.pdf');
            exit;

        } catch (Exception $e) {
            Response::error('Error generating report: ' . $e->getMessage(), 400);
        }
    }

    // ============================================
    // TRIAL BALANCE
    // ============================================
    public function trialBalance() {
        try {
            $query = "SELECT id, account_code, account_name, account_type FROM khate_master ORDER BY account_type, id";
            $result = $this->conn->query($query);

            if (!$result) {
                Response::error('Query failed: ' . $this->conn->error, 400);
            }

            $pdf = new FPDF('P', 'mm', 'A4');
            $pdf->AddPage();
            $pdf->SetFont('Arial', 'B', 14);
            $pdf->Cell(0, 10, 'TRIAL BALANCE (TERIJ PATRAK)', 0, 1, 'C');
            
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(0, 5, 'Generated on: ' . date('d-m-Y H:i:s'), 0, 1);
            $pdf->Ln(5);

            // Table Header
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->SetFillColor(200, 200, 200);
            $pdf->Cell(30, 7, 'Code', 1, 0, 'C', true);
            $pdf->Cell(80, 7, 'Account Name', 1, 0, 'C', true);
            $pdf->Cell(50, 7, 'Debit (₹)', 1, 0, 'C', true);
            $pdf->Cell(50, 7, 'Credit (₹)', 1, 1, 'C', true);

            // Table Body
            $pdf->SetFont('Arial', '', 8);
            $totalDebit = 0;
            $totalCredit = 0;

            while ($account = $result->fetch_assoc()) {
                $debitQuery = "SELECT COALESCE(SUM(amount), 0) as total FROM debit_entries WHERE khate_id = ?";
                $debitStmt = $this->conn->prepare($debitQuery);
                $debitStmt->bind_param('i', $account['id']);
                $debitStmt->execute();
                $debitData = $debitStmt->get_result()->fetch_assoc();
                $debitAmount = floatval($debitData['total']);

                $creditQuery = "SELECT COALESCE(SUM(amount), 0) as total FROM credit_entries WHERE khate_id = ?";
                $creditStmt = $this->conn->prepare($creditQuery);
                $creditStmt->bind_param('i', $account['id']);
                $creditStmt->execute();
                $creditData = $creditStmt->get_result()->fetch_assoc();
                $creditAmount = floatval($creditData['total']);

                if ($debitAmount > 0 || $creditAmount > 0) {
                    $pdf->Cell(30, 6, $account['account_code'], 1, 0, 'C');
                    $pdf->Cell(80, 6, substr($account['account_name'], 0, 30), 1, 0, 'L');
                    $pdf->Cell(50, 6, $debitAmount > 0 ? number_format($debitAmount, 2) : '-', 1, 0, 'R');
                    $pdf->Cell(50, 6, $creditAmount > 0 ? number_format($creditAmount, 2) : '-', 1, 1, 'R');

                    $totalDebit += $debitAmount;
                    $totalCredit += $creditAmount;
                }
            }

            // Totals
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->SetFillColor(220, 220, 220);
            $pdf->Cell(110, 6, 'TOTALS', 1, 0, 'R', true);
            $pdf->Cell(50, 6, number_format($totalDebit, 2), 1, 0, 'R', true);
            $pdf->Cell(50, 6, number_format($totalCredit, 2), 1, 1, 'R', true);

            $pdf->Output('D', 'Trial_Balance_' . date('Ymd') . '.pdf');
            exit;

        } catch (Exception $e) {
            Response::error('Error generating report: ' . $e->getMessage(), 400);
        }
    }

    // ============================================
    // PROFIT & LOSS STATEMENT
    // ============================================
    public function profitAndLoss() {
        try {
            // Income
            $incomeQuery = "SELECT COALESCE(SUM(amount), 0) as total FROM credit_entries";
            $incomeResult = $this->conn->query($incomeQuery);
            $incomeData = $incomeResult->fetch_assoc();
            $totalIncome = floatval($incomeData['total']);

            // Expenses
            $expenseQuery = "SELECT COALESCE(SUM(amount), 0) as total FROM debit_entries";
            $expenseResult = $this->conn->query($expenseQuery);
            $expenseData = $expenseResult->fetch_assoc();
            $totalExpense = floatval($expenseData['total']);

            $profit = $totalIncome - $totalExpense;

            $pdf = new FPDF('P', 'mm', 'A4');
            $pdf->AddPage();
            $pdf->SetFont('Arial', 'B', 14);
            $pdf->Cell(0, 10, 'PROFIT & LOSS STATEMENT', 0, 1, 'C');
            
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(0, 5, 'Generated on: ' . date('d-m-Y H:i:s'), 0, 1);
            $pdf->Ln(10);

            // Income Section
            $pdf->SetFont('Arial', 'B', 11);
            $pdf->Cell(0, 7, 'INCOME', 0, 1);
            $pdf->SetFont('Arial', '', 10);
            $pdf->Cell(100, 6, 'Total Income', 0, 0);
            $pdf->Cell(0, 6, '₹' . number_format($totalIncome, 2), 0, 1, 'R');

            // Expense Section
            $pdf->Ln(10);
            $pdf->SetFont('Arial', 'B', 11);
            $pdf->Cell(0, 7, 'EXPENSES', 0, 1);
            $pdf->SetFont('Arial', '', 10);
            $pdf->Cell(100, 6, 'Total Expenses', 0, 0);
            $pdf->Cell(0, 6, '₹' . number_format($totalExpense, 2), 0, 1, 'R');

            // Profit/Loss
            $pdf->Ln(10);
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->Cell(100, 8, $profit >= 0 ? 'NET PROFIT' : 'NET LOSS', 0, 0);
            $pdf->Cell(0, 8, '₹' . number_format(abs($profit), 2), 0, 1, 'R');

            $pdf->Output('D', 'Profit_Loss_' . date('Ymd') . '.pdf');
            exit;

        } catch (Exception $e) {
            Response::error('Error generating report: ' . $e->getMessage(), 400);
        }
    }

    // ============================================
    // BALANCE SHEET
    // ============================================
    public function balanceSheet() {
        try {
            $query = "SELECT id, account_code, account_name, account_type FROM khate_master ORDER BY account_type, id";
            $result = $this->conn->query($query);

            if (!$result) {
                Response::error('Query failed: ' . $this->conn->error, 400);
            }

            $pdf = new FPDF('P', 'mm', 'A4');
            $pdf->AddPage();
            $pdf->SetFont('Arial', 'B', 14);
            $pdf->Cell(0, 10, 'BALANCE SHEET', 0, 1, 'C');
            
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(0, 5, 'As on: ' . date('d-m-Y'), 0, 1);
            $pdf->Ln(5);

            // Table Header
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->SetFillColor(200, 200, 200);
            $pdf->Cell(30, 7, 'Code', 1, 0, 'C', true);
            $pdf->Cell(80, 7, 'Account Name', 1, 0, 'C', true);
            $pdf->Cell(100, 7, 'Balance (₹)', 1, 1, 'C', true);

            // Table Body
            $pdf->SetFont('Arial', '', 8);
            $totalBalance = 0;

            while ($account = $result->fetch_assoc()) {
                $debitQuery = "SELECT COALESCE(SUM(amount), 0) as total FROM debit_entries WHERE khate_id = ?";
                $debitStmt = $this->conn->prepare($debitQuery);
                $debitStmt->bind_param('i', $account['id']);
                $debitStmt->execute();
                $debitData = $debitStmt->get_result()->fetch_assoc();

                $creditQuery = "SELECT COALESCE(SUM(amount), 0) as total FROM credit_entries WHERE khate_id = ?";
                $creditStmt = $this->conn->prepare($creditQuery);
                $creditStmt->bind_param('i', $account['id']);
                $creditStmt->execute();
                $creditData = $creditStmt->get_result()->fetch_assoc();

                $balance = floatval($creditData['total']) - floatval($debitData['total']);

                if ($balance != 0) {
                    $pdf->Cell(30, 6, $account['account_code'], 1, 0, 'C');
                    $pdf->Cell(80, 6, substr($account['account_name'], 0, 30), 1, 0, 'L');
                    $pdf->Cell(100, 6, number_format($balance, 2), 1, 1, 'R');

                    $totalBalance += $balance;
                }
            }

            // Total
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->SetFillColor(220, 220, 220);
            $pdf->Cell(110, 6, 'TOTAL BALANCE', 1, 0, 'R', true);
            $pdf->Cell(100, 6, number_format($totalBalance, 2), 1, 1, 'R', true);

            $pdf->Output('D', 'Balance_Sheet_' . date('Ymd') . '.pdf');
            exit;

        } catch (Exception $e) {
            Response::error('Error generating report: ' . $e->getMessage(), 400);
        }
    }
}

// NOTE: Controller is instantiated in index.php router - do NOT instantiate here

?>