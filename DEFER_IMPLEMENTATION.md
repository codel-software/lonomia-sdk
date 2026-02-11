🧩 Task — Controle e Redução de Payload no Lonomia SDK
1) Objetivo

Implementar uma estratégia de controle de tamanho de payload no Lonomia SDK para:

Evitar envio excessivo de dados

Reduzir consumo de banda

Prevenir sobrecarga no cliente e no servidor

Manter o máximo de contexto possível, principalmente em cenários de erro

Garantir que o envio nunca quebre a aplicação por volume excessivo.

2) Problema Atual

Algumas interceptações geram payloads muito grandes (request, response, logs, redis, etc.)

Isso pode:

Estourar limites de tamanho

Aumentar latência

Sobrecarregar servidor e cliente

Consumir banda desnecessariamente

Em cenários de erro, cortar demais pode perder o contexto do problema

3) Estratégia Geral

Implementar um pipeline de redução progressiva por regras, baseado em:

Tamanho final do payload

Tipo de resposta (normal vs erro)

Prioridade de dados

Com dois limites distintos:

Limite padrão (status OK / esperado) → agressivo

Limite de erro (status inesperado) → mais permissivo

4) Regras de Limite
4.1 Limites

Definir dois thresholds configuráveis:

MAX_PAYLOAD_OK → limite menor (ex: 200KB – 500KB)

MAX_PAYLOAD_ERROR → limite maior (ex: 1MB – 2MB)

Critério de erro:

Status fora de 2xx / 3xx

Exceções

Timeouts

Falhas internas

5) Pipeline de Redução (Regras Progressivas)

A redução ocorre em etapas, até o payload ficar dentro do limite.

Ordem sugerida de aplicação:

🥇 Regra 1 — Redução de Request / Response (Body)

Aplicada primeiro.

Ações:

Ignorar sempre:

Arquivos (files, uploads, binários)

Para JSON:

Percorrer recursivamente a árvore

Manter todas as chaves

Reduzir valores:

Strings → manter apenas primeiros N caracteres (ex: 3–10 chars)

Arrays grandes → truncar após N itens

Objetos grandes → limitar profundidade

Objetivo:

Preservar estrutura + chaves, reduzir volume de dados

🥈 Regra 2 — Redução de Logs

Aplicada se ainda exceder limite.

Ações:

Limitar quantidade de logs enviados

Truncar mensagens longas

Remover campos menos relevantes

Preservar:

Nível (error, warn, info)

Timestamp

Mensagem principal

Stack trace (em erro)

🥉 Regra 3 — Redução de Requests Capturados

Ações:

Limitar headers enviados

Remover:

Cookies grandes

Authorization (ou mascarar)

Headers redundantes

Truncar bodies grandes novamente se necessário

🏅 Regra 4 — Redução de Redis / Cache / Extras

Ações:

Limitar número de chaves

Truncar valores

Remover dumps grandes

🔚 Regra Final — Corte Forçado

Se ainda estiver acima do limite:

Aplicar corte bruto:

Remover seções inteiras menos prioritárias

Manter apenas:

Metadata

Request básico

Response básico

Erro / stack trace (se existir)

6) Priorização de Dados (Ordem de Importância)

Prioridade máxima:

Erro / Exception / Stack trace

Status code

URL + método

Headers principais

Request body (reduzido)

Response body (reduzido)

Logs

Redis / extras

7) Comportamento por Tipo de Resposta
Caso OK (2xx / 3xx)

Usar limite menor

Redução agressiva

Menos contexto necessário

Caso Erro (4xx / 5xx / Exception)

Usar limite maior

Redução mais conservadora

Preservar:

Stack trace

Mensagens completas (até certo ponto)

Contexto maior

8) Requisitos Técnicos

Processo determinístico (sempre gera algo válido)

Nunca lançar exceção durante redução

Nunca impedir envio do evento

Sempre garantir:

Payload válido

JSON consistente

Estrutura preservada

9) Aceite (Critérios de Conclusão)

A task é considerada concluída quando:

 Payload nunca excede os limites definidos

 Regras são aplicadas em pipeline progressivo

 Em erro, mais dados são preservados

 Arquivos nunca são enviados

 Estrutura JSON é mantida mesmo após redução

 Nenhum cenário quebra a aplicação por volume

10) Observações de Arquitetura

Sugestão de estrutura:

PayloadBuilder
  └── SizeChecker
        └── RulePipeline
              ├── RuleReduceBody
              ├── RuleReduceLogs
              ├── RuleReduceRequests
              ├── RuleReduceRedis
              └── RuleFinalCut


Cada regra:

Recebe payload

Retorna payload reduzido

Informa novo tamanho