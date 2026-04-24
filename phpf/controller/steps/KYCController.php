<?php
// KYCController.php

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Form Session Handler Class
 */
class FormSessionHandler {
    private string $sessionKey = 'multi_step_form';
    private array $allowedFileTypes = ['pdf', 'jpg', 'jpeg', 'png'];
    private int $maxFileSize = 10 * 1024 * 1024; // 10MB
    
    public function __construct() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    /**
     * Save KYC Step Data (Step 1)
     */
    public function saveKYCData(array $data): bool {
        try {
            // Basic validation
            $requiredFields = [
                'reg_status', 'entity_name', 'main_activity', 
                'emirate', 'address', 'total_turnover'
            ];
            
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    throw new Exception("Required field '{$field}' is missing");
                }
            }
            
            // Validate turnover
            $turnover = floatval($data['total_turnover']);
            if ($turnover > 50000000) {
                throw new Exception("Turnover exceeds maximum allowed limit");
            }
            
            // Save data
            $_SESSION[$this->sessionKey]['kyc_data'] = [
                'basic_info' => [
                    'reg_status' => $this->sanitize($data['reg_status']),
                    'entity_name' => $this->sanitize($data['entity_name']),
                    'main_activity' => $this->sanitize($data['main_activity']),
                    'emirate' => $this->sanitize($data['emirate']),
                    'address' => $this->sanitize($data['address']),
                    'total_turnover' => $turnover
                ],
                'license_data' => $data['license_data'] ?? [],
                'acknowledgements' => [
                    'kyc_terms' => !empty($data['kyc_terms']),
                    'documents_certification' => !empty($data['documents_certification'])
                ]
            ];
            
            // Save shareholders
            if (!empty($data['shareholders'])) {
                $_SESSION[$this->sessionKey]['kyc_data']['shareholders'] = 
                    $this->validatePersonData($data['shareholders'], 'shareholder');
            }
            
            // Save UBOs if applicable
            if (!empty($data['ubos']) && isset($data['ubo_question']) && $data['ubo_question'] === 'Yes') {
                $_SESSION[$this->sessionKey]['kyc_data']['ubos'] = 
                    $this->validatePersonData($data['ubos'], 'ubo');
                $_SESSION[$this->sessionKey]['kyc_data']['ubo_question'] = 'Yes';
            } else {
                $_SESSION[$this->sessionKey]['kyc_data']['ubo_question'] = $data['ubo_question'] ?? 'No';
            }
            
            // Save management
            if (!empty($data['management'])) {
                $_SESSION[$this->sessionKey]['kyc_data']['management'] = 
                    $this->validatePersonData($data['management'], 'management');
            }
            
            // Save mainland type if applicable
            if (!empty($data['mainland_type'])) {
                $_SESSION[$this->sessionKey]['kyc_data']['mainland_type'] = 
                    $this->sanitize($data['mainland_type']);
            }
            
            $_SESSION[$this->sessionKey]['current_step'] = 1;
            $_SESSION[$this->sessionKey]['last_updated'] = date('Y-m-d H:i:s');
            
            return true;
            
        } catch (Exception $e) {
            error_log("Error saving KYC data: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Save uploaded files
     */
    public function saveFiles(array $files): array {
        $savedFiles = [];
        
        foreach ($files as $fileKey => $fileData) {
            if ($fileData['error'] !== UPLOAD_ERR_OK) {
                continue;
            }
            
            // Validate file type
            $fileExt = strtolower(pathinfo($fileData['name'], PATHINFO_EXTENSION));
            if (!in_array($fileExt, $this->allowedFileTypes)) {
                continue;
            }
            
            // Validate file size
            if ($fileData['size'] > $this->maxFileSize) {
                continue;
            }
            
            // Generate unique filename
            $uniqueName = uniqid() . '_' . time() . '.' . $fileExt;
            $uploadPath = $this->getUploadPath() . '/' . $uniqueName;
            
            // Move uploaded file
            if (move_uploaded_file($fileData['tmp_name'], $uploadPath)) {
                $savedFiles[$fileKey] = [
                    'original_name' => $this->sanitize($fileData['name']),
                    'saved_name' => $uniqueName,
                    'path' => $uploadPath,
                    'size' => $fileData['size'],
                    'type' => $fileData['type'],
                    'uploaded_at' => date('Y-m-d H:i:s')
                ];
            }
        }
        
        if (!empty($savedFiles)) {
            if (!isset($_SESSION[$this->sessionKey]['uploaded_files'])) {
                $_SESSION[$this->sessionKey]['uploaded_files'] = [];
            }
            $_SESSION[$this->sessionKey]['uploaded_files'] = 
                array_merge($_SESSION[$this->sessionKey]['uploaded_files'], $savedFiles);
        }
        
        return $savedFiles;
    }
    
    /**
     * Save Step 2 Data - Audit Fee Acknowledgement
     */
    public function saveAuditFeeData(array $data): bool {
        $required = ['fee_amount', 'payment_terms', 'acknowledged'];
        
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return false;
            }
        }
        
        $_SESSION[$this->sessionKey]['audit_fee_data'] = [
            'fee_amount' => floatval($data['fee_amount']),
            'payment_terms' => $this->sanitize($data['payment_terms']),
            'acknowledged' => !empty($data['acknowledged']),
            'acknowledged_at' => date('Y-m-d H:i:s')
        ];
        
        $_SESSION[$this->sessionKey]['current_step'] = 2;
        return true;
    }
    
    /**
     * Save Step 3 Data - Financial Year Details
     */
    public function saveFinancialYearData(array $data): bool {
        // Validate dates
        if (empty($data['start_date']) || empty($data['end_date'])) {
            return false;
        }
        
        $_SESSION[$this->sessionKey]['financial_year_data'] = [
            'start_date' => $this->sanitize($data['start_date']),
            'end_date' => $this->sanitize($data['end_date']),
            'duration_days' => $this->calculateDateDifference($data['start_date'], $data['end_date'])
        ];
        
        $_SESSION[$this->sessionKey]['current_step'] = 3;
        return true;
    }
    
    /**
     * Save Step 4 Data - Tax Status Disclosure
     */
    public function saveTaxStatusData(array $data): bool {
        $_SESSION[$this->sessionKey]['tax_status_data'] = [
            'vat_registered' => !empty($data['vat_registered']),
            'vat_number' => $this->sanitize($data['vat_number'] ?? ''),
            'corporate_tax_registered' => !empty($data['corporate_tax_registered']),
            'tax_period' => $this->sanitize($data['tax_period'] ?? ''),
            'declaration_date' => date('Y-m-d H:i:s')
        ];
        
        $_SESSION[$this->sessionKey]['current_step'] = 4;
        return true;
    }
    
    /**
     * Save Step 5 Data - Engagement Letter Acceptance
     */
    public function saveEngagementLetterData(array $data): bool {
        if (empty($data['accepted'])) {
            return false;
        }
        
        $_SESSION[$this->sessionKey]['engagement_letter_data'] = [
            'accepted' => true,
            'accepted_at' => date('Y-m-d H:i:s'),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ];
        
        $_SESSION[$this->sessionKey]['current_step'] = 5;
        $_SESSION[$this->sessionKey]['completed_at'] = date('Y-m-d H:i:s');
        
        return true;
    }
    
    /**
     * Get all form data
     */
    public function getAllData(): array {
        return $_SESSION[$this->sessionKey] ?? [];
    }
    
    /**
     * Get specific step data
     */
    public function getStepData(int $step): array {
        $stepMap = [
            1 => 'kyc_data',
            2 => 'audit_fee_data',
            3 => 'financial_year_data',
            4 => 'tax_status_data',
            5 => 'engagement_letter_data'
        ];
        
        $key = $stepMap[$step] ?? null;
        return $key ? ($_SESSION[$this->sessionKey][$key] ?? []) : [];
    }
    
    /**
     * Get current step
     */
    public function getCurrentStep(): int {
        return $_SESSION[$this->sessionKey]['current_step'] ?? 1;
    }
    
    /**
     * Prepare final data for database storage
     */
    public function prepareFinalData(): array {
        $allData = $this->getAllData();
        
        // Validate all steps are complete
        for ($i = 1; $i <= 5; $i++) {
            if (empty($this->getStepData($i))) {
                throw new Exception("Step {$i} data is incomplete");
            }
        }
        
        // Add summary information
        $summary = [
            'application_id' => $this->generateApplicationId(),
            'total_steps' => 5,
            'completed_steps' => $this->countCompletedSteps(),
            'submission_date' => date('Y-m-d H:i:s'),
            'entity_name' => $allData['kyc_data']['basic_info']['entity_name'] ?? 'Unknown',
            'total_turnover' => $allData['kyc_data']['basic_info']['total_turnover'] ?? 0,
            'status' => 'completed'
        ];
        
        $allData['summary'] = $summary;
        
        return $allData;
    }
    
    /**
     * Clear specific step data
     */
    public function clearStep(int $step): void {
        $stepMap = [
            1 => 'kyc_data',
            2 => 'audit_fee_data',
            3 => 'financial_year_data',
            4 => 'tax_status_data',
            5 => 'engagement_letter_data'
        ];
        
        $key = $stepMap[$step] ?? null;
        if ($key && isset($_SESSION[$this->sessionKey][$key])) {
            unset($_SESSION[$this->sessionKey][$key]);
        }
    }
    
    /**
     * Clear all form data
     */
    public function clearAll(): void {
        // Delete uploaded files
        if (isset($_SESSION[$this->sessionKey]['uploaded_files'])) {
            foreach ($_SESSION[$this->sessionKey]['uploaded_files'] as $file) {
                if (file_exists($file['path'])) {
                    unlink($file['path']);
                }
            }
        }
        
        unset($_SESSION[$this->sessionKey]);
    }
    
    /**
     * Check if form is complete
     */
    public function isComplete(): bool {
        return isset($_SESSION[$this->sessionKey]['completed_at']);
    }
    
    /**
     * Save to database (you'll need to implement your DB connection)
     */
    public function saveToDatabase(): bool {
        if (!$this->isComplete()) {
            return false;
        }
        
        $finalData = $this->prepareFinalData();
        
        // Save to JSON file for now (implement your DB logic here)
        $logFile = $this->getUploadPath() . '/applications/' . 
                  $finalData['summary']['application_id'] . '.json';
        
        if (!file_exists(dirname($logFile))) {
            mkdir(dirname($logFile), 0777, true);
        }
        
        return file_put_contents($logFile, json_encode($finalData, JSON_PRETTY_PRINT)) !== false;
    }
    
    // ********************** HELPER METHODS **********************
    
    private function validatePersonData(array $persons, string $type): array {
        $validated = [];
        
        foreach ($persons as $index => $person) {
            // Basic required fields
            $required = ['name', 'nationality', 'emirates_id', 'expiry_date'];
            
            foreach ($required as $field) {
                if (empty($person[$field])) {
                    throw new Exception("{$type} {$index}: {$field} is required");
                }
            }
            
            // Validate EID format
            if (!preg_match('/^[0-9]{15}$/', $person['emirates_id'])) {
                throw new Exception("{$type} {$index}: Emirates ID must be 15 digits");
            }
            
            // Validate date format
            if (!preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $person['expiry_date'])) {
                throw new Exception("{$type} {$index}: Date must be in DD/MM/YYYY format");
            }
            
            // Check if expired
            $expiryDate = DateTime::createFromFormat('d/m/Y', $person['expiry_date']);
            $today = new DateTime();
            if ($expiryDate <= $today) {
                throw new Exception("{$type} {$index}: Emirates ID is expired");
            }
            
            // Validate capital percentage for shareholders and UBOs
            if (in_array($type, ['shareholder', 'ubo'])) {
                if (empty($person['capital_percentage'])) {
                    throw new Exception("{$type} {$index}: Capital percentage is required");
                }
                
                $capital = floatval($person['capital_percentage']);
                if ($type === 'ubo' && $capital < 25) {
                    throw new Exception("{$type} {$index}: UBO must have 25% or more capital");
                }
                
                if ($capital < 0 || $capital > 100) {
                    throw new Exception("{$type} {$index}: Capital percentage must be between 0-100");
                }
            }
            
            $validated[] = [
                'name' => $this->sanitize($person['name']),
                'capital_percentage' => $type !== 'management' ? floatval($person['capital_percentage']) : null,
                'position' => $type === 'management' ? $this->sanitize($person['position'] ?? '') : null,
                'nationality' => $this->sanitize($person['nationality']),
                'emirates_id' => $this->sanitize($person['emirates_id']),
                'expiry_date' => $this->sanitize($person['expiry_date']),
                'pep_status' => $this->sanitize($person['pep_status'] ?? 'No'),
                'type' => $type
            ];
        }
        
        // Validate total capital for shareholders
        if ($type === 'shareholder') {
            $totalCapital = array_sum(array_column($validated, 'capital_percentage'));
            if (round($totalCapital) !== 100) {
                throw new Exception("Total shareholders capital must equal 100% (currently {$totalCapital}%)");
            }
        }
        
        return $validated;
    }
    
    private function sanitize(string $input): string {
        return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
    }
    
    private function getUploadPath(): string {
        $path = __DIR__ . '/uploads/' . date('Y/m/d');
        
        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        }
        
        return $path;
    }
    
    private function generateApplicationId(): string {
        return 'APP-' . date('Ymd') . '-' . strtoupper(uniqid());
    }
    
    private function countCompletedSteps(): int {
        $count = 0;
        for ($i = 1; $i <= 5; $i++) {
            if (!empty($this->getStepData($i))) {
                $count++;
            }
        }
        return $count;
    }
    
    private function calculateDateDifference(string $start, string $end): int {
        $startDate = DateTime::createFromFormat('d/m/Y', $start);
        $endDate = DateTime::createFromFormat('d/m/Y', $end);
        
        if (!$startDate || !$endDate) {
            return 0;
        }
        
        $interval = $startDate->diff($endDate);
        return $interval->days;
    }
}

/**
 * KYC Controller Class
 */
class KYCController {
    private $sessionHandler;
    private $currentStep = 1;
    
    public function __construct() {
        $this->sessionHandler = new FormSessionHandler();
        $this->currentStep = $this->sessionHandler->getCurrentStep();
        
        // Handle form submissions
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handlePostRequest();
        }
    }
    
    private function handlePostRequest() {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'save_step_1':
                $this->saveStep1Data();
                break;
            case 'save_files':
                $this->saveUploadedFiles();
                break;
            case 'save_step_2':
                $this->saveStep2Data();
                break;
            case 'save_step_3':
                $this->saveStep3Data();
                break;
            case 'save_step_4':
                $this->saveStep4Data();
                break;
            case 'save_step_5':
                $this->saveStep5Data();
                break;
            case 'clear_step':
                $step = intval($_POST['step'] ?? 1);
                $this->sessionHandler->clearStep($step);
                $this->jsonResponse(true, "Step {$step} cleared");
                break;
            case 'clear_all':
                $this->sessionHandler->clearAll();
                $this->jsonResponse(true, "All data cleared");
                break;
            case 'submit_final':
                $this->submitFinalData();
                break;
            case 'get_session_data':
                $this->getSessionData();
                break;
        }
    }
    
    private function saveStep1Data() {
        try {
            // Collect all form data
            $data = [
                'reg_status' => $_POST['reg_status'] ?? '',
                'entity_name' => $_POST['entity_name'] ?? '',
                'main_activity' => $_POST['main_activity'] ?? '',
                'emirate' => $_POST['emirate'] ?? '',
                'address' => $_POST['address'] ?? '',
                'total_turnover' => $_POST['total_turnover'] ?? 0,
                'kyc_terms' => isset($_POST['kyc_terms']) ? 1 : 0,
                'documents_certification' => isset($_POST['documents_certification']) ? 1 : 0,
                'ubo_question' => $_POST['ubo_question'] ?? 'No',
                'mainland_type' => $_POST['mainland_type'] ?? '',
            ];
            
            // Process shareholders
            $data['shareholders'] = $this->extractPersonData('shareholder');
            
            // Process UBOs if applicable
            if ($data['ubo_question'] === 'Yes') {
                $data['ubos'] = $this->extractPersonData('ubo');
            }
            
            // Process management
            $data['management'] = $this->extractPersonData('management');
            
            // Add license data if available
            if (!empty($_SESSION['license_data'])) {
                $data['license_data'] = $_SESSION['license_data'];
            }
            
            // Save to session
            $result = $this->sessionHandler->saveKYCData($data);
            
            if ($result) {
                $this->jsonResponse(true, 'KYC data saved successfully', [
                    'current_step' => $this->sessionHandler->getCurrentStep(),
                    'step_data' => $this->sessionHandler->getStepData(1)
                ]);
            } else {
                throw new Exception('Failed to save KYC data');
            }
            
        } catch (Exception $e) {
            $this->jsonResponse(false, $e->getMessage());
        }
    }
    
    private function extractPersonData($type) {
        $persons = [];
        
        if ($type === 'management') {
            // Check if management data exists
            if (isset($_POST['management_name']) && !empty($_POST['management_name'])) {
                $persons[] = [
                    'name' => $_POST['management_name'] ?? '',
                    'position' => $_POST['management_position'] ?? '',
                    'nationality' => $_POST['management_nationality'] ?? '',
                    'emirates_id' => $_POST['management_emirates_id'] ?? '',
                    'expiry_date' => $_POST['management_expiry_date'] ?? '',
                    'pep_status' => $_POST['management_pep'] ?? 'No',
                    'capital_percentage' => null
                ];
            }
        } else {
            // For shareholders and UBOs
            $prefix = $type;
            $count = 0;
            
            // Count how many persons were submitted
            foreach ($_POST as $key => $value) {
                if (strpos($key, $prefix . '_name_') === 0) {
                    $index = str_replace($prefix . '_name_', '', $key);
                    if (is_numeric($index) && !empty($value)) {
                        $persons[] = [
                            'name' => $value,
                            'capital_percentage' => $_POST[$prefix . '_capital_' . $index] ?? 0,
                            'nationality' => $_POST[$prefix . '_nationality_' . $index] ?? '',
                            'emirates_id' => $_POST[$prefix . '_emirates_id_' . $index] ?? '',
                            'expiry_date' => $_POST[$prefix . '_expiry_date_' . $index] ?? '',
                            'pep_status' => $_POST[$prefix . '_pep_' . $index] ?? 'No'
                        ];
                    }
                }
            }
        }
        
        return $persons;
    }
    
    private function saveUploadedFiles() {
        if (!empty($_FILES)) {
            $savedFiles = $this->sessionHandler->saveFiles($_FILES);
            
            $this->jsonResponse(true, count($savedFiles) . ' file(s) uploaded successfully', [
                'files' => $savedFiles
            ]);
        } else {
            $this->jsonResponse(false, 'No files uploaded');
        }
    }
    
    private function saveStep2Data() {
        $data = [
            'fee_amount' => $_POST['fee_amount'] ?? 0,
            'payment_terms' => $_POST['payment_terms'] ?? '',
            'acknowledged' => isset($_POST['acknowledged']) ? 1 : 0
        ];
        
        $result = $this->sessionHandler->saveAuditFeeData($data);
        
        $this->jsonResponse($result, 
            $result ? 'Audit fee data saved' : 'Failed to save audit fee data',
            ['current_step' => $this->sessionHandler->getCurrentStep()]
        );
    }
    
    private function saveStep3Data() {
        $data = [
            'start_date' => $_POST['start_date'] ?? '',
            'end_date' => $_POST['end_date'] ?? ''
        ];
        
        $result = $this->sessionHandler->saveFinancialYearData($data);
        
        $this->jsonResponse($result,
            $result ? 'Financial year data saved' : 'Failed to save financial year data',
            ['current_step' => $this->sessionHandler->getCurrentStep()]
        );
    }
    
    private function saveStep4Data() {
        $data = [
            'vat_registered' => isset($_POST['vat_registered']) ? 1 : 0,
            'vat_number' => $_POST['vat_number'] ?? '',
            'corporate_tax_registered' => isset($_POST['corporate_tax_registered']) ? 1 : 0,
            'tax_period' => $_POST['tax_period'] ?? ''
        ];
        
        $result = $this->sessionHandler->saveTaxStatusData($data);
        
        $this->jsonResponse($result,
            $result ? 'Tax status data saved' : 'Failed to save tax status data',
            ['current_step' => $this->sessionHandler->getCurrentStep()]
        );
    }
    
    private function saveStep5Data() {
        $data = [
            'accepted' => isset($_POST['accepted']) ? 1 : 0
        ];
        
        $result = $this->sessionHandler->saveEngagementLetterData($data);
        
        $this->jsonResponse($result,
            $result ? 'Engagement letter accepted' : 'Failed to save acceptance',
            ['current_step' => $this->sessionHandler->getCurrentStep()]
        );
    }
    
    private function submitFinalData() {
        try {
            if ($this->sessionHandler->saveToDatabase()) {
                $finalData = $this->sessionHandler->prepareFinalData();
                
                $this->jsonResponse(true, 'Application submitted successfully!', [
                    'application_id' => $finalData['summary']['application_id']
                ]);
                
                // Clear session after successful submission
                $this->sessionHandler->clearAll();
            } else {
                throw new Exception('Failed to save to database');
            }
        } catch (Exception $e) {
            $this->jsonResponse(false, $e->getMessage());
        }
    }
    
    private function getSessionData() {
        $data = $this->sessionHandler->getAllData();
        $this->jsonResponse(true, 'Session data retrieved', $data);
    }
    
    public function getCurrentStep() {
        return $this->currentStep;
    }
    
    public function getSessionDataForView() {
        return $this->sessionHandler->getAllData();
    }
    
    public function getStepDataForView($step) {
        return $this->sessionHandler->getStepData($step);
    }
    
    private function jsonResponse($success, $message, $data = []) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $success,
            'message' => $message,
            'data' => $data
        ]);
        exit;
    }
    
    public function processRequest() {
        // This method will be called from your HTML form
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Already handled in constructor
            return;
        }
    }
}

// Initialize controller
$controller = new KYCController();
$sessionData = $controller->getSessionDataForView();
$kycData = $sessionData['kyc_data'] ?? [];
$currentStep = $controller->getCurrentStep();

// Determine if KYC checkbox should be checked
$kycChecked = isset($kycData['acknowledgements']['kyc_terms']) && 
              $kycData['acknowledgements']['kyc_terms'] ? 'checked' : '';

// Determine if documents certification should be checked
$docsChecked = isset($kycData['acknowledgements']['documents_certification']) && 
               $kycData['acknowledgements']['documents_certification'] ? 'checked' : '';

// Determine step statuses for sidebar
$step1Status = !empty($kycData) ? 'completed' : 'pending';
$step2Status = isset($sessionData['audit_fee_data']) ? 'completed' : '';
$step3Status = isset($sessionData['financial_year_data']) ? 'completed' : '';
$step4Status = isset($sessionData['tax_status_data']) ? 'completed' : '';
$step5Status = isset($sessionData['engagement_letter_data']) ? 'completed' : '';

$step1Text = !empty($kycData) ? 'Completed' : 'Pending';
$step2Text = isset($sessionData['audit_fee_data']) ? 'Completed' : 'Not Started';
$step3Text = isset($sessionData['financial_year_data']) ? 'Completed' : 'Not Started';
$step4Text = isset($sessionData['tax_status_data']) ? 'Completed' : 'Not Started';
$step5Text = isset($sessionData['engagement_letter_data']) ? 'Completed' : 'Not Started';
?>