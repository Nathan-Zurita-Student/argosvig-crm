# Argosvig em produção

Configure o servidor para servir apenas `public/`. Configure HTTPS no domínio do CRM, redirecionamento de HTTP para HTTPS e `APP_URL` com o endereço final. Restrinja o `.env` e os diretórios de armazenamento ao usuário da aplicação. Em migração de uma instalação existente, preserve a `APP_KEY` pelo canal seguro usado para as credenciais.

Use `APP_ENV=production`, `APP_DEBUG=false` e `SESSION_SECURE_COOKIE=true`. Use `APP_TIMEZONE=America/Sao_Paulo`, `APP_LOCALE=pt_BR` e `APP_CURRENCY=BRL` para a instalação da Argosvig. Configure `LOG_CHANNEL=daily` e `LOG_LEVEL=warning` para rotação de logs. Configure o SMTP corporativo no servidor com `MAIL_REQUIRE_TLS=true`. Valide o envio com `php artisan crm:test-email caixa-de-teste@dominio-da-empresa` e confirme o recebimento na caixa autorizada.

Após aplicar as migrações e atualizar a configuração do ambiente, rode `php artisan crm:check-production`. Configure o monitor externo para consultar `/up` por HTTPS. A rota verifica que a aplicação responde. A verificação via CLI cobre o banco e os discos de arquivos. Configure o agendador do servidor para executar `php artisan schedule:run` a cada minuto e acompanhar falhas. Os lembretes de documentos rodam às 08h no fuso da aplicação.

Cada administrador pode ativar a verificação em duas etapas em **Minha conta**. O fluxo pede a senha atual, confirma um código do autenticador e exibe códigos de recuperação de uso único. Guarde os códigos em local seguro e valide um novo login antes de encerrar a sessão de configuração.

## Documentos na nuvem

Por padrão, contracheques, folhas de ponto e os demais documentos de RH ficam em `storage/app`, no próprio servidor. Para que continuem acessíveis e protegidos quando o servidor cair, grave-os num bucket privado compatível com S3, como AWS S3, Cloudflare R2 ou Backblaze B2.

1. Crie o bucket sem acesso público, com criptografia em repouso e, se o serviço oferecer, versionamento de objetos.
2. Crie uma chave de acesso restrita a esse bucket, com leitura, gravação e exclusão.
3. No `.env`, preencha `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION` e `AWS_BUCKET`. Para R2, B2 ou MinIO, preencha também `AWS_ENDPOINT` e, se o serviço pedir, `AWS_USE_PATH_STYLE_ENDPOINT=true`.
4. Defina `FILESYSTEM_DOCUMENTS_DISK=s3` e rode `php artisan config:cache` e `php artisan crm:check-production`. A verificação passa a gravar e ler um arquivo de teste no bucket.
5. Documentos novos já vão para o bucket. Os antigos continuam baixando do disco em que foram salvos. Para movê-los, veja a prévia com `php artisan hr:move-documents` e depois rode `php artisan hr:move-documents --apply`. Cada arquivo é copiado, conferido pelo tamanho e só então apagado do servidor.

O download continua passando pelo CRM, com permissão e registro no histórico. O bucket nunca deve ter link público.

## Backup e teste de recuperação

Prepare um arquivo de opções do cliente MySQL, fora do projeto, com permissões de leitura limitadas ao operador. Preencha host, usuário e senha na seção `[client]`. Mantenha um destino privado de backup com acesso restrito, criptografia em repouso e cópia fora do servidor. O diretório padrão dos arquivos do CRM é `storage/app`.

Durante a captura, suspenda as gravações da aplicação, de integrações e de filas, ou use snapshots coordenados de banco e arquivos oferecidos pela hospedagem. Programe a captura para uma janela de manutenção. O comando abaixo grava um pacote com o banco, os arquivos e os hashes para verificação. O processo que agenda o backup deve verificar o código de saída e alertar se uma execução falhar ou faltar.

```sh
bash bin/backup-crm.sh argosvig /caminho/privado/mysql-backup.cnf storage/app /caminho/privado/backups
```

Para testar, use um diretório privado e um banco vazio de homologação com credenciais próprias. Confirme o banco de destino antes de importar.

```sh
tar -xzf crm-AAAA.tar.gz
sha256sum -c SHA256SUMS
mysql --defaults-extra-file=/caminho/privado/mysql-homologacao.cnf argosvig_restore < database.sql
tar -xzf attachments.tar.gz -C /caminho/privado/instalacao-homologacao/storage/app
```

Aponte a instalação isolada para o banco restaurado, configure sua `APP_KEY` original pelo canal seguro e rode `php artisan crm:check-production` com a configuração de produção. Confira totais de tabelas, o acesso de um perfil autorizado e o download de um documento privado conhecido antes de considerar a recuperação validada. Acompanhe o tamanho do backup e o tempo de recuperação para dimensionar a retenção e a janela de manutenção.

## Migração do CRM antigo

Faça o primeiro ensaio em homologação. Solicite exportações com identificadores estáveis e inventário dos anexos. Confira como o sistema anterior vincula contatos, oportunidades e arquivos. Para empresas, prepare um CSV UTF-8 separado por ponto e vírgula com as colunas `external_id;name;existing_organization_id`. Mantenha zeros à esquerda nos identificadores. Preencha a terceira coluna apenas para vincular uma empresa já conferida no CRM.

```sh
php artisan crm:import-organizations /caminho/privado/empresas.csv --source=crm-antigo --owner=42
php artisan crm:import-organizations /caminho/privado/empresas.csv --source=crm-antigo --owner=42 --apply
```

O primeiro comando apresenta uma prévia. O segundo grava em uma transação e mantém o vínculo com o identificador de origem. Repita o mesmo identificador de origem em todos os lotes desse sistema. Cada lote aceita até 1.000 empresas. Confira possíveis duplicidades antes de aplicar. O relatório mostra os IDs que devem preencher `organization_id` na importação nativa de pessoas. Use a importação nativa de oportunidades após mapear os IDs dos contatos, responsáveis e etapas. Compare os totais e os vínculos em homologação. Para histórico e anexos, aguarde o inventário e valide a correspondência antes de copiar arquivos.
