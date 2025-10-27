<?php
/**
 * Sistema de Indicações — Entrega Prioritária em 2 Dias
 * Helmer Logistics
 * Serviço de indicação com prioridade automática e previsão de entrega acelerada
 */

require_once 'config.php';
require_once 'db_connect.php';

class ReferralSystem {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Registrar uma indicação
     */
    public function registrarIndicacao($codigoIndicador, $codigoIndicado, $dadosIndicado = []) {
        try {
            // Verificar se o indicador existe
            if (!$this->clienteExiste($codigoIndicador)) {
                throw new Exception("Código do indicador não encontrado");
            }
            
            // Verificar se o indicado já existe
            if ($this->clienteExiste($codigoIndicado)) {
                throw new Exception("Este código já está cadastrado");
            }
            
            // Cadastrar o cliente indicado
            $this->cadastrarCliente($codigoIndicado, $dadosIndicado);
            
            // Registrar a indicação
            $sql = "INSERT INTO indicacoes (codigo_indicador, codigo_indicado, prioridade, data_entrega_prevista) 
                    VALUES (?, ?, TRUE, DATE_ADD(CURDATE(), INTERVAL 2 DAY))";
            
            executeQuery($this->pdo, $sql, [$codigoIndicador, $codigoIndicado]);
            
            writeLog("Indicação registrada: $codigoIndicador indicou $codigoIndicado", 'INFO');
            
            return [
                'success' => true,
                'message' => 'Indicação registrada com sucesso! Entrega prioritária em 2 dias.',
                'data_entrega_prevista' => date('Y-m-d', strtotime('+2 days'))
            ];
            
        } catch (Exception $e) {
            writeLog("Erro ao registrar indicação: " . $e->getMessage(), 'ERROR');
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Processar compra com indicação
     */
    public function processarCompraComIndicacao($codigoCliente, $codigoIndicador, $valor, $dadosRastreio = []) {
        try {
            // Verificar se a indicação existe
            $indicacao = $this->buscarIndicacao($codigoCliente, $codigoIndicador);
            if (!$indicacao) {
                throw new Exception("Indicação não encontrada");
            }
            
            // Registrar a compra
            $sql = "INSERT INTO compras (codigo_cliente, codigo_indicador, valor, prioridade, data_entrega_prevista) 
                    VALUES (?, ?, ?, TRUE, DATE_ADD(CURDATE(), INTERVAL 2 DAY))";
            
            executeQuery($this->pdo, $sql, [$codigoCliente, $codigoIndicador, $valor]);
            
            // Atualizar status da indicação
            $sql = "UPDATE indicacoes SET status = 'confirmada' 
                    WHERE codigo_indicador = ? AND codigo_indicado = ?";
            executeQuery($this->pdo, $sql, [$codigoIndicador, $codigoCliente]);
            
            // Criar rastreio com prioridade
            $this->criarRastreioPrioritario($codigoCliente, $dadosRastreio, $codigoIndicador);
            
            writeLog("Compra com indicação processada: $codigoCliente (indicado por $codigoIndicador)", 'INFO');
            
            return [
                'success' => true,
                'message' => 'Compra processada com prioridade! Entrega em 2 dias.',
                'data_entrega_prevista' => date('Y-m-d', strtotime('+2 days')),
                'prioridade' => true
            ];
            
        } catch (Exception $e) {
            writeLog("Erro ao processar compra com indicação: " . $e->getMessage(), 'ERROR');
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Criar rastreio com prioridade
     */
    private function criarRastreioPrioritario($codigo, $dadosRastreio, $codigoIndicador) {
        $etapas = [
            ["📦 Objeto postado", "Objeto recebido no ponto de coleta", "bg-green-500"],
            ["🚚 Em trânsito", "A caminho do centro de distribuição", "bg-orange-500"],
            ["🏢 No centro de distribuição", "Processando encaminhamento", "bg-yellow-500"],
            ["🚀 Saiu para entrega", "Saiu para entrega ao destinatário", "bg-red-500"],
            ["✅ Entregue", "Objeto entregue com sucesso", "bg-green-500"]
        ];
        
        $dataInicial = time();
        $dataEntrega = strtotime('+2 days');
        
        foreach ($etapas as $index => $etapa) {
            $data = date('Y-m-d H:i:s', $dataInicial + ($index * 3600)); // 1 hora entre etapas
            $status = $etapa[0];
            $titulo = $etapa[0];
            $subtitulo = $etapa[1];
            $cor = $etapa[2];
            
            $sql = "INSERT INTO rastreios_status 
                    (codigo, cidade, status_atual, titulo, subtitulo, data, cor, prioridade, codigo_indicador, data_entrega_prevista) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, TRUE, ?, ?)";
            
            executeQuery($this->pdo, $sql, [
                $codigo,
                $dadosRastreio['cidade'] ?? 'Não informado',
                $status,
                $titulo,
                $subtitulo,
                $data,
                $cor,
                $codigoIndicador,
                date('Y-m-d', $dataEntrega)
            ]);
        }
    }
    
    /**
     * Verificar se cliente existe
     */
    private function clienteExiste($codigo) {
        $sql = "SELECT COUNT(*) as total FROM clientes WHERE codigo = ?";
        $result = fetchOne($this->pdo, $sql, [$codigo]);
        return $result['total'] > 0;
    }
    
    /**
     * Cadastrar novo cliente
     */
    private function cadastrarCliente($codigo, $dados) {
        $sql = "INSERT INTO clientes (codigo, nome, telefone, cidade) 
                VALUES (?, ?, ?, ?)";
        
        executeQuery($this->pdo, $sql, [
            $codigo,
            $dados['nome'] ?? 'Cliente',
            $dados['telefone'] ?? null,
            $dados['cidade'] ?? 'Não informado'
        ]);
    }
    
    /**
     * Buscar indicação
     */
    private function buscarIndicacao($codigoCliente, $codigoIndicador) {
        $sql = "SELECT * FROM indicacoes 
                WHERE codigo_indicador = ? AND codigo_indicado = ? 
                AND status = 'pendente'";
        
        return fetchOne($this->pdo, $sql, [$codigoIndicador, $codigoCliente]);
    }
    
    /**
     * Buscar rastreios prioritários
     */
    public function buscarRastreiosPrioritarios() {
        $sql = "SELECT rs.*, c.nome as nome_cliente, ci.nome as nome_indicador
                FROM rastreios_status rs
                LEFT JOIN clientes c ON rs.codigo = c.codigo
                LEFT JOIN clientes ci ON rs.codigo_indicador = ci.codigo
                WHERE rs.prioridade = TRUE
                ORDER BY rs.data_entrega_prevista ASC, rs.data ASC";
        
        return fetchData($this->pdo, $sql);
    }
    
    /**
     * Estatísticas de indicações
     */
    public function getEstatisticasIndicacoes() {
        $sql = "SELECT 
                    COUNT(*) as total_indicacoes,
                    COUNT(CASE WHEN status = 'confirmada' THEN 1 END) as confirmadas,
                    COUNT(CASE WHEN status = 'entregue' THEN 1 END) as entregues,
                    AVG(CASE WHEN status = 'entregue' THEN DATEDIFF(data_entrega_prevista, data_indicacao) END) as tempo_medio_entrega
                FROM indicacoes";
        
        return fetchOne($this->pdo, $sql);
    }
    
    /**
     * Buscar histórico de indicações de um cliente
     */
    public function getHistoricoIndicacoes($codigoCliente) {
        $sql = "SELECT i.*, c.nome as nome_indicado, c.cidade as cidade_indicado
                FROM indicacoes i
                LEFT JOIN clientes c ON i.codigo_indicado = c.codigo
                WHERE i.codigo_indicador = ?
                ORDER BY i.data_indicacao DESC";
        
        return fetchData($this->pdo, $sql, [$codigoCliente]);
    }
}
?>
