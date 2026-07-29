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

## Fase 4 — MedicalRecords (concluída)
- Prontuário (`medical_record_entries`) append-only: `update()`/`delete()` sobrescritos no Model
  lançando exceção (mesmo padrão do `AuditLog` da Fase 1); cada edição cria uma nova versão
  (`version` incremental, `previous_version_id` apontando pra anterior) em vez de sobrescrever.
  Campos não enviados numa nova versão herdam o valor da versão anterior. Exclusão administrativa
  excepcional continua possível via soft delete (`SoftDeletes::runSoftDelete()` faz update via query
  builder cru, não passa por `Model::update()` — só o override de `update()` bloqueia edição direta,
  `delete()` não foi sobrescrito de propósito).
- Conteúdo (`notes`, `therapeutic_objectives`, `therapeutic_plan`) gravado como um único JSON por
  versão, cifrado com `Modules\Security\Casts\EncryptedJson` (mesma arquitetura de envelope encryption
  da Fase 1, reaproveitada sem alterações).
- Anexos (`medical_record_attachments`): um por versão, conteúdo do arquivo inteiro cifrado em memória
  via `EncryptionService` e salvo no disco privado do Laravel sob nome aleatório (UUID); nome original
  do arquivo também cifrado. Limite de 10MB (cifrar em memória não é adequado pra arquivo grande).
- "Psicólogo responsável" é derivado, não é um campo de atribuição: qualquer psicólogo que já teve ao
  menos uma `Session` (Fase 3) com o paciente tem acesso de leitura e escrita ao prontuário —
  modela continuidade de cuidado entre psicólogos da mesma clínica. `admin_clinica`/`manage-users` do
  mesmo tenant e `super_admin` também têm acesso. Paciente não acessa o próprio prontuário nesta fase
  (fica para a Fase 10, como processo formal de solicitação LGPD, não autoatendimento).
- Autorização via `Gate::define` (`MedicalRecordPolicy::view`/`create`) em vez de `Gate::policy` —
  a decisão é sobre uma relação `(User, Patient)`, não sobre uma instância `MedicalRecordEntry` já
  existente.
- Rotas com `resolve.tenant` + `CurrentTenant::ownsOrFail()` explícito em `Patient`/
  `MedicalRecordAttachment` recebidos por binding implícito, disciplina reforçada desde o gotcha da
  Fase 3.
- 66 testes PHPUnit no total (suíte completa Fases 1-4); verificado manualmente de ponta a ponta contra
  MySQL real via `php artisan serve`, incluindo confirmação direta no banco de que `content_encrypted` e
  `original_filename_encrypted` não contêm texto puro.
- **Pendências explícitas desta fase, não bloqueantes:** autoatendimento do paciente ao próprio
  prontuário (Fase 10); edição/remoção de versão passada; múltiplos anexos por envio; busca em texto
  cifrado (limitação já documentada em `02-Banco-de-Dados.md`); preenchimento automático de `session_id`
  ao concluir uma sessão.

## Fase 5 — Financial, Payments (concluída)
- Cobrança (`financial_charges`, módulo `Financial`) e pagamento (`financial_payments`, módulo
  `Payments`) como Models separados — `FinancialCharge` não é append-only (precisa de `update()` normal
  pra transições de status, recálculo de multa/juros e edição de desconto), mas `Payment` nunca é editado
  nem apagado: reversão de pagamento é `reversed_at`, nunca `delete()`, preservando a trilha de "esta
  cobrança teve um pagamento que foi estornado" como algo distinto de "nunca foi paga".
- O status da cobrança nunca é fonte de verdade isolada — é sempre recomputado a partir dos pagamentos
  não estornados (`Modules\Financial\Services\ChargeStatusCalculator`): total pago ≥ total devido → pago;
  parcial se cobrir só parte; `estornado` se já teve pagamento e ele foi revertido (distinto de
  `em_aberto`/`vencido`, que nunca tiveram pagamento algum); `cancelado` é estado terminal, nunca
  recalculado por cima.
- Parcelamento (`CreateChargeAction`) gera N linhas independentes em `financial_charges` — não existe
  tabela de "grupo de parcelamento" no schema documentado; `installment_number`/`installment_total` já
  descrevem a posição. Valor e desconto são divididos em centavos inteiros, com a última parcela
  absorvendo o resto da divisão (evita perda/sobra por arredondamento). Vencimentos espaçados por 1 mês.
- Multa/juros de atraso seguem a convenção brasileira comum (multa fixa de 2%, juros de mora de 1% ao mês
  pro-rata die), configurável via `config/financial.php`
  (`FINANCIAL_LATE_FINE_PERCENT`/`FINANCIAL_LATE_INTEREST_PERCENT_PER_MONTH`) — sem base documental
  própria no projeto, foi uma decisão de escopo explícita. Recalculados (não acumulados) diariamente pelo
  comando `financial:apply-late-fees`, agendado via `configureSchedules()` do
  `FinancialServiceProvider` (mecanismo nativo do `nwidart/laravel-modules`, mesmo scheduler que já roda
  o worker de fila do projeto).
- Registrar pagamento (`RecordPaymentAction`) e estornar pagamento (`ReversePaymentAction`) travam a
  linha da `FinancialCharge` com `lockForUpdate()` antes de recalcular o status — mesmo padrão de
  `BookSessionAction` da Fase 3 (travar a linha pai, não uma linha ainda-não-existente).
- Autorização (`Gate::define`, mesmo padrão de `MedicalRecordPolicy` da Fase 4): psicólogo que já tratou
  o paciente tem acesso de **leitura**; só quem tem a nova permissão `manage-financial`
  (`super_admin`/`admin_clinica`/`financeiro`) pode criar cobrança, registrar/estornar pagamento, editar
  desconto ou cancelar. **Primeira permissão real do papel `financeiro`**, seedado desde a Fase 1 sem uso
  até agora.
- `PaymentGatewayInterface` (módulo `Payments`) existe só como interface (`charge`/`refund`), sem
  implementação nem binding no container — puramente arquitetural, conforme pedido. O método de pagamento
  `pix` já existe no enum mas continua sendo registro manual (staff marca "recebi via PIX fora do
  sistema"), sem chamar gateway nenhum.
- Lista mínima de pacientes do tenant (`/financeiro/pacientes`, nome + link) construída só pra permitir
  navegação até o financeiro de um paciente — **não** é a tela completa de gestão de pacientes (busca,
  edição, desativação) ainda pendente desde a Fase 2.
- 93 testes PHPUnit no total (suíte completa Fases 1-5); verificado manualmente de ponta a ponta contra
  MySQL real via `php artisan serve`, incluindo o comando de vencidas rodado contra uma cobrança inserida
  diretamente no banco com `due_date` no passado.
- **Pendências explícitas desta fase, não bloqueantes:** relatórios/recibos formais e portal do paciente
  pro próprio financeiro (Fase 6/Reports); tela completa de gestão de pacientes (Fase 2, ainda pendente);
  integração real de gateway/PIX (Fase 11 ou quando for priorizado); "abatimento" como conceito distinto
  de desconto (tratados como o mesmo campo `discount_amount` nesta fase, por não haver campo separado no
  schema documentado).

## Fase 6 — Reports, Dashboards (concluída)
- Três relatórios separados por assunto para o psicólogo — Sessões, Financeiro, Comparecimento
  (`Modules\Reports\Actions\Build{Sessions,Financial,Attendance}ReportAction`) — cada um com tela Inertia
  filtrável (período/paciente/status) e exportação em PDF (`barryvdh/laravel-dompdf`, primeira vez que
  entra no projeto) e Excel (`maatwebsite/excel`, idem). Nenhuma tabela nova: tudo computado on-the-fly a
  partir de `clinical_sessions`/`financial_charges`/`financial_payments` já existentes.
- Exportação é **síncrona no request** (sem fila, sem polling) — a arquitetura lista geração de PDF/Excel
  como trabalho de Job, mas o módulo Notifications (que avisaria quando o arquivo está pronto) só chega
  na Fase 7; gerar assíncrono sem poder notificar ninguém seria trabalho pela metade. Decisão de escopo
  revisável quando Notifications existir.
- "Book de pacientes" do psicólogo nos relatórios/dashboard é derivado de `Session` existente
  (`Modules\Reports\Support\PsychologistPatientScope`), mesmo padrão de `MedicalRecordPolicy`/
  `FinancialPolicy` (Fases 4/5) — `admin_clinica`/`super_admin` veem todo o tenant (ou um psicólogo
  específico via filtro); `psicologo` só o próprio book, sem opção de trocar.
- "Comparecimento" = `Realizada / (Realizada + NaoCompareceu)` por paciente — `Cancelada`/`Reagendada`
  ficam fora do denominador (mudança de agenda, não falha de comparecimento). Nenhuma das duas definições
  está em `docs/`, foram decisões de escopo explícitas desta fase.
- Relatórios do paciente **reaproveitam Fases 3 e 5 em vez de reconstruir**: "sessões" já era
  `GET /minhas-sessoes` (Fase 3); "situação financeira" já era `GET /pacientes/{patient}/financeiro`
  (Fase 5) — só precisou estender `FinancialPolicy::view` pra permitir o próprio paciente
  (`$actor->id === $patient->user_id`), fechando a pendência "portal do paciente pro próprio financeiro"
  deixada em aberto na Fase 5; a mesma tela React (`Ledger.jsx`) já escondia os controles de gestão
  quando `canManage` é `false`, então não precisou de nenhuma tela nova.
- "Recibos" é um PDF por `Payment` (`Modules\Payments\Http\Controllers\PaymentReceiptController`),
  listando a cobrança quitada e a sessão vinculada a ela quando existe (o schema só suporta uma sessão
  por cobrança — não foi criada nenhuma tabela N:N pagamento↔sessão pra isso). Autorizado pela mesma regra
  de `financial.view` (staff, psicólogo que tratou o paciente, ou o próprio paciente).
- Dashboards (`Modules\Reports\Http\Controllers\DashboardController`, substituindo a antiga closure-route
  de `/dashboard`) só têm dados reais pra `psicologo` (agenda do dia, sessões da semana, receita do mês,
  pendências, pacientes ativos/inativos, aniversariantes) e `paciente` (próxima sessão, pendências,
  histórico, atalhos) — únicos papéis citados no bullet do roadmap; qualquer outro papel mantém o card
  genérico de boas-vindas.
- "Pacientes ativos/inativos" e "aniversariantes" exigem decifrar `Patient::birth_date_encrypted` em PHP
  — não há coluna `_hash` pra mês/dia (só `document_number` tem hash de busca), então não dá pra filtrar
  isso no SQL. Aceitável no volume de uma clínica única; documentado como limitação conhecida.
- 114 testes PHPUnit no total (suíte completa Fases 1-6); verificado manualmente de ponta a ponta contra
  MySQL real via `php artisan serve`, incluindo download de PDF/Excel reais (`content-type` correto) dos
  três relatórios, recibo de pagamento (psicólogo tratante, o próprio paciente, e 403 pra psicólogo sem
  relação), e dashboards de psicólogo/paciente com dados corretos.
- **Pendências explícitas desta fase, não bloqueantes:** exportação assíncrona com fila + notificação
  (revisitar quando o módulo Notifications existir, Fase 7); dashboard dedicado pra
  admin_clinica/financeiro/secretaria; seletor de psicólogo na UI dos relatórios pro admin (a API já
  aceita `psychologist_id` via query string, só não tem um `<select>` na tela ainda — não há endpoint de
  "listar psicólogos do tenant" pra alimentar esse seletor); gráficos/visualizações ricas (só cards e
  tabelas simples nesta fase).

## Fase 7 — Notifications (concluída)
- Primeira vez que módulos de negócio (Scheduling, Financial, Payments) disparam Events de domínio
  (`SessionWasCancelled`, `SessionWasRescheduled`, `ChargeWasCreated`, `PaymentWasRecorded`,
  `PaymentWasReversed`) — até a Fase 6 as Actions só mutavam estado diretamente, sem o padrão "Eventos
  para efeitos colaterais" de `docs/03-Padroes-de-Codigo.md` chegar a ser exercitado. Cada Action
  relevante (`CancelSessionAction`, `RescheduleSessionAction`, `CreateChargeAction`,
  `RecordPaymentAction`, `ReversePaymentAction`) passou a disparar o Event correspondente ao final da
  própria transação; Listeners do módulo `Notifications` (`Modules\Notifications\Listeners`) consomem
  esses eventos e enviam a Notification certa — Scheduling/Financial/Payments continuam sem saber que
  Notifications existe (baixo acoplamento, igual o documentado).
- Arquitetura de canal pluggable: toda Notification estende `Modules\Notifications\Notifications\
  TenantNotification` (`ShouldQueue` + `SerializesModels`), cujo `via()` lê
  `config('notifications.channels.default')` (`mail,database` por padrão, configurável por
  `NOTIFICATIONS_DEFAULT_CHANNELS`) em vez de cada subclasse decidir o canal. Adicionar SMS/WhatsApp no
  futuro é acrescentar o nome do canal nessa config e implementar `toSms()`/`toWhatsApp()` nas 8 classes
  já existentes — nenhuma delas precisa ser refatorada nem o Listener que as dispara.
- 8 Notifications: `SessionReminderNotification`, `SessionCancelledNotification`,
  `SessionRescheduledNotification`, `ChargeCreatedNotification` (cobrança), `ChargeDueSoonNotification`,
  `ChargeOverdueNotification`, `PaymentConfirmedNotification` (inclui link do recibo da Fase 6, sem
  duplicar uma notification separada só pra isso), `PaymentReversedNotification`. Cancelamento/
  reagendamento notificam as duas partes (paciente e psicólogo — `SessionPolicy` já permitia os dois
  cancelarem/reagendarem desde a Fase 3), não só quem tomou a ação. "Confirmação de cadastro/e-mail" do
  bullet original do roadmap **não** ganhou uma nova Notification — já é resolvida pelo fluxo nativo
  `MustVerifyEmail`/`sendEmailVerificationNotification()` da Fase 1, refatorar isso pra dentro da nova
  arquitetura não agregaria nada.
- Lembrete de sessão (`notifications:send-session-reminders`, horário) e lembrete de cobrança
  (`notifications:send-charge-reminders`, diário, a vencer + vencida) são polling, não eventos — uma
  sessão/cobrança não "acontece" no momento do lembrete, o comando pergunta "o que está na janela agora".
  Idempotência via colunas dedicadas (`clinical_sessions.reminder_sent_at`,
  `financial_charges.due_soon_reminder_sent_at`/`overdue_reminder_sent_at`) — sem elas, rodar o comando
  mais de uma vez na mesma janela duplicaria o envio; "vencida" é um alerta único, não repetido a cada
  execução diária do comando (diferente de `financial:apply-late-fees`, que recalcula sempre).
- Canal `database`: tabela `notifications` (schema padrão do Laravel, com `uuidMorphs('notifiable')` em
  vez do `morphs()` de bigint padrão — mesma classe de ajuste já feita pra `model_has_roles` na Fase 1,
  necessário porque `User` tem PK UUID). Sem `tenant_id` — o isolamento vem inteiramente da relação
  `$user->notifications()`, mesmo raciocínio que já justifica `User` não usar `BelongsToTenant`.
- Tela `/notificacoes` (lista paginada, marcar uma/todas como lidas) e contador de não lidas
  (`unreadNotificationsCount`, prop compartilhada globalmente por `HandleInertiaRequests`) com um sino
  (`NotificationBell`) adicionado ao `Dashboard.jsx` — não foi criado um layout autenticado
  compartilhado pra isso (não existe um ainda no projeto; cada página monta o próprio wrapper), então o
  sino por ora só aparece no Dashboard, não em todas as páginas autenticadas.
- 132 testes PHPUnit no total (suíte completa Fases 1-7); verificado manualmente de ponta a ponta contra
  MySQL real: fila `database` real (não só `Notification::fake()`), drenada com
  `php artisan queue:work`, canais mail (log) e database confirmados com conteúdo correto; os dois
  comandos de lembrete rodados duas vezes seguidas pra confirmar a idempotência; fluxo HTTP completo
  (login + MFA + `/notificacoes` + marcar uma lida + marcar todas lidas) via `curl.exe` real.
- **Pendências explícitas desta fase, não bloqueantes:** canais SMS/WhatsApp (arquitetura pronta, sem
  implementação real — não há gateway contratado); preferências de notificação por usuário/tenant
  (o que pode ser desligado, silenciado etc. — depende do módulo Settings); sino de notificação nas
  demais páginas autenticadas (só no Dashboard por ora, sem layout compartilhado ainda);
  lembrete de sessão para o psicólogo (só o paciente recebe `SessionReminderNotification` hoje).

## Fase 8 — CMS (concluída)
- Páginas públicas editáveis num editor visual GrapesJS embutido numa página Inertia/React
  (`resources/js/Pages/CMS/Editor.jsx`), montado via `useEffect` sobre um `ref` — GrapesJS é vanilla-JS,
  não React. **Usado `grapesjs-preset-webpage`, não `grapesjs-preset-newsletter` do bullet original**: o
  preset newsletter é layout de e-mail baseado em tabelas; para páginas web públicas o preset webpage é a
  escolha tecnicamente correta (diretriz do prompt mestre de resolver ambiguidade para a decisão mais
  robusta — mesmo espírito do ADR-005). Import de HTML cru desabilitado no editor (`modalImportButton:
  false`), atendendo ao "sem edição manual de HTML pelo usuário final".
- Nove componentes próprios registrados como blocos do GrapesJS (`resources/js/cms/blocks.js`), categoria
  "Componentes da clínica": Banner, Hero, Cards, FAQ, Depoimentos, Botão, Formulário, Contato, Rodapé —
  cada um com estilo inline clean próprio, para renderizar bem na página pública (Blade puro, sem o bundle
  Tailwind do app).
- Model `Page` (`cms_pages`, `BelongsToTenant`, não cifrado — página pública é pública por definição):
  `slug` único por tenant, `status` (`rascunho`/`publicada`, enum `PageStatus`), `is_home` (só uma inicial
  por tenant, forçado na Action), `html`/`css` (artefatos já sanitizados servidos ao visitante),
  `project_data` (estado do editor GrapesJS para reabrir — nunca exposto publicamente), meta título/descrição.
- **Sanitização defense-in-depth (`Modules\CMS\Services\HtmlSanitizer`) no momento de salvar**, não só na
  renderização: apesar de o usuário editar por blocos (não digita HTML cru), o HTML gerado é servido em
  `{!! $html !!}` numa página pública — vetor clássico de stored XSS. Allowlist de tags + atributos via
  DOMDocument (mais seguro que regex), remove `<script>`/`<iframe>`/handlers `on*`/URLs `javascript:`/
  `data:text/html`, preservando classes/ids/estilos inline que casam com o blob de CSS. CSS sanitizado à
  parte (`@import`/`expression()`/`javascript:` removidos). Coberto por `HtmlSanitizerTest`.
- Gestão admin (`GET/POST/PUT/DELETE /cms/paginas...`, Inertia) restrita à nova permissão `manage-cms`
  (`super_admin` + `admin_clinica`, seedada — mesmo padrão de `manage-financial` na Fase 5) via
  `PagePolicy` (`Gate::policy`, aqui o recurso É a Page). Rotas com `resolve.tenant` +
  `CurrentTenant::ownsOrFail` no Page vindo por binding (disciplina do gotcha da Fase 3).
- Renderização pública server-side em Blade (`cms::public.show`), **não Inertia** — é HTML desenhado pelo
  usuário, não uma tela React. Rotas de convidado `GET /c/{tenant:slug}` (página inicial) e
  `GET /c/{tenant:slug}/p/{pageSlug}` (por slug); tenant resolvido pelo binding `{tenant:slug}` (não há
  `resolve.tenant` em contexto de convidado), Page buscada à mão com `withoutTenantScope()` +
  `where('tenant_id')` explícito (mesmo raciocínio do cadastro público de paciente, Fase 2). Só páginas
  `publicada` são servidas; rascunho/tenant inativo → 404.
- 155 testes PHPUnit no total (132 das Fases 1-7 + 23 novos), suíte completa verde; verificado
  manualmente contra MySQL real: `CreatePageAction` executada de verdade com payload XSS (script/
  `javascript:`/`onclick`/`@import` confirmados removidos no banco), render HTTP público em
  `php artisan serve` (home 200 com conteúdo/CSS, rascunho e tenant inexistente 404).
- **Pendências explícitas desta fase, não bloqueantes:** submissão real dos blocos Formulário/Contato
  (captação de lead persistente exige consentimento LGPD — fica para a Fase 10; hoje o Formulário é
  design/preview e o Contato usa `mailto:`); code-splitting do editor (GrapesJS entra no bundle principal
  via o glob eager de `app.jsx`, ~+1MB — o editor deveria ser carregado sob demanda); QR/preview embutido
  do site no admin; versionamento/histórico de página; menu de navegação entre páginas públicas gerado
  automaticamente; upload de mídia próprio (imagens hoje entram como data-URI via o Asset Manager do
  GrapesJS).

## Fase 9 — Audit/Security hardening (concluída)
- **Cabeçalhos de segurança HTTP** (`Modules\Security\Http\Middleware\SecurityHeaders`, anexado ao grupo
  `web` em `bootstrap/app.php`): CSP, `X-Content-Type-Options: nosniff`, `X-Frame-Options`,
  `Referrer-Policy`, `Permissions-Policy`, e HSTS **só sobre https** (navegador ignora HSTS em http, e
  emiti-lo em dev só polui). Tudo configurável em `config/security.php` ('headers'), com toggle
  `SECURITY_HEADERS_ENABLED`. A CSP mantém `style-src 'unsafe-inline'` (React/shadcn e as páginas
  públicas do CMS dependem de estilo inline), mas `script-src 'self'` — o bundle Vite é servido do
  próprio domínio. O middleware não sobrescreve um cabeçalho já definido por uma rota.
- **Rotação de chaves de criptografia** (fecha a pendência central da Fase 1/ADR-006): `EncryptionService::
  rotate($context)` aposenta a DEK ativa e cria a próxima versão ativa numa transação com `lockForUpdate`
  (evita corrida gerar a mesma `version`); dado antigo continua legível pela DEK aposentada (nunca
  apagada), porque a versão sempre viajou no bundle cifrado desde a Fase 1. `RotateEncryptionKeyJob`
  recifra o dado antigo em background para a nova versão, descobrindo o(s) atributo(s) do contexto pelo
  `getCasts()` do Model (registro `security.encryption_contexts` mapeia só contexto→Model, não cada
  coluna). Comando `php artisan security:rotate-key {context?} {--sync}` orquestra rotação + recifragem.
  **Fora da recifragem automática (a chave rotaciona, a migração em massa é pendência documentada):**
  `medical_record_content` (MedicalRecordEntry é append-only, `update()` lança exceção — não dá para
  recifrar in place) e o blob do arquivo de anexo em disco (cifrado direto pelo `AttachmentStorage`, não
  por cast).
- **Cobertura de auditoria** das ações obrigatórias de agenda/financeiro: `Modules\Audit\Listeners\
  RecordDomainAuditEvents` consome os Events de domínio já disparados desde a Fase 7
  (`SessionWasCancelled`/`SessionWasRescheduled`/`ChargeWasCreated`/`PaymentWasRecorded`/
  `PaymentWasReversed`) e grava em `audit_logs` com ação/ator/sujeito/tenant — Audit é consumidor
  cross-cutting desses eventos, mesma direção de baixo acoplamento que Notifications. Síncrono de
  propósito (captura IP/User-Agent/ator do contexto da requisição, que enfileirar perderia).
- **Rate limiting revisado**: os endpoints de exportação de relatório (PDF/Excel dos 3 relatórios) e o
  download de recibo em PDF ganharam `throttle:30,1` (docs/04-Seguranca.md pede rate limiting em
  exportação, que estava só em login/MFA/reset até aqui).
- 168 testes PHPUnit no total (155 das Fases 1-8 + 13 novos), suíte completa verde; verificado
  manualmente contra MySQL real: comando `security:rotate-key --sync` rotacionando uma DEK e recifrando
  dado existente, e cabeçalhos de segurança presentes na resposta HTTP com o app ainda carregando normal.
- **Pendências explícitas desta fase, não bloqueantes:** endurecimento de auditoria na camada de banco
  (`GRANT INSERT,SELECT` sem `UPDATE`/`DELETE` em `audit_logs` — depende do ambiente Plesk de produção);
  recifragem em massa de `medical_record_content` (append-only) e dos blobs de anexo em disco na rotação;
  métricas de autenticação e de fila (dashboard/observabilidade — precisa de backend de métricas, ex.
  Pulse, ainda não contratado); rotação de DEK por-tenant (hoje os contextos usam DEK global, `tenant_id`
  nulo); cobertura de auditoria dos demais eventos obrigatórios sem Event de domínio ainda (criação de
  prontuário, exportação/download em si, publicação/remoção no CMS, exclusão lógica) — a infra
  (`AuditLogger` + listener) está pronta, falta disparar/escutar esses casos; testes de concorrência
  adicionais além dos já existentes de `lockForUpdate` (Fases 3/5); runbook de backup/restauração
  (docs/04-Seguranca.md, depende do pipeline de backup do Plesk).

## Fase 10 — LGPD (concluída)
- Vive no módulo `Security` (docs/04-Seguranca.md documenta LGPD dentro de Segurança) sob o namespace
  `Lgpd`, em vez de criar um 19º módulo.
- **Documentos legais versionados por tenant** (`legal_documents`, `BelongsToTenant`, tipos
  `privacy_policy`/`terms_of_use` no enum `LegalDocumentType`): publicar uma nova versão
  (`PublishLegalDocumentAction`) nunca sobrescreve — cria a próxima `version` como `is_current` e aposenta
  a anterior (histórico preservado). Gestão em `/lgpd/documentos...` restrita à nova permissão
  `manage-legal` (`super_admin`/`admin_clinica`).
- **Registro de aceite append-only** (`lgpd_consents`, Model `Consent` com `update()`/`delete()` lançando
  exceção, mesmo padrão de `AuditLog`): `RecordConsentAction` grava tipo, versão aceita, data/hora, IP e
  User-Agent — a prova de consentimento. Auditado (`lgpd.consent_recorded`).
- **Gating de re-consentimento** (`EnsureLgpdConsent`, no grupo `web`): se o tenant tem uma versão atual
  de documento obrigatório que o usuário ainda não aceitou (`ConsentChecker`), redireciona para
  `/lgpd/consentimento` antes de qualquer ação. Publicar uma nova versão invalida o aceite anterior e
  reexige o aceite automaticamente. **Opt-in por clínica**: se o tenant não publicou documento nenhum, é
  no-op — não força consentimento sobre o vazio nem quebra fluxos existentes.
- **Direito de acesso/portabilidade (Art. 18)** — fecha a pendência da Fase 4: `GET /lgpd/meus-dados`
  (tela) e `/lgpd/meus-dados/download` (JSON) montam o pacote de dados do próprio titular
  (`BuildPersonalDataExportAction`: conta, perfil decifrado, responsáveis, sessões, cobranças,
  consentimentos). O download é uma exportação auditada (`lgpd.data_exported`) e rate-limited.
- **Anonimização irreversível** (`AnonymizePatientAction` + `php artisan lgpd:anonymize-patient {id}
  --force`): substitui a PII do paciente por marcadores, apaga campos cifrados/hashes, marca
  `anonymized_at`, faz soft-delete e cascateia para responsáveis e para a conta de login — mantendo a
  linha para as obrigações de retenção (vínculo com sessões/cobranças), sem dado identificável. Auditado
  (`lgpd.patient_anonymized`), idempotente, processo manual e explícito (exige `--force`).
- 184 testes PHPUnit no total (168 das Fases 1-9 + 16 novos), suíte completa verde; verificado
  manualmente contra MySQL real: publicação de documento → `pendingFor` 1 → aceite → `pendingFor` 0;
  `lgpd:anonymize-patient --force` limpando a PII (nome vira marcador, documento nulo, `anonymized_at`
  setado, soft-delete); e o app carregando normal com o novo middleware na cadeia.
- **Pendências explícitas desta fase, não bloqueantes:** exclusão física pós-retenção (hoje só soft-delete
  + anonimização; a exclusão física definitiva conforme prazos de retenção do CFP fica como processo
  manual documentado, não automático); integração do consentimento com os blocos Formulário/Contato do
  CMS (Fase 8) para captação de lead com base legal; anonimização em lote / por critério de retenção
  (hoje é um paciente por vez); versão pública dos documentos legais servida na página do CMS; exportação
  do "meus dados" em PDF além do JSON; expurgo do `medical_record_content` na anonimização (o prontuário
  é append-only e cifrado — a exclusão/anonimização do conteúdo clínico exige processo dedicado, alinhado
  ao mesmo deferral de recifragem da Fase 9).

## Fase 11 — Produtização SaaS (concluída)
- Primeira fase a dar vida ao módulo `Settings` (skeleton desde a Fase 0) — é onde a configuração
  por-tenant e a camada de produtização passam a morar.
- **Planos e limites** (catálogo em `config/plans.php`, não tabela — é catálogo da plataforma, não dado
  de tenant): `trial`/`basico`/`profissional`, cada um com `max_psychologists`/`max_patients` (`null` =
  ilimitado). `Modules\Settings\Services\PlanLimits` aplica o limite; `RegisterPsychologistAction` chama
  `assertCanAddPsychologist` antes de criar qualquer linha, e o Controller converte a
  `PlanLimitReachedException` numa mensagem de validação. **Cobrança real de gateway/PIX continua marco
  futuro** — "billing" aqui é estado de assinatura + trial + aplicação de limite, sem pagamento.
- **Provisionamento/onboarding** (`ProvisionTenantAction`): fonte única de "como nasce um tenant" (plano
  default, janela de trial `config('plans.trial_days')`, slug único). `RegisterClinicAdminAction`
  (auto-cadastro do Admin da Clínica) foi refatorada para usá-la, então o cadastro e a criação manual por
  Super Admin caem no mesmo estado inicial. Coluna nova `tenants.trial_ends_at` (as demais —
  `plan`/`status`/`settings` — já existiam da Fase 1).
- **Configuração por-tenant** (`Modules\Settings\Services\TenantSettings`): lê de `tenants.settings` (JSON)
  com **fallback para o `config/*` global** — um tenant que não mexeu numa chave herda o default, sem
  duplicar valor no banco. Registro de chaves conhecidas (agenda: horizonte de reserva, antecedência
  mínima; marca: nome de exibição, cor primária). Tela `/configuracoes` (`manage-clinic-settings`,
  primeira permissão real desse escopo, seedada desde a Fase 1). **Fecha as pendências antigas de "timeout
  de sessão / antecedência mínima configurável por tenant"** (Fases 1/3): os consumidores do Scheduling
  (`AgendaController`, `EnsuresMinimumNotice`) agora leem via `TenantSettings->current(...)`.
- **Gestão de tenants pela plataforma** (`/plataforma/tenants`, `PlatformTenantController`): **primeira
  rota a usar `platform.manage-tenants`** (seedada desde a Fase 1, sem uso até aqui). Super Admin lista,
  provisiona e altera plano/status de qualquer tenant. Sem `resolve.tenant` — o Super Admin opera
  cross-tenant e não tem tenant próprio.
- **Marca por-tenant** exposta como prop compartilhada do Inertia (`branding` em `HandleInertiaRequests`,
  lazy) para as telas usarem nome/cor da clínica.
- **Isolamento físico (ADR-003)**: reavaliado e registrado como **ADR-007** em `01-Arquitetura.md` — a
  decisão de manter isolamento por coluna é confirmada para esta fase; o caminho de migração para
  schema/database-per-tenant fica documentado como gatilho por-cliente, não implementado.
- 202 testes PHPUnit no total (184 das Fases 1-10 + 18 novos), suíte completa verde; verificado
  manualmente contra MySQL real: override de `booking_horizon_days` por tenant vencendo o config,
  bloqueio de criação de psicólogo além do limite do plano trial, e provisionamento com trial via
  `ProvisionTenantAction`.
- **Pendências explícitas desta fase, não bloqueantes:** integração de gateway de pagamento/PIX e cobrança
  recorrente de verdade (marco futuro); enforcement do `max_patients` no auto-cadastro de paciente (hoje o
  limite existe e é exibido, mas só psicólogos são bloqueados na criação); bloqueio de acesso quando o
  trial expira ou o tenant é `suspended` (hoje o status é gravado e exibido, mas não há middleware que
  barre o login/uso — decisão de produto para quando houver billing real); aplicação da cor/marca do
  tenant no tema visual além de expor a prop (as páginas ainda não consomem `branding` no layout);
  configuração por-tenant do timeout de sessão (o `EnsureSessionIsValid` roda no grupo `web` antes de
  `resolve.tenant`, então o override por-tenant do timeout exige resolver o tenant mais cedo — os demais
  parâmetros de agenda já são por-tenant); isolamento físico de dados (ADR-007, documentado, não
  implementado); telas de billing/faturas e histórico de mudança de plano.

## Marcos futuros (fora de fases numeradas, mantidos como visão)
- ~~Múltiplos psicólogos por clínica, múltiplas unidades, secretárias com escopo próprio.~~ **(concluído)**
  Múltiplos psicólogos já existiam desde a Fase 2/11. **Unidades** (filiais): modelo `Unit` + CRUD
  (`/unidades`, `manage-clinic-settings`), pivot `unit_user` vinculando psicólogos/secretárias a unidades,
  `clinical_sessions.unit_id` preenchido pela `BookSessionAction` a partir da unidade do psicólogo. **Papel
  `secretaria` ativado** (seedado sem uso desde a Fase 1): permissão `manage-scheduling`, convite via
  `/secretarias` (`InviteSecretaryAction`), e escopo por unidade (`Modules\Settings\Services\UnitScope`) —
  a secretária só vê a agenda das suas unidades (`/agenda-unidade`), o admin vê todas. Pendências:
  transferência de sessão entre unidades, relatórios por unidade, edição de vínculo unidade↔psicólogo
  fora do cadastro inicial.
- ~~Convênios, teleconsulta,~~ assinatura eletrônica, emissão de notas fiscais. **(convênios + teleconsulta
  concluídos; assinatura e NFe = contratos documentados)** **Convênios**: modelo `HealthPlan` + CRUD
  (`/convenios`, `manage-financial`); paciente escolhe o convênio no perfil (`patients.health_plan_id`), e
  a cobrança herda o convênio do paciente (`financial_charges.health_plan_id`, `CreateChargeAction`).
  **Teleconsulta**: `clinical_sessions.meeting_url` (nullable) — o psicólogo/staff define o link da sessão
  online (`POST /sessoes/{id}/teleconsulta`, autorizado por `SessionPolicy::markStatus`, editável na
  agenda da unidade) e o paciente vê "Entrar na teleconsulta" em `/minhas-sessoes`; sem integração de
  vídeo (link manual, mesmo espírito do PIX manual da Fase 5). **Assinatura eletrônica** e **NFe** ficam
  como **contratos** (`Modules\MedicalRecords\Contracts\SignatureProviderInterface`,
  `Modules\Payments\Contracts\InvoiceIssuerInterface`) sem implementação/binding — dependem de provedor
  contratado (Clicksign/D4Sign; Focus NFe/eNotas), mesmo padrão do `PaymentGatewayInterface`. Pendências
  de convênios: faturamento ao convênio (guias/TISS), convênio por cobrança na UI (hoje herda do paciente).
- Gateways de pagamento reais e PIX.
- Aplicativo móvel.
- API pública REST (o desacoplamento Actions/Services já feito desde a Fase 0 evita refatoração
  significativa quando isso for priorizado).
- SMS e WhatsApp como canais de notificação adicionais.
