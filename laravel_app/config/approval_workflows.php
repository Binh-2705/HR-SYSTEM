<?php

return [
    'leave_requests' => [
        // Number of unique approvers required before a leave request is finalized.
        'required_approvals' => (int) env('LEAVE_REQUIRED_APPROVALS', 2),

        // Role names are normalized before comparison (Admin -> admin, QuanLy -> quanly).
        'allowed_roles' => ['admin', 'quanly', 'hr'],

        // Annual leave balance configuration.
        'leave_balance' => [
            // Default annual entitlement if employee/year does not have a custom setup yet.
            'default_entitled_days' => (int) env('LEAVE_DEFAULT_ENTITLED_DAYS', 12),

            // Leave types that consume annual leave balance.
            'deductible_types' => ['Nghỉ phép năm'],
        ],
    ],
];
