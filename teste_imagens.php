<?php
// Teste de imagens - Helmer Logistics
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste de Imagens - Helmer Logistics</title>
    <style>
        body { 
            background: #0A0A0A; 
            color: white; 
            font-family: Arial, sans-serif; 
            padding: 2rem; 
            margin: 0;
        }
        .test-container { 
            max-width: 1200px; 
            margin: 0 auto; 
        }
        .test-image { 
            width: 200px; 
            height: 300px; 
            border: 2px solid #FF3333; 
            border-radius: 10px; 
            margin: 1rem; 
            object-fit: cover; 
        }
        .test-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); 
            gap: 2rem; 
            margin: 2rem 0;
        }
        h1 { 
            color: #FF3333; 
            text-align: center; 
            margin-bottom: 2rem; 
        }
        .status { 
            background: rgba(22,163,74,0.2); 
            border: 1px solid rgba(22,163,74,0.3);
            border-radius: 8px; 
            padding: 1rem; 
            margin: 1rem 0; 
            text-align: center; 
        }
        .error { 
            background: rgba(255,51,51,0.2); 
            border: 1px solid rgba(255,51,51,0.3);
            border-radius: 8px; 
            padding: 1rem; 
            margin: 1rem 0; 
            text-align: center; 
        }
        .image-info {
            text-align: center;
            margin-top: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="test-container">
        <h1>🧪 Teste de Imagens do WhatsApp - Helmer Logistics</h1>
        
        <div class="status">
            ✅ Testando se as imagens carregam corretamente<br>
            📁 Caminho atual: <?php echo __DIR__; ?>/assets/images/
        </div>
        
        <?php
        $images = [
            'whatsapp-1.jpg' => 'Luiz Gabriel - Petrópolis',
            'whatsapp-2.jpg' => 'juuh santts - Ubá',
            'whatsapp-3.jpg' => 'RKZIN - Jardim Camburi',
            'whatsapp-4.jpg' => 'Vitor João - AdolfoSP',
            'whatsapp-5.jpg' => '2L CLIENTE - Entrega',
            'whatsapp-6.jpg' => 'Bada CLIENTE - Go'
        ];
        
        foreach ($images as $filename => $description) {
            $filepath = "assets/images/" . $filename;
            $exists = file_exists($filepath);
            $size = $exists ? filesize($filepath) : 0;
        ?>
        <div class="test-grid">
            <div>
                <h3><?php echo $description; ?></h3>
                <?php if ($exists): ?>
                    <img src="<?php echo $filepath; ?>" alt="<?php echo $description; ?>" class="test-image">
                    <div class="image-info">
                        ✅ Arquivo existe<br>
                        📏 Tamanho: <?php echo number_format($size); ?> bytes<br>
                        📁 <?php echo $filepath; ?>
                    </div>
                <?php else: ?>
                    <div class="error">
                        ❌ Arquivo não encontrado<br>
                        📁 <?php echo $filepath; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php } ?>
        
        <div class="status">
            📱 Se você consegue ver as imagens acima, o problema está na página sobre.php<br>
            🔧 Se não consegue ver, o problema é com o servidor ou caminhos<br>
            💡 Teste também: <a href="teste_imagens.html" style="color: #FF3333;">teste_imagens.html</a>
        </div>
    </div>
</body>
</html>
