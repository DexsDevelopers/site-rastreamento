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
require_once 'includes/rastreio_media.php';

// Verificar conexão com banco PRIMEIRO
if (!isset($pdo) || $pdo === null) {
    die("❌ Erro: Não foi possível conectar ao banco de dados. Verifique as configurações em includes/db_connect.php");
}

// Função para obter configuração da homepage
function getHomepageConfig($pdo, $chave, $default = '')
{
    try {
        // Verificar se a tabela existe
        $stmt = $pdo->query("SHOW TABLES LIKE 'homepage_config'");
        if ($stmt->rowCount() === 0) {
            return $default; // Tabela não existe ainda
        }

        $result = fetchOne($pdo, "SELECT valor FROM homepage_config WHERE chave = ?", [$chave]);
        return $result && isset($result['valor']) ? $result['valor'] : $default;
    } catch (Exception $e) {
        // Em caso de erro, retornar valor padrão
        return $default;
    }
}

// Carregar configurações da homepage
$nomeEmpresa = getHomepageConfig($pdo, 'nome_empresa', 'Helmer Logistics');
$tituloHero = getHomepageConfig($pdo, 'titulo_hero', 'Acompanhe seus Recebimentos em Tempo Real');
$descricaoHero = getHomepageConfig($pdo, 'descricao_hero', 'Verifique o status dos seus recebimentos com tecnologia de ponta e acompanhamento em tempo real');
$badgeSatisfacao = getHomepageConfig($pdo, 'badge_satisfacao', '98.7% de Satisfação');
$badgeEntregas = getHomepageConfig($pdo, 'badge_entregas', '5.247 Entregas');
$badgeCidades = getHomepageConfig($pdo, 'badge_cidades', '247 Cidades');

// Seção "Como funciona"
$howItWorksTitle = getHomepageConfig($pdo, 'how_it_works_title', 'Como funciona');
$feature1Title = getHomepageConfig($pdo, 'feature1_title', '1) Rastreie');
$feature1Description = getHomepageConfig($pdo, 'feature1_description', 'Digite o código e a cidade para validar e ver o status do envio.');
$feature2Title = getHomepageConfig($pdo, 'feature2_title', '2) Acompanhe');
$feature2Description = getHomepageConfig($pdo, 'feature2_description', 'Veja a linha do tempo com todas as etapas do seu recebimento.');
$feature3Title = getHomepageConfig($pdo, 'feature3_title', '3) Entrega Expressa');
$feature3Description = getHomepageConfig($pdo, 'feature3_description', 'Antecipe em 3 dias com confirmação rápida por PIX, quando disponível.');

// Prova social
$socialProof1Title = getHomepageConfig($pdo, 'social_proof1_title', 'Satisfação 98,7%');
$socialProof1LinkText = getHomepageConfig($pdo, 'social_proof1_link_text', 'Ver metodologia');
$socialProof2Title = getHomepageConfig($pdo, 'social_proof2_title', '+5.247 Entregas');
$socialProof2LinkText = getHomepageConfig($pdo, 'social_proof2_link_text', 'Ver histórico');
$socialProof3Title = getHomepageConfig($pdo, 'social_proof3_title', 'Confiabilidade');
$socialProof3LinkText = getHomepageConfig($pdo, 'social_proof3_link_text', 'Política e garantias');

$codigo = $cidade = "";
$statusList = [];
$erroCidade = "";
$statusAtualTopo = "";
$temTaxa = false;
$tempoLimite = 24;
$fotoPedido = null;
$fotoPedidoSrc = null;
$expressValor = getDynamicConfig('EXPRESS_FEE_VALUE', 29.90);
$isExpress = false;
$autoLoadFromUrl = false;

// Verificar se há código na URL (GET) e buscar cidade automaticamente
if (isset($_GET['codigo']) && !isset($_POST['codigo'])) {
    $codigoFromUrl = strtoupper(trim(sanitizeInput($_GET['codigo'])));
    if (!empty($codigoFromUrl)) {
        try {
            $sql = "SELECT DISTINCT cidade FROM rastreios_status WHERE UPPER(TRIM(codigo)) = ? AND cidade IS NOT NULL AND cidade != '' LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$codigoFromUrl]);
            $row = $stmt->fetch();

            if ($row && !empty($row['cidade'])) {
                $codigo = $codigoFromUrl;
                $cidade = trim($row['cidade']);
                $autoLoadFromUrl = true;

                // Executar consulta automaticamente
                $sql = "SELECT * FROM rastreios_status WHERE UPPER(TRIM(codigo)) = ? ORDER BY data ASC";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$codigo]);
                $results = $stmt->fetchAll();

                if (!empty($results)) {
                    $rows = [];
                    foreach ($results as $row) {
                        if (strtotime($row['data']) <= time()) {
                            $rows[] = $row;
                            if (strpos(strtolower($row['titulo']), 'distribuição') !== false)
                                break;
                        }
                    }

                    if (!empty($rows) && strcasecmp(trim($rows[0]['cidade']), $cidade) === 0) {
                        $statusList = $rows;
                        foreach ($rows as $r) {
                            if (!empty($r['taxa_valor']) && !empty($r['taxa_pix'])) {
                                $temTaxa = true;
                            }
                            if (!empty($r['prioridade'])) {
                                $isExpress = true;
                            }
                        }
                        $statusAtualTopo = $temTaxa ? "⏳ Aguardando pagamento da taxa" : end($statusList)['status_atual'];
                        $fotoPedido = getRastreioFoto($pdo, $codigo);
                        if ($fotoPedido) {
                            $cacheBuster = @filemtime($fotoPedido['absolute']) ?: time();
                            $fotoPedidoSrc = $fotoPedido['url'] . '?v=' . $cacheBuster;
                        } else {
                            $fotoPedidoSrc = null;
                        }
                    } else {
                        $erroCidade = "⚠️ A cidade informada não confere com este código!";
                    }
                } else {
                    $erroCidade = "❌ Código inexistente!";
                }
            }
        } catch (PDOException $e) {
            writeLog("Erro ao buscar código da URL: " . $e->getMessage(), 'ERROR');
        }
    }
}

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
                        if (strpos(strtolower($row['titulo']), 'distribuição') !== false)
                            break;
                    }
                }

                if (!empty($rows) && strcasecmp(trim($rows[0]['cidade']), $cidade) === 0) {
                    $statusList = $rows;
                    foreach ($rows as $r) {
                        if (!empty($r['taxa_valor']) && !empty($r['taxa_pix'])) {
                            $temTaxa = true;
                        }
                        if (!empty($r['prioridade'])) {
                            $isExpress = true;
                        }
                    }
                    $statusAtualTopo = $temTaxa ? "⏳ Aguardando pagamento da taxa" : end($statusList)['status_atual'];
                    $fotoPedido = getRastreioFoto($pdo, $codigo);
                    if ($fotoPedido) {
                        $cacheBuster = @filemtime($fotoPedido['absolute']) ?: time();
                        $fotoPedidoSrc = $fotoPedido['url'] . '?v=' . $cacheBuster;
                    } else {
                        $fotoPedidoSrc = null;
                    }
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
        echo '<div class="results-container"><div class="erro">' . $erroCidade . '</div></div>';
        exit;
    }
    if (!empty($statusList)) {
        echo '<div class="results-container">';
        echo '<div class="results-card">';
        echo '<div class="status">📦 Status atual: ' . htmlspecialchars($statusAtualTopo) . ' — ' . htmlspecialchars($cidade);
        if ($isExpress) {
            echo ' <span class="badge"><i class="fas fa-bolt"></i> Entrega Expressa</span>';
        }
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
            echo '<button class="btn-cta-express" data-tooltip="Entrega em 3 dias após confirmação" onclick=\'solicitarExpress(' . json_encode($codigo) . ', ' . json_encode($cidade) . ', this)\'>⚡ Quero entrega em 3 dias</button>';
            echo '</div>';
        }
        if ($fotoPedido && $fotoPedidoSrc) {
            echo '<div class="photo-proof">';
            echo '<p><i class="fas fa-image"></i> Foto do seu pedido</p>';
            echo '<img src="' . htmlspecialchars($fotoPedidoSrc, ENT_QUOTES, 'UTF-8') . '" alt="Foto do pedido ' . htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8') . '">';
            echo '<small>Imagem enviada pelo time de atendimento para comprovação visual.</small>';
            echo '</div>';
        }
        echo '</div>'; // results-box
        echo '</div>'; // results
        if ($temTaxa) {
            echo '<script>(function(){let tempo=' . ((int) $tempoLimite) . '*60*60;function atualizarContagem(){var el=document.getElementById("countdown");if(!el){return;}var h=Math.floor(tempo/3600),m=Math.floor((tempo%3600)/60),s=tempo%60;el.innerHTML="⏱ Tempo restante: "+String(h).padStart(2,"0")+":"+String(m).padStart(2,"0")+":"+String(s).padStart(2,"0");if(tempo>0){tempo--;setTimeout(atualizarContagem,1000)}else{el.innerHTML="❌ Prazo expirado."}}atualizarContagem()})();</script>';
        }
        exit;
    }
    // Sem erro e sem resultados
    echo '<div class="results-container"><div class="erro">❌ Código inexistente!</div></div>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Helmer Logistics - Acompanhamento de Recebimentos</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap"
        rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="assets/images/favicon.ico">
</head>
</head>

<body>
    <header class="header">
        <div class="nav-container">
            <a href="index.php" class="logo"><?= htmlspecialchars($nomeEmpresa) ?></a>
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
        <div class="container hero-box">
            <div class="search-card">
                <h2 style="margin-bottom: 1.5rem; color: var(--text-main);">
                    <i class="fas fa-search"></i> Rastrear Envio
                </h2>
                <form method="POST" action="index.php">
                    <div class="form-group">
                        <label for="codigo">Código de Rastreamento</label>
                        <input type="text" name="codigo" id="codigo" placeholder="Digite o código" maxlength="12"
                            value="<?= htmlspecialchars($codigo) ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="cidade">Cidade</label>
                        <input type="text" name="cidade" id="cidade" placeholder="Digite a cidade"
                            value="<?= htmlspecialchars($cidade) ?>" required>
                    </div>
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-search"></i> Rastrear
                    </button>
                </form>
            </div>
            <!-- Resultados AJAX sem recarregar -->
            <div id="ajaxResults">
                <?php if (!empty($statusList)): ?>
                    <div class="results-container">
                        <div class="results-card">
                            <div class="status">
                                📦 Status atual: <?= htmlspecialchars($statusAtualTopo) ?> —
                                <?= htmlspecialchars($cidade) ?>
                                <?php if ($isExpress): ?>
                                    <span class="badge"><i class="fas fa-bolt"></i> Entrega Expressa</span>
                                <?php endif; ?>
                            </div>
                            <div class="timeline">
                                <?php foreach ($statusList as $etapa): ?>
                                    <div class="step"
                                        style="border-left-color:<?= htmlspecialchars($etapa['cor'] ?? '#16A34A') ?>;">
                                        <b><?= htmlspecialchars($etapa['titulo']) ?></b>
                                        <small><?= htmlspecialchars($etapa['subtitulo']) ?></small>
                                        <i><?= date("d/m/Y H:i", strtotime($etapa['data'])) ?></i>

                                        <?php if (!empty($etapa['taxa_valor']) && !empty($etapa['taxa_pix'])): ?>
                                            <div class="pix-box">
                                                <p>💰 <b>Taxa de distribuição nacional:</b> R$
                                                    <?= number_format($etapa['taxa_valor'], 2, ',', '.') ?>
                                                </p>
                                                <p>Faça o pagamento via PIX:</p>
                                                <textarea readonly><?= htmlspecialchars($etapa['taxa_pix']) ?></textarea>
                                                <button
                                                    onclick="navigator.clipboard.writeText('<?= htmlspecialchars($etapa['taxa_pix'], ENT_QUOTES) ?>')">
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
                            <?php if (!$temTaxa && !$isExpress): ?>
                                <div class="pix-box express-offer" style="margin-top: 1rem;">
                                    <p><b>Entrega Expressa (3 dias)</b> — antecipe sua entrega por apenas R$
                                        <?= number_format($expressValor, 2, ',', '.') ?>.
                                    </p>
                                    <p>Efetue o pagamento via PIX após solicitar. Confirmação rápida.</p>
                                    <button class="btn-cta-express" data-tooltip="Entrega em 3 dias após confirmação"
                                        onclick='solicitarExpress(<?= json_encode($codigo) ?>, <?= json_encode($cidade) ?>, this)'>⚡
                                        Quero entrega em 3 dias</button>
                                </div>
                            <?php endif; ?>
                            <?php if ($fotoPedido && $fotoPedidoSrc): ?>
                                <div class="photo-proof">
                                    <p><i class="fas fa-image"></i> Foto do seu pedido</p>
                                    <img src="<?= htmlspecialchars($fotoPedidoSrc, ENT_QUOTES, 'UTF-8') ?>"
                                        alt="Foto do pedido <?= htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8') ?>">
                                    <small>Imagem anexada ao pedido pelo atendimento Helmer Logistics.</small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php elseif (!empty($erroCidade)): ?>
                    <div class="results-container">
                        <div class="erro"><?= htmlspecialchars($erroCidade) ?></div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="hero-content">
                <h1><?= htmlspecialchars($tituloHero) ?></h1>
                <p><?= htmlspecialchars($descricaoHero) ?></p>

                <div class="hero-actions" style="justify-content: flex-start;">
                    <a href="sobre.php" class="btn-hero secondary"><i class="fas fa-info-circle"></i> Sobre nós</a>
                    <a href="indicacao.php" class="btn-hero"><i class="fas fa-users"></i> Indicar Amigo</a>
                </div>
                <div class="badges">
                    <span class="badge"><i class="fas fa-check-circle"></i>
                        <?= htmlspecialchars($badgeSatisfacao) ?></span>
                    <span class="badge"><i class="fas fa-truck"></i> <?= htmlspecialchars($badgeEntregas) ?></span>
                    <span class="badge"><i class="fas fa-map-marker-alt"></i>
                        <?= htmlspecialchars($badgeCidades) ?></span>
                </div>

                <div class="referral-box" style="margin-top: 2rem;">
                    <i class="fas fa-star" style="font-size: 2rem; color: #FF3333; margin-bottom: 1rem;"></i>
                    <h3>Sistema de Indicações — Entrega Prioritária (2 dias)</h3>
                    <p>Indique um amigo e garanta <strong>entrega prioritária em 2 dias</strong> para o seu próximo
                        envio.</p>
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

    <!-- Como funciona -->
    <section class="features" style="margin-top: 3rem;">
        <h2 class="section-title"><?= htmlspecialchars($howItWorksTitle) ?></h2>
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-barcode" style="font-size: 1.5rem;"></i></div>
                <h3><?= htmlspecialchars($feature1Title) ?></h3>
                <p><?= htmlspecialchars($feature1Description) ?></p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-stream" style="font-size: 1.5rem;"></i></div>
                <h3><?= htmlspecialchars($feature2Title) ?></h3>
                <p><?= htmlspecialchars($feature2Description) ?></p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-bolt" style="font-size: 1.5rem;"></i></div>
                <h3><?= htmlspecialchars($feature3Title) ?></h3>
                <p><?= htmlspecialchars($feature3Description) ?></p>
            </div>
        </div>
        <!-- Prova social -->
        <div class="features-grid" style="margin-top:2rem;">
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-star" style="font-size: 1.5rem;"></i></div>
                <h3><?= htmlspecialchars($socialProof1Title) ?></h3>
                <p><a href="sobre.php"
                        style="color:#fff; text-decoration:underline;"><?= htmlspecialchars($socialProof1LinkText) ?></a>
                </p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-truck" style="font-size: 1.5rem;"></i></div>
                <h3><?= htmlspecialchars($socialProof2Title) ?></h3>
                <p><a href="sobre.php"
                        style="color:#fff; text-decoration:underline;"><?= htmlspecialchars($socialProof2LinkText) ?></a>
                </p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-shield-alt" style="font-size: 1.5rem;"></i></div>
                <h3><?= htmlspecialchars($socialProof3Title) ?></h3>
                <p><a href="sobre.php"
                        style="color:#fff; text-decoration:underline;"><?= htmlspecialchars($socialProof3LinkText) ?></a>
                </p>
            </div>
        </div>
    </section>

    <!-- Depoimentos -->
    <section class="features" style="margin-top: 0;">
        <h2 class="section-title">Depoimentos</h2>
        <div class="features-grid">
            <div class="feature-card" style="text-align:left;">
                <p>“Recebi antes do prazo e pude acompanhar tudo. Recomendo!”</p>
                <p style="margin-top:8px; color:#fff;"><strong>Camila S.</strong> — São Paulo/SP</p>
            </div>
            <div class="feature-card" style="text-align:left;">
                <p>“A linha do tempo me deu segurança. Experiência excelente.”</p>
                <p style="margin-top:8px; color:#fff;"><strong>Marcos T.</strong> — Belo Horizonte/MG</p>
            </div>
            <div class="feature-card" style="text-align:left;">
                <p>“Usei a entrega expressa e chegou rapidinho. Muito bom!”</p>
                <p style="margin-top:8px; color:#fff;"><strong>Renata P.</strong> — Curitiba/PR</p>
            </div>
        </div>
        <div style="text-align:center; color: rgba(255,255,255,0.75); margin-top: 12px;">
            <small>Parcerias: <span style="opacity:.85;">Helmer Logistics</span></small>
        </div>
    </section>


    <?php
    // Exibir popup explicativo automaticamente no render completo quando houver taxa
    if (!empty($statusList) && $temTaxa && $autoLoadFromUrl) {
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
        <h2 class="section-title">Por que escolher Helmer Logistics?</h2>
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
        window.TEMPO_LIMITE_HORAS = <?= (int) $tempoLimite ?>;

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
            } catch (_) { }
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
        document.addEventListener('click', function (event) {
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
            link.addEventListener('click', function () {
                const mobileMenu = document.getElementById('mobileMenu');
                const toggle = document.querySelector('.mobile-menu-toggle i');
                mobileMenu.classList.remove('active');
                toggle.classList.remove('fa-times');
                toggle.classList.add('fa-bars');
            });
        });

        // Submissão AJAX do formulário de rastreio
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.querySelector('form[method="POST"][action="index.php"]');
            const results = document.getElementById('ajaxResults');
            const submitBtn = form ? form.querySelector('button[type="submit"]') : null;

            // Se os dados vieram da URL, os resultados já foram renderizados pelo PHP
            // Apenas garantir que o countdown e popup funcionem se necessário
            <?php if ($autoLoadFromUrl && !empty($statusList)): ?>
                setTimeout(function () {
                    try {
                        startCountdownIfPresent();
                        <?php if ($temTaxa): ?>
                            const pixTextarea = document.querySelector('.pix-box textarea');
                            if (pixTextarea && typeof showTaxaPopup === 'function') {
                                let valorTexto = null;
                                const p = pixTextarea.closest('.pix-box') ? pixTextarea.closest('.pix-box').querySelector('p') : null;
                                if (p && /R\$\s*[0-9\.,]+/.test(p.textContent)) {
                                    const m = p.textContent.match(/R\$\s*[0-9\.,]+/);
                                    valorTexto = m ? m[0] : null;
                                }
                                showTaxaPopup(valorTexto);
                            }
                        <?php endif; ?>
                    } catch (_) { /* silencioso */ }
                }, 200);
            <?php endif; ?>

            if (form && results && submitBtn) {
                form.addEventListener('submit', async function (e) {
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
                            const isExpressBox = pixTextarea ? pixTextarea.closest('.express-box') : null;
                            if (!window.__skipTaxPopupOnce && pixTextarea && !isExpressBox && typeof showTaxaPopup === 'function') {
                                let valorTexto = null;
                                const p = pixTextarea.closest('.pix-box') ? pixTextarea.closest('.pix-box').querySelector('p') : null;
                                if (p && /R\$\s*[0-9\.,]+/.test(p.textContent)) {
                                    const m = p.textContent.match(/R\$\s*[0-9\.,]+/);
                                    valorTexto = m ? m[0] : null;
                                }
                                showTaxaPopup(valorTexto);
                            }
                            if (window.__skipTaxPopupOnce) { window.__skipTaxPopupOnce = false; }
                            startCountdownIfPresent();
                        } catch (_) { /* silencioso */ }
                    } catch (err) {
                        results.innerHTML = '<div class="results-container"><div class="erro">❌ Erro ao consultar. Tente novamente.</div></div>';
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
                (function tick() {
                    const horas = Math.floor(tempo / 3600);
                    const minutos = Math.floor((tempo % 3600) / 60);
                    const segundos = tempo % 60;
                    el.innerHTML = '⏱ Tempo restante: ' + String(horas).padStart(2, '0') + ':' + String(minutos).padStart(2, '0') + ':' + String(segundos).padStart(2, '0');
                    if (tempo > 0) { tempo--; setTimeout(tick, 1000); } else { el.innerHTML = '❌ Prazo expirado.'; }
                })();
            }
        });
    </script>

    <script>
        // Solicitar upgrade express (3 dias)
        async function solicitarExpress(codigo, cidade, btn) {
            // Prevenir chamadas múltiplas
            if (window.__expressRequesting) {
                return;
            }

            // CONFIRMAÇÃO OBRIGATÓRIA antes de solicitar
            const expressValor = '<?= number_format($expressValor, 2, ',', '.') ?>';
            const confirmMsg = `Você está solicitando a entrega expressa em 3 dias por R$ ${expressValor}.\n\n` +
                `Após a confirmação do pagamento PIX, sua entrega será acelerada.\n\n` +
                `Deseja continuar?`;

            // Usar ConfirmManager se disponível, senão usar confirm nativo
            let confirmed = false;
            if (typeof ConfirmManager !== 'undefined') {
                confirmed = await ConfirmManager.show(
                    `Você está solicitando a entrega expressa em 3 dias por R$ ${expressValor}.\n\n` +
                    `Após a confirmação do pagamento PIX, sua entrega será acelerada.\n\n` +
                    `Deseja continuar?`,
                    {
                        title: 'Confirmar Entrega Expressa',
                        confirmText: 'Sim, quero entrega expressa',
                        cancelText: 'Cancelar'
                    }
                );
            } else {
                confirmed = confirm(confirmMsg);
            }

            if (!confirmed) {
                return; // Usuário cancelou
            }

            try {
                window.__expressRequesting = true;
                if (btn) {
                    btn.disabled = true;
                    btn.innerText = 'Solicitando...';
                }

                const resp = await fetch('solicitar_express.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ codigo, cidade })
                });
                const data = await resp.json();
                if (!data.success) throw new Error(data.message || 'Falha ao solicitar.');

                // Mostrar mensagem de sucesso
                if (typeof MessageManager !== 'undefined') {
                    MessageManager.success('Solicitação de entrega expressa enviada! Verifique as instruções de pagamento PIX abaixo.');
                } else {
                    alert('Solicitação enviada com sucesso! Verifique as instruções de pagamento PIX.');
                }

                // Recarregar resultados via AJAX para exibir PIX e contagem
                window.__expressJustRequested = true;
                window.__skipTaxPopupOnce = true;
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
                        // Ajustar rótulo para Express e marcar caixa
                        try {
                            const box = results.querySelector('.pix-box');
                            if (box) {
                                box.classList.add('express-box');
                                const p = box.querySelector('p');
                                if (p) {
                                    const m = p.textContent.match(/R\$\s*[0-9\.,]+/);
                                    const valor = m ? m[0] : '';
                                    p.innerHTML = '⚡ <b>Entrega Expressa (3 dias):</b> ' + valor;
                                }
                            }
                        } catch (_) { }
                    } else {
                        // fallback simples
                        location.reload();
                    }
                } catch (_) { location.reload(); }
            } catch (e) {
                if (typeof MessageManager !== 'undefined') {
                    MessageManager.error(e.message || 'Erro ao solicitar entrega expressa.');
                } else {
                    alert(e.message || 'Erro ao solicitar entrega expressa.');
                }
            } finally {
                window.__expressRequesting = false;
                if (btn) {
                    btn.disabled = false;
                    btn.innerText = '⚡ Quero entrega em 3 dias';
                }
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