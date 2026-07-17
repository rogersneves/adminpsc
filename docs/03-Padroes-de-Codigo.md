# 03 - Padrões de Código

## Padrões gerais

Aplicar rigorosamente SOLID, Clean Code, DRY, KISS e PSR-12 em todo código PHP. Tipagem estrita
(`declare(strict_types=1);` em toda classe PHP nova) e tipos de retorno explícitos sempre que possível.

## Nomenclatura por camada

| Camada | Convenção | Exemplo |
|---|---|---|
| Controller | Substantivo + `Controller`, um recurso por controller | `SessionController` |
| FormRequest | Verbo + Substantivo + `Request` | `StoreSessionRequest`, `RescheduleSessionRequest` |
| Action | Verbo no imperativo + `Action`, `__invoke` único | `ScheduleSessionAction`, `CancelSessionAction` |
| Service | Substantivo do domínio + `Service`, orquestra múltiplas Actions | `FinancialClosingService` |
| DTO | Substantivo + `Data` (readonly class) | `SessionData`, `PatientRegistrationData` |
| Enum | Substantivo no singular, nativo do PHP 8.4 | `SessionStatus`, `ChargeStatus` |
| Repository | Substantivo + `Repository` (+ interface `...RepositoryInterface`) | `SessionRepository` |
| Policy | Substantivo do Model + `Policy` | `MedicalRecordEntryPolicy` |
| Rule | Descreve a regra validada | `MinimumRescheduleNotice`, `PatientRequiresGuardianIfMinor` |
| Event | Fato no passado | `SessionWasCancelled`, `PaymentWasReversed` |
| Job | Verbo no imperativo + `Job` | `SendSessionReminderJob`, `RotateEncryptionKeyJob` |
| Exception | Substantivo do problema + `Exception` | `DoubleBookingException` |
| Trait | Adjetivo/capacidade + `Has`/`Is` prefixo quando aplicável | `HasTenant`, `IsAuditable` |

## Regras de camada

- **Controller só orquestra.** Recebe FormRequest já validado, chama uma Action/Service, devolve
  `Inertia::render(...)` ou redirect. Nunca contém `if` de regra de negócio, nunca monta query
  diretamente.
- **Toda regra de negócio mora em Actions, Services ou Domain** (Models com Policies/Rules), nunca em
  Controllers, nunca em componentes React.
- **Repository só quando agrega valor.** Para CRUD trivial (um Model, uma tabela, sem lógica de
  composição), usar Eloquent diretamente no Service/Action — criar um Repository só para "seguir o
  padrão" é over-engineering e viola KISS. Criar Repository quando há: múltiplas fontes de dado,
  cache, queries compostas reaproveitadas em vários lugares, ou necessidade de trocar a implementação
  de acesso a dados em teste.
- **DTOs cruzam camadas, Requests não.** Um `FormRequest` nunca é passado para dentro de um
  Service/Action — ele é convertido em DTO no Controller antes da chamada. Isso mantém Services/Actions
  testáveis sem precisar simular uma requisição HTTP.
- **Eventos para efeitos colaterais.** Quando uma Action de negócio completa (ex.: sessão cancelada),
  disparar um Event; Listeners cuidam de notificação, auditoria, invalidação de cache — a Action
  principal não conhece esses efeitos diretamente (baixo acoplamento).

## Testes (PHPUnit exclusivamente)

- `tests/Unit` — Actions, Services, Rules, Enums, DTOs isolados (sem banco quando possível, ou com
  `RefreshDatabase` quando indispensável).
- `tests/Feature` — fluxo completo via HTTP/Inertia (rota → resposta), incluindo autorização (Policy)
  e efeitos colaterais (Events/Jobs com `Bus::fake()`/`Event::fake()` quando o efeito não é o foco do
  teste).
- Um teste por regra de negócio crítica, nomeado pelo comportamento, não pela implementação:
  `test_cannot_reschedule_session_with_less_than_24_hours_notice`, não `test_reschedule_method`.
- Testes de concorrência (dupla reserva, dupla escrita financeira) usando transações e locks reais
  contra o MySQL de teste, não mocks — mocks não capturariam uma race condition real.
- Módulos com dado sensível (MedicalRecords, Financial, Authentication) exigem cobertura de: caminho
  feliz, caminho de autorização negada, e caminho de dado inválido/malicioso.

## Frontend (React 19 + Inertia v2)

- Componentes de página ficam em `resources/js/Pages`, seguindo a estrutura de rotas Inertia
  (`Pages/Scheduling/Index.jsx`, `Pages/Scheduling/Show.jsx`).
- Componentes de UI reutilizáveis (shadcn/ui + customizações) ficam em `resources/js/Components`.
- Nenhuma lógica de negócio no React — componentes recebem dados já prontos via props do Inertia;
  cálculos de domínio (ex.: "sessão pode ser cancelada?") vêm do backend, não são recalculados no
  cliente.
- Texto nunca é escrito diretamente em JSX nem em Blade — sempre via chave de tradução (ver
  `05-UIUX-Design-System.md`).

## Documentação técnica e comentários

Comentários só quando o "porquê" não é óbvio pelo código (uma decisão não intuitiva, um workaround
para uma limitação específica de uma lib, uma invariante que não é visível localmente). Não documentar
o "o quê" — nomes descritivos já cobrem isso. Decisões arquiteturais relevantes vão para os documentos
em `docs/` (ADRs), não para comentários espalhados no código.
