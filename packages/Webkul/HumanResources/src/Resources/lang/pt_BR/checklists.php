<?php

return [
    'title' => 'Quadro de checklists',
    'hint' => 'Arraste os cartões entre as colunas. Cada cartão tem os itens a marcar, e fica registrado quem marcou e quando.',
    'recent_done' => 'As colunas de conclusão mostram os últimos :days dias. Use a busca para encontrar checklists mais antigos.',
    'show_all_done' => 'Ver todos os concluídos',
    'empty_column' => 'Nenhum cartão nesta coluna.',
    'no_items' => 'Nenhum item ainda.',
    'new_item' => 'Novo item do checklist',
    'add_item' => 'Adicionar',
    'remove_item' => 'Remover item',
    'details' => 'Detalhes',
    'save' => 'Salvar',
    'delete' => 'Excluir cartão',
    'delete_confirm' => 'Excluir este cartão e os itens dele? Essa ação não pode ser desfeita.',
    'completed_by' => 'Concluído em :date por :name',
    'none' => 'Nenhum',

    'filters' => [
        'search' => 'Buscar',
        'search_placeholder' => 'Título, item, colaborador ou posto',
        'all' => 'Todos',
        'apply' => 'Filtrar',
        'clear' => 'Limpar',
    ],

    'fields' => [
        'title' => 'Título',
        'column' => 'Coluna',
        'description' => 'Descrição',
        'employee' => 'Colaborador',
        'work_site' => 'Posto',
        'assignee' => 'Responsável',
        'due_date' => 'Prazo',
        'items' => 'Itens do checklist',
    ],

    'create' => [
        'title' => 'Novo cartão',
        'items_placeholder' => 'Um item por linha',
        'save' => 'Criar cartão',
    ],

    'messages' => [
        'created' => 'Cartão criado.',
        'updated' => 'Cartão atualizado.',
        'deleted' => 'Cartão excluído.',
        'moved' => 'Quadro atualizado.',
        'error' => 'Não foi possível salvar. Tente de novo.',
    ],

    'defaults' => [
        'todo' => 'A fazer',
        'doing' => 'Em andamento',
        'done' => 'Concluído',
    ],

    'colors' => [
        'gray' => 'Cinza',
        'blue' => 'Azul',
        'yellow' => 'Amarelo',
        'orange' => 'Laranja',
        'green' => 'Verde',
        'red' => 'Vermelho',
        'purple' => 'Roxo',
    ],

    'settings' => [
        'group' => 'Operação',
        'group_info' => 'Configure o quadro de checklists da operação.',
        'title' => 'Quadro de checklists',
        'info' => 'Adicione, renomeie, reordene e remova as colunas do quadro.',
        'open_board' => 'Abrir o quadro',
        'save' => 'Salvar colunas',
        'hint' => 'Todas as colunas podem ser renomeadas, reordenadas e removidas; arraste pelo ícone para mudar a ordem. Ligue "Concluir cartões" na coluna que encerra o checklist: o cartão que entra nela fica concluído e vai para o histórico. Ao remover uma coluna, os cartões dela vão para a primeira coluna que não conclui cartões.',
        'new_column' => 'Nova coluna',
        'name' => 'Nome',
        'color' => 'Cor',
        'completes' => 'Concluir cartões',
        'completes_hint' => 'O cartão que entra aqui fica concluído.',
        'cards_count' => 'Cartões nesta coluna: :count',
        'remove' => 'Remover coluna',
        'last_column' => 'O quadro precisa de pelo menos uma coluna',
        'add_title' => 'Adicionar coluna',
        'add_info' => 'Crie uma etapa nova para o quadro.',
        'add' => 'Adicionar coluna',
        'duplicate' => 'Já existe uma coluna com este nome.',
        'remove_confirm' => 'Remover esta coluna? A mudança vale depois de salvar.',
        'remove_confirm_cards' => 'Remover esta coluna? Os cartões dela vão para a primeira coluna que não conclui cartões quando você salvar.',
        'saved' => 'Colunas do quadro salvas.',
        'unknown_column' => 'Uma das colunas não existe mais. Recarregue a página e tente de novo.',
    ],
];
