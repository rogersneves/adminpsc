# 05 - UI/UX Design System

## Stack

React 19 + Inertia.js v2 + Tailwind CSS v4 + shadcn/ui como base de componentes. shadcn/ui não é uma
biblioteca instalada como dependência de runtime — os componentes são copiados para
`resources/js/Components/ui` e customizados livremente conforme o design system do AdminPSC.

## Estrutura de componentes

- `resources/js/Pages` — uma página por rota Inertia, organizada por módulo
  (`Pages/Scheduling/Index.jsx`).
- `resources/js/Components/ui` — componentes base shadcn/ui (button, card, dialog, input...).
- `resources/js/Components` — componentes de domínio compostos a partir dos componentes `ui`
  (ex.: `SessionCard`, `PatientTimeline`), reutilizáveis entre páginas de um mesmo módulo ou entre
  módulos.
- `resources/js/Layouts` — layouts por tipo de usuário (ex.: `ClinicLayout`, `PatientPortalLayout`).

## Tokens de tema (Tailwind v4) — implementados

Definidos via variáveis CSS no tema Tailwind v4 (`@theme`/`:root`/`.dark` em `resources/css/app.css`),
não em classes utilitárias espalhadas. Os tokens semânticos do shadcn (`--primary`, `--background`,
`--card`, `--ring`, ...) estão mapeados para a identidade AdminPSC.

**Paleta (guia de marca):**

| Papel | Token | Claro | Escuro |
|---|---|---|---|
| Primária (petróleo) | `--primary` | `#2D5B7A` | `#4F89A8` |
| Sálvia | `--brand-sage` | `#7FA68A` | `#8FB59A` |
| Lavanda | `--brand-lavender` | `#A487C8` | `#B79FD6` |
| Texto | `--foreground` | `#1F2937` | `#F5F7FA` |
| Texto secundário | `--muted-foreground` | `#5F6875` | `#9AA4B2` |
| Fundo | `--background` | `#FFFFFF` | `#111827` |
| Card | `--card` | `#FFFFFF` | `#1F2937` |
| Borda | `--border` | `#E8ECF0` | `rgba(255,255,255,.08)` |
| Input | `--input` | `#D7DEE5` | `rgba(255,255,255,.12)` |

**Estados** (utilitários `bg-success`/`text-warning`/...): sucesso `#4CAF6A`, aviso `#E5A73A`, erro
(`--destructive`) `#D9534F`, info `#5A8DEE`.

**Tipografia:** Manrope (variável, via `@fontsource-variable/manrope`) como `--font-sans` e `--font-heading`.
Escala: Título 40 · H1 32 · H2 28 · H3 22 · Texto 16 · Legenda 14 · Pequeno 12.

**Raio:** base `--radius: 0.75rem` (12px); a escala `--radius-sm..4xl` deriva dela (cards ~16px,
elementos maiores ~24px). **Espaçamento:** grade 8pt (múltiplos de 4/8).

**Gráficos:** `--chart-1..5` = petróleo, sálvia, lavanda, âmbar (aviso), azul-info — paleta categórica
da marca (ver skill `dataviz` ao construir visualizações).

**Tema por tenant (SaaS, Fase 11):** os tokens de cor podem ser sobrescritos em runtime sem rebuild de
CSS. `branding.primary_color` (prop compartilhada do Inertia, default = petróleo da marca) é injetado em
`resources/js/app.jsx` como override de `--primary`/`--ring`/`--sidebar-primary` sobre `:root`. Editável
em `/configuracoes` pelo Admin da Clínica.

**Modo escuro:** ativado pela classe `.dark` no elemento raiz (`@custom-variant dark`); paleta escura
completa acima (background `#111827`, cards `#1F2937`, primária `#4F89A8`). O toggle de tema em si (persistir
preferência do usuário) ainda não está construído — os tokens já estão prontos para quando for.

## Internacionalização (i18n)

**Nenhum texto é escrito diretamente em Views/Blade ou em componentes React.** Fonte única de verdade:
arquivos de tradução do Laravel (`lang/{locale}/{modulo}.php`), incluindo os do próprio módulo via
`nwidart/laravel-modules`. Para o React, as traduções do locale ativo são expostas como prop
compartilhada do Inertia (`HandleInertiaRequests::share`) e consumidas por um hook `useTranslation()`/
`t('modulo.chave')` no frontend — o mesmo texto nunca é duplicado entre backend (e-mails, PDFs) e
frontend (telas), sempre a mesma chave de tradução.

## Acessibilidade (WCAG 2.2 AA)

- **Navegação por teclado**: todo componente interativo (botões, campos, modais, itens de agenda)
  alcançável e operável via teclado, com ordem de tabulação lógica.
- **Foco visível**: nunca remover o `outline` de foco sem substituir por um indicador visível
  equivalente (token de cor de foco dedicado no tema).
- **Contraste adequado**: tokens de cor validados para contraste mínimo AA antes de entrar no tema
  (texto normal ≥ 4.5:1, texto grande/ícones ≥ 3:1).
- **Suporte a leitores de tela**: uso correto de landmarks semânticos, `aria-label`/`aria-describedby`
  em componentes shadcn/ui customizados, anúncio de mudanças assíncronas relevantes (ex.: confirmação
  de agendamento) via região `aria-live`.
- **Mensagens de erro acessíveis**: erros de formulário associados ao campo via `aria-invalid` +
  `aria-describedby`, nunca comunicados só por cor.

## Componentes de domínio recorrentes (a construir conforme o roadmap avança)

Dashboards (psicólogo e paciente), calendário/agenda, card de sessão, linha do tempo de prontuário,
indicador de situação financeira, componentes do CMS (Banner, Hero, Cards, FAQ, Rodapé, Formulários,
Botões, Depoimentos, Contato) — cada um implementado como bloco reutilizável do GrapesJS (ver
`06-Roadmap.md`, fase CMS), nunca como HTML editado manualmente pelo usuário final.
