<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation messages (Italian)
    |--------------------------------------------------------------------------
    |
    | Messages for the rules used by the application. Rules not listed here
    | fall back to the English defaults of the framework.
    |
    */

    'accepted' => 'Il campo :attribute deve essere accettato.',
    'after' => 'Il campo :attribute deve essere una data successiva a :date.',
    'after_or_equal' => 'Il campo :attribute deve essere una data uguale o successiva a :date.',
    'array' => 'Il campo :attribute deve essere un elenco.',
    'before' => 'Il campo :attribute deve essere una data precedente a :date.',
    'before_or_equal' => 'Il campo :attribute deve essere una data uguale o precedente a :date.',
    'between' => [
        'array' => 'Il campo :attribute deve avere tra :min e :max elementi.',
        'file' => 'Il campo :attribute deve essere tra :min e :max kilobyte.',
        'numeric' => 'Il campo :attribute deve essere compreso tra :min e :max.',
        'string' => 'Il campo :attribute deve avere tra :min e :max caratteri.',
    ],
    'boolean' => 'Il campo :attribute deve essere vero o falso.',
    'date' => 'Il campo :attribute deve essere una data valida.',
    'date_format' => 'Il campo :attribute deve essere nel formato :format.',
    'distinct' => 'Il campo :attribute contiene un valore duplicato.',
    'email' => 'Il campo :attribute deve essere un indirizzo email valido.',
    'enum' => 'Il valore selezionato per :attribute non è valido.',
    'exists' => 'Il valore selezionato per :attribute non è valido.',
    'gt' => [
        'numeric' => 'Il campo :attribute deve essere maggiore di :value.',
    ],
    'gte' => [
        'numeric' => 'Il campo :attribute deve essere maggiore o uguale a :value.',
    ],
    'in' => 'Il valore selezionato per :attribute non è valido.',
    'integer' => 'Il campo :attribute deve essere un numero intero.',
    'lt' => [
        'numeric' => 'Il campo :attribute deve essere minore di :value.',
    ],
    'lte' => [
        'numeric' => 'Il campo :attribute deve essere minore o uguale a :value.',
    ],
    'max' => [
        'array' => 'Il campo :attribute non può avere più di :max elementi.',
        'file' => 'Il campo :attribute non può superare :max kilobyte.',
        'numeric' => 'Il campo :attribute non può essere maggiore di :max.',
        'string' => 'Il campo :attribute non può superare :max caratteri.',
    ],
    'min' => [
        'array' => 'Il campo :attribute deve avere almeno :min elementi.',
        'file' => 'Il campo :attribute deve essere almeno di :min kilobyte.',
        'numeric' => 'Il campo :attribute deve essere almeno :min.',
        'string' => 'Il campo :attribute deve avere almeno :min caratteri.',
    ],
    'numeric' => 'Il campo :attribute deve essere un numero.',
    'prohibited' => 'Il campo :attribute non è consentito.',
    'prohibited_if' => 'Il campo :attribute non è consentito quando :other è :value.',
    'regex' => 'Il formato del campo :attribute non è valido.',
    'required' => 'Il campo :attribute è obbligatorio.',
    'required_if' => 'Il campo :attribute è obbligatorio quando :other è :value.',
    'string' => 'Il campo :attribute deve essere un testo.',
    'unique' => 'Il valore del campo :attribute è già in uso.',
    'uuid' => 'Il campo :attribute deve essere un UUID valido.',

    'custom' => [],

    'attributes' => [],

];
