<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db_connect.php';

if (empty($_SESSION['logado'])) {
    header('Location: admin.php');
    exit;
}

$createdStructures = [];
$errors = [];
$executed = false;

function runStatement(PDO $pdo, string $sql, string $label, array &$log, array &$errors): void {
    try {
        $pdo->exec($sql);
        $log[] = [
            'label' => $label,
            'status' => 'success'
        ];
    } catch (Throwable $e) {
        $errors[] = [
            'label' => $label,
            'error' => $e->getMessage()
        ];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $executed = true;

    $statements = [
        [
            'label' => 'Adicionar colunas de prioridade em rastreios_status',
            'sql' => "ALTER TABLE rastreios_status 
                ADD COLUMN IF NOT EXISTS prioridade TINYINT(1) DEFAULT 0,
                ADD COLUMN IF NOT EXISTS codigo_indicador VARCHAR(50) NULL,
                ADD COLUMN IF NOT EXISTS data_entrega_prevista DATE NULL,
                ADD INDEX IF NOT EXISTS idx_prioridade (prioridade),
                ADD INDEX IF NOT EXISTS idx_codigo_indicador (codigo_indicador)"
        ],
        [
            'label' => 'Criar tabela whatsapp_contatos',
            'sql' => "CREATE TABLE IF NOT EXISTS whatsapp_contatos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                codigo VARCHAR(50) NOT NULL UNIQUE,
                nome VARCHAR(255) NULL,
                telefone_original VARCHAR(30) NULL,
                telefone_normalizado VARCHAR(20) NULL,
                notificacoes_ativas TINYINT(1) DEFAULT 1,
                criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_codigo (codigo),
                INDEX idx_telefone (telefone_normalizado)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        ],
        [
            'label' => 'Criar tabela whatsapp_notificacoes',
            'sql' => "CREATE TABLE IF NOT EXISTS whatsapp_notificacoes (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                codigo VARCHAR(50) NOT NULL,
                status_titulo VARCHAR(255) NOT NULL,
                status_subtitulo VARCHAR(255) NULL,
                status_data DATETIME NOT NULL,
                telefone VARCHAR(20) NOT NULL,
                mensagem TEXT NOT NULL,
                resposta_http TEXT NULL,
                http_code INT NULL,
                sucesso TINYINT(1) DEFAULT 0,
                criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                enviado_em TIMESTAMP NULL,
                UNIQUE KEY uniq_codigo_status (codigo, status_titulo, status_data),
                INDEX idx_codigo_notificacoes (codigo),
                INDEX idx_sucesso (sucesso)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        ]
    ];

    foreach ($statements as $item) {
        runStatement($pdo, $item['sql'], $item['label'], $createdStructures, $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup WhatsApp - Helmer Logistics</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            color-scheme: light dark;
            --bg: #0b101c;
            --bg-card: rgba(17,24,39,0.88);
            --border: rgba(148,163,184,0.2);
            --text: #e2e8f0;
            --muted: #94a3b8;
            --accent: #38bdf8;
            --accent-strong: #0ea5e9;
            --success: #22c55e;
            --danger: #ef4444;
        }

        [data-theme="light"] {
            --bg: #f8fafc;
            --bg-card: rgba(255,255,255,0.92);
            --border: rgba(100,116,139,0.18);
            --text: #0f172a;
            --muted: #475569;
            --accent: #3b82f6;
            --accent-strong: #2563eb;
            --success: #16a34a;
            --danger: #dc2626;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .wrapper {
            width: 100%;
            max-width: 760px;
        }

        .card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 32px;
            backdrop-filter: blur(12px);
            box-shadow: 0 30px 60px -20px rgba(37, 99, 235, 0.35);
        }

        h1 {
            margin: 0 0 16px;
            font-size: clamp(24px, 3vw, 32px);
        }

        p {
            margin: 0 0 24px;
            color: var(--muted);
            line-height: 1.6;
        }

        .actions {
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 24px;
        }

        .button {
            background: linear-gradient(135deg, var(--accent), var(--accent-strong));
            color: #0f172a;
            padding: 14px 22px;
            border-radius: 999px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .button:hover {
            transform: translateY(-1px);
            box-shadow: 0 20px 40px -20px rgba(14, 165, 233, 0.6);
        }

        .muted {
            color: var(--muted);
            font-size: 14px;
        }

        .logs {
            display: grid;
            gap: 12px;
        }

        .log-item {
            padding: 16px;
            border-radius: 16px;
            border: 1px solid var(--border);
            background: rgba(15, 23, 42, 0.45);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        [data-theme="light"] .log-item {
            background: rgba(249, 250, 251, 0.9);
        }

        .log-item.success {
            border-color: rgba(34, 197, 94, 0.35);
        }

        .log-item.error {
            border-color: rgba(239, 68, 68, 0.35);
        }

        .status {
            font-size: 13px;
            padding: 4px 10px;
            border-radius: 999px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-weight: 600;
        }

        .status.success {
            background: rgba(34, 197, 94, 0.15);
            color: var(--success);
        }

        .status.error {
            background: rgba(239, 68, 68, 0.15);
            color: var(--danger);
        }

        .toggle-theme {
            background: transparent;
            border: 1px solid var(--border);
            color: var(--text);
            border-radius: 999px;
            padding: 12px 18px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s ease;
        }

        .toggle-theme:hover {
            background: rgba(148, 163, 184, 0.15);
        }

        @media (max-width: 640px) {
            .card {
                padding: 24px;
            }

            .log-item {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body data-theme="dark">
    <div class="wrapper">
        <div class="card">
            <h1>Setup de Tabelas WhatsApp</h1>
            <p>
                Esta rotina cria ou atualiza as estruturas necessárias para o envio automático de notificações via WhatsApp.
                Execute apenas após configurar o bot Node.js e garantir que possui backup do banco.
            </p>
            <div class="actions">
                <form method="POST" onsubmit="return confirm('Deseja executar a criação/atualização das tabelas WhatsApp agora?');">
                    <button type="submit" class="button">
                        <span>Executar migração agora</span>
                    </button>
                </form>
                <button class="toggle-theme" type="button" id="themeToggle">Alternar tema</button>
            </div>
            <div class="muted">
                Requerimentos: MySQL 8+, permissões de ALTER/CREATE e sessão administrativa ativa.
            </div>

            <?php if ($executed): ?>
                <div style="margin-top: 28px;">
                    <h2 style="margin: 0 0 16px; font-size: 20px;">Resultado da execução</h2>
                    <div class="logs">
                        <?php foreach ($createdStructures as $entry): ?>
                            <div class="log-item success">
                                <div><?= htmlspecialchars($entry['label']) ?></div>
                                <span class="status success">ok</span>
                            </div>
                        <?php endforeach; ?>
                        <?php foreach ($errors as $error): ?>
                            <div class="log-item error">
                                <div>
                                    <strong><?= htmlspecialchars($error['label']) ?></strong><br>
                                    <span class="muted"><?= htmlspecialchars($error['error']) ?></span>
                                </div>
                                <span class="status error">erro</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <script>
        const toggle = document.getElementById('themeToggle');
        const body = document.body;
        toggle.addEventListener('click', () => {
            const isDark = body.getAttribute('data-theme') === 'dark';
            body.setAttribute('data-theme', isDark ? 'light' : 'dark');
        });
    </script>
</body>
</html>

