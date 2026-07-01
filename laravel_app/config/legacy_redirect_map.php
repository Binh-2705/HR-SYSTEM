<?php

/**
 * Legacy URL Migration Map
 * ────────────────────────
 * Ánh xạ các URL kiểu cũ (?controller=X&action=Y) sang Laravel route names.
 *
 * Khi bạn xoá một controller legacy, chỉ cần xoá entry tương ứng khỏi đây.
 * Middleware LegacyRedirectMiddleware sẽ tự động redirect user sang URL mới.
 *
 * Format:
 *   'controller:action' => ['route' => 'route.name', 'params' => fn($query) => [...]]
 *   Hoặc đơn giản:
 *   'controller:action' => 'route.name'
 *
 * Params 'id' từ query string sẽ được truyền vào route nếu route cần.
 */

return [

    // ─── Dashboard / Home ────────────────────────────────────────────
    'home:index'      => 'dashboard',
    'home:dashboard'  => 'dashboard',
    'dashboard:index' => 'dashboard',

    // ─── Nhân viên ───────────────────────────────────────────────────
    'nhanvien:index'  => 'nhanvien.index',
    'nhanvien:them'   => 'nhanvien.create',
    'nhanvien:sua'    => ['route' => 'nhanvien.edit',    'idParam' => 'MaNV',    'routeKey' => 'nhanvien'],
    'nhanvien:xoa'    => ['route' => 'nhanvien.destroy', 'idParam' => 'MaNV',    'routeKey' => 'nhanvien'],
    'nhanvien:detail' => ['route' => 'hosocanhan.show',  'idParam' => 'MaNV',    'routeKey' => 'profile'],

    // ─── Phòng ban ───────────────────────────────────────────────────
    'phongban:index'  => 'phongban.index',
    'phongban:them'   => 'phongban.create',
    'phongban:sua'    => ['route' => 'phongban.edit',    'idParam' => 'MaPB',    'routeKey' => 'phongban'],
    'phongban:xoa'    => ['route' => 'phongban.destroy', 'idParam' => 'MaPB',    'routeKey' => 'phongban'],

    // ─── Hợp đồng ────────────────────────────────────────────────────
    'hopdong:index'   => 'hopdong.index',
    'hopdong:them'    => 'hopdong.create',
    'hopdong:sua'     => ['route' => 'hopdong.edit',     'idParam' => 'MaHopDong', 'routeKey' => 'hopdong'],
    'hopdong:xoa'     => ['route' => 'hopdong.destroy',  'idParam' => 'MaHopDong', 'routeKey' => 'hopdong'],

    // ─── Chấm công ───────────────────────────────────────────────────
    'chamcong:index'  => 'chamcong.index',
    'chamcong:them'   => 'chamcong.create',
    'chamcong:sua'    => ['route' => 'chamcong.edit',    'idParam' => 'MaCC',    'routeKey' => 'chamcong'],

    // ─── Lương ───────────────────────────────────────────────────────
    'luong:index'     => 'luong.index',
    'luong:them'      => 'luong.create',
    'luong:sua'       => ['route' => 'luong.edit',       'idParam' => 'MaBL',    'routeKey' => 'luong'],
    'luong:tinh'      => 'luong.run-monthly',

    // ─── Nghỉ phép ───────────────────────────────────────────────────
    'nghiphep:index'  => 'nghiphep.index',
    'nghiphep:them'   => 'nghiphep.create',
    'nghiphep:duyet'  => ['route' => 'nghiphep.approve.legacy', 'idParam' => 'id', 'routeKey' => 'nghiphep'],
    'nghiphep:tuchoi' => ['route' => 'nghiphep.reject.legacy',  'idParam' => 'id', 'routeKey' => 'nghiphep'],

    // ─── Tuyển dụng ──────────────────────────────────────────────────
    'tuyendung:index'        => 'tuyendung.index',
    'tuyendung:ungvien'      => 'tuyendung.ungvien.index',
    'tuyendung:hosoungtuyen' => 'tuyendung.hoso.index',

    // ─── Đào tạo ─────────────────────────────────────────────────────
    'daotao:index'    => 'daotao.index',

    // ─── Báo cáo ─────────────────────────────────────────────────────
    'baocao:index'    => 'baocao.index',
    'baocao:dashboard'=> 'dashboard',

    // ─── Tài khoản / phân quyền ──────────────────────────────────────
    'taikhoan:index'  => 'taikhoan.index',
    'phanquyen:index' => 'phanquyen.index',
    'vaitro:index'    => 'vaitro.index',

    // ─── Hồ sơ cá nhân ───────────────────────────────────────────────
    'hosocanhan:index'=> 'hosocanhan.index',

    // ─── Chatbot ─────────────────────────────────────────────────────
    'chatbot:index'   => 'chatbot.index',
    'chatbot:audit'   => 'chatbot.index',

    // ─── System ──────────────────────────────────────────────────────
    'systemhealth:index' => 'systemhealth.index',
    'auditlog:index'     => 'auditlog.index',

    // ─── Auth ────────────────────────────────────────────────────────
    'home:login'    => 'login.form',
    'home:logout'   => 'logout.bridge',
    'home:settings' => 'settings.show',

];
