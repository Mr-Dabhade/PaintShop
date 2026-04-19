<?php
require_once 'models/Company.php';
require_once 'utils/Response.php';

class CompanyController {
    private $companyModel;
    private $response;

    public function __construct() {
        $this->companyModel = new Company();
        $this->response = new Response();
    }

    public function getAll() {
        $companies = $this->companyModel->getAll();
        $this->response->success(['data' => $companies]);
    }

    public function getById($id) {
        $company = $this->companyModel->getById($id);
        if ($company) {
            $this->response->success(['data' => $company]);
        } else {
            $this->response->error('Company not found', 404);
        }
    }

    public function create() {
        $data = json_decode(file_get_contents("php://input"));
        
        if (!isset($data->name)) {
            $this->response->error('Company name required', 400);
            return;
        }

        if ($this->companyModel->create($data)) {
            $this->response->success([], 'Company created successfully', 201);
        } else {
            $this->response->error('Failed to create company', 400);
        }
    }

    public function update($id) {
        $data = json_decode(file_get_contents("php://input"));
        
        if ($this->companyModel->update($id, $data)) {
            $this->response->success([], 'Company updated successfully');
        } else {
            $this->response->error('Failed to update company', 400);
        }
    }

    public function delete($id) {
        if ($this->companyModel->delete($id)) {
            $this->response->success([], 'Company deleted successfully');
        } else {
            $this->response->error('Failed to delete company', 400);
        }
    }
}
?>