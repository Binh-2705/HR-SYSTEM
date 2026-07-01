import os
import re
import unicodedata
from datetime import datetime
from typing import Any, Dict, List, Optional, Tuple

import mysql.connector
from dotenv import load_dotenv
from fastapi import FastAPI, Header, HTTPException
from pydantic import BaseModel, Field

try:
    from openai import OpenAI
except Exception:  # pragma: no cover
    OpenAI = None

load_dotenv()


class UserContext(BaseModel):
    ma_tk: int = 0
    username: str = ""
    role: str = ""
    permissions: List[str] = Field(default_factory=list)


class ChatMessage(BaseModel):
    role: str
    content: str


class ChatRequest(BaseModel):
    message: str = Field(min_length=1, max_length=1000)
    history: List[ChatMessage] = Field(default_factory=list)
    user: UserContext


class ChatResponse(BaseModel):
    reply: str
    actions: List[str] = Field(default_factory=list)
    suggestions: List[str] = Field(default_factory=list)
    action_draft: Optional[Dict[str, Any]] = None
    source: str = "bot_service"


class DataTools:
    def __init__(self) -> None:
        self.db_host = os.getenv("DB_HOST", "127.0.0.1")
        self.db_port = int(os.getenv("DB_PORT", "3306"))
        self.db_user = os.getenv("DB_USER", "root")
        self.db_password = os.getenv("DB_PASSWORD", "")
        self.db_name = os.getenv("DB_NAME", "quanlynhansu")

    def _connect(self):
        return mysql.connector.connect(
            host=self.db_host,
            port=self.db_port,
            user=self.db_user,
            password=self.db_password,
            database=self.db_name,
            charset="utf8",
        )

    def employee_count(self) -> int:
        conn = self._connect()
        try:
            cur = conn.cursor()
            cur.execute("SELECT COUNT(*) FROM nhanvien")
            row = cur.fetchone()
            return int((row or [0])[0])
        finally:
            conn.close()

    def pending_leave_count(self) -> int:
        conn = self._connect()
        try:
            cur = conn.cursor()
            cur.execute("SELECT COUNT(*) FROM nghiphep WHERE TrangThai = %s", ("Chờ duyệt",))
            row = cur.fetchone()
            return int((row or [0])[0])
        finally:
            conn.close()

    def search_employee(self, keyword: str, limit: int = 5) -> List[Dict[str, Any]]:
        conn = self._connect()
        try:
            cur = conn.cursor(dictionary=True)
            like = f"%{keyword}%"
            cur.execute(
                """
                SELECT MaNV, HoTen, Email, DienThoai, TrangThai
                FROM nhanvien
                WHERE CAST(MaNV AS CHAR) LIKE %s OR HoTen LIKE %s
                ORDER BY MaNV DESC
                LIMIT %s
                """,
                (like, like, limit),
            )
            rows = cur.fetchall() or []
            return rows
        finally:
            conn.close()

    def leave_status_summary(self) -> List[Dict[str, Any]]:
        conn = self._connect()
        try:
            cur = conn.cursor(dictionary=True)
            cur.execute(
                """
                SELECT TrangThai, COUNT(*) AS total
                FROM nghiphep
                GROUP BY TrangThai
                ORDER BY total DESC
                """
            )
            return cur.fetchall() or []
        finally:
            conn.close()

    def leave_request_detail(self, ma_np: int) -> Optional[Dict[str, Any]]:
        conn = self._connect()
        try:
            cur = conn.cursor(dictionary=True)
            cur.execute(
                """
                SELECT np.MaNP, np.MaNV, np.TuNgay, np.DenNgay, np.SoNgayNghi, np.LoaiNghi, np.TrangThai, nv.HoTen
                FROM nghiphep np
                JOIN nhanvien nv ON np.MaNV = nv.MaNV
                WHERE np.MaNP = %s
                LIMIT 1
                """,
                (ma_np,),
            )
            return cur.fetchone()
        finally:
            conn.close()

    def contracts_expiring(self, days: int = 30, limit: int = 5) -> List[Dict[str, Any]]:
        conn = self._connect()
        try:
            cur = conn.cursor(dictionary=True)
            cur.execute(
                """
                SELECT hd.SoHopDong, hd.NgayKetThuc, nv.HoTen
                FROM hopdong hd
                LEFT JOIN nhanvien nv ON hd.MaNV = nv.MaNV
                WHERE hd.TrangThai = %s
                  AND hd.NgayKetThuc IS NOT NULL
                  AND hd.NgayKetThuc <= DATE_ADD(CURDATE(), INTERVAL %s DAY)
                  AND hd.NgayKetThuc >= CURDATE()
                ORDER BY hd.NgayKetThuc ASC
                LIMIT %s
                """,
                ("Còn hiệu lực", days, limit),
            )
            return cur.fetchall() or []
        finally:
            conn.close()

    def department_headcount(self, limit: int = 6) -> List[Dict[str, Any]]:
        conn = self._connect()
        try:
            cur = conn.cursor(dictionary=True)
            cur.execute(
                """
                SELECT pb.TenPB, COUNT(DISTINCT pc.MaNV) AS total
                FROM phancong pc
                INNER JOIN phongban pb ON pc.MaPB = pb.MaPB
                WHERE pc.NgayKetThuc IS NULL OR pc.NgayKetThuc >= CURDATE()
                GROUP BY pb.MaPB, pb.TenPB
                ORDER BY total DESC
                LIMIT %s
                """,
                (limit,),
            )
            return cur.fetchall() or []
        finally:
            conn.close()

    def employee_detail(self, keyword: str) -> Optional[Dict[str, Any]]:
        conn = self._connect()
        try:
            cur = conn.cursor(dictionary=True)
            like = f"%{keyword}%"
            cur.execute(
                """
                SELECT nv.MaNV, nv.HoTen, nv.Email, nv.DienThoai, nv.TrangThai,
                       pb.TenPB, cv.TenCV
                FROM nhanvien nv
                LEFT JOIN phancong pc ON pc.MaNV = nv.MaNV AND (pc.NgayKetThuc IS NULL OR pc.NgayKetThuc >= CURDATE())
                LEFT JOIN phongban pb ON pc.MaPB = pb.MaPB
                LEFT JOIN chucvu cv ON pc.MaCV = cv.MaCV
                WHERE CAST(nv.MaNV AS CHAR) LIKE %s OR nv.HoTen LIKE %s
                ORDER BY nv.MaNV DESC
                LIMIT 1
                """,
                (like, like),
            )
            return cur.fetchone()
        finally:
            conn.close()

    def recruitment_status_summary(self) -> Optional[Dict[str, Any]]:
        conn = self._connect()
        try:
            cur = conn.cursor(dictionary=True)
            cur.execute(
                """
                SELECT
                    COUNT(*) AS Tong,
                    SUM(CASE WHEN TrangThai='Nộp hồ sơ' THEN 1 ELSE 0 END) AS NopHoSo,
                    SUM(CASE WHEN TrangThai='Sàng lọc' THEN 1 ELSE 0 END) AS SangLoc,
                    SUM(CASE WHEN TrangThai='Phỏng vấn' THEN 1 ELSE 0 END) AS PhongVan,
                    SUM(CASE WHEN TrangThai='Offer' THEN 1 ELSE 0 END) AS Offer,
                    SUM(CASE WHEN TrangThai='Nhận việc' THEN 1 ELSE 0 END) AS NhanViec,
                    SUM(CASE WHEN TrangThai='Rớt' THEN 1 ELSE 0 END) AS Rot
                FROM hosoungtuyen
                """
            )
            return cur.fetchone()
        finally:
            conn.close()

    def top_candidates(self, limit: int = 5) -> List[Dict[str, Any]]:
        conn = self._connect()
        try:
            cur = conn.cursor(dictionary=True)
            cur.execute(
                """
                SELECT uv.HoTen,
                       ROUND((dg.DiemKyNang + dg.DiemKinhNghiem + dg.DiemThaiDo) / 3, 2) AS DiemTB
                FROM danhgiaphongvan dg
                JOIN hosoungtuyen hs ON dg.MaHS = hs.MaHS
                JOIN ungvien uv ON hs.MaUV = uv.MaUV
                ORDER BY DiemTB DESC
                LIMIT %s
                """,
                (limit,),
            )
            return cur.fetchall() or []
        finally:
            conn.close()

    def attendance_summary_this_month(self) -> Optional[Dict[str, Any]]:
        conn = self._connect()
        try:
            cur = conn.cursor(dictionary=True)
            cur.execute(
                """
                SELECT
                    COUNT(DISTINCT MaNV) AS SoNhanVien,
                    SUM(CASE WHEN TrangThai='Di lam' THEN 1 ELSE 0 END) AS TongDiLam,
                    SUM(CASE WHEN TrangThai='Nghi phep' THEN 1 ELSE 0 END) AS TongNghiPhep,
                    SUM(CASE WHEN TrangThai='Nghi khong luong' THEN 1 ELSE 0 END) AS TongNghiKhongLuong,
                    ROUND(AVG(CASE WHEN TrangThai='Di lam' AND GioVao IS NOT NULL AND GioRa IS NOT NULL
                         THEN TIMESTAMPDIFF(MINUTE, GioVao, GioRa)/60.0 ELSE NULL END), 2) AS TBGioLam
                FROM chamcong
                WHERE MONTH(Ngay) = MONTH(CURDATE()) AND YEAR(Ngay) = YEAR(CURDATE())
                """
            )
            return cur.fetchone()
        finally:
            conn.close()

    def overtime_top_this_month(self, limit: int = 5) -> List[Dict[str, Any]]:
        conn = self._connect()
        try:
            cur = conn.cursor(dictionary=True)
            cur.execute(
                """
                SELECT nv.HoTen,
                       ROUND(SUM(GREATEST(TIMESTAMPDIFF(MINUTE, cc.GioVao, cc.GioRa)/60.0 - 8, 0)), 2) AS GioOT
                FROM chamcong cc
                JOIN nhanvien nv ON cc.MaNV = nv.MaNV
                WHERE cc.TrangThai = 'Di lam'
                  AND cc.GioVao IS NOT NULL AND cc.GioRa IS NOT NULL
                  AND MONTH(cc.Ngay) = MONTH(CURDATE()) AND YEAR(cc.Ngay) = YEAR(CURDATE())
                GROUP BY cc.MaNV, nv.HoTen
                HAVING GioOT > 0
                ORDER BY GioOT DESC
                LIMIT %s
                """,
                (limit,),
            )
            return cur.fetchall() or []
        finally:
            conn.close()

    def training_upcoming(self, days: int = 60, limit: int = 5) -> List[Dict[str, Any]]:
        conn = self._connect()
        try:
            cur = conn.cursor(dictionary=True)
            cur.execute(
                """
                SELECT TenKhoaDaoTao, TuNgay, DenNgay, DonViToChuc, TrangThai
                FROM khoadaotao
                WHERE TuNgay >= CURDATE()
                  AND TuNgay <= DATE_ADD(CURDATE(), INTERVAL %s DAY)
                ORDER BY TuNgay ASC
                LIMIT %s
                """,
                (days, limit),
            )
            return cur.fetchall() or []
        finally:
            conn.close()

    def training_ongoing(self, limit: int = 5) -> List[Dict[str, Any]]:
        conn = self._connect()
        try:
            cur = conn.cursor(dictionary=True)
            cur.execute(
                """
                SELECT TenKhoaDaoTao, TuNgay, DenNgay, DonViToChuc, TrangThai
                FROM khoadaotao
                WHERE TrangThai = 'Đang đào tạo'
                ORDER BY TuNgay ASC
                LIMIT %s
                """,
                (limit,),
            )
            return cur.fetchall() or []
        finally:
            conn.close()

    def insurance_summary(self) -> Optional[Dict[str, Any]]:
        conn = self._connect()
        try:
            cur = conn.cursor(dictionary=True)
            cur.execute(
                """
                SELECT
                    COUNT(DISTINCT MaNV) AS SoNhanVienCoHoSo,
                    SUM(CASE WHEN TrangThai='Đang đóng' THEN 1 ELSE 0 END) AS DangDong,
                    SUM(CASE WHEN TrangThai='Đã dừng' THEN 1 ELSE 0 END) AS DaDung,
                    SUM(CASE WHEN LoaiBaoHiem='BHXH' AND TrangThai='Đang đóng' THEN 1 ELSE 0 END) AS BHXH,
                    SUM(CASE WHEN LoaiBaoHiem='BHYT' AND TrangThai='Đang đóng' THEN 1 ELSE 0 END) AS BHYT,
                    SUM(CASE WHEN LoaiBaoHiem='BHTN' AND TrangThai='Đang đóng' THEN 1 ELSE 0 END) AS BHTN
                FROM baohiem
                """
            )
            return cur.fetchone()
        finally:
            conn.close()

    def employee_birthday_this_month(self, limit: int = 10) -> List[Dict[str, Any]]:
        conn = self._connect()
        try:
            cur = conn.cursor(dictionary=True)
            cur.execute(
                """
                SELECT HoTen, NgaySinh, DAY(NgaySinh) AS Ngay
                FROM nhanvien
                WHERE MONTH(NgaySinh) = MONTH(CURDATE())
                  AND TrangThai = 'Đang làm việc'
                ORDER BY DAY(NgaySinh) ASC
                LIMIT %s
                """,
                (limit,),
            )
            return cur.fetchall() or []
        finally:
            conn.close()

    def recognition_recent(self, limit: int = 5) -> List[Dict[str, Any]]:
        conn = self._connect()
        try:
            cur = conn.cursor(dictionary=True)
            cur.execute(
                """
                SELECT nv.HoTen, lk.TenLoai, kt.HinhThuc, kt.SoTien, kt.LyDo, kt.NgayQuyetDinh
                FROM khenthuongkyluat kt
                JOIN nhanvien nv ON kt.MaNV = nv.MaNV
                JOIN loaikhenthuongkyluat lk ON kt.MaLoai = lk.MaLoai
                ORDER BY kt.NgayQuyetDinh DESC
                LIMIT %s
                """,
                (limit,),
            )
            return cur.fetchall() or []
        finally:
            conn.close()

    def new_employees_this_month(self, limit: int = 10) -> List[Dict[str, Any]]:
        conn = self._connect()
        try:
            cur = conn.cursor(dictionary=True)
            cur.execute(
                """
                SELECT MaNV, HoTen, Email, NgayVaoLam, TrangThai
                FROM nhanvien
                WHERE MONTH(NgayVaoLam) = MONTH(CURDATE())
                  AND YEAR(NgayVaoLam) = YEAR(CURDATE())
                ORDER BY NgayVaoLam DESC
                LIMIT %s
                """,
                (limit,),
            )
            return cur.fetchall() or []
        finally:
            conn.close()

    def department_list(self) -> List[Dict[str, Any]]:
        conn = self._connect()
        try:
            cur = conn.cursor(dictionary=True)
            cur.execute(
                """
                SELECT pb.MaPB, pb.TenPB, pb.MoTa,
                       COUNT(DISTINCT pc.MaNV) AS SoNhanVien
                FROM phongban pb
                LEFT JOIN phancong pc ON pc.MaPB = pb.MaPB
                    AND (pc.NgayKetThuc IS NULL OR pc.NgayKetThuc >= CURDATE())
                GROUP BY pb.MaPB, pb.TenPB, pb.MoTa
                ORDER BY SoNhanVien DESC
                """
            )
            return cur.fetchall() or []
        finally:
            conn.close()

    def payroll_summary_this_month(self) -> Optional[Dict[str, Any]]:
        conn = self._connect()
        try:
            cur = conn.cursor(dictionary=True)
            cur.execute(
                """
                SELECT
                    COUNT(DISTINCT MaNV) AS SoNhanVienCoLuong,
                    ROUND(AVG(TongLuong), 0) AS LuongTrungBinh,
                    ROUND(MAX(TongLuong), 0) AS LuongCaoNhat,
                    ROUND(MIN(TongLuong), 0) AS LuongThapNhat,
                    ROUND(SUM(TongLuong), 0) AS TongQuyLuong,
                    SUM(CASE WHEN TrangThai='Đã duyệt' THEN 1 ELSE 0 END) AS DaDuyet,
                    SUM(CASE WHEN TrangThai='Chờ duyệt' THEN 1 ELSE 0 END) AS ChoDuyet
                FROM bangluong
                WHERE Thang = MONTH(CURDATE()) AND Nam = YEAR(CURDATE())
                """
            )
            return cur.fetchone()
        finally:
            conn.close()

    def resolve_employee_id(self, ma_tk: int) -> int:
        conn = self._connect()
        try:
            cur = conn.cursor(dictionary=True)
            cur.execute(
                """
                SELECT COALESCE(MaNVRef, CAST(NULLIF(MaNV, '') AS UNSIGNED)) AS MaNV
                FROM taikhoan
                WHERE MaTK = %s
                LIMIT 1
                """,
                (ma_tk,),
            )
            row = cur.fetchone() or {}
            return int(row.get("MaNV") or 0)
        finally:
            conn.close()

    def self_profile(self, ma_tk: int) -> Optional[Dict[str, Any]]:
        ma_nv = self.resolve_employee_id(ma_tk)
        if ma_nv <= 0:
            return None

        conn = self._connect()
        try:
            cur = conn.cursor(dictionary=True)
            cur.execute(
                """
                SELECT nv.MaNV, nv.HoTen, nv.GioiTinh, nv.NgaySinh, nv.Email, nv.DienThoai, nv.TrangThai,
                       pb.TenPB, cv.TenCV
                FROM nhanvien nv
                LEFT JOIN phancong pc ON pc.MaNV = nv.MaNV
                    AND (pc.NgayKetThuc IS NULL OR pc.NgayKetThuc >= CURDATE())
                LEFT JOIN phongban pb ON pb.MaPB = pc.MaPB
                LEFT JOIN chucvu cv ON cv.MaCV = pc.MaCV
                WHERE nv.MaNV = %s
                ORDER BY pc.NgayBatDau DESC
                LIMIT 1
                """,
                (ma_nv,),
            )
            return cur.fetchone()
        finally:
            conn.close()

    def self_recent_leave(self, ma_tk: int, limit: int = 5) -> List[Dict[str, Any]]:
        ma_nv = self.resolve_employee_id(ma_tk)
        if ma_nv <= 0:
            return []

        conn = self._connect()
        try:
            cur = conn.cursor(dictionary=True)
            cur.execute(
                """
                SELECT MaNP, TuNgay, DenNgay, SoNgayNghi, LoaiNghi, TrangThai, NgayNopDon, NgayDuyet
                FROM nghiphep
                WHERE MaNV = %s
                ORDER BY NgayNopDon DESC, MaNP DESC
                LIMIT %s
                """,
                (ma_nv, limit),
            )
            return cur.fetchall() or []
        finally:
            conn.close()

    def self_current_contract(self, ma_tk: int) -> Optional[Dict[str, Any]]:
        ma_nv = self.resolve_employee_id(ma_tk)
        if ma_nv <= 0:
            return None

        conn = self._connect()
        try:
            cur = conn.cursor(dictionary=True)
            cur.execute(
                """
                SELECT hd.MaHopDong, hd.SoHopDong, hd.LoaiHopDong, hd.NgayKy, hd.NgayBatDau, hd.NgayKetThuc,
                       hd.TrangThai, hd.MaBac, bl.TenBac, bl.HeSoLuong, bl.LuongCoSo,
                       (bl.HeSoLuong * bl.LuongCoSo) AS LuongCoBan
                FROM hopdong hd
                LEFT JOIN bacluong bl ON bl.MaBac = hd.MaBac
                WHERE hd.MaNV = %s
                  AND hd.TrangThai = 'Còn hiệu lực'
                ORDER BY hd.NgayBatDau DESC, hd.MaHopDong DESC
                LIMIT 1
                """,
                (ma_nv,),
            )
            return cur.fetchone()
        finally:
            conn.close()

    def self_salary_current_month(self, ma_tk: int) -> Optional[Dict[str, Any]]:
        ma_nv = self.resolve_employee_id(ma_tk)
        if ma_nv <= 0:
            return None

        conn = self._connect()
        try:
            cur = conn.cursor(dictionary=True)
            cur.execute(
                """
                SELECT Thang, Nam, LuongCoSo, HeSoLuong, HeSoChucVu, PhuCap, Thuong, Phat, BaoHiem, TongLuong, TrangThai
                FROM bangluong
                WHERE MaNV = %s
                  AND Thang = MONTH(CURDATE())
                  AND Nam = YEAR(CURDATE())
                LIMIT 1
                """,
                (ma_nv,),
            )
            row = cur.fetchone()
            if row:
                row["source_name"] = "bangluong"
                return row

            cur.execute(
                """
                SELECT hd.MaHopDong, bl.LuongCoSo, bl.HeSoLuong,
                       IFNULL(cv.HeSoChucVu, 1) AS HeSoChucVu,
                       IFNULL(cv.PhuCap, 0) AS PhuCap
                FROM hopdong hd
                JOIN bacluong bl ON bl.MaBac = hd.MaBac
                LEFT JOIN phancong pc ON pc.MaNV = hd.MaNV
                    AND (pc.NgayKetThuc IS NULL OR pc.NgayKetThuc >= CURDATE())
                LEFT JOIN chucvu cv ON cv.MaCV = pc.MaCV
                WHERE hd.MaNV = %s
                  AND hd.TrangThai = 'Còn hiệu lực'
                ORDER BY hd.NgayBatDau DESC, hd.MaHopDong DESC
                LIMIT 1
                """,
                (ma_nv,),
            )
            fallback = cur.fetchone()
            if fallback:
                fallback["TongLuong"] = float(fallback.get("LuongCoSo") or 0) * float(fallback.get("HeSoLuong") or 0) + float(fallback.get("PhuCap") or 0)
                fallback["source_name"] = "contract_estimate"
            return fallback
        finally:
            conn.close()

    def contract_detail(self, ma_hop_dong: int) -> Optional[Dict[str, Any]]:
        conn = self._connect()
        try:
            cur = conn.cursor(dictionary=True)
            cur.execute(
                """
                SELECT hd.MaHopDong, hd.SoHopDong, hd.MaNV, hd.MaBac, hd.LoaiHopDong, hd.NgayBatDau, hd.NgayKetThuc,
                       hd.TrangThai, nv.HoTen, bl.TenBac
                FROM hopdong hd
                LEFT JOIN nhanvien nv ON nv.MaNV = hd.MaNV
                LEFT JOIN bacluong bl ON bl.MaBac = hd.MaBac
                WHERE hd.MaHopDong = %s
                LIMIT 1
                """,
                (ma_hop_dong,),
            )
            return cur.fetchone()
        finally:
            conn.close()


def normalize_text(text: str) -> str:
    lowered = text.lower().strip()
    normalized = unicodedata.normalize("NFD", lowered)
    no_marks = "".join(ch for ch in normalized if unicodedata.category(ch) != "Mn")
    return no_marks.replace("đ", "d")


def is_pending_leave_status(status_value: Any) -> bool:
    return normalize_text(str(status_value or "")) == "cho duyet"


def parse_date_token(raw_value: str) -> Optional[str]:
    token = raw_value.strip()
    if re.fullmatch(r"\d{4}-\d{2}-\d{2}", token):
        return token
    if re.fullmatch(r"\d{2}/\d{2}/\d{4}", token):
        day, month, year = token.split("/")
        return f"{year}-{month}-{day}"
    return None


def extract_dates_from_text(text: str) -> List[str]:
    raw_tokens = re.findall(r"\d{4}-\d{2}-\d{2}|\d{2}/\d{2}/\d{4}", text)
    dates: List[str] = []
    for token in raw_tokens:
        parsed = parse_date_token(token)
        if parsed:
            dates.append(parsed)
    return dates


class ChatEngine:
    def __init__(self) -> None:
        self.api_key = os.getenv("OPENAI_API_KEY", "").strip()
        self.model = os.getenv("OPENAI_MODEL", "gpt-4o-mini").strip()
        self.client = OpenAI(api_key=self.api_key) if self.api_key and OpenAI else None
        self.tools = DataTools()

    def answer(self, request: ChatRequest) -> ChatResponse:
        message = request.message.strip()
        self._current_user_role = request.user.role

        plan_reply = self._try_action_plan(message, request.user)
        if plan_reply is not None:
            return ChatResponse(
                reply=plan_reply[0],
                actions=plan_reply[1],
                suggestions=plan_reply[2],
                action_draft=plan_reply[3],
                source="action_plan",
            )

        tool_reply = self._try_tool_answer(message, request.user)
        if tool_reply is not None:
            return ChatResponse(reply=tool_reply[0], actions=tool_reply[1], suggestions=tool_reply[2], source="tool")

        llm_reply = self._llm_answer(request)
        if llm_reply:
            return ChatResponse(
                reply=llm_reply,
                actions=["Tư vấn chế độ chỉ đọc", "Chưa thực thi thay đổi dữ liệu"],
                suggestions=self._default_suggestions(),
                source="llm",
            )

        fallback = (
            "Tôi đã nhận câu hỏi của bạn. Hiện tại dịch vụ LLM chưa được cấu hình, "
            "nhưng bạn vẫn có thể dùng các lệnh tra cứu dữ liệu như tổng nhân viên, đơn nghỉ phép chờ duyệt, "
            "thống kê nghỉ phép, hợp đồng sắp hết hạn, tìm nhân viên."
        )
        return ChatResponse(
            reply=fallback,
            actions=["Cấu hình OPENAI_API_KEY để bật trợ lý nâng cao"],
            suggestions=self._default_suggestions(),
            source="fallback",
        )

    def _default_suggestions(self) -> List[str]:
        role = getattr(self, "_current_user_role", "")
        role_suggestions: Dict[str, List[str]] = {
            "Admin": [
                "Tổng số nhân viên hiện tại là bao nhiêu?",
                "Thống kê nghỉ phép",
                "Hợp đồng sắp hết hạn",
            ],
            "HR": [
                "Nhân viên mới tháng này",
                "Có bao nhiêu đơn nghỉ phép chờ duyệt?",
                "Tóm tắt tuyển dụng",
            ],
            "KeToan": [
                "Thống kê lương tháng này",
                "Tổng số nhân viên hiện tại là bao nhiêu?",
                "Tổng quan bảo hiểm",
            ],
            "QuanLy": [
                "Phân bổ nhân sự theo phòng ban",
                "Hợp đồng sắp hết hạn",
                "Thống kê nghỉ phép",
            ],
            "NhanVien": [
                "Thông tin cá nhân của tôi",
                "Đơn nghỉ phép của tôi",
                "Lương tháng này của tôi",
            ],
        }
        return role_suggestions.get(role, [
            "Tổng số nhân viên hiện tại là bao nhiêu?",
            "Thống kê nghỉ phép",
            "Hợp đồng sắp hết hạn",
        ])

    def _try_action_plan(self, message: str, user: UserContext) -> Optional[Tuple[str, List[str], List[str], Optional[Dict[str, Any]]]]:
        q = normalize_text(message)
        write_verbs = ["tao", "them", "xoa", "cap nhat", "duyet", "cham dut", "reset", "doi mat khau"]

        if not any(token in q for token in write_verbs):
            return None

        if any(token in q for token in ["tao", "them", "dang ky", "xin"]) and "nghi phep" in q:
            if not self._has_permission(user.permissions, "them_nghiphep"):
                denied = self._permission_denied("them_nghiphep")
                return (denied[0], denied[1], denied[2], None)

            ma_nv = self.tools.resolve_employee_id(user.ma_tk)
            profile = self.tools.self_profile(user.ma_tk)
            if ma_nv <= 0 or not profile:
                return (
                    "Tôi chưa xác định được hồ sơ nhân viên gắn với tài khoản hiện tại để tạo đơn nghỉ phép.",
                    ["Kiểm tra lại liên kết tài khoản - nhân viên trong bảng taiKhoan"],
                    ["Thông tin cá nhân của tôi", "Đơn nghỉ phép của tôi"],
                    None,
                )

            dates = extract_dates_from_text(message)
            if len(dates) < 2:
                return (
                    "Để tạo đơn nghỉ phép, bạn cần ghi rõ ngày bắt đầu và ngày kết thúc. Ví dụ: tạo đơn nghỉ phép từ 2026-04-10 đến 2026-04-12 lý do việc riêng.",
                    ["Định dạng ngày hỗ trợ: YYYY-MM-DD hoặc DD/MM/YYYY"],
                    ["Đơn nghỉ phép của tôi", "Thống kê nghỉ phép"],
                    None,
                )

            leave_type = "Nghỉ phép năm"
            if "om" in q:
                leave_type = "Nghỉ ốm"
            elif "khong luong" in q:
                leave_type = "Nghỉ không lương"
            elif "viec rieng" in q:
                leave_type = "Nghỉ việc riêng"

            reason_match = re.search(r"ly do\s+(.+)$", message, re.IGNORECASE)
            leave_reason = reason_match.group(1).strip() if reason_match else ""

            return (
                (
                    f"Tôi đã chuẩn bị đơn nghỉ phép cho {profile.get('HoTen')} từ {dates[0]} đến {dates[1]}. "
                    f"Loại nghỉ: {leave_type}."
                ),
                [
                    "Sau khi xác nhận, hệ thống sẽ tạo đơn ở trạng thái Chờ duyệt",
                    "Bạn vẫn có thể duyệt/từ chối theo quy trình hiện có",
                ],
                ["Đơn nghỉ phép của tôi", "Thống kê nghỉ phép", "Thông tin cá nhân của tôi"],
                {
                    "action_type": "leave_create",
                    "title": "Tạo đơn nghỉ phép",
                    "summary": f"Nhân viên: {profile.get('HoTen')} | Từ {dates[0]} đến {dates[1]} | Loại: {leave_type}",
                    "required_permission": "them_nghiphep",
                    "confirm_label": "Gửi đơn nghỉ phép",
                    "payload": {
                        "ma_nv": ma_nv,
                        "tu_ngay": dates[0],
                        "den_ngay": dates[1],
                        "loai_nghi": leave_type,
                        "ly_do": leave_reason,
                    },
                },
            )

        if "gia han" in q and "hop dong" in q:
            if not self._has_permission(user.permissions, "giahan_hopdong"):
                denied = self._permission_denied("giahan_hopdong")
                return (denied[0], denied[1], denied[2], None)

            contract_match = re.search(r"hop dong\s*(?:ma)?\s*(\d+)", q)
            ma_hop_dong = int(contract_match.group(1)) if contract_match else 0
            dates = extract_dates_from_text(message)
            new_end_date = dates[-1] if dates else ""

            if ma_hop_dong <= 0 or new_end_date == "":
                return (
                    "Để gia hạn hợp đồng, hãy nêu rõ mã hợp đồng và ngày kết thúc mới. Ví dụ: gia hạn hợp đồng 125 đến 2026-12-31.",
                    ["Yêu cầu cần có: mã hợp đồng + ngày kết thúc mới"],
                    ["Hợp đồng sắp hết hạn", "Thông tin cá nhân của tôi"],
                    None,
                )

            contract = self.tools.contract_detail(ma_hop_dong)
            if not contract:
                return (
                    "Không tìm thấy hợp đồng cần gia hạn.",
                    ["Kiểm tra lại mã hợp đồng"],
                    ["Hợp đồng sắp hết hạn", "Tìm nhân viên 12"],
                    None,
                )

            return (
                (
                    f"Tôi đã chuẩn bị bản xác nhận gia hạn hợp đồng #{contract.get('MaHopDong')} "
                    f"({contract.get('SoHopDong')}) của {contract.get('HoTen')} đến {new_end_date}."
                ),
                [
                    "Sau khi xác nhận, hệ thống sẽ tạo một hợp đồng gia hạn mới dựa trên hợp đồng gốc",
                    "Thông tin bậc lương và nhân viên sẽ kế thừa từ hợp đồng hiện tại",
                ],
                ["Hợp đồng sắp hết hạn", "Tìm nhân viên 12", "Tổng số nhân viên hiện tại là bao nhiêu?"],
                {
                    "action_type": "contract_extend",
                    "title": f"Gia hạn hợp đồng #{contract.get('MaHopDong')}",
                    "summary": (
                        f"Nhân viên: {contract.get('HoTen')} | Số HĐ: {contract.get('SoHopDong')} | "
                        f"Kết thúc mới: {new_end_date}"
                    ),
                    "required_permission": "giahan_hopdong",
                    "confirm_label": "Tạo hợp đồng gia hạn",
                    "payload": {
                        "ma_hop_dong": ma_hop_dong,
                        "new_end_date": new_end_date,
                    },
                },
            )

        if "duyet" in q and "nghi phep" in q:
            match = re.search(r"ma\s+(\d+)", q)
            ma_np = int(match.group(1)) if match else 0
            if ma_np > 0:
                detail = self.tools.leave_request_detail(ma_np)
                if detail:
                    if not is_pending_leave_status(detail.get("TrangThai")):
                        return (
                            (
                                f"Đơn #{detail.get('MaNP')} hiện đang ở trạng thái {detail.get('TrangThai')}, "
                                "không thể duyệt lại."
                            ),
                            [
                                "Không tạo action draft vì trạng thái không hợp lệ",
                                "Chỉ có thể duyệt đơn ở trạng thái Chờ duyệt",
                            ],
                            ["Thống kê nghỉ phép", "Có bao nhiêu đơn nghỉ phép chờ duyệt?", "Tìm nhân viên 12"],
                            None,
                        )

                    return (
                        (
                            "Tôi đã chuẩn bị bản xác nhận duyệt nghỉ phép. "
                            f"Đơn #{detail.get('MaNP')} của {detail.get('HoTen')} từ {detail.get('TuNgay')} đến {detail.get('DenNgay')} "
                            f"({detail.get('SoNgayNghi')} ngày) hiện ở trạng thái {detail.get('TrangThai')}."
                        ),
                        [
                            "Kiểm tra lại thông tin đơn trước khi xác nhận",
                            "Sau khi xác nhận, chatbot sẽ gọi chức năng duyệt đơn",
                        ],
                        ["Xác nhận duyệt đơn này", "Thống kê nghỉ phép", "Có bao nhiêu đơn nghỉ phép chờ duyệt?"],
                        {
                            "action_type": "leave_approve",
                            "title": f"Duyệt đơn nghỉ phép #{detail.get('MaNP')}",
                            "summary": (
                                f"Nhân viên: {detail.get('HoTen')} | Từ {detail.get('TuNgay')} đến {detail.get('DenNgay')} | "
                                f"Loại: {detail.get('LoaiNghi')} | Trạng thái hiện tại: {detail.get('TrangThai')}"
                            ),
                            "required_permission": "duyet_nghiphep",
                            "confirm_label": "Xác nhận duyệt đơn",
                            "payload": {"ma_np": ma_np},
                        },
                    )

            return (
                "Yêu cầu này nên đi qua quy trình duyệt an toàn: xác định mã đơn nghỉ phép, kiểm tra trạng thái hiện tại, xem phạm vi ngày nghỉ bị ảnh hưởng, sau đó mới xác nhận duyệt.",
                [
                    "Chưa duyệt đơn thực tế",
                    "Cần có: mã đơn nghỉ phép hoặc mã nhân viên + khoảng ngày",
                ],
                [
                    "Thống kê nghỉ phép",
                    "Có bao nhiêu đơn nghỉ phép chờ duyệt?",
                    "Hãy lập action plan duyệt nghỉ phép mã 5",
                ],
                None,
            )

        if ("tu choi" in q or "tuchoi" in q) and "nghi phep" in q:
            match = re.search(r"ma\s+(\d+)", q)
            ma_np = int(match.group(1)) if match else 0
            if ma_np > 0:
                detail = self.tools.leave_request_detail(ma_np)
                if detail:
                    if not is_pending_leave_status(detail.get("TrangThai")):
                        return (
                            (
                                f"Đơn #{detail.get('MaNP')} hiện đang ở trạng thái {detail.get('TrangThai')}, "
                                "không thể từ chối ở bước này."
                            ),
                            [
                                "Không tạo action draft vì trạng thái không hợp lệ",
                                "Chỉ có thể từ chối đơn ở trạng thái Chờ duyệt",
                            ],
                            ["Thống kê nghỉ phép", "Có bao nhiêu đơn nghỉ phép chờ duyệt?", "Tìm nhân viên 12"],
                            None,
                        )

                    return (
                        (
                            "Tôi đã chuẩn bị bản xác nhận từ chối nghỉ phép. "
                            f"Đơn #{detail.get('MaNP')} của {detail.get('HoTen')} từ {detail.get('TuNgay')} đến {detail.get('DenNgay')} "
                            f"({detail.get('SoNgayNghi')} ngày) hiện ở trạng thái {detail.get('TrangThai')}."
                        ),
                        [
                            "Kiểm tra lại thông tin đơn trước khi xác nhận",
                            "Sau khi xác nhận, chatbot sẽ gọi chức năng từ chối đơn",
                        ],
                        ["Xác nhận từ chối đơn này", "Thống kê nghỉ phép", "Có bao nhiêu đơn nghỉ phép chờ duyệt?"],
                        {
                            "action_type": "leave_reject",
                            "title": f"Từ chối đơn nghỉ phép #{detail.get('MaNP')}",
                            "summary": (
                                f"Nhân viên: {detail.get('HoTen')} | Từ {detail.get('TuNgay')} đến {detail.get('DenNgay')} | "
                                f"Loại: {detail.get('LoaiNghi')} | Trạng thái hiện tại: {detail.get('TrangThai')}"
                            ),
                            "required_permission": "tuchoi_nghiphep",
                            "confirm_label": "Xác nhận từ chối đơn",
                            "payload": {"ma_np": ma_np},
                        },
                    )

            return (
                "Yêu cầu từ chối nghỉ phép cần có mã đơn cụ thể để tôi chuẩn bị bản xác nhận an toàn.",
                ["Chưa từ chối đơn thực tế", "Ví dụ: từ chối nghỉ phép mã 5"],
                ["Thống kê nghỉ phép", "Có bao nhiêu đơn nghỉ phép chờ duyệt?"],
                None,
            )

        if ("tao" in q or "them" in q) and "phan cong" in q:
            return (
                "Tôi có thể hỗ trợ tạo kế hoạch phân công an toàn. Trình tự nên là: xác định nhân viên, phòng ban, chức vụ, ngày bắt đầu, lý do điều chuyển, sau đó hiển thị bản xem trước trước khi lưu.",
                [
                    "Chưa tạo dữ liệu thật",
                    "Cần xác định: Mã NV, phòng ban, chức vụ, ngày bắt đầu, loại điều chuyển",
                ],
                [
                    "Hãy lập action plan tạo phân công cho nhân viên mã 12",
                    "Tìm nhân viên 12",
                    "Phân bổ nhân sự theo phòng ban",
                ],
                None,
            )

        if ("tao" in q or "them" in q) and ("lich phong van" in q or "phong van" in q):
            return (
                "Tôi có thể hỗ trợ quy trình tạo lịch phỏng vấn: xác định hồ sơ ứng tuyển, ngày, giờ, địa điểm, ghi chú và kiểm tra lịch trống trước khi xác nhận.",
                [
                    "Chưa tạo lịch phỏng vấn thật",
                    "Cần có: mã hồ sơ ứng tuyển, ngày, giờ, địa điểm",
                ],
                [
                    "Tóm tắt tuyển dụng",
                    "Top ứng viên hiện tại",
                    "Hãy lập action plan tạo lịch phỏng vấn cho hồ sơ 8",
                ],
                None,
            )

        return (
            "Yêu cầu của bạn có thể ảnh hưởng đến dữ liệu hệ thống. Tôi đề xuất chuyển sang chế độ Action an toàn: "
            "(1) xác định chức năng cần thực hiện, (2) kiểm tra quyền tài khoản, (3) xem trước dữ liệu sẽ thay đổi, "
            "(4) bạn xác nhận rồi mới thực thi.",
            [
                "Chưa thực thi thao tác ghi/xóa",
                "Phản hồi: xac nhan + mo ta cu the để tạo action plan chi tiết",
            ],
            self._default_suggestions(),
            None,
        )

    def _permission_denied(self, permission: str) -> Tuple[str, List[str], List[str]]:
        return (
            "Tôi không thể truy cập dữ liệu này vì tài khoản hiện tại chưa có quyền phù hợp.",
            [f"Quyền yêu cầu: {permission}", "Hãy liên hệ quản trị viên để được cấp quyền"],
            self._default_suggestions(),
        )

    def _has_permission(self, user_permissions: List[str], permission: str) -> bool:
        return permission in set(user_permissions or [])

    def _try_tool_answer(self, message: str, user: UserContext) -> Optional[Tuple[str, List[str], List[str]]]:
        q = normalize_text(message)
        user_permissions = user.permissions

        if ("luong" in q and ("cua toi" in q or "ban than" in q or "toi" in q)):
            salary = self.tools.self_salary_current_month(user.ma_tk)
            if not salary:
                return (
                    "Tôi chưa tìm thấy dữ liệu lương gắn với tài khoản hiện tại.",
                    ["Kiểm tra liên kết tài khoản với nhân viên hoặc dữ liệu bảng lương"],
                    ["Thông tin cá nhân của tôi", "Hợp đồng của tôi", "Đơn nghỉ phép của tôi"],
                )

            source_name = str(salary.get("source_name") or "bangluong")
            reply = (
                "Tóm tắt lương tháng này của bạn:\n"
                f"- Lương cơ sở: {salary.get('LuongCoSo', 0)}\n"
                f"- Hệ số lương: {salary.get('HeSoLuong', 0)}\n"
                f"- Hệ số chức vụ: {salary.get('HeSoChucVu', 0)}\n"
                f"- Phụ cấp: {salary.get('PhuCap', 0)}\n"
                f"- Tổng lương: {salary.get('TongLuong', 0)}"
            )
            return (
                reply,
                [f"Nguồn: {'bảng lương tháng hiện tại' if source_name == 'bangluong' else 'ước tính từ hợp đồng đang hiệu lực'}"],
                ["Hợp đồng của tôi", "Thông tin cá nhân của tôi", "Đơn nghỉ phép của tôi"],
            )

        if ("thong tin ca nhan" in q or "ho so cua toi" in q or "thong tin cua toi" in q):
            row = self.tools.self_profile(user.ma_tk)
            if not row:
                return (
                    "Tôi chưa tìm thấy hồ sơ cá nhân gắn với tài khoản này.",
                    ["Kiểm tra lại liên kết tài khoản - nhân viên"],
                    ["Hợp đồng của tôi", "Đơn nghỉ phép của tôi"],
                )

            reply = (
                "Thông tin cá nhân của bạn:\n"
                f"- Mã NV: {row.get('MaNV')}\n"
                f"- Họ tên: {row.get('HoTen')}\n"
                f"- Giới tính: {row.get('GioiTinh') or 'Chưa có'}\n"
                f"- Ngày sinh: {row.get('NgaySinh') or 'Chưa có'}\n"
                f"- Phòng ban: {row.get('TenPB') or 'Chưa có'}\n"
                f"- Chức vụ: {row.get('TenCV') or 'Chưa có'}\n"
                f"- Email: {row.get('Email') or 'Chưa có'}\n"
                f"- Điện thoại: {row.get('DienThoai') or 'Chưa có'}"
            )
            return (
                reply,
                ["Nguồn: nhanvien + phancong hiện tại"],
                ["Hợp đồng của tôi", "Đơn nghỉ phép của tôi", "Lương tháng này của tôi"],
            )

        if "hop dong cua toi" in q or ("hop dong" in q and ("hien tai" in q or "cua toi" in q)):
            row = self.tools.self_current_contract(user.ma_tk)
            if not row:
                return (
                    "Tôi chưa tìm thấy hợp đồng đang hiệu lực của bạn.",
                    ["Kiểm tra dữ liệu hợp đồng hoặc liên kết tài khoản"],
                    ["Lương tháng này của tôi", "Thông tin cá nhân của tôi"],
                )

            reply = (
                "Hợp đồng hiện tại của bạn:\n"
                f"- Mã HĐ: {row.get('MaHopDong')}\n"
                f"- Số HĐ: {row.get('SoHopDong')}\n"
                f"- Loại: {row.get('LoaiHopDong')}\n"
                f"- Bắt đầu: {row.get('NgayBatDau')}\n"
                f"- Kết thúc: {row.get('NgayKetThuc') or 'Không xác định'}\n"
                f"- Bậc lương: {row.get('TenBac') or 'Chưa có'}\n"
                f"- Lương cơ bản theo bậc: {row.get('LuongCoBan') or 0}"
            )
            return (
                reply,
                ["Nguồn: hopdong + bacluong"],
                ["Lương tháng này của tôi", "Thông tin cá nhân của tôi", "Đơn nghỉ phép của tôi"],
            )

        if "don nghi phep cua toi" in q or ("nghi phep" in q and ("cua toi" in q or "don cua toi" in q)):
            rows = self.tools.self_recent_leave(user.ma_tk, limit=5)
            if not rows:
                return (
                    "Bạn chưa có đơn nghỉ phép nào gần đây.",
                    ["Nguồn: bảng nghiphep theo tài khoản hiện tại"],
                    ["Tạo đơn nghỉ phép từ 2026-04-10 đến 2026-04-12", "Thống kê nghỉ phép"],
                )

            lines = ["Đơn nghỉ phép gần đây của bạn:"]
            for row in rows:
                lines.append(
                    f"- #{row.get('MaNP')} | {row.get('LoaiNghi')} | {row.get('TuNgay')} -> {row.get('DenNgay')} | {row.get('TrangThai')}"
                )
            return (
                "\n".join(lines),
                ["Nguồn: bảng nghiphep theo nhân viên hiện tại"],
                ["Tạo đơn nghỉ phép từ 2026-04-10 đến 2026-04-12", "Lương tháng này của tôi", "Thông tin cá nhân của tôi"],
            )

        if "tong" in q and "nhan vien" in q:
            if not self._has_permission(user_permissions, "xem_nhanvien"):
                return self._permission_denied("xem_nhanvien")

            count = self.tools.employee_count()
            return (
                f"Tổng số nhân viên hiện tại là {count}.",
                ["Nguồn: bảng nhanvien", "Bạn có thể hỏi thêm theo phòng ban"],
                ["Phân bổ nhân sự theo phòng ban", "Tìm nhân viên 12", "Chi tiết nhân viên Nguyễn Văn A"],
            )

        if "nghi phep" in q and "cho duyet" in q:
            if not self._has_permission(user_permissions, "xem_nghiphep"):
                return self._permission_denied("xem_nghiphep")

            count = self.tools.pending_leave_count()
            return (
                f"Số đơn nghỉ phép đang chờ duyệt là {count}.",
                ["Nguồn: bảng nghiphep", "Có thể lọc theo ngày nếu cần"],
                ["Thống kê nghỉ phép", "Hãy lập action plan duyệt nghỉ phép mã 5", "Tổng số nhân viên hiện tại là bao nhiêu?"],
            )

        if "thong ke" in q and "nghi phep" in q:
            if not self._has_permission(user_permissions, "xem_nghiphep"):
                return self._permission_denied("xem_nghiphep")

            rows = self.tools.leave_status_summary()
            if not rows:
                return ("Chưa có dữ liệu nghỉ phép để thống kê.", ["Nguồn: bảng nghiphep"], ["Có bao nhiêu đơn nghỉ phép chờ duyệt?", "Tổng số nhân viên hiện tại là bao nhiêu?"])

            lines = ["Tổng quan trạng thái nghỉ phép:"]
            for row in rows:
                lines.append(f"- {row.get('TrangThai')}: {row.get('total')} đơn")

            return (
                "\n".join(lines),
                ["Nguồn: bảng nghiphep", "Bao gồm tất cả trạng thái"],
                ["Có bao nhiêu đơn nghỉ phép chờ duyệt?", "Hợp đồng sắp hết hạn", "Tóm tắt tuyển dụng"],
            )

        if "hop dong" in q and ("sap het han" in q or "het han" in q or "30 ngay" in q):
            if not self._has_permission(user_permissions, "xem_hopdong"):
                return self._permission_denied("xem_hopdong")

            rows = self.tools.contracts_expiring(days=30, limit=5)
            if not rows:
                return (
                    "Không có hợp đồng còn hiệu lực sắp hết hạn trong 30 ngày tới.",
                    ["Nguồn: bảng hopdong", "Điều kiện: TrangThai còn hiệu lực"],
                    ["Tổng số nhân viên hiện tại là bao nhiêu?", "Phân bổ nhân sự theo phòng ban", "Tóm tắt tuyển dụng"],
                )

            lines = ["Top hợp đồng sắp hết hạn (30 ngày):"]
            for row in rows:
                lines.append(
                    f"- {row.get('SoHopDong')} | {row.get('HoTen')} | Hết hạn: {row.get('NgayKetThuc')}"
                )
            return (
                "\n".join(lines),
                ["Nguồn: bảng hopdong", "Top 5 sắp hết hạn gần nhất"],
                ["Tìm nhân viên 12", "Phân bổ nhân sự theo phòng ban", "Thống kê nghỉ phép"],
            )

        if "phong ban" in q and ("phan bo" in q or "nhieu nhat" in q or "bao nhieu" in q):
            if not self._has_permission(user_permissions, "xem_phancong"):
                return self._permission_denied("xem_phancong")

            rows = self.tools.department_headcount(limit=6)
            if not rows:
                return ("Chưa có dữ liệu phân công để thống kê phòng ban.", ["Nguồn: bảng phancong"], ["Tổng số nhân viên hiện tại là bao nhiêu?", "Hợp đồng sắp hết hạn"])

            lines = ["Phân bổ nhân sự theo phòng ban (phân công đang hiệu lực):"]
            for row in rows:
                lines.append(f"- {row.get('TenPB')}: {row.get('total')} nhân viên")

            return (
                "\n".join(lines),
                ["Nguồn: phancong + phongban", "Top 6 phòng ban"],
                ["Tổng số nhân viên hiện tại là bao nhiêu?", "Tìm nhân viên 12", "Hợp đồng sắp hết hạn"],
            )

        if "chi tiet nhan vien" in q or "thong tin nhan vien" in q:
            if not self._has_permission(user_permissions, "xem_nhanvien"):
                return self._permission_denied("xem_nhanvien")

            keyword = re.sub(r"^(chi tiet nhan vien|thong tin nhan vien)\s+", "", q).strip()
            if not keyword:
                return (
                    "Bạn cần cung cấp tên hoặc mã nhân viên để tôi tra cứu chi tiết.",
                    ["Ví dụ: chi tiết nhân viên 12", "Ví dụ: thông tin nhân viên nguyễn văn a"],
                    ["Tìm nhân viên 12", "Tổng số nhân viên hiện tại là bao nhiêu?"],
                )

            row = self.tools.employee_detail(keyword)
            if not row:
                return (
                    "Không tìm thấy nhân viên phù hợp để xem chi tiết.",
                    ["Thử lại với mã NV hoặc tên gần đúng"],
                    ["Tìm nhân viên 12", "Phân bổ nhân sự theo phòng ban"],
                )

            reply = (
                f"Thông tin nhân viên:\n"
                f"- Mã NV: {row.get('MaNV')}\n"
                f"- Họ tên: {row.get('HoTen')}\n"
                f"- Trạng thái: {row.get('TrangThai')}\n"
                f"- Phòng ban hiện tại: {row.get('TenPB') or 'Chưa có'}\n"
                f"- Chức vụ hiện tại: {row.get('TenCV') or 'Chưa có'}\n"
                f"- Email: {row.get('Email') or 'Chưa có'}\n"
                f"- Điện thoại: {row.get('DienThoai') or 'Chưa có'}"
            )
            return (
                reply,
                ["Nguồn: nhanvien + phancong hiện tại"],
                ["Tìm nhân viên 12", "Phân bổ nhân sự theo phòng ban", "Hợp đồng sắp hết hạn"],
            )

        if "tom tat tuyen dung" in q or ("thong ke" in q and "tuyen dung" in q):
            if not self._has_permission(user_permissions, "xem_dot_tuyen"):
                return self._permission_denied("xem_dot_tuyen")

            row = self.tools.recruitment_status_summary()
            if not row:
                return (
                    "Chưa có dữ liệu tuyển dụng để tổng hợp.",
                    ["Nguồn: bảng hosoungtuyen"],
                    ["Top ứng viên", "Tổng số nhân viên hiện tại là bao nhiêu?"],
                )

            reply = (
                "Tóm tắt tuyển dụng hiện tại:\n"
                f"- Tổng hồ sơ: {row.get('Tong', 0)}\n"
                f"- Nộp hồ sơ: {row.get('NopHoSo', 0)}\n"
                f"- Sàng lọc: {row.get('SangLoc', 0)}\n"
                f"- Phỏng vấn: {row.get('PhongVan', 0)}\n"
                f"- Offer: {row.get('Offer', 0)}\n"
                f"- Nhận việc: {row.get('NhanViec', 0)}\n"
                f"- Rớt: {row.get('Rot', 0)}"
            )
            return (
                reply,
                ["Nguồn: bảng hosoungtuyen"],
                ["Top ứng viên", "Hãy lập action plan tạo lịch phỏng vấn cho hồ sơ 8", "Hợp đồng sắp hết hạn"],
            )

        if "top ung vien" in q or "ung vien tot nhat" in q:
            if not self._has_permission(user_permissions, "xem_dot_tuyen"):
                return self._permission_denied("xem_dot_tuyen")

            rows = self.tools.top_candidates(limit=5)
            if not rows:
                return (
                    "Chưa có dữ liệu đánh giá phỏng vấn để xếp hạng ứng viên.",
                    ["Nguồn: bảng danhgiaphongvan"],
                    ["Tóm tắt tuyển dụng", "Hãy lập action plan tạo lịch phỏng vấn cho hồ sơ 8"],
                )

            lines = ["Top ứng viên theo điểm đánh giá:"]
            for idx, row in enumerate(rows, start=1):
                lines.append(f"- #{idx}: {row.get('HoTen')} | Điểm TB: {row.get('DiemTB')}")
            return (
                "\n".join(lines),
                ["Nguồn: danhgiaphongvan + hosoungtuyen + ungvien"],
                ["Tóm tắt tuyển dụng", "Hãy lập action plan tạo lịch phỏng vấn cho hồ sơ 8", "Tổng số nhân viên hiện tại là bao nhiêu?"],
            )

        m = re.search(r"tim nhan vien\s+(.+)", q)
        if m:
            if not self._has_permission(user_permissions, "xem_nhanvien"):
                return self._permission_denied("xem_nhanvien")

            keyword = m.group(1).strip()
            rows = self.tools.search_employee(keyword, limit=5)
            if not rows:
                return (
                    "Không tìm thấy nhân viên phù hợp với từ khóa.",
                    ["Thử lại với tên hoặc mã NV khác"],
                    ["Chi tiết nhân viên 12", "Tổng số nhân viên hiện tại là bao nhiêu?"],
                )

            lines = ["Đã tìm thấy một số nhân viên:"]
            for row in rows:
                lines.append(
                    f"- MaNV {row.get('MaNV')} | {row.get('HoTen')} | {row.get('TrangThai')}"
                )
            return (
                "\n".join(lines),
                ["Nguồn: bảng nhanvien", "Kết quả tối đa 5 dòng"],
                [f"Chi tiết nhân viên {keyword}", "Phân bổ nhân sự theo phòng ban", "Hợp đồng sắp hết hạn"],
            )

        # --- Chấm công tháng này ---
        if ("cham cong" in q and ("thang nay" in q or "thang" in q or "tong ket" in q or "tong hop" in q)) or q.strip() == "cham cong":
            if not self._has_permission(user_permissions, "xem_chamcong"):
                return self._permission_denied("xem_chamcong")

            row = self.tools.attendance_summary_this_month()
            now = datetime.now()
            if not row or not row.get("SoNhanVien"):
                return ("Chưa có dữ liệu chấm công cho tháng này.", ["Nguồn: bảng chamcong"], self._default_suggestions())

            reply = (
                f"Tổng hợp chấm công tháng {now.month}/{now.year}:\n"
                f"- Số nhân viên có dữ liệu: {row.get('SoNhanVien', 0)}\n"
                f"- Tổng lượt đi làm: {row.get('TongDiLam', 0)}\n"
                f"- Tổng lượt nghỉ phép: {row.get('TongNghiPhep', 0)}\n"
                f"- Nghỉ không lương: {row.get('TongNghiKhongLuong', 0)}\n"
                f"- Trung bình giờ làm/lượt: {row.get('TBGioLam') or 'N/A'} giờ"
            )
            return (
                reply,
                ["Nguồn: bảng chamcong tháng hiện tại"],
                ["Top tăng ca tháng này", "Thống kê nghỉ phép", "Tổng số nhân viên hiện tại là bao nhiêu?"],
            )

        # --- Tăng ca / OT ---
        if "tang ca" in q or "lam them gio" in q or "gio ot" in q or ("lam them" in q and "gio" in q):
            if not self._has_permission(user_permissions, "xem_chamcong"):
                return self._permission_denied("xem_chamcong")

            now = datetime.now()
            rows = self.tools.overtime_top_this_month(limit=5)
            if not rows:
                return ("Không có dữ liệu tăng ca trong tháng này.", ["Nguồn: bảng chamcong"], self._default_suggestions())

            lines = [f"Top tăng ca tháng {now.month}/{now.year}:"]
            for idx, row in enumerate(rows, start=1):
                lines.append(f"- #{idx}: {row.get('HoTen')} | {row.get('GioOT')} giờ OT")
            return (
                "\n".join(lines),
                ["Nguồn: chamcong (giờ làm > 8h/ngày)", "Top 5 tháng hiện tại"],
                ["Tổng hợp chấm công tháng này", "Thống kê nghỉ phép", "Tổng số nhân viên hiện tại là bao nhiêu?"],
            )

        # --- Đào tạo sắp diễn ra ---
        if ("dao tao" in q or "khoa hoc" in q) and ("sap" in q or "lich" in q or "sap toi" in q or "sap dien ra" in q):
            if not self._has_permission(user_permissions, "xem_daotao"):
                return self._permission_denied("xem_daotao")

            rows = self.tools.training_upcoming(days=60, limit=5)
            if not rows:
                return ("Không có khóa đào tạo nào sắp diễn ra trong 60 ngày tới.", ["Nguồn: bảng khoadaotao"], self._default_suggestions())

            lines = ["Khóa đào tạo sắp diễn ra (60 ngày tới):"]
            for row in rows:
                don_vi = row.get('DonViToChuc') or 'Nội bộ'
                lines.append(f"- {row.get('TenKhoaDaoTao')} | {row.get('TuNgay')} → {row.get('DenNgay')} | {don_vi}")
            return (
                "\n".join(lines),
                ["Nguồn: bảng khoadaotao", "Trạng thái: Lên kế hoạch"],
                ["Khóa đào tạo đang diễn ra", "Tóm tắt tuyển dụng", "Tổng số nhân viên hiện tại là bao nhiêu?"],
            )

        # --- Đào tạo đang diễn ra ---
        if ("dao tao" in q or "khoa hoc" in q) and ("dang" in q or "hien tai" in q or "hien hanh" in q):
            if not self._has_permission(user_permissions, "xem_daotao"):
                return self._permission_denied("xem_daotao")

            rows = self.tools.training_ongoing(limit=5)
            if not rows:
                return ("Hiện không có khóa đào tạo nào đang diễn ra.", ["Nguồn: bảng khoadaotao"], self._default_suggestions())

            lines = ["Khóa đào tạo đang diễn ra:"]
            for row in rows:
                don_vi = row.get('DonViToChuc') or 'Nội bộ'
                lines.append(f"- {row.get('TenKhoaDaoTao')} | đến {row.get('DenNgay')} | {don_vi}")
            return (
                "\n".join(lines),
                ["Nguồn: bảng khoadaotao", "Trạng thái: Đang đào tạo"],
                ["Khóa đào tạo sắp diễn ra", "Tóm tắt tuyển dụng", "Tổng số nhân viên hiện tại là bao nhiêu?"],
            )

        # --- Bảo hiểm ---
        if "bao hiem" in q or "bhxh" in q or "bhyt" in q or "bhtn" in q:
            if not self._has_permission(user_permissions, "xem_baohiem"):
                return self._permission_denied("xem_baohiem")

            row = self.tools.insurance_summary()
            if not row:
                return ("Chưa có dữ liệu bảo hiểm.", ["Nguồn: bảng baohiem"], self._default_suggestions())

            reply = (
                "Tổng quan bảo hiểm nhân viên:\n"
                f"- Số NV có hồ sơ BH: {row.get('SoNhanVienCoHoSo', 0)}\n"
                f"- Đang đóng: {row.get('DangDong', 0)} | Đã dừng: {row.get('DaDung', 0)}\n"
                f"- BHXH đang đóng: {row.get('BHXH', 0)}\n"
                f"- BHYT đang đóng: {row.get('BHYT', 0)}\n"
                f"- BHTN đang đóng: {row.get('BHTN', 0)}"
            )
            return (
                reply,
                ["Nguồn: bảng baohiem"],
                ["Tổng số nhân viên hiện tại là bao nhiêu?", "Thống kê nghỉ phép", "Hợp đồng sắp hết hạn"],
            )

        # --- Sinh nhật tháng này ---
        if "sinh nhat" in q:
            if not self._has_permission(user_permissions, "xem_nhanvien"):
                return self._permission_denied("xem_nhanvien")

            now = datetime.now()
            rows = self.tools.employee_birthday_this_month(limit=10)
            if not rows:
                return (f"Không có nhân viên nào có sinh nhật trong tháng {now.month}.", ["Nguồn: bảng nhanvien"], self._default_suggestions())

            lines = [f"Sinh nhật nhân viên tháng {now.month}/{now.year}:"]
            for row in rows:
                lines.append(f"- {row.get('HoTen')} | ngày {row.get('Ngay')}/{now.month}")
            return (
                "\n".join(lines),
                [f"Nguồn: nhanvien.NgaySinh | Tháng {now.month}/{now.year}"],
                ["Tổng số nhân viên hiện tại là bao nhiêu?", "Phân bổ nhân sự theo phòng ban"],
            )

        # --- Khen thưởng / Kỷ luật ---
        if "khen thuong" in q or "ky luat" in q or ("khen" in q and "thuong" in q):
            if not self._has_permission(user_permissions, "xem_khenthuong"):
                return self._permission_denied("xem_khenthuong")

            rows = self.tools.recognition_recent(limit=5)
            if not rows:
                return ("Chưa có dữ liệu khen thưởng/kỷ luật gần đây.", ["Nguồn: bảng khenthuongkyluat"], self._default_suggestions())

            lines = ["Khen thưởng/kỷ luật gần đây:"]
            for row in rows:
                so_tien = f" | {int(row.get('SoTien', 0)):,}đ" if row.get('SoTien') else ""
                lines.append(f"- {row.get('HoTen')} | {row.get('TenLoai')}{so_tien} | {row.get('NgayQuyetDinh')}")
            return (
                "\n".join(lines),
                ["Nguồn: khenthuongkyluat + loaikhenthuongkyluat", "5 gần nhất"],
                ["Tổng số nhân viên hiện tại là bao nhiêu?", "Phân bổ nhân sự theo phòng ban"],
            )

        # --- Nhân viên mới tháng này ---
        if ("nhan vien moi" in q or "tuyen moi" in q or ("moi" in q and "nhan vien" in q and "thang" in q)):
            if not self._has_permission(user_permissions, "xem_nhanvien"):
                return self._permission_denied("xem_nhanvien")

            now = datetime.now()
            rows = self.tools.new_employees_this_month(limit=10)
            if not rows:
                return (
                    f"Không có nhân viên nào vào làm trong tháng {now.month}/{now.year}.",
                    ["Nguồn: nhanvien.NgayVaoLam"],
                    self._default_suggestions(),
                )

            lines = [f"Nhân viên mới tháng {now.month}/{now.year}:"]
            for row in rows:
                lines.append(f"- MaNV {row.get('MaNV')} | {row.get('HoTen')} | Vào làm: {row.get('NgayVaoLam')}")
            return (
                "\n".join(lines),
                [f"Nguồn: nhanvien.NgayVaoLam | Tháng {now.month}/{now.year}"],
                ["Tổng số nhân viên hiện tại là bao nhiêu?", "Phân bổ nhân sự theo phòng ban"],
            )

        # --- Danh sách phòng ban ---
        if ("danh sach phong ban" in q or "cac phong ban" in q or ("phong ban" in q and "co nhung" in q)):
            if not self._has_permission(user_permissions, "xem_nhanvien"):
                return self._permission_denied("xem_nhanvien")

            rows = self.tools.department_list()
            if not rows:
                return ("Chưa có dữ liệu phòng ban.", ["Nguồn: bảng phongban"], self._default_suggestions())

            lines = ["Danh sách phòng ban:"]
            for row in rows:
                nv = row.get("SoNhanVien") or 0
                lines.append(f"- {row.get('TenPB')} ({nv} nhân viên)")
            return (
                "\n".join(lines),
                ["Nguồn: phongban + phancong hiện tại"],
                ["Phân bổ nhân sự theo phòng ban", "Tổng số nhân viên hiện tại là bao nhiêu?"],
            )

        # --- Thống kê lương tháng này ---
        if ("thong ke luong" in q or "thong ke bang luong" in q or ("luong" in q and "thang nay" in q and "tong" in q)):
            if not self._has_permission(user_permissions, "xem_bangluong"):
                return self._permission_denied("xem_bangluong")

            now = datetime.now()
            row = self.tools.payroll_summary_this_month()
            if not row or not row.get("SoNhanVienCoLuong"):
                return (
                    f"Chưa có dữ liệu bảng lương tháng {now.month}/{now.year}.",
                    ["Nguồn: bảng bangluong"],
                    self._default_suggestions(),
                )

            reply = (
                f"Thống kê lương tháng {now.month}/{now.year}:\n"
                f"- Số nhân viên có bảng lương: {row.get('SoNhanVienCoLuong', 0)}\n"
                f"- Lương trung bình: {int(row.get('LuongTrungBinh') or 0):,}đ\n"
                f"- Lương cao nhất: {int(row.get('LuongCaoNhat') or 0):,}đ\n"
                f"- Lương thấp nhất: {int(row.get('LuongThapNhat') or 0):,}đ\n"
                f"- Tổng quỹ lương: {int(row.get('TongQuyLuong') or 0):,}đ\n"
                f"- Đã duyệt: {row.get('DaDuyet', 0)} | Chờ duyệt: {row.get('ChoDuyet', 0)}"
            )
            return (
                reply,
                ["Nguồn: bảng bangluong", f"Tháng {now.month}/{now.year}"],
                ["Tổng quan bảo hiểm", "Hợp đồng sắp hết hạn", "Tổng số nhân viên hiện tại là bao nhiêu?"],
            )

        return None

    def _llm_answer(self, request: ChatRequest) -> str:
        if not self.client:
            return ""

        role_contexts: Dict[str, str] = {
            "Admin": (
                "Bạn hỗ trợ Quản trị viên hệ thống. Ưu tiên: tài khoản, phân quyền, cấu hình, "
                "toàn bộ module. Có thể gợi ý thao tác quản trị nhưng luôn yêu cầu xác nhận."
            ),
            "HR": (
                "Bạn hỗ trợ bộ phận Nhân sự. Ưu tiên: tuyển dụng, hồ sơ nhân viên, nghỉ phép, "
                "chấm công, đào tạo, hợp đồng. Trả lời theo đúng quy trình HR chuẩn."
            ),
            "KeToan": (
                "Bạn hỗ trợ Kế toán. Ưu tiên: lương, bảo hiểm, phụ cấp, thuế TNCN, bậc lương. "
                "Trả lời chính xác số liệu tài chính. Không tiết lộ mức lương người khác trừ khi có quyền."
            ),
            "QuanLy": (
                "Bạn hỗ trợ Quản lý. Ưu tiên: báo cáo tổng hợp, phê duyệt đơn, phân công, "
                "tình hình nhân sự theo phòng ban, chỉ số KPI cần theo dõi."
            ),
            "NhanVien": (
                "Bạn hỗ trợ nhân viên. Chỉ trả lời về thông tin cá nhân của chính họ: lịch nghỉ phép, "
                "hợp đồng, lương bản thân, chấm công. Không tiết lộ thông tin người khác."
            ),
        }

        role_context = role_contexts.get(request.user.role, "Bạn là trợ lý HR nội bộ, trả lời thân thiện và chính xác.")

        system_prompt = (
            "Bạn là trợ lý ảo HR nội bộ cho hệ thống quản lý nhân sự Việt Nam. "
            f"{role_context} "
            "Luôn trả lời bằng tiếng Việt rõ ràng, ngắn gọn, thực tế. "
            "Không bao giờ khẳng định đã thực thi thao tác ghi/xóa dữ liệu. "
            "Nếu yêu cầu thao tác rủi ro, hãy đưa quy trình an toàn và yêu cầu xác nhận. "
            "Ưu tiên gợi ý dạng các bước có đánh số khi phù hợp. "
            "Khi có thể, hãy nêu rõ nguồn dữ liệu hoặc giả định đang dùng."
        )

        messages: List[Dict[str, str]] = [{"role": "system", "content": system_prompt}]

        for item in request.history[-8:]:
            role = item.role if item.role in {"user", "assistant"} else "user"
            messages.append({"role": role, "content": item.content})

        context_line = (
            f"User context: username={request.user.username}, role={request.user.role}, "
            f"permission_count={len(request.user.permissions)}"
        )
        messages.append({"role": "system", "content": context_line})
        messages.append({"role": "user", "content": request.message})

        try:
            completion = self.client.chat.completions.create(
                model=self.model,
                messages=messages,
                temperature=0.3,
                max_tokens=700,
            )
            content = completion.choices[0].message.content if completion.choices else ""
            return (content or "").strip()
        except Exception:
            return ""


app = FastAPI(title="HRM Chatbot Service", version="1.0.0")
engine = ChatEngine()
shared_secret = os.getenv("APP_SHARED_SECRET", "").strip()


class BriefRequest(BaseModel):
    user: UserContext


class BriefResponse(BaseModel):
    items: List[str] = Field(default_factory=list)
    source: str = "brief"


@app.get("/health")
def health() -> Dict[str, Any]:
    return {
        "ok": True,
        "llm_enabled": bool(engine.client),
        "model": engine.model,
    }


@app.post("/chat", response_model=ChatResponse)
def chat(request: ChatRequest, x_app_secret: Optional[str] = Header(default=None)) -> ChatResponse:
    if shared_secret:
        if not x_app_secret or x_app_secret != shared_secret:
            raise HTTPException(status_code=401, detail="INVALID_SHARED_SECRET")

    try:
        return engine.answer(request)
    except mysql.connector.Error as exc:
        raise HTTPException(status_code=500, detail=f"DB_ERROR: {exc}") from exc
    except Exception as exc:
        raise HTTPException(status_code=500, detail=f"CHATBOT_ERROR: {exc}") from exc


@app.post("/brief", response_model=BriefResponse)
def brief(request: BriefRequest, x_app_secret: Optional[str] = Header(default=None)) -> BriefResponse:
    if shared_secret:
        if not x_app_secret or x_app_secret != shared_secret:
            raise HTTPException(status_code=401, detail="INVALID_SHARED_SECRET")

    perms = set(request.user.permissions)
    items: List[str] = []

    try:
        if "xem_nghiphep" in perms:
            count = engine.tools.pending_leave_count()
            if count > 0:
                items.append(f"{count} đơn nghỉ phép đang chờ duyệt")

        if "xem_hopdong" in perms:
            rows = engine.tools.contracts_expiring(days=30, limit=3)
            if rows:
                items.append(f"{len(rows)} hợp đồng sắp hết hạn trong 30 ngày")

        if "xem_nhanvien" in perms:
            bdays = engine.tools.employee_birthday_this_month(limit=3)
            if bdays:
                names = ", ".join(str(r.get("HoTen", "")) for r in bdays[:3])
                items.append(f"Sinh nhật tháng này: {names}")

        if "xem_daotao" in perms:
            trainings = engine.tools.training_ongoing(limit=2)
            if trainings:
                items.append(f"{len(trainings)} khóa đào tạo đang diễn ra")

        if "xem_dot_tuyen" in perms:
            rec = engine.tools.recruitment_status_summary()
            if rec and int(rec.get("PhongVan") or 0) > 0:
                items.append(f"{rec.get('PhongVan')} ứng viên đang ở giai đoạn phỏng vấn")

    except Exception:
        pass

    if not items:
        items.append("Mọi thứ đang ổn. Không có thông báo quan trọng nào.")

    return BriefResponse(items=items)


class StatsResponse(BaseModel):
    ok: bool = True
    stats: Dict[str, Any] = Field(default_factory=dict)


@app.post("/stats", response_model=StatsResponse)
def stats(request: BriefRequest, x_app_secret: Optional[str] = Header(default=None)) -> StatsResponse:
    """Trả về thống kê tổng hợp toàn hệ thống dựa trên quyền người dùng."""
    if shared_secret:
        if not x_app_secret or x_app_secret != shared_secret:
            raise HTTPException(status_code=401, detail="INVALID_SHARED_SECRET")

    perms = set(request.user.permissions)
    result: Dict[str, Any] = {}

    try:
        if "xem_nhanvien" in perms:
            result["tong_nhan_vien"] = engine.tools.employee_count()
            result["nhan_vien_moi_thang_nay"] = len(engine.tools.new_employees_this_month())

        if "xem_nghiphep" in perms:
            result["don_nghi_phep_cho_duyet"] = engine.tools.pending_leave_count()

        if "xem_hopdong" in perms:
            result["hop_dong_sap_het_han_30_ngay"] = len(engine.tools.contracts_expiring(days=30))

        if "xem_chamcong" in perms:
            cc = engine.tools.attendance_summary_this_month()
            if cc:
                result["cham_cong_thang_nay"] = {
                    "so_nhan_vien": cc.get("SoNhanVien", 0),
                    "tong_di_lam": cc.get("TongDiLam", 0),
                    "tong_nghi_phep": cc.get("TongNghiPhep", 0),
                    "tb_gio_lam": cc.get("TBGioLam"),
                }

        if "xem_bangluong" in perms:
            bl = engine.tools.payroll_summary_this_month()
            if bl:
                result["luong_thang_nay"] = {
                    "so_nhan_vien": bl.get("SoNhanVienCoLuong", 0),
                    "luong_trung_binh": bl.get("LuongTrungBinh"),
                    "tong_quy_luong": bl.get("TongQuyLuong"),
                    "cho_duyet": bl.get("ChoDuyet", 0),
                }

        if "xem_dot_tuyen" in perms:
            rec = engine.tools.recruitment_status_summary()
            if rec:
                result["tuyen_dung"] = {
                    "tong_ho_so": rec.get("Tong", 0),
                    "phong_van": rec.get("PhongVan", 0),
                    "offer": rec.get("Offer", 0),
                    "nhan_viec": rec.get("NhanViec", 0),
                }

        if "xem_daotao" in perms:
            result["khoa_dao_tao_dang_dien_ra"] = len(engine.tools.training_ongoing())

    except Exception as exc:
        return StatsResponse(ok=False, stats={"error": str(exc)})

    return StatsResponse(ok=True, stats=result)
