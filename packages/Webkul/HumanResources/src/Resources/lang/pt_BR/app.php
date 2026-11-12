<?php

return [
    'menu' => [
        'title' => 'Pessoas',
        'employees' => 'Colaboradores',
        'departments' => 'Departamentos',
    ],
    'acl' => [
        'title' => 'Pessoas',
        'employees' => 'Colaboradores',
        'departments' => 'Departamentos',
        'create' => 'Criar',
        'edit' => 'Editar',
        'delete' => 'Excluir',
    ],
    'common' => [
        'active' => 'Ativo',
        'cancel' => 'Cancelar',
        'delete' => 'Excluir',
        'edit' => 'Editar',
        'inactive' => 'Inativo',
        'save' => 'Salvar',
        'select' => 'Selecione',
        'status' => 'Situação',
    ],
    'departments' => [
        'index' => [
            'title' => 'Departamentos',
            'description' => 'Organize os colaboradores por área operacional.',
            'create' => 'Novo departamento',
        ],
        'create' => ['title' => 'Novo departamento'],
        'edit' => ['title' => 'Editar departamento'],
        'fields' => [
            'name' => 'Nome',
            'code' => 'Código',
            'description' => 'Descrição',
            'employees_count' => 'Colaboradores',
            'is_active' => 'Departamento ativo',
        ],
        'messages' => [
            'created' => 'Departamento criado com sucesso.',
            'updated' => 'Departamento atualizado com sucesso.',
            'deleted' => 'Departamento excluído com sucesso.',
            'in-use' => 'Este departamento não pode ser excluído enquanto possuir colaboradores.',
        ],
    ],
    'employees' => [
        'index' => [
            'title' => 'Colaboradores',
            'description' => 'Gerencie o quadro de colaboradores e os identificadores de integração da Argosvig.',
            'create' => 'Novo colaborador',
        ],
        'create' => ['title' => 'Novo colaborador'],
        'edit' => ['title' => 'Editar colaborador'],
        'fields' => [
            'employee_number' => 'Matrícula',
            'full_name' => 'Nome completo',
            'cpf' => 'CPF',
            'email' => 'E-mail',
            'phone' => 'Telefone',
            'job_title' => 'Cargo',
            'hire_date' => 'Data de admissão',
            'department' => 'Departamento',
            'alterdata_employee_id' => 'ID no Alterdata',
        ],
        'statuses' => [
            'active' => 'Ativo',
            'on_leave' => 'Afastado',
            'inactive' => 'Inativo',
        ],
        'messages' => [
            'created' => 'Colaborador criado com sucesso.',
            'updated' => 'Colaborador atualizado com sucesso.',
            'deleted' => 'Colaborador excluído com sucesso.',
        ],
    ],
];
