<?php
require_once 'models/User.php';
require_once 'utils/Response.php';

class AuthController {
    private $userModel;
    private $response;

    public function __construct() {
        $this->userModel = new User();
        $this->response = new Response();
    }

    public function login() {
        $data = json_decode(file_get_contents("php://input"));
        
        if (!isset($data->email) || !isset($data->password)) {
            $this->response->error('Email and password required', 400);
            return;
        }

        $user = $this->userModel->login($data->email, $data->password);
        
        if ($user) {
            $this->response->success([
                'user' => $user,
                'token' => $this->generateToken($user['id'])
            ], 'Login successful');
        } else {
            $this->response->error('Invalid credentials', 401);
        }
    }

    public function register() {
        $data = json_decode(file_get_contents("php://input"));
        
        if (!isset($data->name) || !isset($data->email) || !isset($data->password)) {
            $this->response->error('Name, email and password required', 400);
            return;
        }

        $result = $this->userModel->register($data->name, $data->email, $data->password);
        
        if ($result) {
            $user = $this->userModel->getUserByEmail($data->email);
            $this->response->success([
                'user' => $user,
                'token' => $this->generateToken($user['id'])
            ], 'Registration successful');
        } else {
            $this->response->error('Registration failed', 400);
        }
    }

    private function generateToken($userId) {
        return bin2hex(random_bytes(32));
    }
}
?>