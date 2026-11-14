# Argosvig CRM

Sistema interno de gestão comercial e operacional. Inclui oportunidades, cotações, contatos, contratos, colaboradores, escalas, ocorrências, documentos e controle de ponto.

## Instalação local

Use PHP 8.3 ou superior, Composer e um banco MySQL ou MariaDB.

1. Execute `composer install`.
2. Copie `.env.example` para `.env` e configure a URL, o banco de dados e o e-mail.
3. Execute `php artisan key:generate`.
4. Para um banco novo, execute `php artisan argosvig:install` e siga as instruções para criar a conta de administrador. Para atualizar uma instalação existente, use `php artisan migrate --force`.
5. Aponte o servidor web para a pasta `public`. Para desenvolvimento, também é possível iniciar com `php artisan serve`.

Consulte o [guia de produção](docs/argosvig-producao.md) e o [guia de documentos e ponto](docs/argosvig-documentos-e-ponto.md).

## Licença

O projeto é distribuído sob a [licença MIT](LICENSE), com o aviso de copyright original.
