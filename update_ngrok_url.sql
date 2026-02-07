-- Atualizar URL do Ngrok no banco de dados
-- Execute este SQL no phpMyAdmin da Hostinger

INSERT INTO bot_settings (chave, valor) VALUES 
    ('WHATSAPP_API_URL', 'https://lazaro-enforceable-finley.ngrok-free.dev')
    ON DUPLICATE KEY UPDATE valor = 'https://lazaro-enforceable-finley.ngrok-free.dev';

-- Verificar se foi atualizado
SELECT * FROM bot_settings WHERE chave = 'WHATSAPP_API_URL';
