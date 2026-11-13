<?php

return [
    'title' => 'Acompanhamento comercial',
    'tabs' => ['leads' => 'Oportunidades sem próxima ação', 'activities' => 'Atividades atrasadas', 'quotes' => 'Cotações sem resposta registrada'],
    'record' => 'Registro',
    'owner' => 'Responsável',
    'details' => ['leads' => 'Etapa', 'activities' => 'Prazo', 'quotes' => 'Envio registrado em'],
    'empty' => 'Nenhum registro para esse filtro.',
    'quote' => [
        'title' => 'Envios e respostas da cotação',
        'description' => 'Depois de enviar a cotação, registre o envio para guardar uma cópia dos dados e acompanhar a resposta do cliente.',
        'edit' => 'Editar cotação',
        'sent' => 'Registrar envio da versão atual',
        'accepted' => 'Registrar aceite',
        'declined' => 'Registrar recusa',
        'statuses' => ['sent' => 'Aguardando resposta', 'accepted' => 'Aceita', 'declined' => 'Recusada', 'superseded' => 'Substituída'],
        'version' => 'Versão :number',
        'changed' => 'O acompanhamento mudou. Confira a versão atual antes de tentar novamente.',
        'no_versions' => 'Nenhum envio registrado.',
        'open_contract' => 'Abrir contrato vinculado',
        'create_contract' => 'Criar rascunho de contrato',
        'contract_source' => 'Rascunho a partir da cotação :subject (versão :number)',
        'contract_exists' => 'Já existe um contrato vinculado a esta versão.',
        'organization_mismatch' => 'Selecione a empresa vinculada à versão aceita.',
    ],
];
