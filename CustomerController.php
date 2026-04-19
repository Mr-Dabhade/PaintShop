<?php
// backend/controllers/CustomerController.php

require_once '../config/Database.php';
require_once '../core/Response.php';

class CustomerController {
    private $conn;
    private $table = 'customers';

    public function __construct() {
        $db = new Database();
        $this->conn = $db->connect();
    }

    public function getAll() {
        $query = "SELECT * FROM " . $this->table . " ORDER BY id DESC";
        $result = $this->conn->query($query);
        
        if (!$result) {
            Response::error('Query failed: ' . $this->conn->error, 400);
        }

        $customers = [];
        while ($row = $result->fetch_assoc()) {
            $customers[] = $row;
        }

        Response::success(['customers' => $customers], 'Customers retrieved successfully');
    }

    public function create() {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['name']) || !isset($data['phone'])) {
            Response::error('Name and phone are required', 400);
        }

        $name = $data['name'];
        $phone = $data['phone'];
        $address = $data['address'] ?? '';
        $opening_balance = $data['opening_balance'] ?? 0;

        $query = "INSERT INTO " . $this->table . " (name, phone, address, opening_balance, pending_balance) VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        
        if (!$stmt) {
            Response::error('Prepare failed: ' . $this->conn->error, 400);
        }

        $stmt->bind_param('sssdd', $name, $phone, $address, $opening_balance, $opening_balance);

        if ($stmt->execute()) {
            Response::success(['id' => $this->conn->insert_id], 'Customer created successfully', 201);
        } else {
            Response::error('Failed to create customer: ' . $stmt->error, 400);
        }
    }

    public function update($id) {
        $data = json_decode(file_get_contents('php://input'), true);

        $fields = [];
        $values = [];
        $types = '';

        if (isset($data['name'])) {
            $fields[] = 'name = ?';
            $values[] = $data['name'];
            $types .= 's';
        }
        if (isset($data['phone'])) {
            $fields[] = 'phone = ?';
            $values[] = $data['phone'];
            $types .= 's';
        }
        if (isset($data['address'])) {
            $fields[] = 'address = ?';
            $values[] = $data['address'];
            $types .= 's';
        }

        if (empty($fields)) {
            Response::error('No fields to update', 400);
        }

        $values[] = $id;
        $types .= 'i';

        $query = "UPDATE " . $this->table . " SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        
        $stmt->bind_param($types, ...$values);

        if ($stmt->execute()) {
            Response::success([], 'Customer updated successfully');
        } else {
            Response::error('Failed to update customer: ' . $stmt->error, 400);
        }
    }

    public function delete($id) {
        $query = "DELETE FROM " . $this->table . " WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $id);

        if ($stmt->execute()) {
            Response::success([], 'Customer deleted successfully');
        } else {
            Response::error('Failed to delete customer: ' . $stmt->error, 400);
        }
    }

    public function getPendingBalances() {
        $query = "SELECT id, name, pending_balance FROM " . $this->table . " WHERE pending_balance > 0 ORDER BY pending_balance DESC";
        $result = $this->conn->query($query);
        
        if (!$result) {
            Response::error('Query failed: ' . $this->conn->error, 400);
        }

        $customers = [];
        while ($row = $result->fetch_assoc()) {
            $customers[] = $row;
        }

        Response::success(['customers' => $customers], 'Pending balances retrieved');
    }
}

$controller = new CustomerController();
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? null;

switch ($action) {
    case 'getAll':
        $controller->getAll();
        break;
    case 'create':
        $controller->create();
        break;
    case 'update':
        if (!$id) Response::error('ID is required', 400);
        $controller->update($id);
        break;
    case 'delete':
        if (!$id) Response::error('ID is required', 400);
        $controller->delete($id);
        break;
    case 'getPendingBalances':
        $controller->getPendingBalances();
        break;
    default:
        Response::error('Invalid action', 400);
}
?>