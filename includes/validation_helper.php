<?php
/**
 * Validation Helper - Validação robusta de inputs
 */

class ValidationHelper {
    /**
     * Valida código de rastreamento
     */
    public static function validateCodigo($codigo) {
        $codigo = trim(strtoupper($codigo));
        
        if (empty($codigo)) {
            return ['valid' => false, 'error' => 'Código é obrigatório'];
        }
        
        if (strlen($codigo) < 3 || strlen($codigo) > 20) {
            return ['valid' => false, 'error' => 'Código deve ter entre 3 e 20 caracteres'];
        }
        
        if (!preg_match('/^[A-Z0-9]+$/', $codigo)) {
            return ['valid' => false, 'error' => 'Código deve conter apenas letras e números'];
        }
        
        return ['valid' => true, 'value' => $codigo];
    }
    
    /**
     * Valida cidade
     */
    public static function validateCidade($cidade) {
        $cidade = trim($cidade);
        
        if (empty($cidade)) {
            return ['valid' => false, 'error' => 'Cidade é obrigatória'];
        }
        
        if (strlen($cidade) < 2 || strlen($cidade) > 100) {
            return ['valid' => false, 'error' => 'Cidade deve ter entre 2 e 100 caracteres'];
        }
        
        return ['valid' => true, 'value' => $cidade];
    }
    
    /**
     * Valida telefone
     */
    public static function validateTelefone($telefone) {
        $telefone = preg_replace('/\D/', '', $telefone);
        
        if (empty($telefone)) {
            return ['valid' => false, 'error' => 'Telefone é obrigatório'];
        }
        
        if (strlen($telefone) < 10 || strlen($telefone) > 15) {
            return ['valid' => false, 'error' => 'Telefone inválido'];
        }
        
        return ['valid' => true, 'value' => $telefone];
    }
    
    /**
     * Valida email
     */
    public static function validateEmail($email) {
        $email = trim($email);
        
        if (empty($email)) {
            return ['valid' => false, 'error' => 'Email é obrigatório'];
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['valid' => false, 'error' => 'Email inválido'];
        }
        
        return ['valid' => true, 'value' => $email];
    }
    
    /**
     * Valida valor monetário
     */
    public static function validateValor($valor) {
        $valor = str_replace(',', '.', $valor);
        $valor = preg_replace('/[^0-9.]/', '', $valor);
        
        if (empty($valor)) {
            return ['valid' => false, 'error' => 'Valor é obrigatório'];
        }
        
        $valor = floatval($valor);
        
        if ($valor < 0) {
            return ['valid' => false, 'error' => 'Valor não pode ser negativo'];
        }
        
        if ($valor > 999999.99) {
            return ['valid' => false, 'error' => 'Valor muito alto'];
        }
        
        return ['valid' => true, 'value' => round($valor, 2)];
    }
    
    /**
     * Valida data
     */
    public static function validateData($data, $format = 'Y-m-d') {
        $d = DateTime::createFromFormat($format, $data);
        
        if (!$d || $d->format($format) !== $data) {
            return ['valid' => false, 'error' => 'Data inválida'];
        }
        
        return ['valid' => true, 'value' => $data];
    }
    
    /**
     * Valida múltiplos campos
     */
    public static function validateMultiple($rules, $data) {
        $errors = [];
        $validated = [];
        
        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? null;
            
            if (isset($rule['required']) && $rule['required'] && empty($value)) {
                $errors[$field] = $rule['message'] ?? "Campo $field é obrigatório";
                continue;
            }
            
            if (!empty($value)) {
                switch ($rule['type'] ?? 'string') {
                    case 'codigo':
                        $result = self::validateCodigo($value);
                        break;
                    case 'cidade':
                        $result = self::validateCidade($value);
                        break;
                    case 'telefone':
                        $result = self::validateTelefone($value);
                        break;
                    case 'email':
                        $result = self::validateEmail($value);
                        break;
                    case 'valor':
                        $result = self::validateValor($value);
                        break;
                    case 'date':
                        $result = self::validateData($value, $rule['format'] ?? 'Y-m-d');
                        break;
                    default:
                        $result = ['valid' => true, 'value' => sanitizeInput($value)];
                }
                
                if (!$result['valid']) {
                    $errors[$field] = $result['error'];
                } else {
                    $validated[$field] = $result['value'];
                }
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'data' => $validated
        ];
    }
}
?>

