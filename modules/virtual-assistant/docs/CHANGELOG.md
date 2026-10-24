# Alterações

## Não lançado

### Documentação — 30/09/2026

- Esclarecido em USAGE.md e README.md o comportamento dos defaults na revisão introduzida em 1.31.0: pendência inicial nas quatro seções, aceitação explícita dos padrões permitidos, restrições para Knowledge base/Sample de Portfolio e retorno imediato da pendência após Restore default ou Save bem-sucedido.
- Incluídos tabela por seção e exemplo do Offline FAQ. Alteração apenas documental, sem mudança de comportamento do plugin.

## 1.31.1 — 24/10/2026

### Manutenção

- Pasta `src/Services/` renomeada para `src/services/`, seguindo a convenção de nomes do Dovetail Studio (pastas que não correspondem a um namespace ficam em minúsculas). Os `require_once` de `src/runtime.php` foram atualizados. Sem mudança de comportamento.

## 1.31.0 — 29/09/2026

### Revisão da configuração por empresa e aviso atualizado imediatamente

- Substituída a detecção por existência de opções/nomes de empresas por revisão explícita da configuração atual. Comparação normalizada dos modelos e assinatura do conteúdo revisado; salvar modelos não equivale a configurar uma empresa.
- Knowledge base exige texto próprio; Doors e FAQ permitem aceitar padrões explicitamente; Portfolio vazio pode ser aceito intencionalmente, enquanto o Sample inalterado permanece pendente.
- Doors verifica os destinos dos serviços e a validade das referências de Step questions. A revisão inclui os steps e é invalidada quando mudam.
- Processamento dos POSTs separado da renderização e antecipado para `admin_init`. Save/Restore e confirmações atualizam o aviso na mesma resposta, sem segundo clique; os renderizadores reutilizam o resultado e não duplicam a gravação.
- Save/Restore invalidam revisões anteriores. Confirmações usam nonce, permissão administrativa e assinatura contra revisão de conteúdo alterado em outra aba.
- Dados existentes preservados; todos os itens começam pendentes até a revisão explícita. O assistente continua disponível: aviso administrativo, sem bloqueio aos visitantes.
- Testes isolados cobrem modelos, aceitação de padrões, destinos inválidos, confirmação desatualizada, permissões, atualização imediata e prevenção de processamento duplicado.

## 1.30.0 — 29/09/2026

### Aviso no admin: Doors/Offline FAQ/Portfolio/Knowledge base ainda genéricos

- Novo `admin_notices` em `dt-va-server.php`: nas telas do próprio plugin (`edit.php?post_type=dt_enquiry` e submenus), mostra um aviso quando `dt_va_faq_config()`, `dt_va_flow_doors_config()` ou `dt_va_case_studies_config()` estiverem vazios (ainda no fallback genérico de fábrica), com links direto para cada aba de Settings. Não bloqueia nada, só lembra — os fallbacks genéricos continuam funcionando normalmente sem essa configuração.
- Knowledge base não tem estado "vazio" (sempre cai em `knowledge.md` ou no texto embutido em `dt_va_knowledge_base_default()`), então é sinalizada por heurística: sem override salvo (`dt_va_knowledge_override() === ''`) e o texto padrão ainda contém "Design Minds" literalmente.

## 1.29.0 — 29/09/2026

### Import, Export e Download a Sample nos editores JSON

- Controles adicionados junto a Save/Restore em Doors, Step questions, Offline FAQ e Portfolio.
- Import lê arquivos de até 1 MB e valida no servidor com o validador específico de cada editor, exigindo permissão de administrador e nonce. Aceita BOM UTF-8 e rejeita formato/estrutura incorretos. Não persiste dados: preenche o editor para revisão e Save.
- Export baixa o conteúdo validado do editor, incluindo alterações ainda não salvas. Download a Sample fornece um modelo próprio de cada formato sem alterar conteúdo ou opções.
- Erros preservam o editor; edições feitas durante uma importação não são sobrescritas. Save/Restore aguardam a validação em andamento.
- Testes PHP e JavaScript cobrem os quatro formatos, modelos, permissões, nonce, downloads, falhas e concorrência com edição/salvamento.

## 1.28.0 — 29/09/2026

### Portfólio configurável no admin

- Nova aba **Settings → Portfolio** com JSON, formulário Add an entry e Restore default. Valida até 30 projetos, URLs e palavras-chave sem regex. Exemplos ficam apenas no placeholder.
- `caseStudiesConfig` substitui integralmente o portfólio embutido; o assistente prioriza matches e completa até três cards com projetos salvos. URLs são validadas também no cliente.
- Testes PHP e JavaScript cobrem validação, persistência, restauração e seleção dos cards.

### FAQ offline embutido: de específico da Design Minds para genérico

- `var T` (FAQ embutido, `dt-va/assets/front.js` `offlineAnswer()`) reescrito de ~41 categorias com fatos fixos da Design Minds (telefone, endereço, prêmios, portfólio, processo de trabalho, política de revisões) para 6 categorias genéricas: contato, horário de funcionamento, endereço/visita, avaliações, orçamento e "o que vocês fazem". Cada resposta é montada a partir dos campos já existentes em **Settings → Company** (`COMPANY_PHONE_DISPLAY`, `WHATSAPP_DISPLAY`, `COMPANY_EMAIL`, `COMPANY_ADDRESS`, `GOOGLE_RATING`, `GOOGLE_REVIEWS`) em vez de texto fixo — fica correto automaticamente pra qualquer empresa que preencheu esses campos, e cai numa frase neutra quando o campo está em branco (nunca inventa um fato).
- Motivo: `T` é o fallback usado quando não há Offline FAQ customizada e a IA está desligada/indisponível — antes disso significava responder com dados da Design Minds pra qualquer instalação nova que não tivesse configurado a Offline FAQ ainda, mesmo sendo outro tipo de negócio.
- `dt_va_faq_starter()` (`dt-va/faq.php`, texto de exemplo mostrado no admin quando nada está salvo) ajustado para o mesmo conjunto genérico (`cost`, `contact`).
- Conteúdo de negócio específico (processo de trabalho, política de revisões, formatos de arquivo, serviços oferecidos etc.) não tem campo correspondente em Settings → Company e não pode ser genericizado com segurança — foi removido do embutido; cada instalação cadastra o que precisar na Offline FAQ do admin.
- `tests/frontend.cjs` reescrito: um caso por categoria genérica, os dois ramos de campo em branco (endereço e avaliação vazios), um teste de ordem de prioridade e o caso sem match.
- Ver `USAGE.md` na raiz para o checklist de instalação nova e diagnóstico via console (`DT_VA_SERVER.faqConfig`/`askEnabled`).

### Doors e fluxo guiado: de específico da Design Minds para genérico

- `defaultDoors()` (front.js) reduzido de 6 doors específicos de agência de design (`logo`/`print`/`web`/`client`/`other`/`human`) para 4 genéricos: **Enquire about a product or service**, **I'm already a client**, **Something else**, **I'd rather talk to a person**. Motivo: os 3 primeiros doors antigos disparavam perguntas de agência ("novo negócio ou refresh de identidade?", tamanho de brochura, "novo site ou redesign?") sem sentido pra outro tipo de negócio.
- Os três buckets antigos (`logo1`/`print1`/`web1`, com `scope()`/`size()`/`goal()` como sub-perguntas) foram substituídos por um único bucket genérico `enquiry1`, que vai direto para `describe()` (pergunta livre "conte um pouco sobre o negócio"), sem sub-perguntas de tipo/formato.
- `CASE_STUDIES`/`CASE_STUDIES_TAGGED` (portfólio da Design Minds) esvaziados por padrão — `work()` já tinha o fallback "sem case studies, pula pra `money()`", então nenhuma outra função precisou mudar. Antes disso, qualquer instalação nova mostrava o portfólio da Design Minds pros visitantes de outra empresa.
- `clientQ()` (perguntas de cliente existente) generalizada: "reprint"/"website update" viraram "repeat order"/"change to an existing order or service"; os exemplos trocaram nomes de jobs reais da Design Minds ("PMac 4-page brochure", "Kylemore business cards") por texto neutro.
- `LABEL` simplificado para `enquiry`/`client`/`other` (era `logo`/`print`/`web`/`client`/`other`). `pagesFrom()` removida (só existia para o fluxo de impressão).
- PHP: `dt_va_flow_doors_default()`, `dt_va_flow_builtin_step_ids()` e `dt_va_flow_steps_starter()` (`dt-va/flow.php`) atualizados pro mesmo conjunto genérico, mantendo em sincronia com o literal do front.js (são duas cópias mantidas manualmente, por design — ver comentário em `dt_va_flow_doors_default()`).
- `tests/frontend.cjs` e `tests/flow.php` atualizados para os novos ids/contagens.

## 1.27.0 — 29/09/2026

### Peça 6: agendamento do digest semanal fica configurável

- Em **Settings → Company**, nova seção **Weekly digest**: dia da semana, horário (HH:MM, Europe/Dublin) e um destinatário opcional separado do "Enquiry email". Padrão inalterado: segunda-feira 08:00, enviado pro Enquiry email, igual antes.
- `dt_va_digest_day()`, `dt_va_digest_time()` e `dt_va_digest_recipient()` (novo, em `dt-va/company.php`) leem essas opções com validação e fallback pro padrão atual.
- Ao salvar, `dt_va_digest_reschedule()` (em `dt-va-server.php`) chama `wp_clear_scheduled_hook` + `wp_schedule_event` na hora, sem esperar o próximo carregamento de página. Como segurança extra, toda request compara a configuração salva com a última agendada (`dt_va_digest_scheduled_key`) e reagenda se estiverem diferentes — cobre o caso de a opção ter sido alterada por outro caminho.
- `tests/company.php` ganhou casos cobrindo os padrões, o fallback do destinatário, o salvamento válido e a rejeição de dia/hora/email inválidos (sem alterar o que já estava salvo).

## 1.26.0 — 29/09/2026

### Offline FAQ ganhou o mesmo formulário "Add an entry" de Doors/Steps

- Em **Settings → Offline FAQ**, novo formulário **Add an entry** acima do textarea: id, keywords (separadas por vírgula) e answer. Ao salvar, o servidor pega a lista atual (salva ou o exemplo inicial), acrescenta a nova entrada e valida o conjunto inteiro com a mesma `dt_va_faq_validate()` de sempre.
- Mesmo raciocínio da 1.25.0: elimina o erro mais comum de editar um array JSON grande à mão pra adicionar só um item. O textarea continua disponível pra edição em lote, reordenação ou remoção.
- `tests/faq.php` ganhou casos cobrindo o novo formulário: adição válida com split/trim de `keywords`, e rejeição quando falta o id (sem alterar o que já estava salvo).

## 1.25.0 — 29/09/2026

### "Add a door" e "Add a step": formulário pra não precisar editar JSON à mão

- Em **Settings → Doors**, novo formulário **Add a door** acima do textarea: campos simples (id, label, action, service, sub, short, icon). Ao salvar, o servidor pega a lista atual (salva ou padrão), acrescenta o novo door e valida o conjunto inteiro com a mesma `dt_va_flow_doors_validate()` de sempre — sem editar JSON à mão pro caso comum de só adicionar um item.
- Mesma ideia em **Step questions**: formulário **Add a step** com id, type, question, answerKey, options (separadas por vírgula) e next (só id simples, sem ramificação — branch continua exigindo o textarea).
- O textarea continua do lado, pra edição em lote, reordenação, remoção ou `next` com ramificação. Os dois formulários usam a mesma validação do JSON, então não há caminho que aceite algo que o textarea rejeitaria.
- Motivação: editar objetos um a um dentro de um array JSON grande costuma quebrar por vírgula sobrando/faltando; o formulário elimina esse erro pro caso mais comum.
- `tests/flow.php` ganhou casos cobrindo os dois formulários: adição válida, split/trim de `options`, uso de id embutido em `next` sem precisar redefini-lo, e rejeição quando faltam campos obrigatórios (sem alterar o que já estava salvo).

## 1.24.0 — 29/09/2026

### Doors: `service` deixa de ser uma lista fixa de 4 valores

- `service` (usado quando `action: "service"`) aceitava só `logo`, `print`, `web` ou `client` — nomes específicos da Design Minds que não fazem sentido pra outros ramos. Agora aceita qualquer id em minúsculas (letras, números, `-` ou `_`, até 40 caracteres), validado por formato em vez de lista fixa (`dt_va_flow_service_valid()`).
- Continua exigindo um step correspondente (`service + "1"`, ex.: `plumbing1`) cadastrado em Step questions para ter uma pergunta real; sem isso, o door apenas reinicia o assistente. Texto de ajuda da aba Doors atualizado pra deixar isso claro.
- `logo`/`print`/`web`/`client` continuam funcionando exatamente como antes (são só mais um id válido agora, não um caso especial).
- `tests/flow.php` atualizado: teste que antes rejeitava `"plumbing"` agora confirma que é aceito; novo teste cobre formato inválido (espaços/símbolos).

## 1.23.0 — 29/09/2026

### Perguntas de cada etapa editáveis pelo admin (Peça 1b)

- Nova seção **Step questions** dentro de VA Enquiries → Settings → Doors (`dt_va_flow_steps_settings()` em `dt-va/flow.php`), com um JSON num `<textarea>` validado no servidor (opção `dt_va_flow_steps`). Cada step precisa de `type` (`choice` ou `text`), `question`, `answerKey` e `next`; `choice` também precisa de 1–88 `options`.
- `next` aceita um id de step (string) ou uma ramificação `{"if": {"key":..., "answerContains"|"answerMissing"|"answerEquals": ...}, "then":..., "else":...}`, com até 5 níveis de aninhamento, validada recursivamente. Condições são dados enumerados, nunca expressões livres.
- **Override parcial, não tudo ou nada**: um step não redefinido continua usando o comportamento fixo de hoje. `next` pode apontar pra qualquer id já conhecido do assistente (`logo1`, `print1`, `web1`, `client1`, `scope`, `size`, `goal`, `describe`, `work`, `money`, `timing`, `capture`, `other`, `human`) mesmo sem redefini-lo — a pipeline fixa (`work`→`money`→`timing`→`capture`, que faz o casamento de case studies e o formulário de contato) continua com encadeamento interno fixo, só pode ser referenciada como destino, nunca redefinida.
- Novo intérprete de steps em `front.js` (`goToStep()`/`resolveNext()`/`runConfigStep()`): pontos de entrada de cada door (`logo1`/`print1`/`web1`/`client1`) e as transições internas de `service()`/`scope()`/`size()`/`goal()`/`describe()` passam a rotear por `goToStep()`, que usa o step configurado quando existe e cai no comportamento hardcoded de hoje quando não existe — sem risco de regressão pra quem não customizar.
- Exposto como `flowConfig.steps`. "Restore default" limpa a opção.
- Testes novos em `tests/flow.php` (validação de steps, branches, referências desconhecidas) e em `tests/frontend.cjs` (interpretador: fallback pro STEPS[id] embutido, execução de choice/text, resolução de branch com os três operadores).
- Isso conclui a Peça 1 completa (1a e 1b) do FEATURES.md.

## 1.22.0 — 29/09/2026

### FAQ local (offlineAnswer) editável pelo admin

- Nova aba **VA Enquiries → Settings → Offline FAQ** (`dt-va/faq.php`), com um JSON num `<textarea>` validado no servidor (opção `dt_va_faq_entries`). Cada entrada precisa de `id`, ao menos uma `keyword` **simples** (letras, números, espaços, apóstrofos — nunca regex, pra não abrir brecha de ReDoS com conteúdo salvo pelo admin) e um `answer`. `{{COMPANY_NAME}}` no texto da resposta é substituído em tempo de execução pelo nome da empresa configurado.
- Exposto como `faqConfig` em `window.DT_VA_SERVER`. `offlineAnswer()` em `front.js` usa a config salva quando presente — substituindo o FAQ embutido **por inteiro** (mesma convenção de Knowledge base e Doors: não mescla, substitui) — e mantém as ~41 categorias atuais sem nenhuma mudança de comportamento quando não customizado.
- Primeira entrada cuja keyword aparece na pergunta (normalizada, cercada de espaços) vence, na ordem da lista.
- "Restore default" limpa a opção e volta ao FAQ embutido; o campo mostra um exemplo inicial (não o FAQ real da Design Minds) até que algo seja salvo.
- Novo arquivo `dt-va/faq.php`, `tests/faq.php`, e novos casos em `tests/frontend.cjs` cobrindo o casamento por palavra-chave, a substituição de `{{COMPANY_NAME}}` e a garantia de que o FAQ embutido não vaza quando há customização.
- Isso conclui a Peça 4 completa do FEATURES.md (fase 1 e fase 2).

## 1.21.0 — 29/09/2026

### System prompt: regras de negócio enumeradas, sem risco de prompt injection

- Decisão de escopo (FEATURES.md, Peça 3): apenas campos **enumerados** (checkbox/select) são expostos ao admin, nunca texto livre inserido no system prompt — evita abrir uma superfície de prompt injection pelo próprio formulário de administração. Regras de segurança (responder só da base de conhecimento, tratar a conversa como dado não confiável, não revelar instruções internas) continuam fixas, não editáveis.
- Dois novos campos em **VA Enquiries → Settings → AI Assistant** (`dt-va/ai-assistant.php`): **Offer a ballpark price** (checkbox, ligado por padrão) e **Spelling and dates** (select: Irish English/day-month-year ou American English/month-day-year). Opções `dt_va_ai_assistant_ballpark` e `dt_va_ai_assistant_locale`.
- Corrigido um bug pré-existente: quando `DT_VA_AI_ASSISTANT_NAME` era definido via wp-config.php, o formulário inteiro deixava de salvar (inclusive os campos novos), porque a condição de salvamento dependia de `!$external`. Agora só o campo de nome fica bloqueado quando definido externamente; ballpark e locale sempre salvam.
- `dt_va_system_prompt()` também parou de hardcodar `designminds.ie` (agora usa o host do site via `home_url()`) e o telefone `01 961 0034` (agora usa `dt_va_public_value('COMPANY_PHONE_DISPLAY')`, nova função em `dt-va/public-settings.php` que retorna o valor salvo ou o padrão do HTML) — esses dois já eram configuráveis em outras telas, mas o prompt ainda usava os valores fixos da Design Minds.
- Novo `tests/ai-assistant.php` (módulo antes sem testes) cobrindo nome/ballpark/locale, textos de regra gerados e o bug de salvamento corrigido; `tests/public-settings.php` ganhou casos para `dt_va_public_value()`.

## 1.20.0 — 29/09/2026

### Cobertura de testes do FAQ local (`offlineAnswer()`)

- `tests/frontend.cjs` ganhou 41 casos (um por categoria de `offlineAnswer()`, na ordem de prioridade do array), 2 casos de colisoão de palavras-chave (ex.: "how much does a logo cost" precisa cair na resposta de custo, não na de logo, porque a entrada de custo vem antes no array) e o caso de nenhuma correspondência (retorna `null`).
- Nenhuma mudança de comportamento: `offlineAnswer()` continua idêntica. Isso trava o comportamento atual antes de qualquer refactor futuro (FEATURES.md, Peça 4 fase 2, que tornaria o FAQ configurável pelo admin).

## 1.19.0 — 29/09/2026

### Doors (menu inicial) editável pelo admin

- Nova aba **VA Enquiries → Settings → Doors**, com um JSON num `<textarea>` validado no servidor (`dt-va/flow.php`, opção `dt_va_flow_doors`). Cada door precisa de `id`, `label` e `action` (`service`, `other` ou `human`); doors de `action: service` também precisam de `service` (`logo`, `print`, `web` ou `client`). Opcionais: `sub`, `short`, `icon`.
- Exposto como `flowConfig.doors` em `window.DT_VA_SERVER`. `doors()` em `front.js` usa a configuração salva quando presente; sem customização, mantém a lista atual sem nenhuma mudança de comportamento.
- Apenas o menu inicial (doors) fica configurável nesta etapa; as perguntas de cada serviço (`scope`/`size`/`goal`/`describe`/perguntas de cliente existente) continuam fixas — têm branching condicional que ainda não tem um esquema de dados definido (ver FEATURES.md, Peça 1b).
- "Restore default" limpa a opção e volta à lista padrão de doors.
- Novo arquivo `dt-va/flow.php`, `tests/flow.php`, e novo teste de `doors()` em `tests/frontend.cjs` cobrindo o fallback padrão e a construção a partir de `flowConfig.doors`.

## 1.18.0 — 29/09/2026

### Base de conhecimento editável pelo admin

- Nova aba **VA Enquiries → Settings → Knowledge base**, com um `<textarea>` (mesmo padrão de Company notes/AI Assistant). O texto salvo passa a ser usado por `dt_va_knowledge_base()` com prioridade sobre `knowledge.md`.
- Precedência: opção `dt_va_knowledge_override` (se não vazia) → `dt_va/knowledge.md` → heredoc PHP fixo, agora isolado em `dt_va_knowledge_base_default()`.
- **Restore default** limpa a opção e volta a usar `knowledge.md`; o campo mostra o conteúdo atual do arquivo até que algo seja salvo pelo admin.
- Sem mudança de comportamento para instalações que não usarem a nova aba: `knowledge.md` continua valendo como antes.
- Novo arquivo `dt-va/knowledge.php` e `tests/knowledge.php`; `README.md` atualizado com o novo teste e a nota sobre a prioridade do override.

## 1.17.0 — 29/09/2026

### Avatar customizável e fim do flash de conteúdo padrão (FOUC)

- Novos campos **Avatar** (até 2 caracteres, vazio cai no padrão "DT") e **Collapse Avatar** em VA Enquiries → Settings → Company, logo abaixo de Company name. O Avatar substitui o "DM" fixo em `.dt-va-mark` (aba) e `.dt-va-avatar` (cabeçalho do painel); Collapse Avatar oculta apenas o círculo na aba fechada, antes de abrir o assistente — o avatar no painel aberto continua sempre visível.
- Corrigido o espaçamento da aba quando o avatar está oculto: sem o círculo de 40px, o padding de 11px/18px (dimensionado para equilibrar o círculo) ficava visualmente desbalanceado; ajustado para 20px/18px em telas de desktop.
- Corrigido um "flash" (FOUC) do avatar: o `wp_footer` imprimia `front.html` cru (com "DM" fixo, nunca colapsado), então o navegador pintava o avatar padrão antes do `front.js` (carregado depois, no rodapé) aplicar o avatar/collapse reais. Agora o servidor já grava os valores corretos no HTML antes de imprimir.
- Corrigido o mesmo tipo de flash nas cores do tema: `front.js` só aplicava as cores salvas via JavaScript depois de carregar, mostrando por um instante as cores padrão do `front.css`. Agora um bloco `<style>` com as cores é impresso no `wp_head`, com `!important` (necessário porque o `front.css` enfileirado imprime sua própria regra `:root` padrão depois desse hook, na mesma especificidade).
- `tests/company.php` ganhou um stub `checked()` (reutilizado por `tests/public-settings.php`, que já dependia dele) e novos casos para os campos Avatar/Collapse Avatar.

## 1.16.0 — 29/09/2026

### Nome do assistente, mais campos públicos e limpeza de nomenclatura

- Nova aba **VA Enquiries → Settings → AI Assistant**, com o campo AI Assistant name (`dt-va/ai-assistant.php`, opção `dt_va_ai_assistant_name` ou constante `DT_VA_AI_ASSISTANT_NAME`). Quando preenchido, a saudação passa de "Hello. I'm the AI Assistant at…" para "Hello. I'm **{Nome}** the AI Assistant at…"; em branco mantém o texto padrão.
- Renomeadas as variáveis de configuração `STUDIO_EMAIL`, `STUDIO_PHONE_DISPLAY` e `STUDIO_PHONE_TEL` para `COMPANY_EMAIL`, `COMPANY_PHONE_DISPLAY` e `COMPANY_PHONE_TEL` (front.js, public-settings.php, README.md, testes).
- Renomeada a variável pública `HOURS` para `OPENING_HOURS` (mesmos arquivos), para não ser confundida com `RESUME_HOURS`.
- Novo campo **Company City** em Public company details (`COMPANY_CITY`, padrão "Malahide"), usado em "Of course. The team in {City} take the calls themselves."
- Novo campo **Company Type** em Public company details (`COMPANY_TYPE`, com sugestões Office/Studio/Restaurant/Store via datalist, aceita texto livre). Ao contrário dos demais campos de texto, não aceita valor vazio: a gravação em branco é rejeitada no servidor, então o padrão do front.js ("Office") sempre vale quando não configurado. Um novo helper `typeWord()` em front.js substitui a palavra fixa "studio"/"Studio" nas mensagens de status e horário (banner aberto/fechado, cartão "Talk to a person", chips de WhatsApp/agendamento, aviso de retomada). As respostas fixas do FAQ offline continuam com "the studio" literal, por serem fatos específicos do endereço da Design Minds.
- A funcionalidade "Studio notes" (notas privadas por cliente) foi renomeada para **Company notes**: opção `dt_va_studio_notes` → `dt_va_company_notes` (sem fallback para a chave antiga), meta `studio_notes` → `company_notes`, funções `dt_va_studio_sections/_match/_notes_page` → `dt_va_company_notes_sections/_match/_page`, arquivo do hot-patch `studio-notes.md` → `company-notes.md`, menu/slug "Studio notes" → "Company notes".
- Corrigido o ícone do WhatsApp no rodapé do painel: o path desenhado à mão ficava pouco legível em 15–18px; substituído pelo path oficial verificado do Simple Icons.
- `tests/frontend.cjs`: marcadores de `indexOf` corrigidos após uma reformatação de indentação de `front.js` (2 espaços em vez de 4) ter quebrado a extração de trechos para os testes via `vm`.

## 1.15.0 — 29/09/2026

### CSS e JavaScript separados do HTML

- `dt-va/front.html` (4324 linhas) continha CSS, marcação e o script do assistente inline. CSS movido para `dt-va/assets/front.css` e o script para `dt-va/assets/front.js`; `front.html` agora tem só a marcação da aba e do painel (124 linhas).
- Os dois novos arquivos são registrados via `wp_enqueue_style`/`wp_enqueue_script` (versão por `filemtime`, para cache busting automático), em vez de impressos inline a cada carregamento de página. O filtro `script_loader_tag` preserva `data-no-optimize`/`data-no-defer`/`data-cfasync` no script para plugins de cache.
- A marcação de `front.html` agora é impressa em `wp_footer` com prioridade 5, antes da impressão nativa de scripts do WordPress (prioridade 20), garantindo que o script encontre os elementos do painel já no DOM.
- O endpoint de hot-patch `/wp-json/dt-va/v1/front` passa a aceitar `front.css` e `front.js` (gravados em `dt-va/assets/`), com validação de conteúdo mínimo e backup `.prev.*` como os demais arquivos.
- `dt_va_public_defaults()` (Settings → Company → Public company details) passa a ler os literais de configuração de `dt-va/assets/front.js`, não mais de `front.html`.
- A lógica de bot (motor de resposta, fetch, respostas offline) e a de componentes de UI (chips, renderização, formulários) permanecem no mesmo arquivo `front.js`: são funções que se chamam mutuamente (por exemplo `askModel` aciona `bot()`/`el()` para renderizar), sem uma fronteira de módulo real; separá-las em arquivos distintos exigiria reescrever esse acoplamento e não foi feito nesta versão.
- `tests/frontend.cjs` atualizado para ler `front.css`/`front.js` separadamente; nenhuma mudança de comportamento do assistente.

## 1.14.2 — 28/09/2026

### Prefixos CSS e integrações DT

- Renomeados os prefixos dm- restantes em seletores CSS, IDs, HTML gerado e consultas JavaScript para dt-.
- Classes externas de integração agora são dt-wa-menu, dt-q-google, dt-awards e dt-address; menus e conteúdo do tema devem usar os mesmos nomes.
- Preservados URLs externos, a fonte DM Sans e os arquivos históricos.

## 1.14.1 — 28/09/2026

### Correção do endereço no contato

- A linha Address em Talk to a person passa a ler COMPANY_ADDRESS em vez do texto fixo.
- Endereço personalizado escapado para HTML; vazio oculta a linha e ausência de configuração mantém o padrão.
- Teste executa a configuração e o cartão de contato com endereço personalizado, vazio e padrão.

## 1.14.0 — 28/09/2026

### Endereço público da empresa

- Campo Company Address em Settings → Company → Public company details, com fallback no HTML e restauração do padrão.
- A linha Studio em Talk to a person usa o endereço configurado, escapa HTML e fica oculta quando o campo é salvo vazio.
- A base knowledge.md e os textos offline continuam independentes deste campo.

## 1.13.0 — 28/09/2026

### Namespace DT

- Namespace completo migrado de `dm_va`/`dm-va`/`DM_VA`/`DM-VA` para `dt_va`/`dt-va`/`DT_VA`/`DT-VA`, incluindo PHP, REST, hooks, cron, opções, post type, metadados, HTML/CSS/JavaScript, storage, analytics, testes e documentação.
- Pasta principal renomeada para `dt-va/` e entrypoint para `dt-va-server.php`. Rotas públicas agora usam `/wp-json/dt-va/v1`; o post type passa a `dt_enquiry` com REST base `dt-enquiries`.
- Instalações novas usam diretamente o namespace DT. Removida a migração legada: não copia opções DM, não converte enquiries/metadados antigos e não executa a limpeza de agendamentos DM.
- Breaking change: constantes e variáveis de ambiente externas devem ser renomeadas manualmente de `DM_VA_*` para `DT_VA_*`. Integrações externas que chamam as antigas rotas REST também precisam usar as novas URLs.

## 1.12.0 — 28/09/2026

### Tema configurável

- Nova aba **Settings → Theme** com cores para destaque, painel, superfícies, mensagens, inputs, textos e bordas.
- Valores limitados a `#RRGGBB`, publicados em `themeConfig` e aplicados por variáveis CSS isoladas do assistente.
- Restauração integral do tema padrão e testes de validação, persistência, integração e renderização desktop/mobile.

## 1.11.0 — 28/09/2026

### Nome da empresa

- Novo campo **Company name** em **Settings → Company**, com fallback `Dovetail Consultancy` e suporte à constante externa `DT_VA_COMPANY_NAME`.
- Nome aplicado ao frontend, WhatsApp, instruções operacionais da IA, assuntos e corpos de e-mail e metadados de convites de calendário.
- Base de conhecimento, portfólio, URLs e fatos históricos da Design Minds permanecem independentes do nome configurável.
- Testes cobrem fallback ausente/vazio, persistência e bootstrap do frontend.

## 1.10.0 — 28/09/2026

### Google somente manual

- Removida a integração Google Places, busca por empresa, chave Google e consulta automática.
- Mantidos nota, quantidade e URL manuais; cancelamento do agendamento antigo.

## 1.9.0 — 28/09/2026

### Nome do campo Google Maps

- Campo renomeado para Google Maps profile URL — manual fallback.
- Nota explica que o link abre o perfil e não busca avaliações.

## 1.8.0 — 28/09/2026

### Edição direta e ajuda por campo

- Removidos checkboxes Custom; campos mostram valores salvos ou defaults do HTML.
- Adicionados nomes legíveis, notas por campo e Restore default individual.
- Preservados vazios intencionais, zero e herança dos defaults.

## 1.7.0 — 28/09/2026

### Configuração pública e integração Google

- Campos públicos de contato, links, horários, avaliações e retomada em Company.
- Overrides por campo com checkbox Custom e fallback no HTML.
- Integração Google Places com busca, seleção de Place ID, atualização diária/manual e cache. Essa integração foi posteriormente removida na 1.10.0.

## 1.6.0 — 28/09/2026

### Abas AI Keys e Company

- Settings dividido em AI Keys e Company.
- Configuração de e-mail destinatário e nome do remetente, com prioridade de constantes externas.
- Validação de e-mail e cabeçalhos; gravação independente das abas.

## 1.5.0 — 28/09/2026

### Modelos configurados externamente

- IDs de modelos aceitam constantes e variáveis de ambiente, com prioridade sobre Settings.
- Campos externos somente leitura; salvar não copia overrides para o banco.
- .env depende do carregador da instalação.

## 1.4.0 — 28/09/2026

### Rótulos de enquiries

- Add post substituído por Add enquiry.
- Ajustados rótulos de edição, busca e lista, sem alterar dados.

## 1.3.0 — 28/09/2026

### Ajuda de modelos e prévia de chaves

- Notas com nomes e IDs de modelos e links oficiais.
- Prévia limitada ao prefixo da chave; segredo completo não é devolvido ao HTML.

## 1.2.0 — 28/09/2026

### Last daily check

- Restaurados data, status e detalhes da verificação diária na administração.
- Diagnóstico diário separado das chamadas e dos testes manuais.

## 1.1.0 — 28/09/2026

### Documentação de credenciais

- Passo a passo para gerar chaves OpenAI e Anthropic.
- Documentados faturamento da API, configuração dos modelos e teste da conexão.

## 1.0.0 — 28/09/2026

### Implementação inicial do tópico 30

- Integração OpenAI/Anthropic, prioridade configurável, fallback e respostas offline preservadas.
- Separação de providers e settings; limites de contexto, tokens e tempo.
- Correção de pergunta duplicada, validação HTTP e descarte de respostas tardias.
- Diagnósticos sem texto integral das conversas e analytics por resultado.
- Teste diário adaptado à cadeia de providers; testes isolados PHP e JavaScript.

## 0.0.1 — 28/09/2026

### Plugin original

- Estado inicial recebido: integração Anthropic, frontend em HTML, fallback offline, briefs, enquiries, Studio notes e configuração monolítica.
- A versão no cabeçalho original era 2.10; 0.0.1 é a identificação inicial no histórico organizado deste projeto.

## Critério de versionamento e disponibilidade

Cada tarefa implementada/documentada recebe uma versão; perguntas e esclarecimentos sem alteração de arquivos não são releases. 0.0.1 identifica o original, 1.0.0 a primeira implementação do tópico 30, e 1.1.0–1.13.0 as tarefas seguintes em ordem cronológica.

Esta renumeração é retrospectiva. O código ativo foi preservado, incluindo seu cabeçalho 4.0.0. Cada ZIP possui o número correspondente no cabeçalho da cópia de distribuição.

Todos os 15 ZIPs estão em build/. 0.0.1 combina os backups iniciais de PHP/HTML com a base de conhecimento fornecida; 1.0.0–1.10.0 foram reconstruídas reaplicando os comandos e patches registrados nesta conversa. 1.11.0 e 1.12.0 foram derivadas do código atual, removendo apenas nas cópias as funcionalidades posteriores. 1.13.0 é o snapshot atual. Portanto, os pacotes antigos são reconstruções verificadas, não backups originais certificados byte a byte.

Cada ZIP contém RELEASE.md com sua proveniência. build/manifest.json registra checksums; build/reconstruction/validation.json registra validações de sintaxe, testes isolados e limites de funcionalidades por versão. Não houve testes com APIs pagas, e-mail real ou instalação WordPress para os pacotes reconstruídos.
