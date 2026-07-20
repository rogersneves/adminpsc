# 01 - Arquitetura

## Visão geral

AdminPSC é um monólito modular Laravel 13 + Inertia v2 + React 19, com visão de evolução para SaaS
multi-tenant. "Monólito modular" significa: um único deploy/processo, mas com fronteiras de módulo
fortes o suficiente para que, no futuro, qualquer módulo possa virar um serviço isolado ou ser
habilitado/desabilitado por plano de assinatura, sem reescrever regra de negócio.

## Camadas (por módulo)

Fluxo de uma requisição típica:

```
HTTP Request
  → Route
  → Middleware (auth, MFA, tenant scoping, throttle)
  → FormRequest (validação + autorização de shape)
  → Controller (só orquestra: chama Action/Service, devolve Inertia::render ou redirect)
  → Action ou Service (regra de negócio)
  → Repository (acesso a dados, quando o Model sozinho não é suficiente)
  → Model (Eloquent, com Policies para autorização por registro)
  → Event / Job (efeitos colaterais assíncronos: notificações, auditoria, cache)
```

Regra dura: **Controllers nunca contêm regra de negócio.** Um Controller só sabe orquestrar chamadas
e formatar a resposta. Toda decisão de negócio mora em Actions, Services ou Domain (Models + Policies
+ Rules).

### Papel de cada subpasta dentro de um módulo

| Pasta | Responsabilidade |
|---|---|
| `Actions` | Uma operação de negócio única e nomeada (ex.: `ScheduleSessionAction`), invocável (`__invoke`). Preferível a Service quando a operação é atômica e não tem estado compartilhado com outras operações. |
| `DTOs` | Objetos imutáveis (`readonly class`) para transportar dados entre camadas sem depender de arrays soltos ou de Request diretamente dentro de Services/Actions. |
| `Enums` | Enums nativos do PHP 8.4 (com métodos, quando útil) para status, papéis, modalidades etc. Nunca strings mágicas espalhadas pelo código. |
| `Events` | Fatos de domínio já ocorridos (`SessionWasCancelled`), disparados por Actions/Services. |
| `Exceptions` | Exceções de domínio específicas do módulo (ex.: `DoubleBookingException`), nunca `\Exception` genérica em regra de negócio. |
| `Jobs` | Trabalho assíncrono (fila `database`): envio de notificação, geração de PDF/Excel, rotação de chave. |
| `Models` | Eloquent Models — apenas mapeamento e relacionamentos, sem regra de negócio pesada dentro do Model. |
| `Notifications` | Canais de notificação (mail, database, e futuramente SMS/WhatsApp). |
| `Policies` | Autorização por registro (quem pode ver/editar/apagar este paciente, esta sessão, este prontuário). |
| `Repositories` | Usado quando o acesso a dados tem complexidade (múltiplas fontes, cache, queries compostas) que não deve vazar para o Service. Não criar Repository para CRUD trivial — Eloquent puro já resolve (evita over-engineering). |
| `Rules` | Regras de validação customizadas reutilizáveis (ex.: `MinimumRescheduleNotice`). |
| `Services` | Orquestram múltiplas Actions/Repositories quando a operação é composta (ex.: `FinancialClosingService`). |
| `Traits` | Comportamento horizontal reutilizável entre Models/Classes do módulo (ex.: `HasTenant`, `Auditable`). |

## Módulos iniciais

Implementados via `nwidart/laravel-modules` (ver ADR-002). Cada módulo vive em `Modules/{Nome}` com
autoload PSR-4 próprio, `routes/`, `config/`, `lang/`, `database/migrations` e `resources/js` isolados.

1. **Core** — infraestrutura compartilhada entre módulos: traits base, contratos, helpers, exceptions
   base. Não contém regra de negócio de domínio.
2. **Tenant** — modelo `Tenant`, resolução de tenant atual, global scope de isolamento, provisionamento.
3. **Authentication** — login, registro, MFA (OTP e-mail + TOTP), recuperação de senha, sessão.
4. **Authorization** — papéis e permissões (spatie/laravel-permission), Policies base.
5. **Users** — conta de usuário genérica, perfil, preferências.
6. **Psychologists** — dados profissionais do psicólogo (CRP, especialidades, disponibilidade base).
7. **Patients** — cadastro de pacientes, dados pessoais.
8. **Guardians** — responsáveis legais, vínculo com pacientes menores de 16 anos.
9. **Scheduling** — agenda, disponibilidade, bloqueios, sessões, lista de espera, regras de reagendamento.
10. **MedicalRecords** — prontuário, versionado e criptografado, separado do cadastro do paciente.
11. **Financial** — cobranças, parcelamento, descontos, multas, juros, estornos.
12. **Payments** — registro de pagamentos e (futuro) integração com gateways/PIX.
13. **Reports** — geração de relatórios (Excel/PDF) para psicólogo e paciente.
14. **Notifications** — orquestração de notificações transacionais (lembrete, confirmação, cobrança).
15. **CMS** — páginas públicas editáveis via GrapesJS.
16. **Audit** — trilha de auditoria imutável, consumida por eventos de todos os módulos.
17. **Security** — políticas de sessão, criptografia (Master Key/DEK), rate limiting, cabeçalhos.
18. **Settings** — configurações por tenant (parametrização de regras: antecedência mínima, duração
    padrão de sessão etc).

## Multi-tenant

Estratégia: **single database, shared schema, isolamento por coluna `tenant_id`**, não schema-per-tenant
nem database-per-tenant. Motivo: WAMP/Plesk sem Docker torna operação de múltiplos schemas/bancos mais
cara de manter; isolamento por coluna com Eloquent Global Scope automático dá 90% da segurança com uma
fração da complexidade operacional, e permite migrar para schema-per-tenant depois, se um cliente grande
exigir isolamento físico (decisão reavaliável, registrar novo ADR se acontecer).

Mecanismo (implementado na Fase 1 — `Modules/Tenant`):
- Toda tabela de negócio tem `tenant_id` (FK de aplicação para `tenants.id`, UUID; ver nota sobre
  `users.tenant_id` abaixo — essa coluna específica não leva FK de banco por razão de ordem de migration).
- Um `TenantScope` (Global Scope) é aplicado automaticamente a todo Model que use o trait
  `Modules\Tenant\Traits\BelongsToTenant`, filtrando sempre pelo tenant resolvido da requisição atual.
- Middleware `Modules\Tenant\Http\Middleware\ResolveTenant` (alias `resolve.tenant`) roda depois de `auth`,
  identifica o tenant do usuário autenticado (por `user->tenant_id`; domínio/subdomínio fica para quando
  o SaaS for exposto publicamente) e o injeta no singleton `Modules\Tenant\Support\CurrentTenant`.
- Nenhuma query de negócio pode rodar sem tenant resolvido — Models com `BelongsToTenant` lançam
  `UnresolvedTenantException` se usados fora de um contexto de tenant resolvido em produção (falha segura,
  nunca vaza dado entre tenants por omissão); em console/testes sem tenant resolvido, a scope é ignorada
  para não travar seeders/comandos administrativos.

**Exceção deliberada: `User` não usa `BelongsToTenant`/`TenantScope`.** O login precisa localizar um
usuário pelo e-mail *antes* de qualquer tenant estar resolvido (`User::where('email', ...)->first()`) —
aplicar a scope aqui criaria uma dependência circular (não dá pra resolver o tenant sem antes achar o
usuário, e não dá pra achar o usuário se a query já exige um tenant resolvido). `User` tem uma coluna
`tenant_id` simples + relação `belongsTo(Tenant::class)`, sem auto-filtro. A scope estrita é para dados de
negócio (Patients, Sessions etc., a partir da Fase 2), não para a própria tabela de autenticação.

## Desacoplamento para futura API pública

Mesmo usando Inertia (que devolve props para componentes React, não JSON puro de API), a regra é:
Actions/Services/Repositories **nunca** conhecem Inertia nem HTTP. Um Controller Inertia e um futuro
Controller de API REST devem poder chamar exatamente a mesma Action e obter o mesmo resultado de domínio
(um DTO ou Model), cada um decidindo como serializar a resposta. Isso evita reescrever regra de negócio
quando a API pública for exposta (ver `06-Roadmap.md`).

## ADRs (Architecture Decision Records)

### ADR-001 — Modularização com nwidart/laravel-modules
**Contexto:** o projeto precisa de fronteiras claras entre ~18 áreas de negócio desde o início, e de
capacidade futura de habilitar/desabilitar módulos por plano SaaS.
**Decisão:** usar `nwidart/laravel-modules` (compatível com Laravel 13) em vez de uma estrutura manual
sob `app/`.
**Consequência:** cada módulo tem autoload, rotas, config, lang e migrations isolados; comandos
`php artisan module:make-*` aceleram a criação de novas classes seguindo o padrão do projeto.

### ADR-002 — RBAC com spatie/laravel-permission
**Contexto:** o prompt mestre exige papéis independentes de permissões, com 7 papéis iniciais.
**Decisão:** usar `spatie/laravel-permission` em vez de tabelas de papel/permissão feitas à mão.
**Consequência:** papéis e permissões são entidades separadas e combináveis; o módulo `Authorization`
define os papéis/permissões padrão via seeder, mas a matriz é editável por tenant.

### ADR-003 — Isolamento multi-tenant por coluna, não por schema/database
**Contexto:** deploy é Plesk sem Docker, sem orquestração de múltiplos bancos por tenant.
**Decisão:** `tenant_id` + Global Scope automático, com falha segura se o contexto de tenant não for
resolvido.
**Consequência:** operação simples em um único banco MySQL 8.4; caminho de migração para isolamento
físico documentado, mas não implementado nesta fase.

### ADR-004 — Auth/MFA construídos manualmente, sem Breeze
**Contexto:** o fluxo de login exige MFA obrigatório (OTP e-mail + TOTP) em todo novo login, o que não
é o que scaffolds prontos (Breeze) assumem.
**Decisão:** montar o fluxo de autenticação do zero no módulo `Authentication`, usando Inertia+React
para as telas e `pragmarx/google2fa-laravel` para TOTP.
**Consequência:** mais trabalho inicial, mas controle total do fluxo de sessão/MFA exigido pela
seção de Segurança do prompt mestre.

### ADR-005 — Inertia.js v3 em vez de v2
**Contexto:** o prompt mestre original especificou "Inertia.js v2". No momento em que o scaffold foi
criado (2026), a versão estável corrente já era a v3 (cliente HTTP embutido, SSR simplificado via plugin
Vite, melhorias em `Inertia::optional()`/`defer()`/`merge()`), e é o que `composer require
inertiajs/inertia-laravel` resolve por padrão em um projeto Laravel 13 novo.
**Decisão:** usar Inertia v3 (`inertiajs/inertia-laravel ^3.1`, `@inertiajs/react ^3.6`) em vez de fixar
a v2, aplicando a diretriz do próprio prompt mestre de tomar a decisão tecnicamente mais robusta diante
de ambiguidade.
**Consequência:** API de setup do cliente é mais simples (sem callbacks manuais de resolve/setup) e o
bundle é menor. Se houver um motivo específico para exigir v2, isso precisa ser revisto — registrar novo
ADR se a decisão for revertida.

### ADR-006 — Primitiva de envelope encryption adiantada para a Fase 1
**Contexto:** o roadmap original previa a arquitetura completa de criptografia (Master Key/DEK/rotação)
só na Fase 9. Mas a Fase 1 (MFA obrigatório) precisa cifrar o `mfa_totp_secret` do usuário — `docs/04-
Seguranca.md` já exige isso, e implementar MFA armazenando o secret em texto puro não é uma opção segura,
nem temporária.
**Decisão:** implementar já na Fase 1 o primitivo central de envelope encryption (`Modules\Security\
Services\EncryptionService`, AES-256-GCM, Master Key de `ENCRYPTION_MASTER_KEY` cifrando uma DEK por
contexto, tabela `encryption_keys`, cast Eloquent reutilizável `Modules\Security\Casts\EnvelopeEncrypted`).
Escopo limitado a uma DEK ativa por contexto (versão 1) — sem Job de rotação, sem versionamento avançado
ainda (isso continua na Fase 9).
**Consequência:** o mesmo cast já fica pronto para cifrar PII de pacientes/responsáveis na Fase 2, sem
retrabalho — só reaproveitar `EnvelopeEncrypted::class.':contexto'` no `casts()` do Model. Rotação de
chave, múltiplas DEKs por tenant e o Job de recriptografia em background continuam pendências da Fase 9.
