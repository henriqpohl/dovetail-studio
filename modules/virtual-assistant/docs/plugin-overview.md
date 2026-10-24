# Funcionamento geral do plugin

```mermaid
flowchart TD
    subgraph Admin["WP Admin: VA Enquiries > Settings"]
        A1[AI Keys]
        A2[Company]
        A3[AI Assistant]
        A4[Knowledge base]
        A5[Doors]
        A6[Offline FAQ]
        A7[Theme]
    end

    Admin -->|salvo em wp_options| Bootstrap["wp_head / wp_footer: bootstrap window.DT_VA_SERVER"]
    Bootstrap --> Front["front.js: widget de chat"]

    Front -->|clique em doors/steps| Flow["Fluxo scriptado: doors -> steps -> work/money/timing/capture"]
    Front -->|texto livre, isQuestion| Ask["POST /wp-json/dt-va/v1/ask"]
    Ask --> Provider["dt_va_provider_answer: OpenAI / Anthropic + Knowledge base no system prompt"]
    Provider -. falha, sem chave ou timeout .-> Offline["offlineAnswer(): FAQ configurada ou tabela built-in"]

    Flow --> Submit["POST /wp-json/dt-va/v1/submit"]
    Submit --> Enquiry["dt_enquiry (post privado) + email ao estudio + copia ao cliente + ICS de call-back"]

    Cron1["Cron diario 07:30: self-check"] --> Provider
    Cron2["Cron semanal: digest"] --> Enquiry

    Front -->|analytics| Event["POST /wp-json/dt-va/v1/event"]
    Event --> Stats["Contadores semanais (options dt_va_stats_*), usados no digest"]
```

- **Admin**: cada aba grava opções no banco (`dt_va_*`); nada exige editar código.
- **Bootstrap**: `dt-va-server.php` monta `$cfg` (flowConfig, faqConfig, themeConfig, publicConfig) e injeta no HTML antes do `front.js` carregar.
- **Flow (doors/steps)**: 100% no cliente, sem rede, até chegar em `capture` e disparar `/submit`.
- **Ask**: só acionado quando o texto livre parece pergunta; a IA responde apenas com base na Knowledge base do system prompt.
- **Submit**: sempre roda (mesmo sem IA), gera o registro em VA Enquiries e os emails.
- **Crons**: self-check diário avisa se a IA parou de responder; digest semanal resume a semana (enquiries, funil, doors mais usados).
