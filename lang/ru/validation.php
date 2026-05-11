<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines (RU)
    |--------------------------------------------------------------------------
    |
    | Russian translations for Laravel validator default messages. With
    | APP_LOCALE=ru this file is consulted before falling back to en —
    | without it the user sees raw keys like "validation.exists" in the
    | mobile error toast. Per-rule custom copy on a FormRequest still
    | wins over these.
    |
    */

    'accepted' => 'Поле :attribute должно быть принято.',
    'accepted_if' => 'Поле :attribute должно быть принято, когда :other равно :value.',
    'active_url' => 'Поле :attribute должно быть валидным URL.',
    'after' => 'Поле :attribute должно содержать дату позднее :date.',
    'after_or_equal' => 'Поле :attribute должно содержать дату не ранее :date.',
    'alpha' => 'Поле :attribute должно содержать только буквы.',
    'alpha_dash' => 'Поле :attribute должно содержать только буквы, цифры, дефисы и подчёркивания.',
    'alpha_num' => 'Поле :attribute должно содержать только буквы и цифры.',
    'array' => 'Поле :attribute должно быть массивом.',
    'before' => 'Поле :attribute должно содержать дату ранее :date.',
    'before_or_equal' => 'Поле :attribute должно содержать дату не позднее :date.',
    'between' => [
        'array' => 'Поле :attribute должно содержать от :min до :max элементов.',
        'file' => 'Размер файла :attribute должен быть от :min до :max килобайт.',
        'numeric' => 'Поле :attribute должно быть от :min до :max.',
        'string' => 'Длина :attribute должна быть от :min до :max символов.',
    ],
    'boolean' => 'Поле :attribute должно быть истиной или ложью.',
    'confirmed' => 'Подтверждение поля :attribute не совпадает.',
    'date' => 'Поле :attribute должно содержать корректную дату.',
    'date_equals' => 'Поле :attribute должно содержать дату, равную :date.',
    'date_format' => 'Поле :attribute должно соответствовать формату :format.',
    'different' => 'Поля :attribute и :other должны различаться.',
    'digits' => 'Поле :attribute должно содержать :digits цифр(ы).',
    'digits_between' => 'Поле :attribute должно содержать от :min до :max цифр.',
    'dimensions' => 'Поле :attribute имеет недопустимые размеры изображения.',
    'distinct' => 'Поле :attribute содержит дублирующееся значение.',
    'email' => 'Поле :attribute должно содержать корректный email.',
    'ends_with' => 'Поле :attribute должно заканчиваться одним из значений: :values.',
    'exists' => 'Выбранное значение для :attribute некорректно.',
    'file' => 'Поле :attribute должно быть файлом.',
    'filled' => 'Поле :attribute обязательно для заполнения.',
    'gt' => [
        'array' => 'Поле :attribute должно содержать более :value элементов.',
        'file' => 'Размер файла :attribute должен быть больше :value килобайт.',
        'numeric' => 'Поле :attribute должно быть больше :value.',
        'string' => 'Длина :attribute должна быть больше :value символов.',
    ],
    'gte' => [
        'array' => 'Поле :attribute должно содержать :value или более элементов.',
        'file' => 'Размер файла :attribute должен быть не менее :value килобайт.',
        'numeric' => 'Поле :attribute должно быть не менее :value.',
        'string' => 'Длина :attribute должна быть не менее :value символов.',
    ],
    'image' => 'Поле :attribute должно быть изображением.',
    'in' => 'Выбранное значение для :attribute некорректно.',
    'in_array' => 'Поле :attribute не существует в :other.',
    'integer' => 'Поле :attribute должно быть целым числом.',
    'ip' => 'Поле :attribute должно быть валидным IP-адресом.',
    'ipv4' => 'Поле :attribute должно быть валидным IPv4-адресом.',
    'ipv6' => 'Поле :attribute должно быть валидным IPv6-адресом.',
    'json' => 'Поле :attribute должно быть корректной JSON-строкой.',
    'lt' => [
        'array' => 'Поле :attribute должно содержать менее :value элементов.',
        'file' => 'Размер файла :attribute должен быть меньше :value килобайт.',
        'numeric' => 'Поле :attribute должно быть меньше :value.',
        'string' => 'Длина :attribute должна быть меньше :value символов.',
    ],
    'lte' => [
        'array' => 'Поле :attribute должно содержать не более :value элементов.',
        'file' => 'Размер файла :attribute должен быть не более :value килобайт.',
        'numeric' => 'Поле :attribute должно быть не более :value.',
        'string' => 'Длина :attribute должна быть не более :value символов.',
    ],
    'max' => [
        'array' => 'Поле :attribute должно содержать не более :max элементов.',
        'file' => 'Размер файла :attribute не должен превышать :max килобайт.',
        'numeric' => 'Поле :attribute должно быть не более :max.',
        'string' => 'Длина :attribute не должна превышать :max символов.',
    ],
    'mimes' => 'Поле :attribute должно быть файлом одного из типов: :values.',
    'mimetypes' => 'Поле :attribute должно быть файлом одного из типов: :values.',
    'min' => [
        'array' => 'Поле :attribute должно содержать не менее :min элементов.',
        'file' => 'Размер файла :attribute должен быть не менее :min килобайт.',
        'numeric' => 'Поле :attribute должно быть не менее :min.',
        'string' => 'Длина :attribute должна быть не менее :min символов.',
    ],
    'not_in' => 'Выбранное значение для :attribute некорректно.',
    'not_regex' => 'Поле :attribute имеет недопустимый формат.',
    'numeric' => 'Поле :attribute должно быть числом.',
    'present' => 'Поле :attribute должно присутствовать.',
    'regex' => 'Поле :attribute имеет недопустимый формат.',
    'required' => 'Поле :attribute обязательно для заполнения.',
    'required_if' => 'Поле :attribute обязательно, когда :other равно :value.',
    'required_unless' => 'Поле :attribute обязательно, если :other не равно одному из :values.',
    'required_with' => 'Поле :attribute обязательно, когда указано :values.',
    'required_with_all' => 'Поле :attribute обязательно, когда указаны :values.',
    'required_without' => 'Поле :attribute обязательно, когда не указано :values.',
    'required_without_all' => 'Поле :attribute обязательно, когда не указаны :values.',
    'same' => 'Поля :attribute и :other должны совпадать.',
    'size' => [
        'array' => 'Поле :attribute должно содержать :size элементов.',
        'file' => 'Размер файла :attribute должен быть :size килобайт.',
        'numeric' => 'Поле :attribute должно быть равно :size.',
        'string' => 'Длина :attribute должна быть :size символов.',
    ],
    'starts_with' => 'Поле :attribute должно начинаться с одного из значений: :values.',
    'string' => 'Поле :attribute должно быть строкой.',
    'timezone' => 'Поле :attribute должно быть валидным часовым поясом.',
    'unique' => 'Такое значение поля :attribute уже существует.',
    'uploaded' => 'Не удалось загрузить :attribute.',
    'url' => 'Поле :attribute должно содержать валидный URL.',
    'uuid' => 'Поле :attribute должно быть корректным UUID.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    */

    'attributes' => [],

];
