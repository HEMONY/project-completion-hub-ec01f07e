<?php
session_start();

// Include your existing database connection
require_once __DIR__ . '/../../config/db.php';

// ============================================================================
// MAIN PROCESSING LOGIC
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Validate session
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['error_message'] = 'Please login to continue';
        header("Location: ../../views/direct/kyc-steps/TAX-STATUS.php");
        exit;
    }

    if (!isset($_SESSION['entity_id']) || empty($_SESSION['entity_id'])) {
        $_SESSION['error_message'] = 'No entity found. Please start a new application.';
        header("Location: ../../views/direct/kyc-steps/TAX-STATUS.php");
        exit;
    }

    $entity_id = $_SESSION['entity_id'];
    $user_id = $_SESSION['user_id'];

    try {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Start transaction
        $conn->beginTransaction();
        
        // ====================================================================
        // 1. GET AND VALIDATE FORM DATA
        // ====================================================================
        $form_data = $_POST;
        
        // Get session data for validation
        $business_registration_status = $_SESSION['form']['step1']['registration-status'] ?? '';
        $first_financial_statements = $_SESSION['form']['step3']['year'] ?? '';
        
        // ====================================================================
        // 2. VALIDATION
        // ====================================================================
        $validation_errors = [];
        
        // Required fields for all cases
        $required_fields = [
            'vat_status' => 'VAT Status',
            'excise_tax_status' => 'Excise Tax Status',
            'corporate_tax_status' => 'Corporate Tax Status'
        ];
        
        foreach ($required_fields as $field => $label) {
            if (empty($form_data[$field])) {
                $validation_errors[] = "$label is required";
            }
        }
        
        // Conditional validation
        if (isset($form_data['vat_status']) && $form_data['vat_status'] === 'REGISTERED') {
            if (empty($form_data['vat_registration_number'])) {
                $validation_errors[] = 'VAT Registration Number is required when VAT status is REGISTERED';
            }
        }
        
        if (isset($form_data['corporate_tax_status'])) {
            if ($form_data['corporate_tax_status'] === 'REGISTERED') {
                if (empty($form_data['corporate_tax_registration_number'])) {
                    $validation_errors[] = 'Corporate Tax Registration Number is required when Corporate Tax status is REGISTERED';
                }
                
                if (!isset($form_data['corporate_tax_treatment']) || empty($form_data['corporate_tax_treatment'])) {
                    $validation_errors[] = 'Corporate Tax Treatment is required when Corporate Tax status is REGISTERED';
                } elseif ($form_data['corporate_tax_treatment'] === 'Qualifying Free Zone Person' && $business_registration_status !== 'Free Zone Licensed') {
                    $validation_errors[] = 'Qualifying Free Zone Person option is only available for FREE ZONE LICENSED entities';
                }
                
                if ($business_registration_status !== 'Free Zone Licensed') {
                    if (empty($form_data['small_business_relief'])) {
                        $validation_errors[] = 'Small Business Relief status is required for non-Free Zone entities when Corporate Tax is REGISTERED';
                    }
                }
                
            } elseif ($form_data['corporate_tax_status'] === 'NOT REGISTERED') {
                if (empty($form_data['not_registered_reason'])) {
                    $validation_errors[] = 'Reason for not registering for Corporate Tax is required';
                } elseif ($form_data['not_registered_reason'] === 'Other' && empty($form_data['other_reason_details'])) {
                    $validation_errors[] = 'Please specify the reason for not registering for Corporate Tax';
                }
            }
        }
        
        // Previous period validation (only if not first financial year)
        $is_first_year = ($first_financial_statements === 'Yes');
        
        if (!$is_first_year) {
            $previous_required = [
                'previous_vat_status' => 'Previous Period VAT Status',
                'previous_excise_tax_status' => 'Previous Period Excise Tax Status',
                'previous_corporate_tax_status' => 'Previous Period Corporate Tax Status'
            ];
            
            foreach ($previous_required as $field => $label) {
                if (empty($form_data[$field])) {
                    $validation_errors[] = "$label is required when this is not the first financial year";
                }
            }
            
            if (isset($form_data['previous_corporate_tax_status']) && $form_data['previous_corporate_tax_status'] === 'REGISTERED') {
                if (empty($form_data['previous_corporate_tax_treatment'])) {
                    $validation_errors[] = 'Previous Period Corporate Tax Treatment is required when Corporate Tax status is REGISTERED';
                } elseif ($form_data['previous_corporate_tax_treatment'] === 'Qualifying Free Zone Person' && $business_registration_status !== 'Free Zone Licensed') {
                    $validation_errors[] = 'Qualifying Free Zone Person option is only available for FREE ZONE LICENSED entities (Previous Period)';
                }
                
                if ($business_registration_status !== 'Free Zone Licensed') {
                    if (empty($form_data['previous_small_business_relief'])) {
                        $validation_errors[] = 'Previous Period Small Business Relief status is required for non-Free Zone entities when Corporate Tax is REGISTERED';
                    }
                }
            }
        }
        
        // If validation errors, stop here
        if (!empty($validation_errors)) {
            throw new Exception(implode('; ', $validation_errors));
        }
        
        // ====================================================================
        // 3. PREPARE DATA FOR DATABASE
        // ====================================================================
        
        // Helper function to map UI values to database enum values
        function mapTaxStatus($status) {
            if (empty($status)) return null;
            $map = [
                'REGISTERED' => 'Registered',
                'NOT REGISTERED' => 'Not Registered',
                'Registered' => 'Registered',
                'Not Registered' => 'Not Registered'
            ];
            return $map[$status] ?? null;
        }
        
        function mapCorporateTaxTreatment($treatment) {
            if (empty($treatment)) return null;
            $map = [
                'Standard Corporate Tax rates' => 'General',
                'Qualifying Free Zone Person' => 'Qualifying Free Zone Person',
                'General' => 'General'
            ];
            return $map[$treatment] ?? null;
        }
        
        function mapSmallBusinessRelief($relief) {
            if (empty($relief)) return null;
            $map = [
                'Yes' => 'Yes',
                'No' => 'No',
                '' => null
            ];
            return $map[$relief] ?? null;
        }
        
        // Current year data
        $current_year_vat_status = mapTaxStatus($form_data['vat_status']);
        $current_year_vat_reg_number = ($form_data['vat_status'] === 'REGISTERED') ? 
            ($form_data['vat_registration_number'] ?? null) : null;
        $current_year_excise_tax_status = mapTaxStatus($form_data['excise_tax_status']);
        $current_year_corporate_tax_status = mapTaxStatus($form_data['corporate_tax_status']);
        
        $current_year_corporate_tax_reg_number = null;
        $current_year_corporate_tax_treatment = null;
        $current_year_small_business_relief = null;
        $current_year_reason_not_registered_ct = null;
        
        if ($form_data['corporate_tax_status'] === 'REGISTERED') {
            $current_year_corporate_tax_reg_number = $form_data['corporate_tax_registration_number'] ?? null;
            $current_year_corporate_tax_treatment = mapCorporateTaxTreatment($form_data['corporate_tax_treatment'] ?? null);
            $current_year_small_business_relief = mapSmallBusinessRelief($form_data['small_business_relief'] ?? null);
        } elseif ($form_data['corporate_tax_status'] === 'NOT REGISTERED') {
            if (isset($form_data['not_registered_reason'])) {
                if ($form_data['not_registered_reason'] === 'Other') {
                    $current_year_reason_not_registered_ct = $form_data['other_reason_details'] ?? null;
                } else {
                    $current_year_reason_not_registered_ct = $form_data['not_registered_reason'] ?? null;
                }
            }
        }
        
        // Previous year data - initialize all variables
        $previous_year_vat_status = null;
        $previous_year_vat_reg_number = null;
        $previous_year_excise_tax_status = null;
        $previous_year_corporate_tax_status = null;
        $previous_year_corporate_tax_reg_number = null;
        $previous_year_corporate_tax_treatment = null;
        $previous_year_small_business_relief = null;
        $previous_year_reason_not_registered_ct = null;
        
        if (!$is_first_year) {
            $previous_year_vat_status = mapTaxStatus($form_data['previous_vat_status'] ?? null);
            $previous_year_excise_tax_status = mapTaxStatus($form_data['previous_excise_tax_status'] ?? null);
            $previous_year_corporate_tax_status = mapTaxStatus($form_data['previous_corporate_tax_status'] ?? null);
            
            if (isset($form_data['previous_corporate_tax_status']) && $form_data['previous_corporate_tax_status'] === 'REGISTERED') {
                $previous_year_corporate_tax_treatment = mapCorporateTaxTreatment($form_data['previous_corporate_tax_treatment'] ?? null);
                $previous_year_small_business_relief = mapSmallBusinessRelief($form_data['previous_small_business_relief'] ?? null);
            } elseif (isset($form_data['previous_corporate_tax_status']) && $form_data['previous_corporate_tax_status'] === 'NOT REGISTERED') {
                $previous_year_reason_not_registered_ct = 'Not registered';
            }
        }
        
        // ====================================================================
        // 4. SAVE TO DATABASE
        // ====================================================================
        
        // Check if record exists
        $checkStmt = $conn->prepare("SELECT id FROM entity_step4 WHERE entity_id = ?");
        $checkStmt->execute([$entity_id]);
        $existing_record = $checkStmt->rowCount() > 0;
        
        // Prepare SQL parameters
        $params = [
            ':entity_id' => $entity_id,
            
            // Current year fields
            ':current_year_vat_status' => $current_year_vat_status,
            ':current_year_vat_reg_number' => $current_year_vat_reg_number,
            ':current_year_excise_tax_status' => $current_year_excise_tax_status,
            ':current_year_corporate_tax_status' => $current_year_corporate_tax_status,
            ':current_year_corporate_tax_reg_number' => $current_year_corporate_tax_reg_number,
            ':current_year_corporate_tax_treatment' => $current_year_corporate_tax_treatment,
            ':current_year_small_business_relief' => $current_year_small_business_relief,
            ':current_year_reason_not_registered_ct' => $current_year_reason_not_registered_ct,
            
            // Previous year fields
            ':previous_year_vat_status' => $previous_year_vat_status,
            ':previous_year_vat_reg_number' => $previous_year_vat_reg_number,
            ':previous_year_excise_tax_status' => $previous_year_excise_tax_status,
            ':previous_year_corporate_tax_status' => $previous_year_corporate_tax_status,
            ':previous_year_corporate_tax_reg_number' => $previous_year_corporate_tax_reg_number,
            ':previous_year_corporate_tax_treatment' => $previous_year_corporate_tax_treatment,
            ':previous_year_small_business_relief' => $previous_year_small_business_relief,
            ':previous_year_reason_not_registered_ct' => $previous_year_reason_not_registered_ct
        ];
        
        if ($existing_record) {
            // Update existing record
            $sql = "UPDATE entity_step4 SET 
                    current_year_vat_status = :current_year_vat_status,
                    current_year_vat_reg_number = :current_year_vat_reg_number,
                    current_year_excise_tax_status = :current_year_excise_tax_status,
                    current_year_corporate_tax_status = :current_year_corporate_tax_status,
                    current_year_corporate_tax_reg_number = :current_year_corporate_tax_reg_number,
                    current_year_corporate_tax_treatment = :current_year_corporate_tax_treatment,
                    current_year_small_business_relief = :current_year_small_business_relief,
                    current_year_reason_not_registered_ct = :current_year_reason_not_registered_ct,
                    previous_year_vat_status = :previous_year_vat_status,
                    previous_year_vat_reg_number = :previous_year_vat_reg_number,
                    previous_year_excise_tax_status = :previous_year_excise_tax_status,
                    previous_year_corporate_tax_status = :previous_year_corporate_tax_status,
                    previous_year_corporate_tax_reg_number = :previous_year_corporate_tax_reg_number,
                    previous_year_corporate_tax_treatment = :previous_year_corporate_tax_treatment,
                    previous_year_small_business_relief = :previous_year_small_business_relief,
                    previous_year_reason_not_registered_ct = :previous_year_reason_not_registered_ct,
                    updated_at = NOW()
                    WHERE entity_id = :entity_id";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            
        } else {
            // Insert new record
            $sql = "INSERT INTO entity_step4 
                    (entity_id, 
                     current_year_vat_status, current_year_vat_reg_number, 
                     current_year_excise_tax_status, 
                     current_year_corporate_tax_status, current_year_corporate_tax_reg_number, current_year_corporate_tax_treatment,
                     current_year_small_business_relief, current_year_reason_not_registered_ct,
                     previous_year_vat_status, previous_year_vat_reg_number,
                     previous_year_excise_tax_status,
                     previous_year_corporate_tax_status, previous_year_corporate_tax_reg_number, previous_year_corporate_tax_treatment,
                     previous_year_small_business_relief, previous_year_reason_not_registered_ct,
                     created_at, updated_at) 
                    VALUES (:entity_id, 
                            :current_year_vat_status, :current_year_vat_reg_number, 
                            :current_year_excise_tax_status, 
                            :current_year_corporate_tax_status, :current_year_corporate_tax_reg_number, :current_year_corporate_tax_treatment,
                            :current_year_small_business_relief, :current_year_reason_not_registered_ct,
                            :previous_year_vat_status, :previous_year_vat_reg_number,
                            :previous_year_excise_tax_status,
                            :previous_year_corporate_tax_status, :previous_year_corporate_tax_reg_number, :previous_year_corporate_tax_treatment,
                            :previous_year_small_business_relief, :previous_year_reason_not_registered_ct,
                            NOW(), NOW())";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
        }
        
        // ====================================================================
        // 5. UPDATE ENTITY STEP
        // ====================================================================
        $updateEntityStmt = $conn->prepare("UPDATE entities SET current_step = 5 WHERE id = :entity_id");
        $updateEntityStmt->execute([':entity_id' => $entity_id]);
        
        // ====================================================================
        // 6. UPDATE SESSION
        // ====================================================================
        $_SESSION['form']['step4'] = [
            'vat_status' => $form_data['vat_status'] ?? '',
            'vat_registration_number' => $form_data['vat_registration_number'] ?? '',
            'excise_tax_status' => $form_data['excise_tax_status'] ?? '',
            'corporate_tax_status' => $form_data['corporate_tax_status'] ?? '',
            'corporate_tax_registration_number' => $form_data['corporate_tax_registration_number'] ?? '',
            'corporate_tax_treatment' => $form_data['corporate_tax_treatment'] ?? '',
            'small_business_relief' => $form_data['small_business_relief'] ?? '',
            'not_registered_reason' => $form_data['not_registered_reason'] ?? '',
            'other_reason_details' => $form_data['other_reason_details'] ?? '',
            'previous_vat_status' => $form_data['previous_vat_status'] ?? '',
            'previous_excise_tax_status' => $form_data['previous_excise_tax_status'] ?? '',
            'previous_corporate_tax_status' => $form_data['previous_corporate_tax_status'] ?? '',
            'previous_corporate_tax_treatment' => $form_data['previous_corporate_tax_treatment'] ?? '',
            'previous_small_business_relief' => $form_data['previous_small_business_relief'] ?? ''
        ];
        
        // Clear any previous error messages
        if (isset($_SESSION['error_message'])) {
            unset($_SESSION['error_message']);
        }
        
        // ====================================================================
        // 7. COMMIT AND REDIRECT TO NEXT STEP
        // ====================================================================
        $conn->commit();
        
        // Redirect to the next step - ENGAGEMENT LETTER
        header("Location: ../../views/direct/kyc-steps/Engagement-Letter.php");
        exit;
        
    } catch (Exception $e) {
        // Rollback on error
        if (isset($conn) && $conn->inTransaction()) {
            $conn->rollBack();
        }
        
        // Store form data in session for debugging
        $_SESSION['form_data'] = $_POST;
        $_SESSION['error_message'] = $e->getMessage();
        
        // Redirect back to tax status page with error
        header("Location: ../../views/direct/kyc-steps/TAX-STATUS.php");
        exit;
    }
    
} else {
    // Invalid request method - redirect back
    header("Location: ../../views/direct/kyc-steps/TAX-STATUS.php");
    exit;
}