<?php

return [
    'entities' => [
        'expert' => [
            'expertise_field' => [
                'Select',
                'fields' => [
                    'accounting',
                    'engineering',
                    'banking',
                    'architecture',
                    'mechanical_engineering',
                    'electromechanical_engineering',
                    'arbitrary',
                    'information_technology',
                    'agriculture',
                    'civil_engineering',
                ]
            ]
        ],
        'court',
        'broker' => [
            'bank_name' => 'TextInput',
            'bank_iban' => 'TextInput',
            'commission_percentage' => 'TextInput',
        ],
        'party',
        'legal_representative' => [
            'type' => 'Select',
        ],
    ]
];
