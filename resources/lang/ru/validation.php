<?php

return [
    'required' => 'Поле :attribute обязательно для заполнения.',
    'email' => ':attribute должен быть действительным адресом эл. почты.',
    'string' => ':attribute должен быть строкой.',
    'max' => [
        'string' => ':attribute не может быть длиннее :max символов.',
        'numeric' => ':attribute не может быть больше :max.',
    ],
    'min' => [
        'string' => ':attribute должен быть не менее :min символов.',
        'numeric' => ':attribute должен быть не менее :min.',
    ],
    'unique' => ':attribute уже используется.',
    'exists' => 'Выбранное значение :attribute недействительно.',
    'numeric' => ':attribute должен быть числом.',
    'integer' => ':attribute должен быть целым числом.',
    'boolean' => 'Поле :attribute должно быть true или false.',
    'in' => 'Выбранное значение :attribute недействительно.',
    'array' => ':attribute должен быть массивом.',
    'url' => ':attribute должен быть действительным URL.',
    'ip' => ':attribute должен быть действительным IP-адресом.',
    'nullable' => 'Поле :attribute может быть пустым.',
    'present' => 'Поле :attribute должно присутствовать.',
    'confirmed' => 'Подтверждение :attribute не совпадает.',
];
