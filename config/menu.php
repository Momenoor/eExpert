<?php

return [
    'main' => [
        [
            'title' => 'matters',
            'class' => '',
            'permission' => [
                'matter-view',
                'matter-only-own-view',
                'matter-create',
            ],
            'submenu' => [
                [
                    'title' => 'matters_list',
                    'link' => 'matter.index',
                    'class' => '',
                    'permission' => [
                        'matter-view',
                        'matter-only-own-view'
                    ],
                ],
                [
                    'title' => 'create_matter',
                    'link' => 'matter.create',
                    'class' => '',
                    'permission' => [
                        'matter-create',
                    ],
                ],
            ]
        ],
        [
            'title' => 'matter_types',
            'link' => 'type.index',
            'permission' => [
                'type-view'
            ],
        ],
        [
            'title' => 'experts',
            'permission' => ['expert-view', 'expert-create'],
            'submenu' => [
                [
                    'title' => 'experts-list',
                    'link' => 'expert.index',
                    'permission' => 'expert-view',
                ],
                [
                    'title' => 'create-expert',
                    'link' => 'expert.create',
                    'permission' => 'expert-create',
                ],

            ]
        ],
        [
            'title' => 'courts',
            'permission' => ['court-view', 'court-create'],
            'submenu' => [
                [
                    'title' => 'courts-list',
                    'link' => 'court.index',
                    'permission' => 'court-view',
                ],
                [
                    'title' => 'create-court',
                    'link' => 'court.create',
                    'permission' => 'court-create',
                ],

            ]
        ],
        [
            'title' => 'users',
            'permission' => [
                'user-view',
                'user-create',
                'permission-view',
                'role-view',
            ],
            'submenu' => [
                [
                    'title' => 'users_list',
                    'link' => 'user.index',
                    'permission' => [
                        'user-view',
                    ],
                ],
                [
                    'title' => 'create_user',
                    'link' => 'user.create',
                    'permission' => [
                        'user-create',
                    ],
                ],
                [
                    'title' => 'user_permissions',
                    'link' => 'permission.index',
                    'permission' => [
                        'permission-view',
                    ],
                ],
                [
                    'title' => 'user_roles',
                    'link' => 'role.index',
                    'permission' => [
                        'role-view',
                    ],
                ],
            ]
        ],
        [
            'title' => 'matter-distributing',
            'link' => 'matter.distributing',
        ]
    ]
];
