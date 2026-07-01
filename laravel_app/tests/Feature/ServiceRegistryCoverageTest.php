<?php

namespace Tests\Feature;

use Tests\TestCase;

class ServiceRegistryCoverageTest extends TestCase
{
    public function test_registry_covers_expected_schema_tables_and_views(): void
    {
        $services = config('service_registry.services', []);
        $mappedTables = [];

        foreach ($services as $service) {
            foreach (($service['resources'] ?? []) as $resource) {
                $mappedTables[] = $resource['table'] ?? null;
            }
        }

        $mappedTables = array_values(array_unique(array_filter($mappedTables)));

        $expectedTables = [
            'bacluong',
            'bangluong',
            'baocao',
            'baohiem',
            'cauhinh_chamcong',
            'chamcong',
            'chucnang',
            'chucvu',
            'danhgiaphongvan',
            'dottuyendung',
            'hopdong',
            'hosonhanvien',
            'hosoungtuyen',
            'khenthuongkyluat',
            'khoadaotao',
            'lichphongvan',
            'lichsu_luong',
            'lichsu_luong_hopdong',
            'loaikhenthuongkyluat',
            'ngachluong',
            'nghiphep',
            'nhanvien',
            'phancong',
            'phanquyen',
            'phongban',
            'taikhoan',
            'taikhoanvaitro',
            'thongbao_daxem',
            'password_reset_tokens',
            'session_audit',
            'thamgiadaotao',
            'ungvien',
            'vaitro',
            'v_tonghopcong',
            'chatbot_sessions',
            'chatbot_action_drafts',
            'chatbot_messages',
        ];

        sort($mappedTables);
        sort($expectedTables);

        $this->assertSame($expectedTables, $mappedTables);
    }
}