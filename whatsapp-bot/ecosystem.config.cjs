module.exports = {
    apps: [
        {
            name: 'whatsapp-bot-rastreamento',
            script: 'index.js',
            instances: 1,
            exec_mode: 'fork', // Modo fork é mais estável para Baileys
            watch: false,
            max_memory_restart: '1G', // Reiniciar se usar mais de 1GB
            env_production: {
                NODE_ENV: 'production',
                PORT: 3000 // Hostinger geralmente injeta a porta correta, mas deixamos um padrão
            },
            error_file: './logs/err.log',
            out_file: './logs/out.log',
            log_date_format: 'YYYY-MM-DD HH:mm:ss',
            merge_logs: true,
            autorestart: true,
            exp_backoff_restart_delay: 100, // Delay progressivo em caso de crash
            wait_ready: true, // Espera o app sinalizar ready
            listen_timeout: 10000, // 10 segundos para o app subir
            kill_timeout: 3000 // Tempo para fechar conexões
        }
    ]
};
