-- Add missing attendance status used by monthly matrix inline editor.
ALTER TABLE chamcong
    MODIFY COLUMN TrangThai ENUM('Di lam','Di muon','Nghi phep','Nghi khong luong','Cong tac','Le')
    NOT NULL DEFAULT 'Di lam';
