# 04 - Segurança

## Criptografia de dados clínicos e pessoais sensíveis

### Modelo de envelope encryption

```
Master Key (fora do banco, em .env/gerenciador de segredos do Plesk)
   └─ cifra/decifra → Data Encryption Keys (DEK), versionadas, guardadas cifradas em `encryption_keys`
        └─ cada DEK cifra/decifra os dados de um contexto (prontuário, PII de paciente, PII de
           responsável) com AES-256-GCM
```

- **Master Key**: nunca fica no banco de dados. Vive em variável de ambiente/segredo gerenciado pelo
  Plesk, fora do controle de versão. Usada apenas para cifrar/decifrar as DEKs (envelope), nunca para
  cifrar dado de negócio diretamente — isso permite rotacionar DEKs sem tocar na Master Key, e trocar a
  Master Key sem recifrar todo o banco (só as DEKs).
- **DEK (Data Encryption Key)**: uma chave AES-256 por contexto (`medical_records`, `patients_pii`,
  `guardians_pii`, ...), opcionalmente por tenant. Gerada aleatoriamente, cifrada pela Master Key e
  armazenada na tabela `encryption_keys` (coluna `wrapped_dek`) — nunca em texto puro em lugar nenhum.
- **Versionamento**: toda linha cifrada guarda `encryption_key_version` junto do ciphertext. Rotação de
  chave = gerar nova DEK (`status = ativa`), aposentar a anterior (`status = aposentada`, mantida para
  decifrar dados antigos), e um Job (`RotateEncryptionKeyJob`) recifra gradualmente os registros antigos
  para a nova versão em background, sem downtime.
- **Nonce**: 12 bytes aleatórios gerados por operação de cifragem (nunca reaproveitado), armazenados
  junto do ciphertext (não precisa ser secreto, só único).
- **Authentication Tag (GCM)**: gerado pela própria cifragem, armazenado junto — garante integridade
  (qualquer adulteração do ciphertext falha a decifragem, não é só confidencialidade).
- Formato de armazenamento sugerido por campo cifrado: `base64(nonce) . '.' . base64(ciphertext) .
  '.' . base64(tag) . '.' . key_version`, ou colunas separadas quando o campo for consultado por outra
  camada (a decisão final de formato fica para a implementação do módulo `Security`, documentada como
  ADR quando codificada).
- **Nunca armazenar dado sensível em texto puro** — nem em log, nem em cache, nem em fila (payloads de
  Job que carregam dado sensível devem carregar apenas o ID do registro, recarregando e decifrando
  dentro do Job).
- Campos que precisam de busca exata usam a estratégia híbrida (hash HMAC determinístico ao lado do
  ciphertext) descrita em `02-Banco-de-Dados.md`.

## MFA (Multi-Factor Authentication)

- **Obrigatória para todos os papéis, sem exceção** — incluindo Paciente e Responsável Legal.
- **Todo novo login exige MFA** — não existe "confiar neste dispositivo por 30 dias" nesta fase (pode
  ser reavaliado depois como opção configurável por tenant, registrado como ADR se implementado).
- **Métodos suportados:**
  - OTP por e-mail (código de uso único, expira em poucos minutos).
  - TOTP (Google Authenticator/compatível) via `pragmarx/google2fa-laravel`, com o segredo do usuário
    cifrado em repouso (mesma estratégia de envelope encryption acima).
- Fluxo: senha correta → desafio de MFA → sessão só é considerada autenticada após o segundo fator.
  Falhas de MFA são auditadas (ver Auditoria) e sujeitas a rate limiting.

## Sessão

- **Timeout absoluto**: sessão expira após um tempo máximo configurável por tenant, independente de
  atividade.
- **Timeout por inatividade**: sessão expira após um período sem interação, configurável por tenant.
- Ambos aplicados via middleware do módulo `Security`, checando timestamps de emissão/última atividade
  guardados na sessão do Laravel.

## Auditoria (imutável)

Tabela `audit_logs` é **append-only por design**:
- O Model `AuditLog` não expõe métodos de update/delete (sem `fillable` para edição, sem uso de
  `SoftDeletes`, sem `updated_at`).
- Nenhuma rota/controller do sistema oferece edição ou exclusão de log — a ausência é estrutural, não
  apenas por convenção de UI.
- Endurecimento adicional recomendado na camada de banco (a aplicar quando o ambiente Plesk permitir):
  usuário de aplicação do MySQL com `GRANT INSERT, SELECT ON audit_logs` e **sem** `UPDATE`/`DELETE`
  nessa tabela especificamente, para que nem um bug de aplicação consiga apagar trilha.
- Eventos obrigatoriamente auditados: login, logout, falha de autenticação, alteração cadastral,
  alteração financeira, alteração de agenda, alteração de prontuário, exportação, download, exclusão
  lógica, mudança administrativa. Cada Action/Service relevante dispara um Event capturado por um
  Listener genérico do módulo `Audit` (`RecordAuditLog`), evitando espalhar `AuditLog::create(...)`
  manualmente em cada módulo.

## LGPD

- **Consentimentos**: tabela `lgpd_consents` registra tipo, versão do documento aceito, data/hora e IP.
- **Política de privacidade / termos de uso**: versionados; nova versão exige novo aceite (histórico
  preservado, nunca sobrescrito).
- **Anonimização**: quando legalmente permitida (ex.: fim de retenção obrigatória do CFP), um Job
  substitui PII por marcadores irreversíveis, mantendo apenas o necessário para obrigações legais/
  estatísticas, e registra a operação na auditoria.
- **Exclusão**: exclusão lógica (soft delete) é o padrão; exclusão física só ocorre quando a legislação
  exigir e após todas as obrigações de retenção (financeira, clínica, conforme CFP) serem satisfeitas —
  processo documentado, não automático.

## Backup

- Backups incluem os dados já cifrados em repouso (o backup nunca decifra) — logo, a Master Key e as
  DEKs cifradas precisam ser preserváveis/restauráveis junto do backup do banco, ou o backup é inútil.
- Processo de restauração deve ser documentado e testado (runbook a ser escrito quando o pipeline de
  backup do Plesk for configurado — fica registrado como pendência no `06-Roadmap.md`).

## Superfície OWASP / ASVS

Diretrizes aplicadas transversalmente (detalhamento técnico por módulo conforme implementado):
- Validação de entrada via FormRequest + Rules em toda rota de escrita.
- Autorização por Policy em toda ação sobre um registro específico (não só por papel/rota).
- Rate limiting em login, MFA, recuperação de senha e endpoints de exportação.
- Proteção CSRF (padrão Laravel) e cabeçalhos de segurança (CSP, HSTS, X-Content-Type-Options) no
  módulo `Security`.
- Nenhum ID sequencial exposto em rota/URL (UUID em todo lugar — ver `02-Banco-de-Dados.md`).
