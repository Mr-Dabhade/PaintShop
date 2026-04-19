<?php
// backend/controllers/AccountController.php

require_once '../config/Database.php';
require_once '../core/Response.php';

class AccountController {
    private $conn;
    private $table = 'khate_master';

    public function __construct() {
        $db = new Database();
        $this->conn = $db->connect();
    }

    public function getAll() {
        $query = "SELECT * FROM " . $this->table . " ORDER BY id DESC";
        $result = $this->conn->query($query);
        
        $accounts = [];
        while ($row = $result->fetch_assoc()) {
            $accounts[] = $row;
        }

        Response::success(['accounts' => $accounts], 'Accounts retrieved successfully');
    }

    public function create() {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['account_code']) || !isset($data['account_name']) || !isset($data['account_type'])) {
            Response::error('Account code, name, and type are required', 400);
        }

        $account_code = $data['account_code'];
        $account_name = $data['account_name'];
        $account_type = $data['account_type'];
        $opening_balance = $data['opening_balance'] ?? 0;

        $query = "INSERT INTO " . $this->table . " (account_code, account_name, account_type, opening_balance) VALUES (?, ?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('sssd', $account_code, $account_name, $account_type, $opening_balance);

        if ($stmt->execute()) {
            Response::success(['id' => $this->conn->insert_id], 'Account created successfully', 201);
        } else {
            Response::error('Failed to create account: ' . $stmt->error, 400);
        }
    }

    public function getBalance($id) {
        // Calculate balance from debit and credit entries
        $debitQuery = "SELECT COALESCE(SUM(amount), 0) as total_debit FROM debit_entries WHERE khate_id = ?";
        $debitStmt = $this->conn->prepare($debitQuery);
        $debitStmt->bind_param('i', $id);
        $debitStmt->execute();
        $debitResult = $debitStmt->get_result();
        $debitData = $debitResult->fetch_assoc();

        $creditQuery = "SELECT COALESCE(SUM(amount), 0) as total_credit FROM credit_entries WHERE khate_id = ?";
        $creditStmt = $this->conn->prepare($creditQuery);
        $creditStmt->bind_param('i', $id);
        $creditStmt->execute();
        $creditResult = $creditStmt->get_result();
        $creditData = $creditResult->fetch_assoc();

        $accountQuery = "SELECT opening_balance FROM " . $this->table . " WHERE id = ?";
        $accountStmt = $this->conn->prepare($accountQuery);
        $accountStmt->bind_param('i', $id);
        $accountStmt->execute();
        $accountResult = $accountStmt->get_result();
        $accountData = $accountResult->fetch_assoc();

        $current_balance = $accountData['opening_balance'] + $creditData['total_credit'] - $debitData['total_debit'];

        Response::success([
            'opening_balance' => $accountData['opening_balance'],
            'total_credit' => $creditData['total_credit'],
            'total_debit' => $debitData['total_debit'],
            'current_balance' => $current_balance
        ], 'Account balance retrieved');
    }
}

$controller = new AccountController();
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? null;

switch ($action) {
    case 'getAll':
        $controller->getAll();
        break;
    case 'create':
        $controller->create();
        break;
    case 'getBalance':
        if (!$id) Response::error('ID is required', 400);
        $controller->getBalance($id);
        break;
    default:
        Response::error('Invalid action', 400);
}
?>