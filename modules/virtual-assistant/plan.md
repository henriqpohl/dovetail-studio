# Plano: tela de admin para Case Studies/Portfólio

## Contexto (para a IA que for implementar)

Repositório: plugin WordPress MU (`dt-va-server.php` + pasta `dt-va/`). Sem build tooling — front-end é JS/CSS puro enfileirado via `wp_enqueue_style`/`wp_enqueue_script`. Convenção já estabelecida em **Doors** (`dt-va/flow.php`) e **Offline FAQ** (`dt-va/faq.php`): admin configura no painel, salvo como `wp_option`, exposto em `window.DT_VA_SERVER` via `dt-va-server.php`, e o front-end (`dt-va/assets/front.js`) usa a config salva **substituindo por inteiro** o conteúdo embutido (nunca faz merge) quando presente.

Sessão anterior genericizou o fallback embutido: `CASE_STUDIES`/`CASE_STUDIES_TAGGED` em `front.js` (dentro de `DT_VA_CONFIG`, por volta da linha 33) foram esvaziados para `{}`/`[]` porque eram 100% portfólio da Design Minds. `work()` já trata isso graciosamente (pula pra `money()` quando a lista está vazia). Falta a tela de admin pra cada instalação cadastrar o próprio portfólio.

## O que já existe (usar como modelo)

- `dt-va/faq.php`: `dt_va_faq_config()`, `dt_va_faq_validate($json)`, `dt_va_faq_starter()`, `dt_va_faq_settings()` (renderiza aba com textarea JSON + formulário "Add an entry" + botão "Restore default").
- `dt-va/flow.php`: mesmo padrão para Doors/Steps, com `dt_va_flow_doors_default()`, `dt_va_flow_doors_validate()`.
- `dt-va/settings.php`: registra as abas (`ai-keys`, `company`, `ai-assistant`, `knowledge`, `flow`, `faq`, `theme`).
- `dt-va-server.php` linha ~609-620: monta `$cfg` com `askEnabled`, `faqConfig`, `flowConfig`, `themeConfig`, `publicConfig` e faz `echo` do `window.DT_VA_SERVER=...` inline no `<head>`.
- `tests/faq.php` e `tests/flow.php`: padrão de teste (funções `check($cond, $label)`, POST simulado, `dt_va_faq_settings()` chamada com `ob_start()`/`ob_get_clean()`).

## Passos

### 1. `dt-va/case-studies.php` (novo arquivo)

Funções, espelhando `faq.php`:

- `dt_va_case_studies_config()`: lê `get_option('dt_va_case_studies', array())`, retorna `array()` se vazio/inválido.
- `dt_va_case_studies_validate($json)`: decodifica JSON, valida array de objetos com campos:
  - `t` (título, string, obrigatório, max ~60 chars)
  - `s` (subtítulo/descrição curta, string, obrigatório, max ~140 chars)
  - `o` (resultado/outcome, string, opcional, max ~80 chars)
  - `url` (opcional, mesma validação de URL usada em `public-settings.php`: path relativo `/...` ou `http(s)://`)
  - `img` (opcional, mesma validação de URL, só aceitar `http(s)://` — nunca permitir `javascript:` etc.)
  - `sec` (setor, opcional, string curta — usado pra agrupar)
  - `ind` (palavras-chave de indústria pra matching, string de palavras separadas por espaço, mesmo padrão de `CASE_STUDIES_TAGGED[].ind` original — **plain words, nunca regex**, mesma justificativa de segurança do `faq.php`: reutilizar a mesma validação de "só letras/números/espaços/apóstrofo" pra evitar qualquer vetor de injeção)
  - Limite de quantidade: min 1, max ~30 entradas (mesmo padrão de `dt_va_faq_validate`: `count($decoded) < 1 || count($decoded) > N`)
  - Retorna `WP_Error` no primeiro problema encontrado, ou array validado.
- `dt_va_case_studies_starter()`: 1-2 exemplos genéricos (não específicos de nenhum ramo) mostrados como placeholder no textarea vazio — mesmo texto de aviso "não é o portfólio real, é só um ponto de partida" usado em `dt_va_faq_starter()`.
- `dt_va_case_studies_settings()`: renderiza a aba — textarea JSON grande + form "Add an entry" (campos: título, descrição, resultado, URL, imagem, setor, palavras-chave) + botão "Restore default" (`delete_option`).

Seguir exatamente o mesmo texto de aviso de segurança/convenção usado em `faq.php` linha 2 (comentário de topo do arquivo) adaptado: "Optional admin override for the built-in case studies (work()'s portfolio cards). Replaces the built-in list wholesale when set, same convention as Doors and Offline FAQ."

### 2. `dt-va/settings.php`

Adicionar `'case-studies' => 'Portfolio'` no array de abas (linha ~11) e o `if ($tab === 'case-studies') { dt_va_case_studies_settings(); ... }` (linha ~20, mesmo padrão do `faq`).

### 3. `dt-va-server.php`

No bloco que monta `$cfg` (~linha 609-620), adicionar `'caseStudiesConfig' => dt_va_case_studies_config(),`. Fazer `require`/`include` do novo `dt-va/case-studies.php` junto dos outros módulos (procurar onde `faq.php`/`flow.php` são incluídos e seguir o mesmo padrão).

### 4. `dt-va/assets/front.js`

- Em `caseStudiesFor(service)` (função existente, usa `matchCaseStudies()` + `C.CASE_STUDIES[service]`): adicionar leitura de `S.caseStudiesConfig` no topo — se presente e não vazio, usar esse array no lugar de `C.CASE_STUDIES_TAGGED`/`C.CASE_STUDIES[service]` (a nova config é uma lista única, não separada por `service` como o `CASE_STUDIES` original — simplificar `caseStudiesFor()` pra só usar `matchCaseStudies()` sobre essa lista única, já que o bucket único `enquiry` não distingue mais logo/print/web).
- `matchCaseStudies()`: trocar `C.CASE_STUDIES_TAGGED` por `(S && S.caseStudiesConfig) || C.CASE_STUDIES_TAGGED` (mantém o array vazio embutido como fallback quando não configurado, igual ao padrão do `offlineAnswer()`/`doors()`).
- Sanitizar `img`/`url` no client-side com `esc()` antes de usar em atributos, mesma cautela já aplicada em outras partes do arquivo.

### 5. Testes

- `tests/case-studies.php` (novo, mesmo esqueleto de `tests/faq.php`): sem custom por padrão, validação aceita/rejeita casos (JSON malformado, faltando `t`/`s`, URL inválida, `ind` com regex/caracteres inválidos, limite de entradas), save/restore default, formulário "Add an entry".
- `tests/frontend.cjs`: adicionar um bloco testando `matchCaseStudies()`/`caseStudiesFor()` com `S.caseStudiesConfig` presente vs ausente (padrão idêntico ao bloco que já existe pra `doors()` com `flowConfig.doors`).

### 6. Documentação

- `docs/CHANGELOG.md`: nova entrada em "Não lançado" descrevendo a tela.
- `USAGE.md`: atualizar o item "Portfólio/case studies" do checklist (hoje diz "não há tela de admin ainda") pra apontar pra Settings > Portfolio.
- `README.md`: se o README lista as abas de Settings, adicionar a nova.

### 7. Validação final (rodar antes de considerar pronto)

```bash
php -l dt-va/case-studies.php
find dt-va -name '*.php' -print0 | xargs -0 -n1 php -l   # nunca usar for/do/done em fish, trava o terminal
php tests/case-studies.php
php tests/faq.php
php tests/flow.php
php tests/company.php
php tests/public-settings.php
php tests/theme.php
node tests/frontend.cjs
```

### Cuidados específicos deste repositório (ler antes de mexer)

- **Nunca** usar sintaxe `for ... do ... done` (bash) num terminal fish — trava o shell persistente. Usar `find ... | xargs`.
- `tests/frontend.cjs` faz slicing de string (`js.indexOf(...)`) direto no código-fonte pra extrair funções — muito sensível a mudança de formatação/indentação. Depois de editar `front.js`, rodar os testes e ajustar os marcadores de slice se necessário.
- `dt_va_public_defaults()` (`public-settings.php`) faz parsing por regex do `front.js` pra alguns campos simples — não deve ser afetado por essa mudança (Case Studies não é um desses campos), mas confirmar rodando `php tests/public-settings.php`.
- Seguir a convenção **substituir por inteiro, nunca merge** (mesma de Doors/FAQ) — só reforçando.
- `providers.php`/`dt-va-server.php` têm uma falha pré-existente e não relacionada em `tests/providers.php` (`wp_clear_scheduled_hook` indefinida fora do WordPress) — não é regressão introduzida por essa tarefa, pode ignorar.
