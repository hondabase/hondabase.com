<?php
require 'vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Article;
use App\Support\ArticleDocument;

$newSummaries = [
    '6260a-testing' => 'Análise técnica do código de teste da ECU Honda 6260A, explicando como ativa o LED de diagnóstico para piscar os códigos de erro do motor.',
    'add-knock-to-p30g00' => 'Guia de hardware passo a passo para adicionar um circuito de sensor de detonação a ECUs Honda P30 OBD1 não equipadas, utilizando modificações de componentes específicos.',
    'label-decode' => 'Aprenda a interpretar as etiquetas de identificação nas ECUs Honda OBD1 para determinar a sua origem, compatibilidade com o motor e tipo de transmissão.',
    'obd0-code-compatibility' => 'Um guia sobre a compatibilidade de código das ECUs Honda OBD0. Saiba quais as ECUs que podem partilhar código e como trocar binários entre unidades com sucesso.',
    'p2p' => 'Visão geral técnica da ECU Honda Civic EX P2P OBD2, abrangendo configurações de bin de origem, mapas de ponto de ignição e especificações de hardware.',
    'popol-vuh' => 'Uma visão histórica do projeto Wiki PGMFI.org, abrangendo as suas origens, desenvolvimento e contribuições da comunidade para a afinação de ECUs Honda.',
    'super-pro-z' => 'Visão geral técnica do programador de EPROM Xeltek SuperProZ, incluindo tipos de chips suportados e a sua utilização na reprogramação (chipping) de ECUs Honda.',
    'text-formatting-rules' => 'Guia de referência para a formatação de markup Wiki, incluindo ênfase, listas e definições de termos utilizados para editar a documentação técnica da Hondabase.',
    'what-you-need' => 'Introdução prática à reprogramação (chipping) de ECUs Honda: o que significa, as ferramentas essenciais necessárias e o processo de gravação de novos ficheiros bin.',
    'chipping-obd1-big-case' => 'Brevemente: Um guia detalhado para a instalação de sockets e reprogramação de ECUs Honda OBD1 de caixa grande, incluindo requisitos de componentes e passos do processo.',
    'new-markup-test-page' => 'Uma área de testes para experimentar e verificar novas funcionalidades de formatação de Markdown e markup Wiki para a documentação da Hondabase.',
    'vise-grip' => 'Um olhar rápido sobre ferramentas mecânicas essenciais, como o alicate de pressão (vise-grip), que são indispensáveis para modificações de hardware em veículos Honda.',
    'wiki' => 'Visão geral do projeto Wiki PGMFI.org, que documenta os sistemas de gestão de motor Honda OBD0 e OBD1.',
    'injector-sizing' => 'Saiba como calcular e selecionar corretamente os injetores de combustível adequados com base no seu objetivo de potência e modificações do motor.',
    'obd1p08-auto-manual' => 'Guia técnico para converter ECUs Honda P08 OBD1 automáticas para configurações de transmissão manual, modificando os valores internos da placa de resistências.',
    'p27' => 'Especificações técnicas e visão geral da aplicação para a ECU Honda Civic P27 92-95 OBD1 Euro/Asiática.',
    '02d011f0-1500' => 'Guia de modificação de hardware para converter placas de ECU Honda "11F0" sem VTEC para VTEC, incluindo as adições de componentes necessárias.',
    'p2e' => 'Especificações técnicas e visão geral da aplicação para a ECU Honda Civic LX 96+.',
    'p2n' => 'Especificações técnicas e visão geral da aplicação para a ECU Honda Civic HX 96+.',
    'p2t' => 'Especificações técnicas e visão geral da aplicação para a ECU Honda Civic Si 99+.',
    'cpu' => 'Visão geral do papel da Unidade Central de Processamento (CPU) como o controlador principal nos sistemas de gestão de motor das ECUs Honda.',
    'd1780' => 'Folha de dados técnicos e especificações para o transistor NEC 2SD1780 utilizado em circuitos de hardware de ECUs Honda.',
    'g-spot' => 'Informação sobre o fórum da comunidade GSpot, dedicado à análise de hardware de ECUs Honda e discussões técnicas.',
    'most-popular' => 'Artigos mais visualizados na base de conhecimentos Hondabase, proporcionando acesso fácil a tópicos populares de afinação e diagnóstico.',
    'octal' => 'Explicação da numeração octal e a sua relevância na lógica digital e operações bit-a-bit para a afinação de ECUs Honda.',
    'oz-dm' => 'Visão geral das especificações de veículos e ECUs Honda do Mercado Doméstico Australiano (OZaDM).',
    'pj7' => 'Especificações técnicas e visão geral da aplicação para a ECU Honda Prelude Si (B20A3) 86-87.',
    'square-brackets' => 'Relatório de problema técnico relativo a erros de formatação em páginas wiki que requerem manutenção e correção de conteúdo.',
    'wot' => 'Definição e significado de "Wide Open Throttle" (WOT - Aceleração Total) na afinação de motores Honda e mapeamento de diagnóstico.',
    '5128xram' => 'Explicação técnica do chip SRAM 5128 de 2K bytes utilizado nas ECUs Honda OBD0, incluindo o seu mapeamento de memória e finalidade.',
    '66k-resources' => 'Lista abrangente de recursos para a família de processadores Oki 66K utilizados nas ECUs Honda OBD1 e OBD2, incluindo assemblers e manuais.',
    'blue-loctite' => 'Guia para a utilização adequada de compostos de travamento de roscas Loctite para fixar componentes de motor e hardware em projetos Honda.',
    'disable-vtec-vss-check-p28' => 'Guia técnico para contornar a verificação do Sensor de Velocidade do Veículo (VSS) na rotina de VTEC para ECUs Honda P28 OBD1.',
    'dual-maps' => 'Introdução a configurações de ROM com mapas duplos em ECUs Honda, permitindo perfis de afinação de motor comutáveis.',
    'dual-tables' => 'Explicação das estruturas de ROM com tabelas duplas em ECUs Honda, permitindo funcionalidades de afinação avançadas como mapas de combustível e ignição dependentes do VTEC.',
    'ecu-boost-controller' => 'Visão geral das funcionalidades de controlo de pressão de turbo em desenvolvimento para ECUs Honda, utilizando sinais PWM para regulação da pressão do solenoide da wastegate.',
    'ideal-gas-law' => 'Aplicação da Lei dos Gases Ideais nos sistemas de gestão de motor Honda baseados em velocidade-densidade para calcular os requisitos de combustível com base na pressão e temperatura.',
    'inter-wiki' => 'Referência para as capacidades de ligação inter-wiki na Hondabase, permitindo uma navegação fluida entre projetos de documentação técnica relacionados.',
    'internal-rom' => 'Explicação do armazenamento de memória ROM Interna nos microcontroladores (MCUs) das ECUs Honda e o seu papel na gestão do motor.',
    'java-script' => 'Visão geral do scripting em JavaScript para automatizar funcionalidades no Crome e noutros softwares de edição de ROM Honda.',
    'launch-control' => 'Explicação técnica das funcionalidades de limitador de rotação "two-step" (controlo de arranque) em ECUs Honda, auxiliando em arranques consistentes do veículo parado.',
    'obd0-edit' => 'Informação sobre o fórum da comunidade OBD0Edit, um recurso para discussões técnicas e desenvolvimento relativo a ECUs Honda OBD0.',
    'obd1-8bit-fuel' => 'Referência de fórmula técnica para interpretar valores de combustível de 8 bits em tabelas de editores de ROM para a gestão de motor Honda OBD1.',
    'p1j' => 'Visão geral da ECU Honda Civic D14 do Reino Unido de 96-00, abrangendo a sua compatibilidade e potencial de conversão para VTEC.',
    'p1k' => 'Visão geral da ECU Honda Civic D14 do Reino Unido de 96-00, abrangendo a sua compatibilidade e potencial de conversão para VTEC.',
    'red-loctite' => 'Conselhos técnicos sobre a utilização e remoção do composto de travamento de roscas Loctite Vermelho em aplicações de componentes de motor Honda de elevado esforço.',
    'turbo-compressor-map' => 'Guia para ler e interpretar mapas de compressores de turbocompressores para adequar os turbocompressores aos objetivos de performance do motor Honda.',
    'uv-erase' => 'Guia técnico para a utilização de luz UV para apagar chips EPROM com janelas transparentes para reprogramação.',
    'warning-about-adding-a-knock-sensor' => 'Aviso técnico relativo à instalação de sensores de detonação em motores não equipados, citando as complexidades do diâmetro do cilindro e da calibração da placa de detonação.',
    'full-throttle-shift' => 'Um guia para implementar o Full Throttle Shift (FTS) em ECUs Honda OBD1, permitindo passagens de caixa mais rápidas ao manter a pressão de turbo e a rotação durante as mudanças.',
    '66k-assembler-routines' => 'Uma coleção de rotinas em linguagem assembly Oki 66K e trechos de código para modificar o firmware das ECUs Honda OBD1 e OBD2.',
    'chipping-obd1-small-case' => 'Instruções passo a passo para a instalação de sockets e reprogramação de ECUs Honda OBD1 de caixa pequena, comummente encontradas em modelos JDM e alguns europeus.',
    'disable-vtec-vss-check' => 'Saiba como desativar a verificação do Sensor de Velocidade do Veículo (VSS) de VTEC em ECUs Honda OBD1, permitindo a ativação do VTEC sem um sinal de velocidade funcional.',
    'ect' => 'Visão geral técnica do sensor de Temperatura do Líquido de Arrefecimento do Motor (ECT) em motores Honda, incluindo o seu papel no combustível, ponto de ignição e enriquecimento no arranque a frio.',
    'electronic-air-control-vale' => 'Uma explicação detalhada da Válvula de Controlo de Ar Eletrónica (EACV), também conhecida como Válvula de Controlo de Ar de Relenti (IACV), e a sua função na manutenção do ralenti Honda.',
    'engine-simulator' => 'Guia para utilizar ou construir um simulador de motor para testar ECUs Honda OBD0 e OBD1 em bancada sem um veículo.',
    'erm' => 'Informação sobre o sistema UberData Engine Management (ERM), uma solução de afinação legado para ECUs Honda OBD1.',
    'fuel-octane' => 'Compreender os índices de octanagem do combustível e o seu impacto na performance do motor Honda, resistência à detonação e otimização do ponto de ignição.',
    'iat' => 'Visão geral técnica do sensor de Temperatura do Ar de Admissão (IAT) e o seu papel crítico nos cálculos de densidade do ar e correções de combustível para ECUs Honda.',
    'io' => 'Visão geral básica das portas de Entrada/Saída (I/O) nos microcontroladores das ECUs Honda e como fazem a interface com sensores e atuadores do motor.',
    'jdm' => 'Uma referência para as ECUs Honda JDM (Mercado Doméstico Japonês), destacando as diferenças de hardware, como a falta de sensores ELD e de detonação em comparação com os modelos USDM.',
    'latches' => 'Explicação técnica dos latches de endereço (ex: 74HC373) utilizados nas ECUs Honda OBD1 para fazer a interface do MCU com a memória EPROM externa.',
    'lego-zoo' => 'Uma página histórica da comunidade dedicada aos primórdios da wiki PGMFI.org e aos colaboradores que ajudaram a construir a base de conhecimentos de afinação Honda.',
    'p0c' => 'Especificações técnicas e informações de pinagem para a ECU Honda Accord 2.2L OBD1 P0C 92-95.',
    'p11' => 'Especificações técnicas e visão geral do hardware para a ECU Honda Prelude 2.0i (BB3) OBD1 P11 92-95.',
    'p5m' => 'Análise de hardware e especificações de componentes para a ECU Honda Prelude 2.2VTi (EDM) OBD2 P5M 97+.',
    'p5p' => 'Visão geral da ECU Honda Prelude Type-S JDM OBD2 P5P, incluindo as suas características únicas de hardware e mapeamento de performance.',
    'p75' => 'Comparação técnica das ECUs Honda Integra LS/GS P75 OBD2, incluindo a sua transição de OBD1 e variações de hardware.',
    'p76' => 'Visão geral técnica da ECU Honda Integra SOHC ZC JDM OBD1 P76 e a sua aplicação na afinação.',
    'p84' => 'Especificações para a ECU Honda Civic ETi JDM OBD1 P84, concebida para motores VTEC-E de economia de combustível com transmissões automáticas.',
    'pa-sensor' => 'Guia técnico para o sensor de Pressão Atmosférica (PA) nas ECUs Honda, explicando o seu papel na compensação de altitude e ajustes de combustível.',
    'pr7' => 'Visão geral do hardware e especificações técnicas para a ECU Honda NSX OBD1 PR7 91-94.',
    'pt5' => 'Análise da ECU Honda Accord EDM OBD1 PT5, abrangendo a sua arquitetura de PCB partilhada com outras unidades Accord.',
    'pwm' => 'Explicação da Modulação por Largura de Pulso (PWM) e a sua utilização nas ECUs Honda para controlar solenoides como a IACV, controladores de pressão de turbo e VTEC.',
    'rtfm' => 'Um lembrete humorístico mas importante para consultar a documentação e os manuais disponíveis antes de realizar modificações complexas em ECUs Honda.',
    'sectors' => 'Compreender os setores de memória em chips EPROM e Flash utilizados para a afinação de ECUs Honda e armazenamento de dados.',
    'service-manual' => 'Informação sobre como obter e utilizar manuais de serviço de fábrica (Helms) para uma cablagem e reparação mecânica precisa de veículos Honda.',
    'uber-data' => 'Visão histórica do software de afinação UberData, uma das plataformas gratuitas originais para a reprogramação de ECUs Honda OBD1.',
    'usdm' => 'Referência para as ECUs Honda USDM (Mercado Doméstico dos Estados Unidos), conhecidas pelos seus conjuntos abrangentes de funcionalidades, incluindo ELD e controlo de detonação.',
    'vss' => 'Guia técnico para o Sensor de Velocidade do Veículo (VSS) em veículos Honda, abrangendo tipos de sinal, resolução de problemas e o seu impacto no VTEC.',
    '02d01980-1500' => 'Lista detalhada de peças e guia de modificação de hardware para converter a placa de ECU Honda 02D01980-1500 para suporte de VTEC e IAB.',
    '5050s' => 'Guia de resolução de problemas e instalação para a utilização de componentes 5050s em circuitos de conversão VTEC Honda, incluindo correções para o código MIL 21.',
    'edm' => 'Referência para as ECUs Honda EDM (Mercado Doméstico Europeu), detalhando as diferenças comuns de hardware, como a falta de um circuito de teste de injetores.',
    'maf-sensor' => 'Explicação técnica dos sensores de Fluxo de Massa de Ar (MAF) e por que a Honda utiliza principalmente sistemas de velocidade-densidade baseados em MAP.',
    'obd1-conversion-formulae' => 'Uma lista abrangente de fórmulas matemáticas para converter valores hexadecimais brutos da ROM em unidades legíveis por humanos, como RPM, pressão e temperatura.',
    'obd1-oki66207-reader-plcc68' => 'Guia avançado para construir um leitor de hardware para extrair dados da ROM Interna de processadores PLCC68 Oki 66207 encontrados em ECUs Honda OBD1.',
    'obd1cn2' => 'Guia de pinagem e cablagem para a porta de datalogging série CN2 nas ECUs Honda OBD1, essencial para afinação e diagnóstico em tempo real.',
    'obd2-oki66507-reader-nico' => 'Guia técnico para construir um dumper de hardware para ler a memória interna de microcontroladores Oki 66507 em ECUs Honda OBD2.',
    'p07' => 'Análise técnica da ECU Honda Civic VX OBD1 P07, apresentando uma arquitetura única de processador duplo para controlo de mistura pobre.',
    'pcb' => 'Uma introdução ao design e construção de Placas de Circuito Impresso (PCB) no que diz respeito às unidades de controlo de motor Honda.',
    'pgsrc-translation' => 'Uma matriz de referência para nomes de páginas traduzidos dentro do projeto de documentação técnica PGMFI.',
    'rm11' => 'Correção técnica para substituir a rede de resistências RM11 em ECUs Honda por resistências individuais para conversões VTEC.',
    'rom-maps' => 'Um índice colaborativo de endereços de memória e mapas de ROM para ECUs Honda Civic e Integra OBD1, essencial para a engenharia reversa de firmware.',
    'rtp-project' => 'Informação sobre o projeto de Programação em Tempo Real (RTP), com o objetivo de permitir a afinação de ECUs em tempo real para plataformas Honda OBD1.',
    'ecu-troubleshooting' => 'Um guia abrangente de resolução de problemas para diagnosticar e resolver problemas de ECUs Honda, incluindo luzes CEL sólidas, códigos de diagnóstico de problemas (DTC) e problemas de falta de arranque do motor em veículos OBD0 e OBD1.',
    'honda-error-codes' => 'Um guia de referência completo de códigos de diagnóstico de problemas (DTC) Honda OBD0, OBD1 e OBD2. Saiba como recuperar códigos de flashes CEL, interpretar códigos de erro comuns de motores Honda e diagnosticar avarias no veículo.',
    'nico-analyze' => 'Informação sobre a ferramenta de análise de ficheiros binários Nico, utilizada para comparar e analisar ROMs de ECUs Honda.',
];

$updatedCount = 0;

foreach ($newSummaries as $slug => $summary) {
    $article = Article::where('slug', $slug)->where('locale', 'pt')->first();
    if (!$article) continue;
    
    $repoPath = 'content/' . $article->repo_path;
    if (!file_exists($repoPath)) continue;
    
    $raw = file_get_contents($repoPath);
    $doc = ArticleDocument::parse($raw);
    
    $doc['fm']['summary'] = $summary;
    $newRaw = ArticleDocument::compose($doc['fm'], $doc['body']);
    
    file_put_contents($repoPath, $newRaw);
    
    // Update DB
    $article->summary = $summary;
    $article->save();
    $updatedCount++;
}

echo "Successfully updated $updatedCount Portuguese articles with SEO-friendly summaries.\n";
