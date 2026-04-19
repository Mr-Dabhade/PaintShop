<?php
// backend/controllers/DebitCreditController.php

require_once '../config/Database.php';
require_once '../core/Response.php';

class DebitCreditController {
    private $conn;
    private $debitTable = 'debit_entries';
    private $creditTable = 'credit_entries';

    public function __construct() {
        $db = new Database();
        $this->conn = $db->connect();
    }

    public function addDebit() {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['khate_id']) || !isset($data['amount'])) {
            Response::error('Khate ID and amount are required', 400);
        }

        $khate_id = $data['khate_id'];
        $description = $data['description'] ?? 'Expense';
        $amount = $data['amount'];
        $entry_date = $data['entry_date'] ?? date('Y-m-d');

        $query = "INSERT INTO " . $this->debitTable . " (khate_id, description, amount, entry_date) VALUES (?, ?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('isds', $khate_id, $description, $amount, $entry_date);

        if ($stmt->execute()) {
            Response::success(['id' => $this->conn->insert_id], 'Debit entry added successfully', 201);
        } else {
            Response::error('Failed to add debit entry: ' . $stmt->error, 400);
        }
    }

    public function addCredit() {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['khate_id']) || !isset($data['amount'])) {
            Response::error('Khate ID and amount are required', 400);
        }

        $khate_id = $data['khate_id'];
        $description = $data['description'] ?? 'Income';
        $amount = $data['amount'];
        $entry_date = $data['entry_date'] ?? date('Y-m-d');

        $query = "INSERT INTO " . $this->creditTable . " (khate_id, description, amount, entry_date) VALUES (?, ?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('isds', $khate_id, $description, $amount, $entry_date);

        if ($stmt->execute()) {
            Response::success(['id' => $this->conn->insert_id], 'Credit entry added successfully', 201);
        } else {
            Response::error('Failed to add credit entry: ' . $stmt->error, 400);
        }
    }

    public function getDebits() {
        $data = json_decode(file_get_contents('php://input'), true);
        $start_date = $data['start_date'] ?? date('Y-m-01');
        $end_date = $data['end_date'] ?? date('Y-m-d');

        $query = "SELECT d.*, k.account_name FROM " . $this->debitTable . " d
                  LEFT JOIN khate_master k ON d.khate_id = k.id
                  WHERE d.entry_date BETWEEN ? AND ?
                  ORDER BY d.entry_date DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('ss', $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();

        $debits = [];
        $totalAmount = 0;
        while ($row = $result->fetch_assoc()) {
            $debits[] = $row;
            $totalAmount += $row['amount'];
        }

        Response::success([
            'debits' => $debits,
            'total' => $totalAmount
        ], 'Debit entries retrieved');
    }

    public function getCredits() {
        $data = json_decode(file_get_contents('php://input'), true);
        $start_date = $data['start_date'] ?? date('Y-m-01');
        $end_date = $data['end_date'] ?? date('Y-m-d');

        $query = "SELECT c.*, k.account_name FROM " . $this->creditTable . " c
                  LEFT JOIN khate_master k ON c.khate_id = k.id
                  WHERE c.entry_date BETWEEN ? AND ?
                  ORDER BY c.entry_date DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('ss', $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();

        $credits = [];
        $totalAmount = 0;
        while ($row = $result->fetch_assoc()) {
            $credits[] = $row;
            $totalAmount += $row['amount'];
        }

        Response::success([
            'credits' => $credits,
            'total' => $totalAmount
        ], 'Credit entries retrieved');
    }
}

$controller = new DebitCreditController();
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'addDebit':
        $controller->addDebit();
        break;
    case 'addCredit':
        $controller->addCredit();
        break;
    case 'getDebits':
        $controller->getDebits();
        break;
    case 'getCredits':
        $controller->getCredits();
        break;
    default:
        Response::error('Invalid action', 400);
}
?>