# Cadastro: Knowledge base, Doors e Offline FAQ

Os três seguem o mesmo padrão: um formulario grava uma opcao do WordPress depois de validar; em runtime, o front-end ou o system prompt usam essa opcao se ela existir, senao caem no comportamento padrao (hardcoded).

### Knowledge base (`dt-va/knowledge.php`)

```mermaid
flowchart TD
    KB1["Textarea (texto livre)"] --> KB2["update_option dt_va_knowledge_override"]
    KB2 --> KB3["dt_va_knowledge_base(): override, senao knowledge.md, senao default embutido"]
    KB3 --> KB4["dt_va_system_prompt(): embutido no prompt da IA"]
```

### Doors (`dt-va/flow.php`)

```mermaid
flowchart TD
    D1["Add a door (form simples)"] --> D2["monta candidate = doors atuais + novo"]
    D3["Textarea JSON (edicao em lote)"] --> D2b["candidate = JSON completo"]
    D2 --> D4["dt_va_flow_doors_validate()"]
    D2b --> D4
    D4 -->|ok| D5["update_option dt_va_flow_doors"]
    D4 -->|erro| D6["Notice de erro, nada salvo"]
    D5 --> D7["doors() no front.js via flowConfig.doors"]
```

### Offline FAQ (`dt-va/faq.php`)

```mermaid
flowchart TD
    F1["Add an entry (form simples)"] --> F2["monta candidate = entries atuais + nova"]
    F3["Textarea JSON (edicao em lote)"] --> F2b["candidate = JSON completo"]
    F2 --> F4["dt_va_faq_validate()"]
    F2b --> F4
    F4 -->|ok| F5["update_option dt_va_faq_entries"]
    F4 -->|erro| F6["Notice de erro, nada salvo"]
    F5 --> F7["offlineAnswer() no front.js via faqConfig"]
```

- **Padrao comum**: "Add ..." (form simples) e textarea (JSON bruto) alimentam a MESMA funcao de validacao, entao nunca ha um caminho que aceite algo que o outro rejeitaria.
- **Restore default**: em todos os tres, apaga a opcao (`delete_option`) e volta ao comportamento padrao hardcoded, sem apagar arquivos.
- **Seguranca por design**: Doors valida `action`/`service` por formato (nunca free text executavel); Offline FAQ aceita so palavras/frases simples como keyword, nunca regex (evita ReDoS); Knowledge base e texto livre mas so alimenta o system prompt, nunca e executado.
