<?php
// backend/controllers/ProductController.php

require_once '../config/Database.php';
require_once '../core/Response.php';

class ProductController {
    private $conn;
    private $table = 'products';

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

        $products = [];
        while ($row = $result->fetch_assoc()) {
            $products[] = $row;
        }

        Response::success(['products' => $products], 'Products retrieved successfully');
    }

    public function getById($id) {
        $query = "SELECT * FROM " . $this->table . " WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            Response::error('Product not found', 404);
        }

        Response::success(['product' => $result->fetch_assoc()], 'Product retrieved successfully');
    }

    public function create() {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['name']) || !isset($data['mrp'])) {
            Response::error('Name and MRP are required', 400);
        }

        $barcode = $data['barcode'] ?? '';
        $name = $data['name'];
        $brand = $data['brand'] ?? '';
        $color = $data['color'] ?? '';
        $shade = $data['shade'] ?? '';
        $size = $data['size'] ?? '';
        $quantity = $data['quantity'] ?? 0;
        $mrp = $data['mrp'];
        $purchase_rate = $data['purchase_rate'] ?? 0;
        $sale_rate = $data['sale_rate'] ?? 0;
        $gst_percent = $data['gst_percent'] ?? 18;
        $min_stock = $data['min_stock'] ?? 0;

        $query = "INSERT INTO " . $this->table . " (barcode, name, brand, color, shade, size, quantity, mrp, purchase_rate, sale_rate, gst_percent, min_stock) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        
        if (!$stmt) {
            Response::error('Prepare failed: ' . $this->conn->error, 400);
        }

        $stmt->bind_param('ssssssiidddi', $barcode, $name, $brand, $color, $shade, $size, $quantity, $mrp, $purchase_rate, $sale_rate, $gst_percent, $min_stock);

        if ($stmt->execute()) {
            Response::success(['id' => $this->conn->insert_id], 'Product created successfully', 201);
        } else {
            Response::error('Failed to create product: ' . $stmt->error, 400);
        }
    }

    public function update($id) {
        $data = json_decode(file_get_contents('php://input'), true);

        $fields = [];
        $values = [];
        $types = '';

        $mappings = [
            'barcode' => 's',
            'name' => 's',
            'brand' => 's',
            'color' => 's',
            'shade' => 's',
            'size' => 's',
            'quantity' => 'i',
            'mrp' => 'd',
            'purchase_rate' => 'd',
            'sale_rate' => 'd',
            'gst_percent' => 'd',
            'min_stock' => 'i'
        ];

        foreach ($mappings as $field => $type) {
            if (isset($data[$field])) {
                $fields[] = $field . ' = ?';
                $values[] = $data[$field];
                $types .= $type;
            }
        }

        if (empty($fields)) {
            Response::error('No fields to update', 400);
        }

        $values[] = $id;
        $types .= 'i';

        $query = "UPDATE " . $this->table . " SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        
        if (!$stmt) {
            Response::error('Prepare failed: ' . $this->conn->error, 400);
        }

        $stmt->bind_param($types, ...$values);

        if ($stmt->execute()) {
            Response::success([], 'Product updated successfully');
        } else {
            Response::error('Failed to update product: ' . $stmt->error, 400);
        }
    }

    public function delete($id) {
        $query = "DELETE FROM " . $this->table . " WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $id);

        if ($stmt->execute()) {
            Response::success([], 'Product deleted successfully');
        } else {
            Response::error('Failed to delete product: ' . $stmt->error, 400);
        }
    }

    public function getLowStock() {
        $query = "SELECT * FROM " . $this->table . " WHERE quantity < min_stock ORDER BY quantity ASC";
        $result = $this->conn->query($query);
        
        if (!$result) {
            Response::error('Query failed: ' . $this->conn->error, 400);
        }

        $products = [];
        while ($row = $result->fetch_assoc()) {
            $products[] = $row;
        }

        Response::success(['products' => $products], 'Low stock products retrieved');
    }
}
// NOTE: No controller instantiation here - handled by index.php router

?>