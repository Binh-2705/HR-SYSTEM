<?php

return [
    'services' => [
        'hr' => [
            'connection' => env('HR_SERVICE_CONNECTION', 'hr_service'),
            'resources' => [
                'employees' => [
                    'table' => 'nhanvien',
                    'primary_key' => 'MaNV',
                ],
                'departments' => [
                    'table' => 'phongban',
                    'primary_key' => 'MaPB',
                    'read_only' => true,
                ],
                'accounts' => [
                    'table' => 'taikhoan',
                    'primary_key' => 'MaTK',
                ],
                'employee-profiles' => [
                    'table' => 'hosonhanvien',
                    'primary_key' => 'MaHoSo',
                ],
                'positions' => [
                    'table' => 'chucvu',
                    'primary_key' => 'MaCV',
                    'read_only' => true,
                ],
                'assignments' => [
                    'table' => 'phancong',
                    'primary_key' => 'MaQT',
                ],
                'insurances' => [
                    'table' => 'baohiem',
                    'primary_key' => 'MaBH',
                ],
                'leave-requests' => [
                    'table' => 'nghiphep',
                    'primary_key' => 'MaNP',
                ],
                'leave-balances' => [
                    'table' => 'leave_balances',
                    'primary_key' => 'id',
                ],
                'reward-discipline-types' => [
                    'table' => 'loaikhenthuongkyluat',
                    'primary_key' => 'MaLoai',
                ],
                'reward-discipline-records' => [
                    'table' => 'khenthuongkyluat',
                    'primary_key' => 'MaKTKL',
                ],
                'roles' => [
                    'table' => 'vaitro',
                    'primary_key' => 'MaVaiTro',
                ],
                'features' => [
                    'table' => 'chucnang',
                    'primary_key' => 'MaCN',
                ],
                'role-permissions' => [
                    'table' => 'phanquyen',
                    'primary_key' => ['MaVaiTro', 'MaCN'],
                ],
                'account-roles' => [
                    'table' => 'taikhoanvaitro',
                    'primary_key' => ['MaTK', 'MaVaiTro'],
                ],
                'notification-flags' => [
                    'table' => 'thongbao_daxem',
                    'primary_key' => 'MaTK',
                ],
                'session-audits' => [
                    'table' => 'session_audit',
                    'primary_key' => 'id',
                ],
                'password-reset-tokens' => [
                    'table' => 'password_reset_tokens',
                    'primary_key' => 'id',
                ],
            ],
        ],
        'payroll' => [
            'connection' => env('PAYROLL_SERVICE_CONNECTION', 'payroll_service'),
            'resources' => [
                'payrolls' => [
                    'table' => 'bangluong',
                    'primary_key' => 'MaBL',
                ],
                'salary-grades' => [
                    'table' => 'bacluong',
                    'primary_key' => 'MaBac',
                    'read_only' => true,
                ],
                'salary-bands' => [
                    'table' => 'ngachluong',
                    'primary_key' => 'MaNgach',
                    'read_only' => true,
                ],
                'contracts' => [
                    'table' => 'hopdong',
                    'primary_key' => 'MaHopDong',
                ],
                'salary-history' => [
                    'table' => 'lichsu_luong',
                    'primary_key' => 'MaLichSu',
                ],
                'contract-salary-history' => [
                    'table' => 'lichsu_luong_hopdong',
                    'primary_key' => 'MaLS',
                ],
            ],
        ],
        'attendance' => [
            'connection' => env('ATTENDANCE_SERVICE_CONNECTION', 'attendance_service'),
            'resources' => [
                'attendance-records' => [
                    'table' => 'chamcong',
                    'primary_key' => 'MaCC',
                ],
                'attendance-configs' => [
                    'table' => 'cauhinh_chamcong',
                    'primary_key' => 'ID',
                ],
                'attendance-summaries' => [
                    'table' => 'v_tonghopcong',
                    'primary_key' => ['MaNV', 'Thang', 'Nam'],
                    'read_only' => true,
                ],
            ],
        ],
        'recruitment' => [
            'connection' => env('RECRUITMENT_SERVICE_CONNECTION', 'recruitment_service'),
            'resources' => [
                'candidates' => [
                    'table' => 'ungvien',
                    'primary_key' => 'MaUV',
                ],
                'recruitment-campaigns' => [
                    'table' => 'dottuyendung',
                    'primary_key' => 'MaDTD',
                ],
                'applications' => [
                    'table' => 'hosoungtuyen',
                    'primary_key' => 'MaHS',
                ],
                'interviews' => [
                    'table' => 'lichphongvan',
                    'primary_key' => 'MaPV',
                ],
                'interview-reviews' => [
                    'table' => 'danhgiaphongvan',
                    'primary_key' => 'MaDG',
                ],
            ],
        ],
        'training' => [
            'connection' => env('TRAINING_SERVICE_CONNECTION', 'training_service'),
            'resources' => [
                'courses' => [
                    'table' => 'khoadaotao',
                    'primary_key' => 'MaKDT',
                ],
                'participants' => [
                    'table' => 'thamgiadaotao',
                    'primary_key' => 'MaTGDT',
                ],
            ],
        ],
        'reporting' => [
            'connection' => env('REPORTING_SERVICE_CONNECTION', 'reporting_service'),
            'resources' => [
                'reports' => [
                    'table' => 'baocao',
                    'primary_key' => 'MaBC',
                ],
            ],
        ],
        'chatbot' => [
            'connection' => env('CHATBOT_SERVICE_CONNECTION', 'chatbot_service'),
            'resources' => [
                'sessions' => [
                    'table' => 'chatbot_sessions',
                    'primary_key' => 'id',
                ],
                'action-drafts' => [
                    'table' => 'chatbot_action_drafts',
                    'primary_key' => 'id',
                ],
                'messages' => [
                    'table' => 'chatbot_messages',
                    'primary_key' => 'id',
                ],
            ],
        ],
    ],
];