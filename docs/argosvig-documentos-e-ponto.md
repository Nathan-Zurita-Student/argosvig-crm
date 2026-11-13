# Documentos e ponto

## Controle de acesso

Os documentos de RH, documentos compartilhados e marcações de ponto são recursos globais, sem acesso por grupos ou individual. Para delegar, crie um papel com escopo de visualização global e selecione apenas as permissões de RH pertinentes. O perfil do colaborador exige a permissão **Gerenciar documentos** para visualizar anexos, baixar, enviar ou excluir arquivos. Na central, combine **Central de documentos** com a permissão da categoria e, se necessário, **Gerenciar arquivos**. Arquivos ficam no disco privado. O histórico registra envio, edição, troca de arquivo, download e exclusão.

Documento Solidário foi tratado como arquivo interno reutilizável entre empresas e contratos. Confirme o significado e o fluxo com a Argosvig antes de usar em produção. As associações organizam registros dentro da central e não concedem acesso a clientes ou terceiros.

## Controle de ponto

O CRM permite consultar e exportar as marcações por colaborador e período. A apuração e o espelho oficial continuam na Alterdata.

1. Cadastre no colaborador o identificador retornado pela Alterdata. Preserve zeros à esquerda.
2. Exporte as marcações no produto contratado. Converta as colunas usando o modelo CSV da tela Folha de ponto, mantendo UTF-8 e uma marcação por linha.
3. Use `employee_code;occurred_at;external_id`. Exemplo sintético: `00042;2026-11-13 08:00:00;A123`. A terceira coluna é opcional. Datas sem offset usam o fuso configurado no CRM; datas ISO 8601 com offset são aceitas.
4. Importe até 10 mil marcações por lote. Registros inválidos, códigos sem vínculo ou conflitos de ID bloqueiam o lote. Reenvios do mesmo horário para a mesma pessoa são ignorados.

A exportação pública descrita pela Alterdata é uma fonte possível para conversão. O modelo CSV do CRM não pressupõe o cabeçalho nativo do fornecedor.

## Conectar a API

O pacote disponibiliza o contrato `Webkul\HumanResources\Contracts\TimekeepingProvider`. O adaptador concreto depende da documentação, de acesso de homologação e da versão contratada.

Solicite à Alterdata o produto/versão habilitado, URL e método de autenticação, escopo de acesso de leitura, endpoint de marcações, filtros de período, chaves da empresa/funcionário/marcação, fuso, paginação, limites e comportamento para ajustes, cancelamentos e marcações tratadas. Use uma amostra de resposta anonimizada. A API ePlugin de eContador e a integração Flit não devem ser presumidas como a API de marcações deste produto.

Com o contrato confirmado, vincule a implementação na configuração do Laravel. O provedor retorna `employee_code`, `occurred_at` e, quando disponível, um `external_id` estável por marcação, qualificado pela empresa quando necessário. Implemente paginação e normalize os identificadores no adaptador. Mantenha o segredo na configuração privada do ambiente. O comando `php artisan hr:sync-alterdata-timekeeping YYYY-MM-DD YYYY-MM-DD` reutiliza o mesmo importador, falha quando o adaptador está ausente e deve ser agendado apenas após validação em homologação. A importação atual é de marcações e rejeita mudanças de horário para um ID já gravado. Defina o tratamento de correções após receber as regras do fornecedor.
