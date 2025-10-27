<?php
/**
 * Sistema de Rastreamento Helmer Logistics
 * Serviços Especializados de Entrega
 */

// Headers para evitar cache
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Incluir configurações
require_once 'includes/config.php';
require_once 'includes/db_connect.php';

$codigo = $cidade = "";
$statusList = [];
$erroCidade = "";
$statusAtualTopo = "";
$temTaxa = false;
$tempoLimite = 24;

if (isset($_POST['codigo']) && isset($_POST['cidade'])) {
    $codigo = strtoupper(trim(sanitizeInput($_POST['codigo'])));
    $cidade = trim(sanitizeInput($_POST['cidade']));
    
    if (empty($codigo) || empty($cidade)) {
        $erroCidade = "❌ Código e cidade são obrigatórios!";
    } else {
        try {
            $sql = "SELECT * FROM rastreios_status WHERE UPPER(TRIM(codigo)) = ? ORDER BY data ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$codigo]);
            $results = $stmt->fetchAll();

            if (!empty($results)) {
                $rows = [];
                foreach ($results as $row) {
                    if (strtotime($row['data']) <= time()) {
                        $rows[] = $row;
                        if (strpos(strtolower($row['titulo']), 'distribuição') !== false) break;
                    }
                }

                if (!empty($rows) && strcasecmp(trim($rows[0]['cidade']), $cidade) === 0) {
                    $statusList = $rows;
                    foreach ($rows as $r) {
                        if (!empty($r['taxa_valor']) && !empty($r['taxa_pix'])) {
                            $temTaxa = true;
                        }
                    }
                    $statusAtualTopo = $temTaxa ? "⏳ Aguardando pagamento da taxa" : end($statusList)['status_atual'];
                } else {
                    $erroCidade = "⚠️ A cidade informada não confere com este código!";
                }
            } else {
                $erroCidade = "❌ Código inexistente!";
            }
        } catch (PDOException $e) {
            writeLog("Erro na consulta: " . $e->getMessage(), 'ERROR');
            $erroCidade = "❌ Erro interno. Tente novamente.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Helmer Logistics - Acompanhamento de Envios</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
:root {
    --primary: #FF3333; --primary-dark: #CC0000; --secondary: #FF6600;
    --dark: #0A0A0A; --dark-light: #1A1A1A; --light: #FFF; 
    --success: #16A34A; --gradient: linear-gradient(135deg, #FF0000 0%, #FF6600 100%);
}
body { font-family: 'Inter', sans-serif; background: var(--dark); color: var(--light); }

/* Header */
.header { background: rgba(10, 10, 10, 0.95); backdrop-filter: blur(10px); 
          border-bottom: 1px solid rgba(255, 51, 51, 0.1); position: sticky; 
          top: 0; z-index: 1000; padding: 1rem 0; }
.nav-container { max-width: 1400px; margin: 0 auto; padding: 0 2rem; 
                 display: flex; justify-content: space-between; align-items: center; }
.logo { font-size: 1.5rem; font-weight: 800; background: var(--gradient); 
        -webkit-background-clip: text; -webkit-text-fill-color: transparent; 
        text-decoration: none; }
.nav-links { display: flex; gap: 2rem; }
.nav-links a { color: rgba(255,255,255,0.8); text-decoration: none; 
               font-weight: 500; transition: 0.3s; }
.nav-links a:hover { color: var(--primary); }

/* Hero */
.hero { min-height: calc(100vh - 80px); display: flex; align-items: center; 
        padding: 4rem 2rem; background: linear-gradient(180deg, #0A0A0A 0%, #1A0000 100%); }
.hero-container { max-width: 1400px; margin: 0 auto; width: 100%; 
                  display: grid; grid-template-columns: 1fr 1fr; gap: 4rem; align-items: center; }
.hero-content h1 { font-size: 3.5rem; font-weight: 900; line-height: 1.2; 
                   margin-bottom: 1.5rem; background: var(--gradient); 
                   -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
.hero-content p { font-size: 1.25rem; color: rgba(255,255,255,0.7); margin-bottom: 2rem; }

/* Search Box */
.search-container { background: rgba(255,255,255,0.05); backdrop-filter: blur(10px); 
                    border-radius: 24px; padding: 2rem; border: 1px solid rgba(255,51,51,0.2); }
.form-group { margin-bottom: 1.5rem; }
.form-group label { display: block; margin-bottom: 0.5rem; color: rgba(255,255,255,0.8); font-weight: 500; }
.form-group input { width: 100%; padding: 1rem 1.5rem; background: rgba(255,255,255,0.05); 
                    border: 2px solid rgba(255,51,51,0.2); border-radius: 12px; 
                    color: var(--light); font-size: 1rem; }
.form-group input:focus { outline: none; border-color: var(--primary); 
                          box-shadow: 0 0 0 4px rgba(255,51,51,0.1); }
.btn-primary { width: 100%; padding: 1rem 2rem; background: var(--gradient); 
               border: none; border-radius: 12px; color: var(--light); 
               font-size: 1rem; font-weight: 600; cursor: pointer; 
               transition: transform 0.3s, box-shadow 0.3s; }
.btn-primary:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(255,51,51,0.3); }

/* Referral Box */
.referral-box { background: linear-gradient(135deg, rgba(255,51,51,0.1) 0%, rgba(255,102,0,0.1) 100%); 
                border: 2px solid var(--primary); border-radius: 20px; padding: 2rem; 
                margin: 2rem 0; text-align: center; }
.referral-box h3 { color: var(--primary); margin-bottom: 1rem; font-size: 1.5rem; }
.referral-box p { color: rgba(255,255,255,0.9); margin-bottom: 1.5rem; }
.btn-group { display: flex; gap: 1rem; flex-wrap: wrap; justify-content: center; }
.btn { padding: 0.75rem 1.5rem; border: none; border-radius: 10px; 
       font-weight: 600; cursor: pointer; transition: 0.3s; text-decoration: none;
       display: inline-flex; align-items: center; gap: 0.5rem; }
.btn-referral { background: var(--gradient); color: var(--light); }
.btn-referral:hover { transform: scale(1.05); box-shadow: 0 5px 20px rgba(255,51,51,0.4); }
.btn-info { background: linear-gradient(135deg, #16A34A 0%, #059669 100%); color: var(--light); }
.btn-info:hover { transform: scale(1.05); box-shadow: 0 5px 20px rgba(22,163,74,0.4); }

/* Features */
.features { max-width: 1400px; margin: 6rem auto; padding: 0 2rem; }
.features h2 { text-align: center; font-size: 2.5rem; margin-bottom: 3rem; 
               background: var(--gradient); -webkit-background-clip: text; 
               -webkit-text-fill-color: transparent; font-weight: 800; }
.features-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem; }
.feature-card { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,51,51,0.1); 
                border-radius: 20px; padding: 2rem; text-align: center; 
                transition: transform 0.3s, box-shadow 0.3s; }
.feature-card:hover { transform: translateY(-5px); box-shadow: 0 10px 30px rgba(255,51,51,0.2); }
.feature-icon { width: 60px; height: 60px; background: var(--gradient); 
                border-radius: 12px; display: flex; align-items: center; 
                justify-content: center; margin: 0 auto 1.5rem; }
.feature-card h3 { color: var(--light); margin-bottom: 1rem; font-size: 1.25rem; }
.feature-card p { color: rgba(255,255,255,0.7); }

/* Results */
.results { max-width: 1400px; margin: 3rem auto; padding: 0 2rem; }
.results-box { background: rgba(255,255,255,0.05); backdrop-filter: blur(10px); 
               border-radius: 24px; padding: 2rem; border: 1px solid rgba(255,51,51,0.2); }
.erro { background: rgba(255,68,68,0.1); border: 2px solid #ff4444; border-radius: 12px; 
        padding: 1rem; color: #ff4444; margin-bottom: 1rem; text-align: center; }
.status { background: linear-gradient(135deg, rgba(255,51,51,0.2), rgba(255,102,0,0.2)); 
          border: 2px solid var(--primary); border-radius: 12px; padding: 1rem; 
          color: var(--light); font-weight: 600; margin-bottom: 2rem; text-align: center; }
.timeline { display: flex; flex-direction: column; gap: 1rem; }
.step { background: rgba(255,255,255,0.03); border-left: 4px solid var(--success); 
        border-radius: 12px; padding: 1.5rem; position: relative; }
.step b { display: block; color: var(--light); font-size: 1.1rem; margin-bottom: 0.5rem; }
.step small { display: block; color: rgba(255,255,255,0.7); margin-bottom: 0.5rem; }
.step i { color: rgba(255,255,255,0.5); font-size: 0.9rem; }
.pix-box { background: rgba(255,51,51,0.1); border: 2px solid var(--primary); 
           border-radius: 12px; padding: 1rem; margin-top: 1rem; }
.pix-box p { color: var(--light); margin-bottom: 0.5rem; }
.pix-box textarea { width: 100%; padding: 0.75rem; border-radius: 8px; 
                    background: rgba(0,0,0,0.3); color: var(--light); border: none; 
                    resize: none; font-size: 0.9rem; }
.pix-box button { margin-top: 0.5rem; padding: 0.75rem 1.5rem; background: var(--gradient); 
                  border: none; border-radius: 8px; color: var(--light); cursor: pointer; 
                  font-weight: 600; }
.countdown { color: #ffcc00; font-size: 1.1rem; font-weight: 700; margin-top: 0.5rem; }

@media (max-width: 1024px) { 
    .hero-container { grid-template-columns: 1fr; gap: 2rem; } 
    .hero-content h1 { font-size: 2.5rem; }
}
@media (max-width: 768px) {
    .nav-container { padding: 0 1rem; }
    .nav-links { display: none; }
    .hero { padding: 2rem 1rem; }
    .search-container, .results-box { padding: 1.5rem; }
    .btn-group { flex-direction: column; }
    .btn { width: 100%; justify-content: center; }
}
</style>
</head>
<body>
<header class="header">
    <div class="nav-container">
        <a href="index.php" class="logo">Helmer Logistics</a>
        <nav class="nav-links">
            <a href="index.php">Início</a>
            <a href="sobre.php">Sobre</a>
        </nav>
    </div>
</header>

<section class="hero">
    <div class="hero-container">
        <div class="hero-content">
            <h1>Acompanhe seus Envios em Tempo Real</h1>
            <p>Verifique o status dos seus envios com tecnologia de ponta e acompanhamento em tempo real</p>
            
            <div class="referral-box">
                <i class="fas fa-star" style="font-size: 2rem; color: #FF3333; margin-bottom: 1rem;"></i>
                <h3>Sistema de Indicações</h3>
                <p>Indique um amigo e ganhe <strong>entrega prioritária em 2 dias!</strong></p>
                <div class="btn-group">
                    <a href="indicacao.php" class="btn btn-referral" target="_blank">
                        <i class="fas fa-users"></i> Indicar Amigo
                    </a>
                    <button onclick="showIndicacaoInfo()" class="btn btn-info">
                        <i class="fas fa-info-circle"></i> Como Funciona
                    </button>
                </div>
            </div>
        </div>
        
        <div class="search-container">
            <h2 style="margin-bottom: 1.5rem; color: var(--light);">
                <i class="fas fa-search"></i> Rastrear Envio
            </h2>
            <form method="POST" action="index.php">
                <div class="form-group">
                    <label for="codigo">Código de Rastreamento</label>
                    <input type="text" name="codigo" id="codigo" placeholder="Digite o código" maxlength="12" required>
                </div>
                <div class="form-group">
                    <label for="cidade">Cidade</label>
                    <input type="text" name="cidade" id="cidade" placeholder="Digite a cidade" required>
                </div>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-search"></i> Rastrear
                </button>
            </form>
        </div>
    </div>
</section>

<?php if (!empty($erroCidade)): ?>
<div class="results">
    <div class="erro"><?= $erroCidade ?></div>
</div>
<?php endif; ?>

<?php if (!empty($statusList)): ?>
<div class="results">
    <div class="results-box">
        <div class="status">
            📦 Status atual: <?= $statusAtualTopo ?> — <?= $cidade ?>
        </div>
        <div class="timeline">
            <?php foreach ($statusList as $etapa): ?>
                <div class="step" style="border-left-color:<?= $etapa['cor'] ?? '#16A34A'; ?>;">
                    <b><?= htmlspecialchars($etapa['titulo']) ?></b>
                    <small><?= htmlspecialchars($etapa['subtitulo']) ?></small>
                    <i><?= date("d/m/Y H:i", strtotime($etapa['data'])) ?></i>

                    <?php if (!empty($etapa['taxa_valor']) && !empty($etapa['taxa_pix'])): ?>
                        <div class="pix-box">
                            <p>💰 <b>Taxa de distribuição nacional:</b> R$ <?= number_format($etapa['taxa_valor'], 2, ',', '.') ?></p>
                            <p>Faça o pagamento via PIX:</p>
                            <textarea readonly><?= htmlspecialchars($etapa['taxa_pix']) ?></textarea>
                            <button onclick="navigator.clipboard.writeText('<?= htmlspecialchars($etapa['taxa_pix'], ENT_QUOTES) ?>')">
                                📋 Copiar chave PIX
                            </button>
                            <?php if ($temTaxa): ?>
                                <div id="countdown" class="countdown"></div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<section class="features">
    <h2>Por que escolher Helmer Logistics?</h2>
    <div class="features-grid">
        <div class="feature-card">
            <div class="feature-icon"><i class="fas fa-rocket" style="font-size: 1.5rem;"></i></div>
            <h3>Entrega Rápida</h3>
            <p>Entrega prioritária em até 2 dias para indicações</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon"><i class="fas fa-shield-alt" style="font-size: 1.5rem;"></i></div>
            <h3>Seguro e Confiável</h3>
            <p>Seus pacotes protegidos com tecnologia avançada</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon"><i class="fas fa-map-marked-alt" style="font-size: 1.5rem;"></i></div>
            <h3>Cobertura Nacional</h3>
            <p>Entregas em todo o Brasil com rastreamento em tempo real</p>
        </div>
    </div>
</section>

<?php if ($temTaxa): ?>
<script>
let tempo = <?= $tempoLimite ?> * 60 * 60;
function atualizarContagem() {
    let horas = Math.floor(tempo / 3600);
    let minutos = Math.floor((tempo % 3600) / 60);
    let segundos = tempo % 60;
    document.getElementById("countdown").innerHTML =
        "⏱ Tempo restante: " + String(horas).padStart(2, '0') + ":" +
        String(minutos).padStart(2, '0') + ":" + String(segundos).padStart(2, '0');
    if (tempo > 0) { tempo--; setTimeout(atualizarContagem, 1000); }
    else { document.getElementById("countdown").innerHTML = "❌ Prazo expirado."; }
}
atualizarContagem();
</script>
<?php endif; ?>

<script>
function showIndicacaoInfo() {
    const modal = document.createElement('div');
    modal.style.cssText = `position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0, 0, 0, 0.9); z-index: 10000; display: flex; justify-content: center;
        align-items: center; padding: 20px;`;
    
    modal.innerHTML = `
        <div style="background: linear-gradient(135deg, #0a0a0a, #1a0000); padding: 40px;
            border-radius: 20px; max-width: 700px; width: 100%; border: 2px solid #ff3333;">
            <h2 style="color: #ff3333; text-align: center; margin-bottom: 30px; font-size: 2rem;">
                <i class="fas fa-star"></i> Sistema de Indicação
            </h2>
            <div style="background: rgba(255, 51, 51, 0.1); padding: 20px; border-radius: 12px; margin-bottom: 20px;">
                <h3 style="color: #ff3333; margin-bottom: 15px;">Como Funciona:</h3>
                <p style="color: #fff; margin-bottom: 10px;">1️⃣ Você indica um amigo</p>
                <p style="color: #fff; margin-bottom: 10px;">2️⃣ Seu amigo compra no mesmo dia</p>
                <p style="color: #fff; margin-bottom: 10px;">3️⃣ A entrega será feita em apenas <strong>2 dias</strong></p>
                <p style="color: #fff;">4️⃣ Prioridade total no sistema</p>
            </div>
            <button onclick="this.closest('div').remove()" style="width: 100%; padding: 15px;
                background: var(--gradient); border: none; border-radius: 10px; color: white;
                font-weight: 600; cursor: pointer; font-size: 1.1rem;">
                Fechar
            </button>
        </div>
    `;
    document.body.appendChild(modal);
    modal.addEventListener('click', (e) => { if (e.target === modal) modal.remove(); });
}
</script>
</body>
</html>
