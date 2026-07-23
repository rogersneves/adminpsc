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
