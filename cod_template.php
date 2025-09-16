<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acompanhamento de Processos - CEHAB</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f2f5;
        }
        .modal-backdrop {
            background-color: rgba(0, 0, 0, 0.5);
        }
        .step {
            transition: all 0.3s ease;
        }
        .step-completed .step-circle {
            background-color: #16a34a; /* green-600 */
            border-color: #16a34a; /* green-600 */
        }
        .step-completed .step-line {
            background-color: #16a34a; /* green-600 */
        }
        .step-current .step-circle {
            background-color: #2563eb; /* blue-600 */
            border-color: #2563eb; /* blue-600 */
            animation: pulse 2s infinite;
        }
        .step-pending .step-circle {
            background-color: #ffffff;
            border-color: #d1d5db; /* gray-300 */
        }
        .step-pending .step-line {
            background-color: #d1d5db; /* gray-300 */
        }
        .step-current .step-line {
             background-color: #d1d5db; /* gray-300 */
        }
        @keyframes pulse {
            0%, 100% {
                box-shadow: 0 0 0 0 rgba(37, 99, 235, 0.4);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(37, 99, 235, 0);
            }
        }
    </style>
</head>
<body class="antialiased">
    <!-- Header -->
    <header class="bg-white shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
            <div class="flex items-center">
                <i class="fas fa-sitemap text-3xl text-blue-600 mr-3"></i>
                <h1 class="text-2xl font-bold text-gray-800">CEHAB - Acompanhamento de Processos</h1>
            </div>
            <button id="newProcessBtn" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg shadow-md transition duration-300 flex items-center">
                <i class="fas fa-plus mr-2"></i> Novo Processo
            </button>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="bg-white p-6 rounded-lg shadow-lg">
            <h2 class="text-xl font-semibold text-gray-700 mb-4">Processos em Andamento</h2>
            
            <!-- Filter Section -->
            <div id="filter-section" class="bg-gray-50 p-4 rounded-lg mb-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-4 items-end">
                <div>
                    <label for="filterProcessNumber" class="block text-sm font-medium text-gray-700">Número do Processo</label>
                    <input type="text" id="filterProcessNumber" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="Pesquisar...">
                </div>
                <div>
                    <label for="filterRequestingSector" class="block text-sm font-medium text-gray-700">Setor Demandante</label>
                    <input type="text" id="filterRequestingSector" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="Pesquisar...">
                </div>
                <div>
                    <label for="filterDescription" class="block text-sm font-medium text-gray-700">Descrição</label>
                    <input type="text" id="filterDescription" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="Pesquisar...">
                </div>
                <div>
                    <label for="filterStatus" class="block text-sm font-medium text-gray-700">Status Atual</label>
                    <select id="filterStatus" class="mt-1 block w-full px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        <!-- Options will be populated by JS -->
                    </select>
                </div>
                <div>
                     <button id="clearFiltersBtn" class="w-full bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded-lg transition duration-300 flex items-center justify-center text-sm">
                        <i class="fas fa-times mr-2"></i> Limpar Filtros
                    </button>
                </div>
                 <div>
                    <button id="generateReportBtn" class="w-full bg-teal-600 hover:bg-teal-700 text-white font-bold py-2 px-4 rounded-lg transition duration-300 flex items-center justify-center text-sm">
                        <i class="fas fa-file-alt mr-2"></i> Gerar Relatório
                    </button>
                </div>
            </div>

            <div id="processList" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Process cards will be inserted here -->
                <div id="loading" class="text-center col-span-full">
                    <i class="fas fa-spinner fa-spin text-4xl text-blue-500"></i>
                    <p class="mt-2 text-gray-600">Carregando processos...</p>
                </div>
            </div>
        </div>
    </main>

    <!-- New/Edit Process Modal -->
    <div id="processModal" class="fixed inset-0 z-50 flex items-center justify-center overflow-auto modal-backdrop hidden">
        <div class="bg-white rounded-lg shadow-2xl m-4 max-w-lg w-full max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b">
                <h3 class="text-2xl font-semibold text-gray-800" id="modalTitle">Novo Processo</h3>
            </div>
            <form id="processForm" class="p-6 space-y-4">
                <input type="hidden" id="processId">
                <div>
                    <label for="processNumber" class="block text-sm font-medium text-gray-700">Número do Processo</label>
                    <input type="text" id="processNumber" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                </div>
                <div>
                    <label for="requestingSector" class="block text-sm font-medium text-gray-700">Setor Demandante</label>
                    <input type="text" id="requestingSector" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                </div>
                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700">Descrição</label>
                    <textarea id="description" rows="3" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></textarea>
                </div>
                 <div class="flex justify-end pt-4 space-x-2">
                    <button type="button" id="closeModalBtn" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded-lg transition duration-300">Cancelar</button>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition duration-300">Salvar Processo</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Process Details Modal -->
    <div id="detailsModal" class="fixed inset-0 z-50 flex items-center justify-center overflow-auto modal-backdrop hidden">
        <div class="bg-white rounded-lg shadow-2xl m-4 max-w-5xl w-full max-h-[90vh] flex flex-col">
            <div class="p-6 border-b flex justify-between items-start">
                <div>
                    <h3 class="text-2xl font-semibold text-gray-800" id="detailsModalTitle">Detalhes do Processo</h3>
                    <p class="text-sm text-gray-500" id="detailsProcessNumber"></p>
                </div>
                <button id="closeDetailsModalBtn" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
            <div class="p-6 overflow-y-auto flex-grow">
                <!-- Details content -->
                <div class="grid grid-cols-1 md:grid-cols-5 gap-8">
                    <!-- Left Column: Workflow -->
                    <div id="workflow-container" class="md:col-span-3">
                        <h4 class="text-lg font-semibold mb-4 text-gray-700">Histórico e Fluxo do Processo</h4>
                        <div id="workflow-steps" class="space-y-0">
                            <!-- Workflow steps will be generated here -->
                        </div>
                    </div>
                    <!-- Right Column: Info & Actions -->
                    <div class="md:col-span-2">
                        <div class="bg-gray-50 p-4 rounded-lg mb-6 sticky top-0">
                            <h4 class="text-lg font-semibold mb-3 text-gray-700">Informações Gerais</h4>
                            <p class="text-sm"><strong>Setor Demandante:</strong> <span id="detailsRequestingSector"></span></p>
                            <p class="text-sm"><strong>Descrição:</strong> <span id="detailsDescription"></span></p>
                            <p class="text-sm"><strong>Criado em:</strong> <span id="detailsCreatedAt"></span></p>
                        </div>
                         <!-- Gemini Summary Section -->
                        <div class="mt-6">
                            <h4 class="text-lg font-semibold mb-3 text-gray-700">Resumo Inteligente</h4>
                            <div id="geminiSummaryContainer" class="bg-blue-50 border-l-4 border-blue-400 text-blue-800 p-4 rounded-r-lg hidden text-sm">
                                <p id="geminiSummaryText"></p>
                            </div>
                            <div id="geminiSummaryLoading" class="text-center py-4 hidden">
                                <i class="fas fa-spinner fa-spin text-blue-500"></i>
                                <p class="text-sm text-gray-600 mt-1">Analisando o histórico e gerando resumo...</p>
                            </div>
                            <button id="generateSummaryBtn" class="w-full mt-2 bg-blue-100 hover:bg-blue-200 text-blue-800 font-bold py-2 px-4 rounded-lg transition duration-300 flex items-center justify-center text-sm">
                                <span class="mr-2">✨</span> Gerar Resumo com IA
                            </button>
                        </div>
                        <div id="action-container" class="mt-6">
                             <h4 class="text-lg font-semibold mb-3 text-gray-700">Ações</h4>
                             <p id="currentStatusText" class="mb-4 text-gray-600"></p>
                             <button id="advanceProcessBtn" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-4 rounded-lg shadow-md transition duration-300 flex items-center justify-center text-lg">
                                <span id="advanceBtnText">Avançar</span> <i class="fas fa-arrow-right ml-2"></i>
                             </button>
                             <button id="addInternalActionBtn" class="w-full mt-2 bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded-lg transition duration-300 flex items-center justify-center text-sm">
                                <i class="fas fa-plus mr-2"></i> Adicionar Ação Interna
                            </button>
                             <p id="processFinishedText" class="hidden text-center text-green-700 font-semibold bg-green-100 p-4 rounded-lg mt-4">
                                <i class="fas fa-check-circle mr-2"></i> Processo finalizado com sucesso!
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Action Modal (for advancing step) -->
    <div id="actionModal" class="fixed inset-0 z-50 flex items-center justify-center overflow-auto modal-backdrop hidden">
        <div class="bg-white rounded-lg shadow-2xl m-4 max-w-md w-full">
            <div class="p-6 border-b">
                <h3 class="text-xl font-semibold text-gray-800">Finalizar Etapa</h3>
            </div>
            <div class="p-6">
                <div class="flex justify-between items-center mb-2">
                     <label for="actionText" class="block text-sm font-medium text-gray-700">Descreva a ação finalizadora:</label>
                     <button id="getSuggestionBtn" class="text-xs bg-blue-100 text-blue-700 hover:bg-blue-200 px-2 py-1 rounded-md flex items-center transition-colors">
                        <span class="mr-1">✨</span> Sugerir Texto
                    </button>
                </div>
                <div class="relative">
                    <textarea id="actionText" rows="4" class="w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Ex: DDO emitida e assinada."></textarea>
                    <div id="actionSuggestionLoading" class="absolute inset-0 bg-white bg-opacity-80 flex flex-col items-center justify-center rounded-md hidden">
                        <i class="fas fa-spinner fa-spin text-blue-500"></i>
                        <p class="text-xs text-gray-500 mt-1">Sugerindo texto...</p>
                    </div>
                </div>
            </div>
            <div class="flex justify-end p-4 bg-gray-50 space-x-2 rounded-b-lg">
                <button type="button" id="cancelActionBtn" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded-lg transition duration-300">Cancelar</button>
                <button type="button" id="confirmActionBtn" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition duration-300">Confirmar e Avançar</button>
            </div>
        </div>
    </div>
    
    <!-- Internal Action Modal -->
    <div id="internalActionModal" class="fixed inset-0 z-50 flex items-center justify-center overflow-auto modal-backdrop hidden">
        <div class="bg-white rounded-lg shadow-2xl m-4 max-w-md w-full">
            <div class="p-6 border-b">
                <h3 class="text-xl font-semibold text-gray-800">Registrar Ação Interna</h3>
            </div>
            <div class="p-6">
                 <label for="internalActionText" class="block text-sm font-medium text-gray-700">Descreva a ação realizada:</label>
                <textarea id="internalActionText" rows="4" class="mt-2 w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Ex: Solicitar orçamento para SEPLAG."></textarea>
            </div>
            <div class="flex justify-end p-4 bg-gray-50 space-x-2 rounded-b-lg">
                <button type="button" id="cancelInternalActionBtn" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded-lg transition duration-300">Cancelar</button>
                <button type="button" id="confirmInternalActionBtn" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition duration-300">Salvar Ação</button>
            </div>
        </div>
    </div>


    <script type="module">
        import { initializeApp } from "https://www.gstatic.com/firebasejs/11.6.1/firebase-app.js";
        import { getFirestore, collection, onSnapshot, doc, addDoc, updateDoc, serverTimestamp, query } from "https://www.gstatic.com/firebasejs/11.6.1/firebase-firestore.js";
        import { getAuth, signInAnonymously, signInWithCustomToken, setPersistence, inMemoryPersistence } from "https://www.gstatic.com/firebasejs/11.6.1/firebase-auth.js";

        // --- CONFIG & INITIALIZATION ---
        const firebaseConfig = typeof __firebase_config !== 'undefined' ? JSON.parse(__firebase_config) : { apiKey: "YOUR_API_KEY", authDomain: "YOUR_AUTH_DOMAIN", projectId: "YOUR_PROJECT_ID" };
        const appId = typeof __app_id !== 'undefined' ? __app_id : 'default-cehab-app';

        const app = initializeApp(firebaseConfig);
        const db = getFirestore(app);
        const auth = getAuth(app);

        // --- AUTHENTICATION ---
        async function authenticateUser() {
            try {
                await setPersistence(auth, inMemoryPersistence);
                if (typeof __initial_auth_token !== 'undefined' && __initial_auth_token) {
                    await signInWithCustomToken(auth, __initial_auth_token);
                } else {
                    await signInAnonymously(auth);
                }
            } catch (error) {
                console.error("Authentication Error:", error);
            }
        }
        
        // --- WORKFLOW DEFINITION ---
        const WORKFLOW_DEPARTMENTS = [
            {
                id: 'DAF',
                department: 'Diretoria Administrativa e Financeira (DAF)',
                steps: [
                    { id: 'DAF_AUTORIZACAO', name: 'Análise e Autorização', description: 'Aguardando análise e autorização da diretoria.' }
                ]
            },
            {
                id: 'SUPLAN',
                department: 'Superintendência e Planejamento e Orçamento (SUPLAN)',
                steps: [
                    { id: 'SUPLAN_ANALISE', name: 'Análise de Planejamento e Orçamento', description: 'Analisando o impacto e alinhamento estratégico.' }
                ]
            },
            {
                id: 'GOP_1',
                department: 'Gerência de Planejamento e Orçamento (GOP)',
                steps: [
                     { id: 'GOP_DDO', name: 'Emissão de Dotação Orçamentária (DDO)', description: 'Verificando disponibilidade e emitindo a DDO.' }
                ]
            },
            {
                id: 'HOMOLOGACAO',
                department: 'Setor Demandante',
                steps: [
                    { id: 'HOMOLOGACAO_EXEC', name: 'Homologação', description: 'Aguardando validação e homologação do setor demandante.' }
                ]
            },
            {
                id: 'GOP_2',
                department: 'Gerência de Planejamento e Orçamento (GOP)',
                steps: [
                    { id: 'GOP_PF', name: 'Liberação de Programação Financeira (PF)', description: 'Verificando cota e liberando a PF.' }
                ]
            },
            {
                id: 'SUFIN',
                department: 'Superintendência Financeira (SUFIN)',
                steps: [
                    { id: 'SUFIN_CIENCIA', name: 'Ciência e Encaminhamento', description: 'Dando ciência e encaminhando para a GEFIN.' }
                ]
            },
            {
                id: 'GEFIN_1',
                department: 'Gerência Financeira (GEFIN)',
                steps: [
                     { id: 'GEFIN_EMPENHO', name: 'Emissão do Empenho', description: 'Preparando e emitindo a nota de empenho.' }
                ]
            },
            {
                id: 'SUJUR',
                department: 'Superintendência de Apoio Jurídico (SUJUR)',
                steps: [
                    { id: 'SUJUR_CONTRATO', name: 'Formalização do Contrato', description: 'Analisando e formalizando o contrato.' }
                ]
            },
            {
                id: 'OS',
                department: 'Setor Demandante',
                steps: [
                    { id: 'OS_EXEC', name: 'Emissão da Ordem de Serviço (OS)', description: 'Preparando e emitindo a OS.' }
                ]
            },
            {
                id: 'GOP_3',
                department: 'Gerência de Planejamento e Orçamento (GOP)',
                steps: [
                    { id: 'GOP_PAGAMENTO', name: 'Liberação para Pagamento', description: 'Conferindo e liberando o processo para pagamento.' }
                ]
            },
            {
                id: 'GEFIN_2',
                department: 'Gerência Financeira (GEFIN)',
                steps: [
                    { id: 'GEFIN_LE', name: 'Liquidação (LE)', description: 'Realizando a liquidação do empenho.' },
                    { id: 'GEFIN_PD', name: 'Previsão de Desembolso (PD)', description: 'Emitindo a previsão de desembolso.' },
                    { id: 'GEFIN_OB', name: 'Ordem Bancária (OB)', description: 'Emitindo a ordem bancária.' },
                    { id: 'GEFIN_RE', name: 'Remessa (RE)', description: 'Enviando a remessa ao banco.' }
                ]
            },
            {
                id: 'FINALIZADO',
                department: 'Sistema',
                steps: [
                    { id: 'FINALIZADO_CONCLUIDO', name: 'Processo Finalizado', description: 'Processo concluído.' }
                ]
            }
        ];


        // --- DOM ELEMENTS ---
        const processModal = document.getElementById('processModal');
        const detailsModal = document.getElementById('detailsModal');
        const actionModal = document.getElementById('actionModal');
        const internalActionModal = document.getElementById('internalActionModal');
        const processForm = document.getElementById('processForm');
        const processList = document.getElementById('processList');
        const loadingIndicator = document.getElementById('loading');
        const filterProcessNumber = document.getElementById('filterProcessNumber');
        const filterRequestingSector = document.getElementById('filterRequestingSector');
        const filterDescription = document.getElementById('filterDescription');
        const filterStatus = document.getElementById('filterStatus');
        const clearFiltersBtn = document.getElementById('clearFiltersBtn');
        const generateReportBtn = document.getElementById('generateReportBtn');
        
        let currentProcessId = null; 

        // --- GEMINI API INTEGRATION ---
        const callGemini = async (prompt, retries = 3, delay = 1000) => {
            const apiKey = ""; 
            const apiUrl = `https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-preview-05-20:generateContent?key=${apiKey}`;

            const payload = {
                contents: [{ parts: [{ text: prompt }] }],
            };

            for (let i = 0; i < retries; i++) {
                try {
                    const response = await fetch(apiUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload)
                    });

                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }

                    const result = await response.json();
                    const candidate = result.candidates?.[0];

                    if (candidate && candidate.content?.parts?.[0]?.text) {
                        return candidate.content.parts[0].text;
                    } else {
                        if (result.promptFeedback?.blockReason) {
                            console.warn("Gemini request blocked:", result.promptFeedback.blockReason);
                             return `A resposta da IA foi bloqueada por: ${result.promptFeedback.blockReason}.`;
                        }
                        throw new Error("Resposta inválida da API Gemini.");
                    }
                } catch (error) {
                    console.error(`Attempt ${i + 1} failed:`, error);
                    if (i < retries - 1) {
                        await new Promise(res => setTimeout(res, delay * Math.pow(2, i)));
                    } else {
                        return null;
                    }
                }
            }
            return null;
        };

        const handleGenerateSummary = async () => {
            const process = processesData.find(p => p.id === currentProcessId);
            if (!process) return;

            const summaryContainer = document.getElementById('geminiSummaryContainer');
            const summaryTextEl = document.getElementById('geminiSummaryText');
            const loadingEl = document.getElementById('geminiSummaryLoading');
            const summaryBtn = document.getElementById('generateSummaryBtn');

            summaryContainer.classList.add('hidden');
            loadingEl.classList.remove('hidden');
            summaryBtn.disabled = true;

            const historyText = (process.history || [])
                .map(h => {
                    const stepInfo = getStepInfo(h.stage);
                    const entered = h.enteredAt?.toDate().toLocaleString('pt-BR');
                    const exited = h.exitedAt ? h.exitedAt.toDate().toLocaleString('pt-BR') : 'Presente';
                    const duration = formatDuration(h.duration);
                    let internalActionsText = '';
                    if (h.internalActions && h.internalActions.length > 0) {
                        internalActionsText = h.internalActions.map(ia => `    - Ação Interna (${ia.createdAt.toDate().toLocaleDateString('pt-BR')}): ${ia.text}`).join('\n');
                    }
                    return `- Etapa: ${stepInfo?.department} - ${stepInfo?.step.name}\n  Ação Final: ${h.action || 'N/A'}\n  Entrada: ${entered}\n  Saída: ${exited}\n  Duração: ${duration}\n${internalActionsText}`;
                })
                .join('\n\n');
            
            const currentStepInfo = getStepInfo(process.status);

            const prompt = `
                Você é um assistente de análise de processos para a empresa CEHAB.
                Analise os seguintes dados de um processo interno e gere um resumo conciso e profissional em um único parágrafo em português.
                O resumo deve destacar o status atual, o setor responsável, o tempo total decorrido desde a criação e qualquer ação interna notável na etapa atual.
                Se houver alguma etapa que demorou significativamente mais que as outras, mencione-a como um possível ponto de atenção.

                DADOS DO PROCESSO:
                Número do Processo: ${process.processNumber}
                Setor Demandante: ${process.requestingSector}
                Descrição: ${process.description}
                Data de Criação: ${process.createdAt?.toDate().toLocaleString('pt-BR')}
                Status Atual: ${currentStepInfo?.department}

                HISTÓRICO DE ETAPAS:
                ${historyText}
            `;
            
            const summary = await callGemini(prompt);
            
            loadingEl.classList.add('hidden');
            summaryBtn.disabled = false;

            if (summary) {
                summaryTextEl.innerText = summary;
                summaryContainer.classList.remove('hidden');
            } else {
                summaryTextEl.innerText = 'Não foi possível gerar o resumo. Por favor, tente novamente.';
                summaryContainer.classList.remove('hidden');
            }
        };

        const handleSuggestAction = async () => {
            const process = processesData.find(p => p.id === currentProcessId);
            if (!process) return;

            const actionTextEl = document.getElementById('actionText');
            const loadingEl = document.getElementById('actionSuggestionLoading');
            const suggestBtn = document.getElementById('getSuggestionBtn');

            loadingEl.classList.remove('hidden');
            suggestBtn.disabled = true;
            actionTextEl.disabled = true;

            const currentStep = getStepInfo(process.status);
            
            const prompt = `
                Para um processo na etapa "${currentStep.step.name}" do departamento "${currentStep.department}" da CEHAB, gere uma sugestão de texto curta e profissional para o campo "ação realizada" que finaliza esta etapa.
                A ação deve descrever a conclusão bem-sucedida desta etapa. Seja direto e formal, usando o vocabulário típico de processos administrativos no Brasil.
                Retorne apenas o texto da ação, sem nenhuma introdução ou aspas.
                
                Exemplos para outras etapas:
                - "DDO emitida e enviada para homologação."
                - "Contrato formalizado e assinado pelas partes."
                - "Ordem de serviço gerada e encaminhada ao fornecedor."
            `;

            const suggestion = await callGemini(prompt);

            loadingEl.classList.add('hidden');
            suggestBtn.disabled = false;
            actionTextEl.disabled = false;

            if (suggestion) {
                actionTextEl.value = suggestion.trim().replace(/^"|"$/g, '');
            } else {
                actionTextEl.placeholder = 'Não foi possível gerar uma sugestão.';
            }
        };

        // --- HELPER FUNCTIONS ---
        const getStepInfo = (stepId) => {
            for (const department of WORKFLOW_DEPARTMENTS) {
                for (const step of department.steps) {
                    if (step.id === stepId) {
                        return { ...department, step };
                    }
                }
            }
            return null;
        };

        const getNextStepInfo = (currentStepId) => {
            let foundCurrent = false;
            for (const department of WORKFLOW_DEPARTMENTS) {
                for (const step of department.steps) {
                    if (foundCurrent) {
                        return { ...department, step }; // Return the very next step
                    }
                    if (step.id === currentStepId) {
                        foundCurrent = true;
                    }
                }
            }
            return null; // End of workflow
        };

        function formatDuration(milliseconds) {
            if (milliseconds === null || typeof milliseconds === 'undefined' || milliseconds < 0) return 'Em andamento';
            if (milliseconds < 1000) return 'Imediato';

            let seconds = Math.floor(milliseconds / 1000);
            let minutes = Math.floor(seconds / 60);
            let hours = Math.floor(minutes / 60);
            let days = Math.floor(hours / 24);

            hours %= 24;
            minutes %= 60;
            seconds %= 60;

            const parts = [];
            if (days > 0) parts.push(`${days}d`);
            if (hours > 0) parts.push(`${hours}h`);
            if (minutes > 0) parts.push(`${minutes}m`);
            if (seconds > 0 && parts.length === 0) parts.push(`${seconds}s`);

            return parts.join(' ') || 'Menos de 1s';
        }

        // --- RENDER FUNCTIONS ---
        const renderProcesses = (processes) => {
            loadingIndicator.classList.add('hidden');
            
            const isFiltering = filterProcessNumber.value || filterRequestingSector.value || filterDescription.value || filterStatus.value;

            if (!processes.length) {
                if (isFiltering) {
                    processList.innerHTML = `<p class="col-span-full text-center text-gray-500">Nenhum processo encontrado com os filtros aplicados.</p>`;
                } else {
                     processList.innerHTML = `<p class="col-span-full text-center text-gray-500">Nenhum processo encontrado. Crie um novo para começar.</p>`;
                }
                return;
            }

            processList.innerHTML = ''; 
            processes.sort((a,b) => b.createdAt?.toDate() - a.createdAt?.toDate());

            processes.forEach(proc => {
                const currentStepInfo = getStepInfo(proc.status) || { department: 'Setor Desconhecido' };
                const card = document.createElement('div');
                card.className = 'bg-white p-5 rounded-lg border border-gray-200 hover:shadow-xl hover:border-blue-500 transition-all duration-300 cursor-pointer flex flex-col justify-between';
                card.dataset.id = proc.id;

                card.innerHTML = `
                    <div>
                        <div class="flex justify-between items-start mb-2">
                            <h3 class="text-lg font-bold text-gray-800 truncate" title="${proc.processNumber}">${proc.processNumber}</h3>
                            <span class="text-xs font-semibold px-2 py-1 rounded-full bg-blue-100 text-blue-800 text-center">${currentStepInfo.department}</span>
                        </div>
                        <p class="text-sm text-gray-600 mb-1"><strong>Setor:</strong> ${proc.requestingSector}</p>
                        <p class="text-sm text-gray-500 line-clamp-2">${proc.description}</p>
                    </div>
                    <div class="mt-4 pt-4 border-t border-gray-200 text-right">
                        <span class="text-xs text-gray-400">Criado em: ${proc.createdAt?.toDate().toLocaleDateString('pt-BR')}</span>
                    </div>
                `;
                card.addEventListener('click', () => showDetailsModal(proc.id));
                processList.appendChild(card);
            });
        };

        const renderWorkflowSteps = (process) => {
            const container = document.getElementById('workflow-steps');
            container.innerHTML = '';
            const history = process.history || [];

            let globalStageIndex = 0;

            WORKFLOW_DEPARTMENTS.forEach(department => {
                // Don't render "Sistema" department if process is not finished
                if (department.id === 'FINALIZADO' && process.status !== 'FINALIZADO_CONCLUIDO') return;

                const departmentHeader = document.createElement('h5');
                departmentHeader.className = 'text-md font-bold text-gray-600 mt-6 mb-2 pl-1';
                if (department.id !== 'FINALIZADO') {
                    departmentHeader.innerText = department.department;
                    container.appendChild(departmentHeader);
                }

                const stepsContainer = document.createElement('div');
                container.appendChild(stepsContainer);

                department.steps.forEach((step, stepIndex) => {
                    const historyEntry = history.find(h => h.stage === step.id);
                    let statusClass = 'step-pending';
                    let detailsHTML = `<p class="text-sm text-gray-500">${step.description}</p>`;
                    let internalActionsHTML = '';

                    if (historyEntry) {
                         if (historyEntry.internalActions && historyEntry.internalActions.length > 0) {
                            internalActionsHTML = `
                                <div class="mt-3 pl-4 border-l-2 border-gray-200">
                                    <h6 class="text-xs font-semibold text-gray-500 mb-1">Ações Internas Registradas:</h6>
                                    <ul class="space-y-2">
                                        ${historyEntry.internalActions.map(action => `
                                            <li class="flex items-start">
                                                <i class="fas fa-tasks text-gray-400 mt-1 mr-2"></i>
                                                <div class="text-sm">
                                                    <p class="text-gray-700">${action.text}</p>
                                                    <p class="text-xs text-gray-400">${action.createdAt.toDate().toLocaleString('pt-BR')}</p>
                                                </div>
                                            </li>
                                        `).join('')}
                                    </ul>
                                </div>
                            `;
                        }

                        const enteredAt = historyEntry.enteredAt?.toDate()?.toLocaleString('pt-BR') || 'N/A';
                        if (historyEntry.exitedAt) { // Completed
                            statusClass = 'step-completed';
                            const exitedAt = historyEntry.exitedAt.toDate().toLocaleString('pt-BR');
                            detailsHTML = `
                                <p class="text-sm font-medium text-gray-800">${historyEntry.action || 'Ação não registrada'}</p>
                                ${internalActionsHTML}
                                <div class="text-xs text-gray-500 mt-2 grid grid-cols-2 gap-x-4">
                                    <span><i class="fas fa-sign-in-alt mr-1"></i> ${enteredAt}</span>
                                    <span><i class="fas fa-sign-out-alt mr-1"></i> ${exitedAt}</span>
                                </div>
                                <p class="text-xs text-green-700 font-semibold mt-1 bg-green-100 px-2 py-1 rounded w-fit"><i class="far fa-clock mr-1"></i> Permanência: ${formatDuration(historyEntry.duration)}</p>
                            `;
                        } else { // Current
                            statusClass = 'step-current';
                            detailsHTML = `
                                <p class="text-sm text-gray-600">${step.description}</p>
                                ${internalActionsHTML}
                                 <div class="text-xs text-gray-500 mt-2">
                                    <span><i class="fas fa-sign-in-alt mr-1"></i> ${enteredAt}</span>
                                </div>
                            `;
                        }
                    }

                    const isLastInGroup = true; // Since there is only one step per group now

                    const stepEl = document.createElement('div');
                    stepEl.className = `step flex items-start ${statusClass} pb-4`;
                    stepEl.innerHTML = `
                        <div class="flex flex-col items-center mr-4 relative">
                            <div class="step-circle w-8 h-8 rounded-full border-2 flex items-center justify-center bg-white z-10">
                               ${statusClass === 'step-completed' ? '<i class="fas fa-check text-white text-base"></i>' : `<span class="font-bold ${statusClass === 'step-current' ? 'text-white' : 'text-gray-400'}">${globalStageIndex + 1}</span>`}
                            </div>
                            ${!isLastInGroup && department.steps.length > 1 ? '<div class="step-line w-0.5 h-full absolute top-6 left-1/2 -translate-x-1/2"></div>' : ''}
                        </div>
                        <div class="pt-1 w-full">
                            <h5 class="font-semibold text-base ${statusClass === 'step-current' ? 'text-blue-600' : 'text-gray-800'}">${step.name}</h5>
                            <div class="text-sm text-gray-600 mt-1">${detailsHTML}</div>
                        </div>
                    `;
                    stepsContainer.appendChild(stepEl);
                    globalStageIndex++;
                });
            });
        };

        // --- FIRESTORE OPERATIONS ---
        let processesData = []; 
        let unsubscribe;

        const getFilteredProcesses = () => {
             const numberFilter = filterProcessNumber.value.toLowerCase();
            const sectorFilter = filterRequestingSector.value.toLowerCase();
            const descriptionFilter = filterDescription.value.toLowerCase();
            const statusFilter = filterStatus.value;

            let filteredProcesses = processesData;

            if (numberFilter) {
                filteredProcesses = filteredProcesses.filter(p => 
                    p.processNumber.toLowerCase().includes(numberFilter)
                );
            }

            if (sectorFilter) {
                filteredProcesses = filteredProcesses.filter(p =>
                    p.requestingSector.toLowerCase().includes(sectorFilter)
                );
            }

            if (descriptionFilter) {
                filteredProcesses = filteredProcesses.filter(p =>
                    p.description.toLowerCase().includes(descriptionFilter)
                );
            }

            if (statusFilter) {
                filteredProcesses = filteredProcesses.filter(p => {
                    const stepInfo = getStepInfo(p.status);
                    return stepInfo && stepInfo.department === statusFilter;
                });
            }
            return filteredProcesses;
        }

        const applyFiltersAndRender = () => {
            const filteredProcesses = getFilteredProcesses();
            renderProcesses(filteredProcesses);
        };
        
        const listenToProcesses = () => {
             if (unsubscribe) unsubscribe(); 
             const q = query(collection(db, `artifacts/${appId}/public/data/processes`));
             unsubscribe = onSnapshot(q, (snapshot) => {
                processesData = snapshot.docs.map(doc => ({ id: doc.id, ...doc.data() }));

                // Adiciona dados fictícios para teste se a base de dados estiver vazia
                if (processesData.length === 0) {
                    addMockDataForTesting();
                }

                applyFiltersAndRender();
                if (currentProcessId && !detailsModal.classList.contains('hidden')) {
                    const updatedProcess = processesData.find(p => p.id === currentProcessId);
                    if (updatedProcess) {
                         populateDetailsModal(updatedProcess);
                    } else {
                        closeDetailsModal();
                    }
                }
            }, (error) => {
                console.error("Error fetching processes:", error);
                processList.innerHTML = `<p class="col-span-full text-center text-red-500">Erro ao carregar processos.</p>`;
            });
        };

        const saveProcess = async (data) => {
            try {
                const initialStep = WORKFLOW_DEPARTMENTS[0].steps[0];
                if (data.id) { 
                    const docRef = doc(db, `artifacts/${appId}/public/data/processes`, data.id);
                    await updateDoc(docRef, {
                        processNumber: data.processNumber,
                        requestingSector: data.requestingSector,
                        description: data.description,
                    });
                } else { // Create new
                    await addDoc(collection(db, `artifacts/${appId}/public/data/processes`), {
                        processNumber: data.processNumber,
                        requestingSector: data.requestingSector,
                        description: data.description,
                        status: initialStep.id, 
                        createdAt: serverTimestamp(),
                        history: [{
                            stage: initialStep.id,
                            enteredAt: new Date(),
                            exitedAt: null,
                            action: null,
                            duration: null,
                            internalActions: []
                        }]
                    });
                }
                closeProcessModal();
            } catch (error) {
                console.error("Error saving process:", error);
            }
        };

        const advanceProcess = async (processId, actionText) => {
            const process = processesData.find(p => p.id === processId);
            if (!process) return;

            const nextStepInfo = getNextStepInfo(process.status);
            if (!nextStepInfo) return; // Already at the end

            const now = new Date();
            const currentHistoryEntry = process.history.find(h => h.stage === process.status);
            let duration = null;
            if(currentHistoryEntry && currentHistoryEntry.enteredAt) {
                duration = now.getTime() - currentHistoryEntry.enteredAt.toDate().getTime();
            }

            const updatedHistory = process.history.map(h => {
                if(h.stage === process.status) {
                    return { ...h, exitedAt: now, action: actionText, duration: duration };
                }
                return h;
            });
            
            if (nextStepInfo.step.id !== 'FINALIZADO_CONCLUIDO') {
                 updatedHistory.push({
                    stage: nextStepInfo.step.id,
                    enteredAt: now,
                    exitedAt: null, action: null, duration: null, internalActions: []
                });
            }

            try {
                const docRef = doc(db, `artifacts/${appId}/public/data/processes`, processId);
                await updateDoc(docRef, {
                    status: nextStepInfo.step.id,
                    history: updatedHistory
                });
                closeActionModal();
            } catch (error) {
                console.error("Error advancing process:", error);
            }
        };

        const saveInternalAction = async (processId, actionText) => {
            const process = processesData.find(p => p.id === processId);
            if (!process || !actionText.trim()) return;

            // Não permite salvar ação interna em dados mockados
            if (process.id.startsWith('mock')) {
                alert('Não é possível adicionar ações internas a um processo de teste.');
                return;
            }

            const updatedHistory = process.history.map(h => {
                if (h.stage === process.status) {
                    const internalActions = h.internalActions || [];
                    internalActions.push({
                        text: actionText,
                        createdAt: new Date()
                    });
                    return { ...h, internalActions };
                }
                return h;
            });

            try {
                const docRef = doc(db, `artifacts/${appId}/public/data/processes`, processId);
                await updateDoc(docRef, { history: updatedHistory });
                closeInternalActionModal();
            } catch (error) {
                console.error("Error saving internal action:", error);
            }
        };
        
        // --- MOCK DATA FUNCTION ---
        const addMockDataForTesting = () => {
            console.log("Base de dados vazia. Adicionando dados fictícios para teste.");
            const now = new Date();
            const mockProcesses = [
                {
                    id: 'mock1',
                    processNumber: '2025/001-FIN',
                    requestingSector: 'Diretoria Financeira',
                    description: 'Contratação de serviço de consultoria para análise de balanço anual.',
                    status: 'SUJUR_CONTRATO',
                    createdAt: { toDate: () => new Date(now.getTime() - 20 * 24 * 60 * 60 * 1000) }, // 20 days ago
                    history: [
                        { stage: 'DAF_AUTORIZACAO', enteredAt: { toDate: () => new Date(now.getTime() - 20 * 24 * 60 * 60 * 1000) }, exitedAt: { toDate: () => new Date(now.getTime() - 19 * 24 * 60 * 60 * 1000) }, action: 'Autorizado', duration: 86400000, internalActions: [] },
                        { stage: 'SUPLAN_ANALISE', enteredAt: { toDate: () => new Date(now.getTime() - 19 * 24 * 60 * 60 * 1000) }, exitedAt: { toDate: () => new Date(now.getTime() - 17 * 24 * 60 * 60 * 1000) }, action: 'Análise concluída', duration: 172800000, internalActions: [] },
                        { stage: 'GOP_DDO', enteredAt: { toDate: () => new Date(now.getTime() - 17 * 24 * 60 * 60 * 1000) }, exitedAt: { toDate: () => new Date(now.getTime() - 16 * 24 * 60 * 60 * 1000) }, action: 'DDO Emitida', duration: 86400000, internalActions: [] },
                        { stage: 'HOMOLOGACAO_EXEC', enteredAt: { toDate: () => new Date(now.getTime() - 16 * 24 * 60 * 60 * 1000) }, exitedAt: { toDate: () => new Date(now.getTime() - 15 * 24 * 60 * 60 * 1000) }, action: 'Homologado', duration: 86400000, internalActions: [] },
                        { stage: 'GOP_PF', enteredAt: { toDate: () => new Date(now.getTime() - 15 * 24 * 60 * 60 * 1000) }, exitedAt: { toDate: () => new Date(now.getTime() - 14 * 24 * 60 * 60 * 1000) }, action: 'PF Liberada', duration: 86400000, internalActions: [] },
                        { stage: 'SUFIN_CIENCIA', enteredAt: { toDate: () => new Date(now.getTime() - 14 * 24 * 60 * 60 * 1000) }, exitedAt: { toDate: () => new Date(now.getTime() - 13 * 24 * 60 * 60 * 1000) }, action: 'Ciência dada', duration: 86400000, internalActions: [] },
                        { stage: 'GEFIN_EMPENHO', enteredAt: { toDate: () => new Date(now.getTime() - 13 * 24 * 60 * 60 * 1000) }, exitedAt: { toDate: () => new Date(now.getTime() - 12 * 24 * 60 * 60 * 1000) }, action: 'Empenho emitido', duration: 86400000, internalActions: [] },
                        { stage: 'SUJUR_CONTRATO', enteredAt: { toDate: () => new Date(now.getTime() - 12 * 24 * 60 * 60 * 1000) }, exitedAt: null, action: null, duration: null, internalActions: [] },
                    ]
                },
                {
                    id: 'mock2',
                    processNumber: '2025/002-RH',
                    requestingSector: 'Recursos Humanos',
                    description: 'Abertura de processo seletivo para vaga de analista de sistemas.',
                    status: 'GOP_DDO',
                    createdAt: { toDate: () => new Date(now.getTime() - 5 * 24 * 60 * 60 * 1000) }, // 5 days ago
                    history: [
                        { stage: 'DAF_AUTORIZACAO', enteredAt: { toDate: () => new Date(now.getTime() - 5 * 24 * 60 * 60 * 1000) }, exitedAt: { toDate: () => new Date(now.getTime() - 4 * 24 * 60 * 60 * 1000) }, action: 'Autorizado', duration: 86400000, internalActions: [] },
                        { stage: 'SUPLAN_ANALISE', enteredAt: { toDate: () => new Date(now.getTime() - 4 * 24 * 60 * 60 * 1000) }, exitedAt: { toDate: () => new Date(now.getTime() - 2 * 24 * 60 * 60 * 1000) }, action: 'Análise concluída', duration: 172800000, internalActions: [] },
                        { stage: 'GOP_DDO', enteredAt: { toDate: () => new Date(now.getTime() - 2 * 24 * 60 * 60 * 1000) }, exitedAt: null, action: null, duration: null, internalActions: [] },
                    ]
                },
                {
                    id: 'mock3',
                    processNumber: '2025/003-INFRA',
                    requestingSector: 'Gerência de Infraestrutura',
                    description: 'Aquisição de 10 novos computadores para o setor de planejamento.',
                    status: 'GEFIN_LE',
                    createdAt: { toDate: () => new Date(now.getTime() - 35 * 24 * 60 * 60 * 1000) }, // 35 days ago
                    history: [
                        { stage: 'DAF_AUTORIZACAO', enteredAt: { toDate: () => new Date(now.getTime() - 35 * 24 * 60 * 60 * 1000) }, exitedAt: { toDate: () => new Date(now.getTime() - 34 * 24 * 60 * 60 * 1000) }, action: 'Autorizado', duration: 86400000, internalActions: [] },
                        { stage: 'SUPLAN_ANALISE', enteredAt: { toDate: () => new Date(now.getTime() - 34 * 24 * 60 * 60 * 1000) }, exitedAt: { toDate: () => new Date(now.getTime() - 33 * 24 * 60 * 60 * 1000) }, action: 'Análise concluída', duration: 86400000, internalActions: [] },
                        { stage: 'GOP_DDO', enteredAt: { toDate: () => new Date(now.getTime() - 33 * 24 * 60 * 60 * 1000) }, exitedAt: { toDate: () => new Date(now.getTime() - 32 * 24 * 60 * 60 * 1000) }, action: 'DDO Emitida', duration: 86400000, internalActions: [] },
                        { stage: 'HOMOLOGACAO_EXEC', enteredAt: { toDate: () => new Date(now.getTime() - 32 * 24 * 60 * 60 * 1000) }, exitedAt: { toDate: () => new Date(now.getTime() - 31 * 24 * 60 * 60 * 1000) }, action: 'Homologado', duration: 86400000, internalActions: [] },
                        { stage: 'GOP_PF', enteredAt: { toDate: () => new Date(now.getTime() - 31 * 24 * 60 * 60 * 1000) }, exitedAt: { toDate: () => new Date(now.getTime() - 30 * 24 * 60 * 60 * 1000) }, action: 'PF Liberada', duration: 86400000, internalActions: [] },
                        { stage: 'SUFIN_CIENCIA', enteredAt: { toDate: () => new Date(now.getTime() - 30 * 24 * 60 * 60 * 1000) }, exitedAt: { toDate: () => new Date(now.getTime() - 29 * 24 * 60 * 60 * 1000) }, action: 'Ciência dada', duration: 86400000, internalActions: [] },
                        { stage: 'GEFIN_EMPENHO', enteredAt: { toDate: () => new Date(now.getTime() - 29 * 24 * 60 * 60 * 1000) }, exitedAt: { toDate: () => new Date(now.getTime() - 28 * 24 * 60 * 60 * 1000) }, action: 'Empenho emitido', duration: 86400000, internalActions: [] },
                        { stage: 'SUJUR_CONTRATO', enteredAt: { toDate: () => new Date(now.getTime() - 28 * 24 * 60 * 60 * 1000) }, exitedAt: { toDate: () => new Date(now.getTime() - 25 * 24 * 60 * 60 * 1000) }, action: 'Contrato formalizado', duration: 259200000, internalActions: [] },
                        { stage: 'OS_EXEC', enteredAt: { toDate: () => new Date(now.getTime() - 25 * 24 * 60 * 60 * 1000) }, exitedAt: { toDate: () => new Date(now.getTime() - 24 * 24 * 60 * 60 * 1000) }, action: 'OS Emitida', duration: 86400000, internalActions: [] },
                        { stage: 'GOP_PAGAMENTO', enteredAt: { toDate: () => new Date(now.getTime() - 24 * 24 * 60 * 60 * 1000) }, exitedAt: { toDate: () => new Date(now.getTime() - 23 * 24 * 60 * 60 * 1000) }, action: 'Liberado para pagamento', duration: 86400000, internalActions: [] },
                        { stage: 'GEFIN_LE', enteredAt: { toDate: () => new Date(now.getTime() - 23 * 24 * 60 * 60 * 1000) }, exitedAt: null, action: null, duration: null, internalActions: [] },
                    ]
                },
                {
                    id: 'mock4',
                    processNumber: '2025/004-COM',
                    requestingSector: 'Comunicação Social',
                    description: 'Criação de campanha de divulgação interna sobre novas políticas de segurança.',
                    status: 'DAF_AUTORIZACAO',
                    createdAt: { toDate: () => now }, // Today
                    history: [
                        { stage: 'DAF_AUTORIZACAO', enteredAt: { toDate: () => now }, exitedAt: null, action: null, duration: null, internalActions: [] }
                    ]
                },
                {
                    id: 'mock5',
                    processNumber: '2025/005-PLAN',
                    requestingSector: 'Superintendência de Planejamento',
                    description: 'Revisão e atualização do Plano Diretor de Tecnologia da Informação (PDTI).',
                    status: 'FINALIZADO_CONCLUIDO',
                    createdAt: { toDate: () => new Date(now.getTime() - 60 * 24 * 60 * 60 * 1000) }, // 60 days ago
                    history: [
                        { stage: 'DAF_AUTORIZACAO', enteredAt: { toDate: () => new Date(now.getTime() - 60 * 24 * 60 * 60 * 1000)}, exitedAt: { toDate: () => new Date(now.getTime() - 59 * 24 * 60 * 60 * 1000)}, action: 'Autorizado', duration: 86400000, internalActions: [] },
                        { stage: 'SUPLAN_ANALISE', enteredAt: { toDate: () => new Date(now.getTime() - 59 * 24 * 60 * 60 * 1000)}, exitedAt: { toDate: () => new Date(now.getTime() - 58 * 24 * 60 * 60 * 1000)}, action: 'Análise concluída', duration: 86400000, internalActions: [] },
                        { stage: 'GOP_DDO', enteredAt: { toDate: () => new Date(now.getTime() - 58 * 24 * 60 * 60 * 1000)}, exitedAt: { toDate: () => new Date(now.getTime() - 57 * 24 * 60 * 60 * 1000)}, action: 'DDO Emitida', duration: 86400000, internalActions: [] },
                        { stage: 'HOMOLOGACAO_EXEC', enteredAt: { toDate: () => new Date(now.getTime() - 57 * 24 * 60 * 60 * 1000)}, exitedAt: { toDate: () => new Date(now.getTime() - 56 * 24 * 60 * 60 * 1000)}, action: 'Homologado', duration: 86400000, internalActions: [] },
                        { stage: 'GOP_PF', enteredAt: { toDate: () => new Date(now.getTime() - 56 * 24 * 60 * 60 * 1000)}, exitedAt: { toDate: () => new Date(now.getTime() - 55 * 24 * 60 * 60 * 1000)}, action: 'PF Liberada', duration: 86400000, internalActions: [] },
                        { stage: 'SUFIN_CIENCIA', enteredAt: { toDate: () => new Date(now.getTime() - 55 * 24 * 60 * 60 * 1000)}, exitedAt: { toDate: () => new Date(now.getTime() - 54 * 24 * 60 * 60 * 1000)}, action: 'Ciência dada', duration: 86400000, internalActions: [] },
                        { stage: 'GEFIN_EMPENHO', enteredAt: { toDate: () => new Date(now.getTime() - 54 * 24 * 60 * 60 * 1000)}, exitedAt: { toDate: () => new Date(now.getTime() - 53 * 24 * 60 * 60 * 1000)}, action: 'Empenho emitido', duration: 86400000, internalActions: [] },
                        { stage: 'SUJUR_CONTRATO', enteredAt: { toDate: () => new Date(now.getTime() - 53 * 24 * 60 * 60 * 1000)}, exitedAt: { toDate: () => new Date(now.getTime() - 52 * 24 * 60 * 60 * 1000)}, action: 'Contrato formalizado', duration: 86400000, internalActions: [] },
                        { stage: 'OS_EXEC', enteredAt: { toDate: () => new Date(now.getTime() - 52 * 24 * 60 * 60 * 1000)}, exitedAt: { toDate: () => new Date(now.getTime() - 51 * 24 * 60 * 60 * 1000)}, action: 'OS Emitida', duration: 86400000, internalActions: [] },
                        { stage: 'GOP_PAGAMENTO', enteredAt: { toDate: () => new Date(now.getTime() - 51 * 24 * 60 * 60 * 1000)}, exitedAt: { toDate: () => new Date(now.getTime() - 50 * 24 * 60 * 60 * 1000)}, action: 'Liberado para pagamento', duration: 86400000, internalActions: [] },
                        { stage: 'GEFIN_LE', enteredAt: { toDate: () => new Date(now.getTime() - 50 * 24 * 60 * 60 * 1000)}, exitedAt: { toDate: () => new Date(now.getTime() - 49 * 24 * 60 * 60 * 1000)}, action: 'Liquidação realizada', duration: 86400000, internalActions: [] },
                        { stage: 'GEFIN_PD', enteredAt: { toDate: () => new Date(now.getTime() - 49 * 24 * 60 * 60 * 1000)}, exitedAt: { toDate: () => new Date(now.getTime() - 48 * 24 * 60 * 60 * 1000)}, action: 'PD emitida', duration: 86400000, internalActions: [] },
                        { stage: 'GEFIN_OB', enteredAt: { toDate: () => new Date(now.getTime() - 48 * 24 * 60 * 60 * 1000)}, exitedAt: { toDate: () => new Date(now.getTime() - 47 * 24 * 60 * 60 * 1000)}, action: 'OB emitida', duration: 86400000, internalActions: [] },
                        { stage: 'GEFIN_RE', enteredAt: { toDate: () => new Date(now.getTime() - 47 * 24 * 60 * 60 * 1000)}, exitedAt: { toDate: () => new Date(now.getTime() - 46 * 24 * 60 * 60 * 1000)}, action: 'Remessa enviada', duration: 86400000, internalActions: [] },
                        { stage: 'FINALIZADO_CONCLUIDO', enteredAt: { toDate: () => new Date(now.getTime() - 46 * 24 * 60 * 60 * 1000)}, exitedAt: null, action: 'Processo concluído no sistema', duration: null, internalActions: [] },
                    ]
                },
            ];
            processesData.push(...mockProcesses);
        };

        // --- MODAL CONTROLLERS ---
        const openProcessModal = () => {
            processForm.reset();
            document.getElementById('modalTitle').textContent = 'Novo Processo';
            processModal.classList.remove('hidden');
        };
        const closeProcessModal = () => processModal.classList.add('hidden');
        
        const openActionModal = () => {
            document.getElementById('actionText').value = '';
            actionModal.classList.remove('hidden');
        };
        const closeActionModal = () => actionModal.classList.add('hidden');

        const openInternalActionModal = () => {
            document.getElementById('internalActionText').value = '';
            internalActionModal.classList.remove('hidden');
        };
        const closeInternalActionModal = () => internalActionModal.classList.add('hidden');


        const showDetailsModal = (processId) => {
            const process = processesData.find(p => p.id === processId);
            if (!process) return;
            currentProcessId = processId;
            populateDetailsModal(process);
             // Reset Gemini Summary
            document.getElementById('geminiSummaryContainer').classList.add('hidden');
            document.getElementById('geminiSummaryText').innerText = '';
            detailsModal.classList.remove('hidden');
        };
        
        const populateDetailsModal = (process) => {
            document.getElementById('detailsModalTitle').textContent = `Detalhes do Processo`;
            document.getElementById('detailsProcessNumber').textContent = `Nº: ${process.processNumber}`;
            document.getElementById('detailsRequestingSector').textContent = process.requestingSector;
            document.getElementById('detailsDescription').textContent = process.description;
            document.getElementById('detailsCreatedAt').textContent = process.createdAt?.toDate().toLocaleString('pt-BR');
            
            renderWorkflowSteps(process);

            const advanceBtn = document.getElementById('advanceProcessBtn');
            const addInternalBtn = document.getElementById('addInternalActionBtn');
            const finishedText = document.getElementById('processFinishedText');
            const currentStatusText = document.getElementById('currentStatusText');
            
            const currentStepInfo = getStepInfo(process.status);
            
            if (currentStepInfo && currentStepInfo.id !== 'FINALIZADO') {
                const nextStepInfo = getNextStepInfo(process.status);
                const advanceBtnText = document.getElementById('advanceBtnText');

                if (nextStepInfo) {
                    if (nextStepInfo.department !== currentStepInfo.department) {
                         advanceBtnText.textContent = `Enviar para ${nextStepInfo.department}`;
                    } else {
                         advanceBtnText.textContent = `Avançar para: ${nextStepInfo.step.name}`;
                    }
                }
               
                currentStatusText.textContent = `Status atual: ${currentStepInfo.step.description}`;
                advanceBtn.classList.remove('hidden');
                addInternalBtn.classList.remove('hidden');
                finishedText.classList.add('hidden');
            } else {
                currentStatusText.textContent = `Status atual: Processo concluído.`;
                advanceBtn.classList.add('hidden');
                addInternalBtn.classList.add('hidden');
                finishedText.classList.remove('hidden');
            }
        };

        const closeDetailsModal = () => {
            currentProcessId = null;
            detailsModal.classList.add('hidden');
        };

        const populateStatusFilter = () => {
            filterStatus.innerHTML = '<option value="">Todos os Status</option>';
            const departmentNames = [...new Set(WORKFLOW_DEPARTMENTS.map(d => d.department))];
            
            departmentNames.forEach(name => {
                if (name !== 'Sistema') {
                    const option = document.createElement('option');
                    option.value = name;
                    option.textContent = name;
                    filterStatus.appendChild(option);
                }
            });
        };

        const generateReport = () => {
            const filteredProcesses = getFilteredProcesses();

            if (filteredProcesses.length === 0) {
                // Using a custom modal/alert in the future would be better than window.alert
                alert("Nenhum processo encontrado com os filtros atuais para gerar o relatório.");
                return;
            }

            const numberFilter = filterProcessNumber.value;
            const sectorFilter = filterRequestingSector.value;
            const descriptionFilter = filterDescription.value;
            const statusFilter = filterStatus.value;
            
            let filterSummary = '<ul style="list-style: none; padding: 0;">';
            let hasFilters = false;

            if(numberFilter) { filterSummary += `<li><strong>Número do Processo:</strong> ${numberFilter}</li>`; hasFilters = true; }
            if(sectorFilter) { filterSummary += `<li><strong>Setor Demandante:</strong> ${sectorFilter}</li>`; hasFilters = true; }
            if(descriptionFilter) { filterSummary += `<li><strong>Descrição:</strong> ${descriptionFilter}</li>`; hasFilters = true; }
            if(statusFilter) { filterSummary += `<li><strong>Status Atual:</strong> ${statusFilter}</li>`; hasFilters = true; }
            
            if (!hasFilters) {
                filterSummary += '<li>Nenhum filtro aplicado.</li>';
            }
            filterSummary += '</ul>';

            const tableRows = filteredProcesses.map(proc => {
                const currentStepInfo = getStepInfo(proc.status) || { department: 'N/A' };
                const createdAt = proc.createdAt ? proc.createdAt.toDate().toLocaleDateString('pt-BR') : 'N/A';
                return `
                    <tr>
                        <td>${proc.processNumber}</td>
                        <td>${proc.requestingSector}</td>
                        <td>${proc.description}</td>
                        <td>${currentStepInfo.department}</td>
                        <td>${createdAt}</td>
                    </tr>
                `;
            }).join('');

            const reportHtml = `
                <!DOCTYPE html>
                <html lang="pt-BR">
                <head>
                    <meta charset="UTF-8">
                    <title>Relatório de Processos - CEHAB</title>
                    <style>
                        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; margin: 2em; color: #333; }
                        .report-header { border-bottom: 2px solid #0056b3; padding-bottom: 1em; margin-bottom: 1em; }
                        h1 { color: #0056b3; }
                        .filter-summary { background-color: #f0f2f5; border: 1px solid #e0e0e0; border-left: 5px solid #0056b3; padding: 1em; margin: 2em 0; }
                        table { width: 100%; border-collapse: collapse; margin-top: 1em; }
                        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
                        th { background-color: #f0f2f5; font-weight: 600; }
                        tr:nth-child(even) { background-color: #f9f9f9; }
                        @media print {
                            body { margin: 0; }
                            .no-print { display: none; }
                        }
                    </style>
                </head>
                <body>
                    <div class="report-header">
                        <h1>Relatório de Processos - CEHAB</h1>
                        <p>Gerado em: ${new Date().toLocaleString('pt-BR')}</p>
                    </div>
                    
                    <div class="filter-summary">
                        <h3>Filtros Aplicados</h3>
                        ${filterSummary}
                    </div>

                    <h2>${filteredProcesses.length} Processo(s) Encontrado(s)</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>Número do Processo</th>
                                <th>Setor Demandante</th>
                                <th>Descrição</th>
                                <th>Status Atual</th>
                                <th>Data de Criação</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${tableRows}
                        </tbody>
                    </table>
                </body>
                </html>
            `;
            const reportWindow = window.open('', '_blank');
            reportWindow.document.write(reportHtml);
            reportWindow.document.close();
            reportWindow.print();
        };


        // --- EVENT LISTENERS ---
        document.getElementById('newProcessBtn').addEventListener('click', openProcessModal);
        document.getElementById('closeModalBtn').addEventListener('click', closeProcessModal);
        document.getElementById('closeDetailsModalBtn').addEventListener('click', closeDetailsModal);
        
        document.getElementById('advanceProcessBtn').addEventListener('click', openActionModal);
        document.getElementById('cancelActionBtn').addEventListener('click', closeActionModal);
        
        document.getElementById('addInternalActionBtn').addEventListener('click', openInternalActionModal);
        document.getElementById('cancelInternalActionBtn').addEventListener('click', closeInternalActionModal);

        document.getElementById('generateSummaryBtn').addEventListener('click', handleGenerateSummary);
        document.getElementById('getSuggestionBtn').addEventListener('click', handleSuggestAction);
        generateReportBtn.addEventListener('click', generateReport);
        
        document.getElementById('confirmActionBtn').addEventListener('click', () => {
            const actionText = document.getElementById('actionText').value;
            if(!actionText.trim()){
                alert('Por favor, descreva a ação finalizadora.');
                return;
            }
            if(currentProcessId) advanceProcess(currentProcessId, actionText);
        });

        document.getElementById('confirmInternalActionBtn').addEventListener('click', () => {
            const actionText = document.getElementById('internalActionText').value;
            if(!actionText.trim()){
                alert('Por favor, descreva a ação interna.');
                return;
            }
            if(currentProcessId) saveInternalAction(currentProcessId, actionText);
        });
        
        processForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const data = {
                id: document.getElementById('processId').value,
                processNumber: document.getElementById('processNumber').value,
                requestingSector: document.getElementById('requestingSector').value,
                description: document.getElementById('description').value
            };
            saveProcess(data);
        });
        
        // Filter listeners
        filterProcessNumber.addEventListener('input', applyFiltersAndRender);
        filterRequestingSector.addEventListener('input', applyFiltersAndRender);
        filterDescription.addEventListener('input', applyFiltersAndRender);
        filterStatus.addEventListener('change', applyFiltersAndRender);
        clearFiltersBtn.addEventListener('click', () => {
            filterProcessNumber.value = '';
            filterRequestingSector.value = '';
            filterDescription.value = '';
            filterStatus.value = '';
            applyFiltersAndRender();
        });


        // Close modals with backdrop click
        [processModal, detailsModal, actionModal, internalActionModal].forEach(modal => {
             modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    if(modal.id === 'processModal') closeProcessModal();
                    if(modal.id === 'detailsModal') closeDetailsModal();
                    if(modal.id === 'actionModal') closeActionModal();
                    if(modal.id === 'internalActionModal') closeInternalActionModal();
                }
            });
        });

        // --- INITIAL LOAD ---
        authenticateUser().then(() => {
            populateStatusFilter();
            listenToProcesses();
        });

    </script>
</body>
</html>


