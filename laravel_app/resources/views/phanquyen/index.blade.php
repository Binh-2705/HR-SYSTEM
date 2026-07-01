@php $title = 'Phân quyền' @endphp
@php $subtitle = 'Quản lý quyền theo vai trò trên hệ thống, thay cho màn legacy' @endphp
@extends('layouts.app')

@section('content')
    <section class="panel">
        <div class="muted">Cập nhật bộ quyền theo vai trò và đồng bộ lại quyền mặc định từ file seed gốc khi cần.</div>
    </section>

    <section class="permission-role-grid">
        @foreach ($roles as $role)
            @php $rolePermissions = array_map('intval', $permissionsByRole[$role['MaVaiTro']] ?? []) @endphp
            <article class="panel" data-role-card>
                <div class="permission-role-head">
                    <div>
                        <h3 class="no-top-margin">Vai trò: {{ $role['TenVaiTro'] }}</h3>
                        <div class="muted top-gap-sm">Mã vai trò: {{ $role['MaVaiTro'] }}</div>
                    </div>
                    <input type="text" placeholder="Tìm quyền trong vai trò này" data-permission-search class="compact-input permission-search">
                </div>

                <div class="button-row spaced top-gap-lg">
                    <button type="button" class="btn btn-secondary" data-select-all>Chọn tất cả đang hiện</button>
                    <button type="button" class="btn btn-secondary" data-clear-all>Bỏ chọn đang hiện</button>
                </div>

                <form method="post" action="{{ route('phanquyen.update', ['role' => $role['MaVaiTro']]) }}">
                    @csrf
                    @foreach ($groupOrder as $groupName)
                        @if (empty($groupedFunctions[$groupName]))
                            @continue
                        @endif
                        <div data-permission-group class="permission-group">
                            <h4 class="no-top-margin">{{ $groupName }} ({{ count($groupedFunctions[$groupName]) }})</h4>
                            <div class="permission-grid top-gap-md">
                                @foreach ($groupedFunctions[$groupName] as $function)
                                    <label data-permission-item data-name="{{ strtolower($function['TenChucNang']) }}" class="permission-item">
                                        <input type="checkbox" name="chucnang[]" value="{{ $function['MaCN'] }}" {{ in_array($function['MaCN'], $rolePermissions, true) ? 'checked' : '' }}>
                                        <span>{{ $function['TenChucNang'] }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach

                    <div class="button-row spaced">
                        <button class="btn" type="submit">Lưu quyền</button>
                    </div>
                </form>

                @if (strtolower(trim((string) $role['TenVaiTro'])) === 'admin')
                    <form method="post" action="{{ route('phanquyen.restore-defaults', ['role' => $role['MaVaiTro']]) }}" class="top-gap-md">
                        @csrf
                        <button class="btn btn-danger" type="submit" onclick="return confirm('Khôi phục quyền mặc định cho vai trò này?');">Khôi phục mặc định</button>
                    </form>
                @else
                    <div class="top-gap-md muted">Vai trò này chưa có bộ quyền mặc định riêng để khôi phục.</div>
                @endif
            </article>
        @endforeach
    </section>

    <script>
        document.querySelectorAll('[data-role-card]').forEach(function (card) {
            var searchInput = card.querySelector('[data-permission-search]');
            var selectAllButton = card.querySelector('[data-select-all]');
            var clearAllButton = card.querySelector('[data-clear-all]');

            function visibleItems() {
                return Array.from(card.querySelectorAll('[data-permission-item]')).filter(function (item) {
                    return item.style.display !== 'none';
                });
            }

            function refreshGroups() {
                card.querySelectorAll('[data-permission-group]').forEach(function (group) {
                    var hasVisibleItem = Array.from(group.querySelectorAll('[data-permission-item]')).some(function (item) {
                        return item.style.display !== 'none';
                    });
                    group.style.display = hasVisibleItem ? '' : 'none';
                });
            }

            if (searchInput) {
                searchInput.addEventListener('input', function () {
                    var term = this.value.trim().toLowerCase();
                    card.querySelectorAll('[data-permission-item]').forEach(function (item) {
                        var value = item.getAttribute('data-name') || '';
                        item.style.display = (!term || value.indexOf(term) !== -1) ? '' : 'none';
                    });
                    refreshGroups();
                });
            }

            if (selectAllButton) {
                selectAllButton.addEventListener('click', function () {
                    visibleItems().forEach(function (item) {
                        var checkbox = item.querySelector('input[type="checkbox"]');
                        if (checkbox) {
                            checkbox.checked = true;
                        }
                    });
                });
            }

            if (clearAllButton) {
                clearAllButton.addEventListener('click', function () {
                    visibleItems().forEach(function (item) {
                        var checkbox = item.querySelector('input[type="checkbox"]');
                        if (checkbox) {
                            checkbox.checked = false;
                        }
                    });
                });
            }
        });
    </script>
@endsection