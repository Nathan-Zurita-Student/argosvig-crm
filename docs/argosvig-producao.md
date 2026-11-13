# Argosvig em produção

Configure o servidor para servir apenas `public/`. Configure HTTPS no domínio do CRM, redirecionamento de HTTP para HTTPS e `APP_URL` com o endereço final. Restrinja o `.env` e os diretórios de armazenamento ao usuário da aplicação. Em migração de uma instalação existente, preserve a `APP_KEY` pelo canal seguro usado para as credenciais.

Use `APP_ENV=production`, `APP_DEBUG=false` e `SESSION_SECURE_COOKIE=true`. Use `APP_TIMEZONE=America/Sao_Paulo`, `APP_LOCALE=pt_BR` e `APP_CURRENCY=BRL` para a instalação da Argosvig. Configure `LOG_CHANNEL=daily` e `LOG_LEVEL=warning` para rotação de logs. Configure o SMTP corporativo no servidor e valide o envio a uma caixa de teste antes da liberação.

Após aplicar as migrações e atualizar a configuração do ambiente, rode `php artisan crm:check-production`. Configure o monitor externo para consultar `/up` por HTTPS. A rota verifica que a aplicação responde. A verificação via CLI cobre o banco e os discos de arquivos. Configure o agendador do servidor para executar `php artisan schedule:run` a cada minuto e acompanhar falhas. Os lembretes de documentos rodam às 08h no fuso da aplicação.

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
