<?php

// Mensagens de validação em português, só para as regras usadas no projeto.
// Regras que não estiverem aqui usam a mensagem em inglês (APP_FALLBACK_LOCALE=en).

return [

    'after_or_equal' => 'O campo :attribute precisa ser uma data igual ou posterior a :date.',
    'alpha_dash' => 'O campo :attribute só pode ter letras, números, hífens e sublinhados.',
    'array' => 'O campo :attribute precisa ser uma lista.',
    'before_or_equal' => 'O campo :attribute precisa ser uma data igual ou anterior a :date.',
    'date' => 'O campo :attribute precisa ser uma data válida.',
    'date_format' => 'O campo :attribute precisa estar no formato :format.',
    'decimal' => 'O campo :attribute precisa ter :decimal casas decimais.',
    'distinct' => 'O campo :attribute tem um valor repetido.',
    'email' => 'O campo :attribute precisa ser um e-mail válido.',
    'exists' => 'O valor informado em :attribute é inválido.',
    'file' => 'O campo :attribute precisa ser um arquivo.',
    'gt' => [
        'numeric' => 'O campo :attribute precisa ser maior que :value.',
    ],
    'in' => 'O valor informado em :attribute é inválido.',
    'integer' => 'O campo :attribute precisa ser um número inteiro.',
    'max' => [
        'array' => 'O campo :attribute não pode ter mais de :max itens.',
        'file' => 'O campo :attribute não pode ter mais de :max kilobytes.',
        'numeric' => 'O campo :attribute não pode ser maior que :max.',
        'string' => 'O campo :attribute não pode ter mais de :max caracteres.',
    ],
    'mimes' => 'O campo :attribute precisa ser um arquivo do tipo: :values.',
    'min' => [
        'array' => 'O campo :attribute precisa ter pelo menos :min item(ns).',
        'file' => 'O campo :attribute precisa ter pelo menos :min kilobytes.',
        'numeric' => 'O campo :attribute precisa ser pelo menos :min.',
        'string' => 'O campo :attribute precisa ter pelo menos :min caracteres.',
    ],
    'required' => 'O campo :attribute é obrigatório.',
    'string' => 'O campo :attribute precisa ser um texto.',
    'unique' => 'Já existe um registro com este :attribute.',

    // Nomes dos campos como aparecem nas mensagens
    'attributes' => [
        'allocations' => 'rateio',
        'allocations.*.percentage' => 'percentual',
        'allocations.*.unit_id' => 'unidade',
        'amount' => 'valor',
        'code' => 'código',
        'company_id' => 'empresa',
        'currency' => 'moeda',
        'description' => 'descrição',
        'email' => 'e-mail',
        'expense_date' => 'data',
        'file' => 'arquivo',
        'from' => 'data inicial',
        'name' => 'nome',
        'password' => 'senha',
        'supplier' => 'fornecedor',
        'to' => 'data final',
    ],

];
