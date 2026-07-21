# 06 - Roadmap

Cada fase pressupõe a anterior concluída e testada (PHPUnit). Antes de iniciar qualquer fase, reavaliar
impacto arquitetural, de segurança, LGPD/CFP, desempenho e escalabilidade (ver Diretrizes Finais do
prompt mestre do projeto).

## Fase 0 — Fundação (concluída)
Documentos de arquitetura (`docs/`), scaffold Laravel 13 + Inertia v3 + React 19 + Tailwind v4 +
shadcn/ui, `nwidart/laravel-modules` com os 18 módulos criados como esqueleto, `spatie/laravel-permission`
instalado (sem dados), configuração de ambiente (MySQL, fila database).

## Fase 1 — Core, Tenant, Authentication, Authorization, Audit (concluída)
- Migration e Model de `Tenant`, `TenantScope`, `BelongsToTenant`, middleware `ResolveTenant`.
- Fluxo de autenticação completo: registro (cria Tenant + Admin da Clínica), verificação de e-mail,
  login, recuperação de senha, MFA obrigatório em todo login (OTP e-mail via Cache + Notification, e
  TOTP via `pragmarx/google2fa-laravel`), sessão com timeout absoluto + inatividade.
- Primitiva de envelope encryption (`EncryptionService`, AES-256-GCM, adiantada da Fase 9 — ver ADR-006
  em `01-Arquitetura.md`), usada para cifrar `mfa_totp_secret`.
- Papéis e permissões seed (7 papéis) via `RolesAndPermissionsSeeder`, `UserPolicy` base, comando
  `authorization:make-super-admin`.
- Módulo `Audit`: `AuditLogger` + listener dos eventos nativos de auth do Laravel (login, logout, falha
  de autenticação, registro, falha de desafio MFA), tabela `audit_logs` append-only.
- 26 testes PHPUnit cobrindo esse fluxo; verificado manualmente de ponta a ponta contra MySQL real.
- **Pendências explícitas desta fase, não bloqueantes:** convite de Secretária/Financeiro, timeout de
  sessão configurável por tenant (depende do módulo Settings), tela de Super Admin, QR Code visual no
  setup de TOTP (hoje mostra secret + URI em texto).

## Fase 2 — Users, Psychologists, Patients, Guardians (concluída)
- Cadastro de paciente sob uma clínica específica (`/c/{tenant:slug}/paciente/registro`): obrigatórios
  e-mail, senha+confirmação, nome de identificação; confirmação de e-mail via link (mesmo padrão da
  Fase 1), não um segundo campo de digitar o e-mail.
- Campos opcionais pós-primeiro-acesso (CPF, telefones, contatos de recado, endereço, nascimento) via
  `GET/PUT /paciente/perfil`, cifrados com `EnvelopeEncrypted`/`EncryptedJson`.
- Cadastro de responsável legal (só registro de contato, sem login) obrigatório quando a idade calculada
  a partir da data de nascimento é menor que 16 — validado no momento em que a data é gravada/alterada,
  via `PatientRequiresGuardianIfMinor`.
- Cadastro profissional do psicólogo pelo Admin da Clínica (`POST /psicologos`), não autocadastro —
  reaproveita o broker de redefinição de senha do Laravel em vez de senha temporária.
- `TenantScope`/`BelongsToTenant` (construídos sem uso na Fase 1) exercitados pela primeira vez em
  Models de negócio reais; hash de busca (`EncryptionService::searchHash`) permite localizar paciente
  por CPF sem guardar texto puro.
- **Pendências explícitas desta fase, não bloqueantes:** convite de Secretária/Financeiro; tela
  administrativa de listar/editar/desativar pacientes; edição de perfil de psicólogo após criado; portal
  do responsável legal (papel `responsavel_legal` seguirá seedado sem uso); rotação/versionamento
  avançado de chave de criptografia (Fase 9).

## Fase 3 — Scheduling (concluída)
- Disponibilidade do psicólogo (`Modules\Psychologists\Models\PsychologistAvailability`): regras
  `recorrente` (semanal) e `particular` (dia avulso) adicionam disponibilidade; `bloqueio`/`ferias`/
  `feriado` removem. Calculadas on-the-fly por `Modules\Scheduling\Services\AvailabilityCalculator`,
  sem materializar slots em tabela.
- Reserva pelo paciente restrita à disponibilidade calculada (`GET/POST /agenda/{psychologist}`),
  bloqueio transacional contra dupla reserva via `lockForUpdate()` na linha do `Psychologist` (não em
  `clinical_sessions` — um horário ainda não reservado não tem linha pra travar).
- Lista de espera (`waiting_list_entries`) sem correspondência automática — depende do módulo
  Notifications (Fase 7).
- Cancelamento/reagendamento com antecedência mínima configurável (`config('scheduling.
  minimum_reschedule_notice_hours')`, padrão 24h); reagendar cria uma nova sessão ligada por
  `rescheduled_from_id`, nunca sobrescreve a original.
- Status de sessão via `Modules\Scheduling\Enums\SessionStatus`.
- **A tabela chama-se `clinical_sessions`, não `sessions`** — colisão real com a tabela de sessão HTTP
  do Laravel, descoberta só ao escrever a migration. Ver `02-Banco-de-Dados.md`.
- **Corrigido um bug estrutural em toda a aplicação, não só desta fase:** o binding implícito de rota
  (`{psychologist}`, `{session}`, etc.) roda antes de `resolve.tenant` por padrão — Laravel ordena
  middleware por uma lista de prioridade interna que dá a `SubstituteBindings` prioridade mais alta que
  qualquer middleware customizado, não importa a ordem no array da rota. Isso quebrava qualquer rota
  usando binding implícito de um Model `BelongsToTenant`, incluindo rotas já existentes das Fases 2 e 3.
  Corrigido uma vez, na raiz (`bootstrap/app.php`, `prependToPriorityList`), não rota por rota. Ver
  gotcha detalhado no CLAUDE.md — releva por que nenhum teste PHPUnit pegou isso sozinho.
- **Pendências explícitas desta fase, não bloqueantes:** notificação automática de vaga aberta na lista
  de espera (Fase 7); edição/exclusão de responsáveis já cadastrados (Fase 2, idem); QR Code visual
  (herdado da Fase 1); calendário visual (grade semanal/mensal) — a tela de reserva lista os horários
  por dia, não um calendário.

## Fase 4 — MedicalRecords
- Prontuário separado do cadastro, com versionamento completo (nunca sobrescreve).
- Conteúdo (anotações, objetivos terapêuticos, plano terapêutico, anexos) cifrado com a mesma
  arquitetura de envelope encryption.
- Policies restringindo acesso ao psicólogo responsável (e papéis administrativos quando aplicável).

## Fase 5 — Financial, Payments
- Modelagem Sessão → Cobrança → Pagamento.
- Parcelamento, descontos, multas, juros, abatimentos, estornos.
- Status de cobrança (em aberto, pago, vencido, parcial, cancelado, estornado).
- Arquitetura preparada para gateways/PIX (interface `PaymentGatewayInterface` no módulo `Payments`,
  sem integração real ainda).

## Fase 6 — Reports, Dashboards
- Relatórios do psicólogo (filtros por período/paciente/situação financeira/sessões/comparecimento),
  exportação PDF (`barryvdh/laravel-dompdf`) e Excel (`maatwebsite/excel`).
- Relatórios do paciente (sessões, situação financeira, recibos).
- Dashboards do psicólogo (agenda do dia/semana, receitas, pendências, pacientes ativos/inativos,
  aniversariantes, indicadores gerais) e do paciente (próxima sessão, pendências, histórico, atalhos).

## Fase 7 — Notifications
- Notificações automáticas: confirmação de cadastro/e-mail, lembrete de sessão, cancelamento,
  reagendamento, confirmação de pagamento, cobrança, recibos, alterações importantes.
- Arquitetura de canal plugável, preparada para SMS/WhatsApp futuros sem refatorar o módulo.

## Fase 8 — CMS
- Páginas públicas editáveis via GrapesJS (`grapesjs-preset-newsletter` como base), interface
  customizada para aparência clean.
- Componentes próprios: Banner, Hero, Cards, FAQ, Rodapé, Formulários, Botões, Depoimentos, Contato.
- Sem edição manual de HTML pelo usuário final.

## Fase 9 — Audit/Security hardening
- Revisão completa de cobertura de auditoria (todas as ações obrigatórias do prompt mestre).
- Rotação de chaves de criptografia em produção (Job agendado), métricas de autenticação e filas.
- Cabeçalhos de segurança, rate limiting revisado, testes de concorrência adicionais.

## Fase 10 — LGPD
- Fluxo completo de consentimento, política de privacidade e termos versionados com histórico de
  aceite, anonimização e exclusão conforme legislação e normas do CFP.

## Fase 11 — Produtização SaaS
- Onboarding de novo tenant, planos/billing, personalização por tenant (tema, configurações),
  eventual caminho de isolamento físico de dados para tenants grandes (reavaliação do ADR-003 de
  `01-Arquitetura.md`).

## Marcos futuros (fora de fases numeradas, mantidos como visão)
- Múltiplos psicólogos por clínica, múltiplas unidades, secretárias com escopo próprio.
- Convênios, teleconsulta, assinatura eletrônica, emissão de notas fiscais.
- Gateways de pagamento reais e PIX.
- Aplicativo móvel.
- API pública REST (o desacoplamento Actions/Services já feito desde a Fase 0 evita refatoração
  significativa quando isso for priorizado).
- SMS e WhatsApp como canais de notificação adicionais.
