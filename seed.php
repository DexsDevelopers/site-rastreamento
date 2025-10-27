<?php
$conn = new mysqli("localhost", "u853242961_johan71", "Lucastav8012@", "u853242961_rastreio");

if ($conn->connect_error) {
    die("Erro na conexão: " . $conn->connect_error);
}

$dados = [
    [
        "codigo" => "GH56YJ1472BR",
        "cidade" => "Parana",
        "status_atual" => "🚚 Em trânsito",
        "steps" => [
            ["2025-09-03 12:11", "Despachado", "Saiu do centro logístico", "bg-yellow-500"],
            ["2025-09-05 10:40", "Taxado na distribuição nacional", "Taxa de liberação: R$ 24,65", "bg-red-600"],
            ["2025-09-10 14:10", "Aguardando pagamento", "Use o botão abaixo para gerar o boleto", "bg-orange-500"]
        ]
    ],
    [
        "codigo" => "GH56YJ1474BR",
        "cidade" => "Carapicuiba SP",
        "status_atual" => "📦 Saiu do centro logístico",
        "steps" => [
            ["2025-08-29 11:35", "Despachado", "Saiu do centro logístico", "bg-yellow-500"],
            ["2025-09-02 16:30", "Em trânsito", "A caminho da cidade destino", "bg-orange-500"]
        ]
    ],
    [
        "codigo" => "GH56YJ1464BR",
        "cidade" => "Rio de Janeiro",
        "status_atual" => "📦 Saiu do centro logístico",
        "steps" => [
            ["2025-09-09 14:35", "Despachado", "Saiu do centro logístico", "bg-yellow-500"]
        ]
    ],
    [
        "codigo" => "GH56YJ1473BR",
        "cidade" => "Mato Grosso",
        "status_atual" => "📦 Saiu do centro logístico",
        "steps" => [
            ["2025-09-05 14:35", "Despachado", "Saiu do centro logístico", "bg-yellow-500"]
        ]
    ],
    [
        "codigo" => "GH56YJ1476BR",
        "cidade" => "PortoAlegre",
        "status_atual" => "📦 Saiu do centro logístico",
        "steps" => [
            ["2025-09-01 13:55", "Despachado", "Saiu do centro logístico", "bg-yellow-500"],
            ["2025-09-02 16:50", "Em trânsito", "A caminho da cidade destino", "bg-orange-500"]
        ]
    ]
];

foreach ($dados as $d) {
    foreach ($d["steps"] as $s) {
        $codigo = $conn->real_escape_string($d["codigo"]);
        $cidade = $conn->real_escape_string($d["cidade"]);
        $status_atual = $conn->real_escape_string($d["status_atual"]);
        $data = $conn->real_escape_string($s[0]);
        $titulo = $conn->real_escape_string($s[1]);
        $subtitulo = $conn->real_escape_string($s[2]);
        $cor = $conn->real_escape_string($s[3]);
        
        $conn->query("INSERT INTO rastreios_status (codigo, cidade, status_atual, titulo, subtitulo, data, cor) 
        VALUES ('$codigo', '$cidade', '$status_atual', '$titulo', '$subtitulo', '$data', '$cor')");
    }
}

echo "Códigos inseridos com sucesso!";
