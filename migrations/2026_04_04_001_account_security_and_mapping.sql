-- 2026_04_04_001_account_security_and_mapping.sql
-- Purpose:
-- 1) Add account security columns for temporary password flows
-- 2) Add MaNVRef (normalized numeric employee reference)
-- 3) Backfill MaNVRef from existing MaNV values like L001/NV002/3

ALTER TABLE taikhoan
    ADD COLUMN IF NOT EXISTS MaNVRef INT(11) NULL AFTER MaNV,
    ADD COLUMN IF NOT EXISTS BuocDoiMatKhau TINYINT(1) NOT NULL DEFAULT 0 AFTER TrangThai,
    ADD COLUMN IF NOT EXISTS NgayCapMatKhauTam DATETIME NULL AFTER BuocDoiMatKhau;

UPDATE taikhoan
SET MaNVRef = NULLIF(REGEXP_REPLACE(IFNULL(MaNV, ''), '[^0-9]', ''), '') + 0;

-- Optional indexes (run once if needed):
-- ALTER TABLE taikhoan ADD INDEX idx_taikhoan_manvref (MaNVRef);
-- ALTER TABLE taikhoan ADD INDEX idx_taikhoan_buocdoimatkhau (BuocDoiMatKhau);
