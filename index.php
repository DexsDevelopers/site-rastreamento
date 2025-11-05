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
$expressValor = getDynamicConfig('EXPRESS_FEE_VALUE', 29.90);
$isExpress = false;

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
                        if (!empty($r['prioridade'])) { $isExpress = true; }
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

// Resposta AJAX: retorna apenas o bloco de resultados sem recarregar a página
if (isset($_POST['ajax']) && $_POST['ajax'] === '1') {
    header('Content-Type: text/html; charset=UTF-8');
    if (!empty($erroCidade)) {
        echo '<div class="results"><div class="erro">' . $erroCidade . '</div></div>';
        exit;
    }
    if (!empty($statusList)) {
        echo '<div class="results">';
        echo '<div class="results-box">';
        echo '<div class="status">📦 Status atual: ' . htmlspecialchars($statusAtualTopo) . ' — ' . htmlspecialchars($cidade);
        if ($isExpress) { echo ' <span class="badge"><i class="fas fa-bolt"></i> Entrega Expressa</span>'; }
        echo '</div>';
        echo '<div class="timeline">';
        foreach ($statusList as $etapa) {
            $cor = !empty($etapa['cor']) ? $etapa['cor'] : '#16A34A';
            echo '<div class="step" style="border-left-color:' . htmlspecialchars($cor) . ';">';
            echo '<b>' . htmlspecialchars($etapa['titulo']) . '</b>';
            echo '<small>' . htmlspecialchars($etapa['subtitulo']) . '</small>';
            echo '<i>' . date("d/m/Y H:i", strtotime($etapa['data'])) . '</i>';
            if (!empty($etapa['taxa_valor']) && !empty($etapa['taxa_pix'])) {
                echo '<div class="pix-box">';
                echo '<p>💰 <b>Taxa de distribuição nacional:</b> R$ ' . number_format($etapa['taxa_valor'], 2, ',', '.') . '</p>';
                echo '<p>Faça o pagamento via PIX:</p>';
                echo '<textarea readonly>' . htmlspecialchars($etapa['taxa_pix']) . '</textarea>';
                echo '<button onclick="navigator.clipboard.writeText(\'' . htmlspecialchars($etapa['taxa_pix'], ENT_QUOTES) . '\')">📋 Copiar chave PIX</button>';
                if ($temTaxa) {
                    echo '<div id="countdown" class="countdown"></div>';
                }
                echo '</div>';
            }
            echo '</div>';
        }
        echo '</div>'; // timeline
        if (!$temTaxa && !$isExpress) {
            echo '<div class="pix-box express-offer" style="margin-top: 1rem;">';
            echo '<p><b>Entrega Expressa (3 dias)</b> — antecipe sua entrega por apenas R$ ' . number_format($expressValor, 2, ',', '.') . '.</p>';
            echo '<p>Efetue o pagamento via PIX após solicitar. Confirmação rápida.</p>';
            echo '<button onclick="solicitarExpress(' . json_encode($codigo) . ', ' . json_encode($cidade) . ', this)">⚡ Quero entrega em 3 dias</button>';
            echo '</div>';
        }
        echo '</div>'; // results-box
        echo '</div>'; // results
        if ($temTaxa) {
            echo '<script>(function(){let tempo=' . ((int)$tempoLimite) . '*60*60;function atualizarContagem(){var el=document.getElementById("countdown");if(!el){return;}var h=Math.floor(tempo/3600),m=Math.floor((tempo%3600)/60),s=tempo%60;el.innerHTML="⏱ Tempo restante: "+String(h).padStart(2,"0")+":"+String(m).padStart(2,"0")+":"+String(s).padStart(2,"0");if(tempo>0){tempo--;setTimeout(atualizarContagem,1000)}else{el.innerHTML="❌ Prazo expirado."}}atualizarContagem()})();</script>';
        }
        exit;
    }
    // Sem erro e sem resultados
    echo '<div class="results"><div class="erro">❌ Código inexistente!</div></div>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Helmer Logistics - Acompanhamento de Recebimentos</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
/* Correção: definir :root corretamente para variáveis CSS */
:root {
    --primary: #FF3333; --primary-dark: #CC0000; --secondary: #FF6600;
    --dark: #0A0A0A; --dark-light: #1A1A1A; --light: #FFF; 
    --success: #16A34A; --gradient: linear-gradient(135deg, #FF0000 0%, #FF6600 100%);
}
:root {
    --primary: #FF3333; --primary-dark: #CC0000; --secondary: #FF6600;
    --dark: #0A0A0A; --dark-light: #1A1A1A; --light: #FFF; 
    --success: #16A34A; --gradient: linear-gradient(135deg, #FF0000 0%, #FF6600 100%);
}
body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #0A0A0A 0%, #1A0000 100%); color: var(--light); }

/* Header com Glassmorphism Moderno */
.header { 
    background: rgba(0,0,0,0.1); 
    backdrop-filter: blur(30px);
    -webkit-backdrop-filter: blur(30px);
    border: 1px solid rgba(255,255,255,0.2);
    border-radius: 25px;
    position: sticky; 
    top: 20px; 
    z-index: 1000; 
    padding: 0;
    margin: 0 20px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.1), inset 0 1px 0 rgba(255,255,255,0.1);
    transition: all 0.3s ease;
}
.nav-container { 
    max-width: 1400px; 
    margin: 0 auto; 
    padding: 0 2rem; 
    display: flex; 
    justify-content: space-between; 
    align-items: center;
    height: 70px;
}
.logo { 
    font-size: 1.8rem; 
    font-weight: 900; 
    background: linear-gradient(135deg, #FF3333, #FF6666);
    -webkit-background-clip: text; 
    -webkit-text-fill-color: transparent; 
    text-decoration: none;
    letter-spacing: -0.5px;
    text-shadow: 0 0 20px rgba(255,51,51,0.3);
}
.nav-links { 
    display: flex; 
    gap: 2.5rem; 
    align-items: center;
}
.nav-links a { 
    color: rgba(255,255,255,0.95); 
    text-decoration: none; 
    font-weight: 500; 
    font-size: 0.95rem;
    transition: all 0.3s ease;
    position: relative;
    padding: 0.5rem 0;
    text-shadow: 0 0 10px rgba(255,255,255,0.1);
}
.nav-links a:hover { 
    color: #FF3333;
    transform: translateY(-1px);
    text-shadow: 0 0 15px rgba(255,51,51,0.4);
}
.nav-links a::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 0;
    height: 2px;
    background: linear-gradient(135deg, #FF3333, #FF6666);
    transition: width 0.3s ease;
    box-shadow: 0 0 10px rgba(255,51,51,0.3);
}
.nav-links a:hover::after {
    width: 100%;
}

/* Mobile Menu com Glassmorphism Moderno */
.mobile-menu-toggle { 
    display: none; 
    background: rgba(255,255,255,0.15); 
    border: 1px solid rgba(255,255,255,0.3);
    border-radius: 12px;
    color: rgba(255,255,255,0.95); 
    font-size: 1.3rem; 
    cursor: pointer; 
    padding: 0.5rem;
    transition: all 0.3s ease;
    backdrop-filter: blur(15px);
    -webkit-backdrop-filter: blur(15px);
    box-shadow: 0 4px 16px rgba(0,0,0,0.1);
}
.mobile-menu-toggle:hover { 
    color: #FF3333; 
    background: rgba(255,51,51,0.2);
    border-color: rgba(255,51,51,0.4);
    box-shadow: 0 4px 16px rgba(255,51,51,0.2);
}
.mobile-menu { 
    display: none; 
    position: absolute; 
    top: 100%; 
    left: 0; 
    right: 0; 
    background: rgba(0,0,0,0.2); 
    backdrop-filter: blur(30px);
    -webkit-backdrop-filter: blur(30px);
    border: 1px solid rgba(255,255,255,0.2);
    border-radius: 0 0 25px 25px;
    padding: 1rem 0; 
    box-shadow: 0 8px 32px rgba(0,0,0,0.1), inset 0 1px 0 rgba(255,255,255,0.1);
    margin: 0 20px;
}
.mobile-menu.active { 
    display: block; 
    animation: slideDown 0.3s ease-out; 
}
.mobile-menu a { 
    display: block; 
    color: rgba(255,255,255,0.95); 
    text-decoration: none; 
    padding: 1rem 2rem; 
    font-weight: 500; 
    font-size: 0.95rem;
    transition: all 0.3s ease; 
    border-bottom: 1px solid rgba(255,255,255,0.05); 
    text-shadow: 0 0 10px rgba(255,255,255,0.1);
}
.mobile-menu a:hover { 
    color: #FF3333; 
    background: rgba(255,51,51,0.1); 
    transform: translateX(10px); 
    text-shadow: 0 0 15px rgba(255,51,51,0.4);
}
.mobile-menu a:last-child { 
    border-bottom: none; 
}

@keyframes slideDown {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Hero */
.hero { min-height: calc(100vh - 80px); display: flex; align-items: center; 
        padding: 4rem 2rem; background: linear-gradient(180deg, #0A0A0A 0%, #1A0000 100%); }
.hero-container { max-width: 1400px; margin: 0 auto; width: 100%; 
                  display: grid; grid-template-columns: 1fr 1fr; gap: 4rem; align-items: center; }
.hero-content h1 { font-size: 3.5rem; font-weight: 900; line-height: 1.2; margin-bottom: 1.5rem; }
/* Título moderno (hero) */
.hero-title { letter-spacing: -0.5px; text-shadow: 0 8px 30px rgba(255,51,51,0.15); }
.hero-title .title-gradient { background: var(--gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
.hero-title .title-accent { position: relative; display: inline-block; }
.hero-title .title-accent::after {
    content: '';
    position: absolute; left: 0; right: 0; bottom: -6px; height: 10px;
    background: radial-gradient(ellipse at center, rgba(255,51,51,0.5), rgba(255,102,0,0) 70%);
    filter: blur(5px);
}

/* Card de vidro para o título */
.hero-title-card {
    display: inline-block;
    padding: 1.1rem 1.4rem;
    margin-bottom: 1rem;
    border-radius: 18px;
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.18);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    box-shadow: 0 12px 32px rgba(0,0,0,0.25), inset 0 1px 0 rgba(255,255,255,0.08);
    position: relative;
    overflow: hidden;
}
.hero-title-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0; height: 3px;
    background: linear-gradient(90deg, rgba(255,51,51,0.9), rgba(255,102,0,0.9));
    opacity: 0.9;
}
.hero-title-card::after {
    content: '';
    position: absolute;
    inset: 0;
    pointer-events: none;
    border-radius: 18px;
    box-shadow: inset 0 0 0 1px rgba(255,255,255,0.05);
}
.hero-content p { font-size: 1.2rem; color: rgba(255,255,255,0.75); margin-bottom: 1.5rem; }

/* Hero extras alinhados ao sobre.php */
.hero-actions { margin-top: 0.5rem; display: flex; gap: 0.75rem; flex-wrap: wrap; }
.hero-actions .btn-hero { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.8rem 1.1rem; border-radius: 12px; font-weight: 700; text-decoration: none; color: #fff; background: var(--gradient); border: 1px solid rgba(255,51,51,0.3); transition: transform .2s ease, box-shadow .2s ease; }
.hero-actions .btn-hero.secondary { background: rgba(255,255,255,0.08); border-color: rgba(255,255,255,0.2); }
.hero-actions .btn-hero:hover { transform: translateY(-2px); box-shadow: 0 10px 24px rgba(255,51,51,0.25); }
.badges { margin-top: 1rem; display: flex; gap: 0.75rem; flex-wrap: wrap; }
.badge { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.5rem 0.9rem; border-radius: 999px; background: rgba(255,255,255,0.06); border: 1px solid rgba(255,51,51,0.2); color: rgba(255,255,255,0.85); font-size: 0.85rem; }
.badge i { color: #FF6666; }

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
.features-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 2rem; }
.feature-card { background: linear-gradient(135deg, rgba(255,51,51,0.08), rgba(255,102,0,0.08)); border: 2px solid rgba(255,51,51,0.2); 
               border-radius: 22px; padding: 2rem; text-align: center; 
               transition: transform 0.3s, box-shadow 0.3s; backdrop-filter: blur(10px); position: relative; overflow: hidden; }
.feature-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: var(--gradient); transform: scaleX(0); transition: transform 0.3s ease; }
.feature-card:hover::before { transform: scaleX(1); }
.feature-card:hover { transform: translateY(-6px); box-shadow: 0 20px 40px rgba(255,51,51,0.25); border-color: var(--primary); }
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
    .hero-content h1 { font-size: 2.2rem; }
    .hero-title-card { display: block; width: 100%; text-align: center; }
}
/* ===== RESPONSIVIDADE MOBILE ===== */
@media (max-width: 768px) {
    /* Reset e Base */
    * {
        box-sizing: border-box;
    }
    
    body {
        font-size: 14px;
        line-height: 1.4;
    }
    
    /* Header Mobile */
    .header {
        margin: 0 10px;
        top: 10px;
        border-radius: 12px;
        width: calc(100% - 20px);
    }
    
    .nav-container {
        padding: 0 15px;
        height: 60px;
    }
    
    .logo {
        font-size: 1.4rem;
    }
    
    .nav-links {
        display: none;
    }
    
    .mobile-menu-toggle {
        display: block;
        padding: 8px;
        font-size: 1.2rem;
    }
    
    .mobile-menu {
        margin: 0 10px;
        border-radius: 0 0 12px 12px;
        width: calc(100% - 20px);
    }
    
    /* Hero Section */
    .hero {
        padding: 60px 20px;
        min-height: 70vh;
    }
    
    .hero-container {
        grid-template-columns: 1fr;
        gap: 30px;
        max-width: 100%;
    }
    
    .hero-content h1 {
        font-size: 2.2rem;
        line-height: 1.2;
        margin-bottom: 20px;
    }
    
    .hero-content p { font-size: 1rem; margin-bottom: 20px; line-height: 1.5; }
    .hero-actions { justify-content: center; }
    .hero-actions .btn-hero { width: 100%; justify-content: center; }
    .badges { justify-content: center; }
    
    /* Search Container */
    .search-container, .results-box {
        padding: 25px 20px;
        border-radius: 12px;
        margin: 0;
        width: 100%;
    }
    
    .btn-group {
        flex-direction: column;
        gap: 15px;
        width: 100%;
    }
    
    .btn {
        width: 100%;
        justify-content: center;
        padding: 12px 20px;
        font-size: 1rem;
        margin: 0;
    }
    
    /* Sections */
    .section {
        padding: 40px 20px;
    }
    
    .container {
        padding: 0;
        max-width: 100%;
    }
    
    .section-title {
        font-size: 1.8rem;
        margin-bottom: 30px;
        text-align: center;
    }
    
    /* Features Grid */
    .features-grid {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .feature-card {
        padding: 25px 20px;
        border-radius: 12px;
        margin: 0;
        width: 100%;
    }
    
    .feature-icon {
        font-size: 2rem;
        margin-bottom: 15px;
    }
    
    .feature-title {
        font-size: 1.2rem;
        margin-bottom: 10px;
    }
    
    .feature-description {
        font-size: 0.95rem;
        line-height: 1.5;
    }
}

@media (max-width: 480px) {
    /* Header Small Mobile */
    .header {
        margin: 0 5px;
        top: 5px;
        border-radius: 10px;
        width: calc(100% - 10px);
    }
    
    .nav-container {
        padding: 0 10px;
        height: 55px;
    }
    
    .logo {
        font-size: 1.2rem;
    }
    
    .mobile-menu {
        margin: 0 5px;
        border-radius: 0 0 10px 10px;
        width: calc(100% - 10px);
    }
    
    /* Hero Small Mobile */
    .hero {
        padding: 40px 15px;
        min-height: 60vh;
    }
    
    .hero-content h1 { font-size: 1.6rem; margin-bottom: 12px; }
    .hero-title-card { padding: 0.9rem 1rem; border-radius: 14px; }
    
    .hero-content p { font-size: 0.9rem; margin-bottom: 18px; }
    .badges { gap: 0.5rem; }
    
    /* Search Small Mobile */
    .search-container, .results-box {
        padding: 20px 15px;
        border-radius: 10px;
    }
    
    .btn {
        padding: 10px 16px;
        font-size: 0.9rem;
    }
    
    /* Sections Small Mobile */
    .section {
        padding: 30px 15px;
    }
    
    .section-title {
        font-size: 1.5rem;
        margin-bottom: 25px;
    }
    
    /* Features Small Mobile */
    .features-grid {
        gap: 15px;
    }
    
    .feature-card {
        padding: 20px 15px;
        border-radius: 10px;
    }
    
    .feature-icon {
        font-size: 1.8rem;
        margin-bottom: 12px;
    }
    
    .feature-title {
        font-size: 1.1rem;
        margin-bottom: 8px;
    }
    
    .feature-description {
        font-size: 0.9rem;
        line-height: 1.4;
    }
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
        <button class="mobile-menu-toggle" onclick="toggleMobileMenu()">
            <i class="fas fa-bars"></i>
        </button>
        <nav class="mobile-menu" id="mobileMenu">
            <a href="index.php">Início</a>
            <a href="sobre.php">Sobre</a>
        </nav>
    </div>
</header>

<section class="hero">
    <div class="hero-container">
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
        <!-- Resultados AJAX sem recarregar -->
        <div id="ajaxResults"></div>
        
        <div class="hero-content">
            <div class="hero-title-card">
                <h1 class="hero-title"><span class="title-accent">Acompanhe</span> seus <span class="title-gradient">Recebimentos</span> em Tempo Real</h1>
            </div>
            <p>Verifique o status dos seus recebimentos com tecnologia de ponta e acompanhamento em tempo real</p>
            
            <div class="hero-actions" style="justify-content: flex-start;">
                <a href="sobre.php" class="btn-hero secondary"><i class="fas fa-info-circle"></i> Sobre nós</a>
                <a href="indicacao.php" class="btn-hero"><i class="fas fa-users"></i> Indicar Amigo</a>
            </div>
            <div class="badges">
                <span class="badge"><i class="fas fa-check-circle"></i> 98.7% de Satisfação</span>
                <span class="badge"><i class="fas fa-truck"></i> 5.247 Entregas</span>
                <span class="badge"><i class="fas fa-map-marker-alt"></i> 247 Cidades</span>
            </div>
            
            <div class="referral-box" style="margin-top: 2rem;">
                <i class="fas fa-star" style="font-size: 2rem; color: #FF3333; margin-bottom: 1rem;"></i>
                <h3>Sistema de Indicações — Entrega Prioritária (2 dias)</h3>
                <p>Indique um amigo e garanta <strong>entrega prioritária em 2 dias</strong> para o seu próximo envio.</p>
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
        <?php if (!$temTaxa): ?>
            <div class="pix-box express-offer" style="margin-top: 1rem;">
                <p><b>Entrega Expressa (3 dias)</b> — antecipe sua entrega por apenas R$ <?= number_format($expressValor, 2, ',', '.') ?>.</p>
                <p>Efetue o pagamento via PIX após solicitar. Confirmação rápida.</p>
                <button onclick="solicitarExpress(<?= json_encode($codigo) ?>,<?= json_encode($cidade) ?>, this)">⚡ Quero entrega em 3 dias</button>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php
// Exibir popup explicativo automaticamente no render completo quando houver taxa
if (!empty($statusList) && $temTaxa && !empty($popupTaxaEnabled)) {
    $taxaValorPrimeira = null;
    foreach ($statusList as $etapa) {
        if (!empty($etapa['taxa_valor'])) {
            $taxaValorPrimeira = number_format($etapa['taxa_valor'], 2, ',', '.');
            break;
        }
    }
    if ($taxaValorPrimeira) {
        echo "<script>document.addEventListener('DOMContentLoaded',function(){ if (typeof showTaxaPopup==='function') { showTaxaPopup('R$ {$taxaValorPrimeira}'); }});</script>";
    } else {
        echo "<script>document.addEventListener('DOMContentLoaded',function(){ if (typeof showTaxaPopup==='function') { showTaxaPopup(); }});</script>";
    }
}
?>

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
// Valor global para inicialização de contagem no fluxo AJAX
window.TEMPO_LIMITE_HORAS = <?= (int)$tempoLimite ?>;

function showIndicacaoInfo() {
    const modal = document.createElement('div');
    modal.className = 'custom-overlay-modal';
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
            <button onclick="closeModalFromChild(this)" style="width: 100%; padding: 15px;
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
<script>
// Função utilitária para fechar modais customizados
function closeModalFromChild(childEl) {
    try {
        const overlay = childEl.closest('.custom-overlay-modal');
        if (overlay) overlay.remove();
    } catch (_) {}
}

// Mobile Menu Toggle
function toggleMobileMenu() {
    const mobileMenu = document.getElementById('mobileMenu');
    const toggle = document.querySelector('.mobile-menu-toggle i');
    
    if (mobileMenu.classList.contains('active')) {
        mobileMenu.classList.remove('active');
        toggle.classList.remove('fa-times');
        toggle.classList.add('fa-bars');
    } else {
        mobileMenu.classList.add('active');
        toggle.classList.remove('fa-bars');
        toggle.classList.add('fa-times');
    }
}

// Close mobile menu when clicking outside
document.addEventListener('click', function(event) {
    const mobileMenu = document.getElementById('mobileMenu');
    const toggle = document.querySelector('.mobile-menu-toggle');
    
    if (!mobileMenu.contains(event.target) && !toggle.contains(event.target)) {
        mobileMenu.classList.remove('active');
        const toggleIcon = document.querySelector('.mobile-menu-toggle i');
        toggleIcon.classList.remove('fa-times');
        toggleIcon.classList.add('fa-bars');
    }
});

// Close mobile menu when clicking on a link
document.querySelectorAll('.mobile-menu a').forEach(link => {
    link.addEventListener('click', function() {
        const mobileMenu = document.getElementById('mobileMenu');
        const toggle = document.querySelector('.mobile-menu-toggle i');
        mobileMenu.classList.remove('active');
        toggle.classList.remove('fa-times');
        toggle.classList.add('fa-bars');
    });
});

// Submissão AJAX do formulário de rastreio
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form[method="POST"][action="index.php"]');
    const results = document.getElementById('ajaxResults');
    const submitBtn = form ? form.querySelector('button[type="submit"]') : null;

    if (form && results && submitBtn) {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            const codigo = (form.querySelector('#codigo') || {}).value || '';
            const cidade = (form.querySelector('#cidade') || {}).value || '';
            if (!codigo || !cidade) return;

            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Consultando...';
            results.innerHTML = '';

            try {
                const response = await fetch('index.php', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ codigo, cidade, ajax: '1' })
                });
                const html = await response.text();
                results.innerHTML = html;
                results.scrollIntoView({ behavior: 'smooth', block: 'start' });

                // Iniciar popup e countdown se houver taxa no retorno AJAX
                try {
                    const pixTextarea = results.querySelector('.pix-box textarea');
                    if (pixTextarea && typeof showTaxaPopup === 'function') {
                        let valorTexto = null;
                        const p = pixTextarea.closest('.pix-box') ? pixTextarea.closest('.pix-box').querySelector('p') : null;
                        if (p && /R\$\s*[0-9\.,]+/.test(p.textContent)) {
                            const m = p.textContent.match(/R\$\s*[0-9\.,]+/);
                            valorTexto = m ? m[0] : null;
                        }
                        showTaxaPopup(valorTexto);
                    }
                    startCountdownIfPresent();
                } catch (_) { /* silencioso */ }
            } catch (err) {
                results.innerHTML = '<div class="results"><div class="erro">❌ Erro ao consultar. Tente novamente.</div></div>';
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        });
    }

    // Inicializa countdown quando houver elemento no DOM (ex.: respostas AJAX)
    function startCountdownIfPresent() {
        const el = document.getElementById('countdown');
        if (!el || window.__countdownStarted) return;
        window.__countdownStarted = true;
        let tempo = (typeof window.TEMPO_LIMITE_HORAS !== 'undefined' ? window.TEMPO_LIMITE_HORAS : 24) * 60 * 60;
        (function tick(){
            const horas = Math.floor(tempo / 3600);
            const minutos = Math.floor((tempo % 3600) / 60);
            const segundos = tempo % 60;
            el.innerHTML = '⏱ Tempo restante: ' + String(horas).padStart(2,'0') + ':' + String(minutos).padStart(2,'0') + ':' + String(segundos).padStart(2,'0');
            if (tempo > 0) { tempo--; setTimeout(tick, 1000); } else { el.innerHTML = '❌ Prazo expirado.'; }
        })();
    }
});
</script>

<script>
// Solicitar upgrade express (3 dias)
async function solicitarExpress(codigo, cidade, btn) {
    try {
        if (btn) { btn.disabled = true; btn.innerText = 'Solicitando...'; }
        const resp = await fetch('solicitar_express.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ codigo, cidade })
        });
        const data = await resp.json();
        if (!data.success) throw new Error(data.message || 'Falha ao solicitar.');

        // Recarregar resultados via AJAX para exibir PIX e contagem
        try {
            const results = document.getElementById('ajaxResults');
            const htmlResp = await fetch('index.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ codigo, cidade, ajax: '1' })
            });
            const html = await htmlResp.text();
            if (results) {
                results.innerHTML = html;
                results.scrollIntoView({ behavior: 'smooth', block: 'start' });
            } else {
                // fallback simples
                location.reload();
            }
        } catch (_) { location.reload(); }
    } catch (e) {
        alert(e.message || 'Erro ao solicitar entrega expressa.');
    } finally {
        if (btn) { btn.disabled = false; btn.innerText = '⚡ Quero entrega em 3 dias'; }
    }
}
</script>

<script>
// Popup explicativo da taxa (cliente)
function showTaxaPopup(valorTexto) {
    const modal = document.createElement('div');
    modal.className = 'custom-overlay-modal';
    modal.style.cssText = `position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0, 0, 0, 0.9); z-index: 10000; display: flex; justify-content: center;
        align-items: center; padding: 20px;`;

    const valorLinha = valorTexto ? `O valor definido pelo Correios para o seu envio foi de <strong>${valorTexto}</strong>, e, após o pagamento, a liberação acontece rapidamente e seu produto segue normalmente para o endereço informado.` : `O valor definido pelo Correios para o seu envio está indicado acima. Após o pagamento, a liberação acontece rapidamente e seu produto segue normalmente para o endereço informado.`;

    modal.innerHTML = `
        <div style="background: linear-gradient(135deg, #0a0a0a, #1a0000); padding: 32px;
            border-radius: 18px; max-width: 820px; width: 100%; border: 2px solid #ff3333; color: #fff;">
            <h2 style="color: #ff3333; text-align: center; margin-bottom: 18px; font-size: 1.6rem;">
                <i class="fas fa-info-circle"></i> Sobre a taxa apresentada
            </h2>
            <div style="display: grid; gap: 10px; line-height: 1.5;">
                <p>Gostaria de esclarecer sobre a taxa que apareceu no seu pedido. O Correios, em determinados envios, aplica uma taxa de despacho/postagem para liberar o produto no sistema logístico. Essa taxa é um procedimento obrigatório do Correios, não sendo uma cobrança feita pela nossa loja.</p>
                <p>Ela serve para cobrir os custos operacionais do Correios no processo de triagem, segurança e manuseio da encomenda. Sem esse pagamento, o pedido fica bloqueado e não segue para entrega.</p>
                <p>${valorLinha}</p>
                <p>Estamos à disposição para auxiliar em qualquer dúvida ou no passo a passo desse processo. Nosso objetivo é garantir que você receba sua compra da forma mais rápida e segura possível.</p>
            </div>
            <div style="display:flex; gap:10px; margin-top: 20px;">
                <button onclick="closeModalFromChild(this)" style="flex:1; padding: 12px 16px; background: var(--gradient); border: none; border-radius: 10px; color: white; font-weight: 700; cursor: pointer;">Entendi</button>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
    modal.addEventListener('click', (e) => { if (e.target === modal) modal.remove(); });
}
</script>


