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

## Tokens de tema (Tailwind v4)

Definidos via variáveis CSS no tema Tailwind v4 (`@theme` em `resources/css/app.css`), não em classes
utilitárias espalhadas: cor primária, cor de destaque, cores semânticas (sucesso, aviso, erro, info),
espaçamento e raio de borda padrão. Preparar desde já para tema por tenant (SaaS): os tokens de cor
devem poder ser sobrescritos por variáveis CSS injetadas por tenant (ex.: cor da clínica), sem exigir
rebuild do CSS por cliente.

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
