<?php
// backend/controllers/DashboardController.php

require_once '../config/Database.php';
require_once '../core/Response.php';

class DashboardController {
    private $conn;

    public function __construct() {
        $db = new Database();
        $this->conn = $db->connect();
        
        if (!$this->conn) {
            Response::error('Database connection failed', 500);
        }
    }

    public function getData() {
        try {
            // Get total sales
            $salesQuery = "SELECT COALESCE(SUM(total_amount), 0) as total FROM outwardmaster";
            $salesResult = $this->conn->query($salesQuery);
            
            if (!$salesResult) {
                throw new Exception('Sales query failed: ' . $this->conn->error);
            }
            
            $salesData = $salesResult->fetch_assoc();
            $totalSales = floatval($salesData['total']);

            // Get total purchase
            $purchaseQuery = "SELECT COALESCE(SUM(total_amount), 0) as total FROM inwardmaster";
            $purchaseResult = $this->conn->query($purchaseQuery);
            
            if (!$purchaseResult) {
                throw new Exception('Purchase query failed: ' . $this->conn->error);
            }
            
            $purchaseData = $purchaseResult->fetch_assoc();
            $totalPurchase = floatval($purchaseData['total']);

            // Get low stock items
            $lowStockQuery = "SELECT COUNT(*) as count FROM products WHERE quantity < min_stock";
            $lowStockResult = $this->conn->query($lowStockQuery);
            
            if (!$lowStockResult) {
                throw new Exception('Low stock query failed: ' . $this->conn->error);
            }
            
            $lowStockData = $lowStockResult->fetch_assoc();
            $lowStockItems = intval($lowStockData['count']);

            // Get pending payments
            $pendingQuery = "SELECT COALESCE(SUM(pending_balance), 0) as total FROM customers WHERE pending_balance > 0";
            $pendingResult = $this->conn->query($pendingQuery);
            
            if (!$pendingResult) {
                throw new Exception('Pending query failed: ' . $this->conn->error);
            }
            
            $pendingData = $pendingResult->fetch_assoc();
            $pendingPayments = floatval($pendingData['total']);

            // Get total customers
            $customersQuery = "SELECT COUNT(*) as count FROM customers";
            $customersResult = $this->conn->query($customersQuery);
            
            if (!$customersResult) {
                throw new Exception('Customers query failed: ' . $this->conn->error);
            }
            
            $customersData = $customersResult->fetch_assoc();
            $totalCustomers = intval($customersData['count']);

            // Get total products
            $productsQuery = "SELECT COUNT(*) as count FROM products";
            $productsResult = $this->conn->query($productsQuery);
            
            if (!$productsResult) {
                throw new Exception('Products query failed: ' . $this->conn->error);
            }
            
            $productsData = $productsResult->fetch_assoc();
            $totalProducts = intval($productsData['count']);

            // Get total employees
            $employeesQuery = "SELECT COUNT(*) as count FROM employees";
            $employeesResult = $this->conn->query($employeesQuery);
            
            if (!$employeesResult) {
                throw new Exception('Employees query failed: ' . $this->conn->error);
            }
            
            $employeesData = $employeesResult->fetch_assoc();
            $totalEmployees = intval($employeesData['count']);

            // Get low stock product details
            $lowStockProductsQuery = "SELECT id, name, quantity, min_stock FROM products WHERE quantity < min_stock LIMIT 10";
            $lowStockProductsResult = $this->conn->query($lowStockProductsQuery);
            
            $lowStockProducts = [];
            if ($lowStockProductsResult) {
                while ($row = $lowStockProductsResult->fetch_assoc()) {
                    $lowStockProducts[] = $row;
                }
            }

            // Get recent transactions
            $recentQuery = "
                (SELECT 'Sale' as type, bill_no as reference, total_amount as amount, bill_date as date FROM outwardmaster ORDER BY id DESC LIMIT 5)
                UNION ALL
                (SELECT 'Purchase' as type, bill_no as reference, total_amount as amount, bill_date as date FROM inwardmaster ORDER BY id DESC LIMIT 5)
                ORDER BY date DESC
                LIMIT 10
            ";
            $recentResult = $this->conn->query($recentQuery);
            $recentTransactions = [];
            
            if ($recentResult) {
                while ($row = $recentResult->fetch_assoc()) {
                    $recentTransactions[] = $row;
                }
            }

            $dashboardData = [
                'totalSales' => $totalSales,
                'totalPurchase' => $totalPurchase,
                'lowStockItems' => $lowStockItems,
                'pendingPayments' => $pendingPayments,
                'totalCustomers' => $totalCustomers,
                'totalProducts' => $totalProducts,
                'totalEmployees' => $totalEmployees,
                'lowStockProducts' => $lowStockProducts,
                'recentTransactions' => $recentTransactions
            ];

            Response::success($dashboardData, 'Dashboard data retrieved successfully');
        } catch (Exception $e) {
            Response::error('Error fetching dashboard data: ' . $e->getMessage(), 400);
        }
    }

    public function getStats() {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $startDate = $data['startDate'] ?? date('Y-m-01');
            $endDate = $data['endDate'] ?? date('Y-m-d');

            // Sales this month
            $monthlySalesQuery = "SELECT COALESCE(SUM(total_amount), 0) as total FROM outwardmaster WHERE bill_date BETWEEN ? AND ?";
            $stmt = $this->conn->prepare($monthlySalesQuery);
            $stmt->bind_param('ss', $startDate, $endDate);
            $stmt->execute();
            $salesResult = $stmt->get_result();
            $salesData = $salesResult->fetch_assoc();

            // Purchase this month
            $monthlyPurchaseQuery = "SELECT COALESCE(SUM(total_amount), 0) as total FROM inwardmaster WHERE bill_date BETWEEN ? AND ?";
            $stmt = $this->conn->prepare($monthlyPurchaseQuery);
            $stmt->bind_param('ss', $startDate, $endDate);
            $stmt->execute();
            $purchaseResult = $stmt->get_result();
            $purchaseData = $purchaseResult->fetch_assoc();

            $stats = [
                'period' => [
                    'start' => $startDate,
                    'end' => $endDate
                ],
                'monthlySales' => floatval($salesData['total']),
                'monthlyPurchase' => floatval($purchaseData['total']),
                'profit' => floatval($salesData['total']) - floatval($purchaseData['total'])
            ];

            Response::success($stats, 'Stats retrieved successfully');
        } catch (Exception $e) {
            Response::error('Error fetching stats: ' . $e->getMessage(), 400);
        }
    }
}

?>