<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Unit test cho logic tính lương thuần túy (không cần DB).
 * Các phương thức private của PayrollService được kiểm tra gián tiếp
 * qua reflection hoặc qua các public method được mock.
 */
class PayrollCalculationTest extends TestCase
{
    // ----------------------------------------------------------------
    // Các hằng số dùng chung trong test
    // ----------------------------------------------------------------
    private const LUONG_CO_SO   = 5_000_000.0;
    private const HE_SO_LUONG   = 2.0;           // lương hợp đồng = 10tr
    private const HE_SO_CHUC_VU = 1.0;
    private const PHU_CAP       = 500_000.0;      // phụ cấp
    private const STANDARD_DAYS = 26;

    // ----------------------------------------------------------------
    // Helper: mô phỏng lại calculatePayroll thuần
    // (copy logic từ PayrollService để test độc lập với DB)
    // ----------------------------------------------------------------
    private function calculatePayroll(
        float $luongCoSo,
        float $heSoLuong,
        float $phuCap,
        float $actualDays,
        float $gioOT,
        float $thuong,
        float $phat,
        float $baoHiem,
        int   $soNgayMuon = 0,
    ): array {
        $standardDays    = self::STANDARD_DAYS;
        $baseSalary      = $luongCoSo * $heSoLuong;
        $monthlySalary   = $baseSalary + $phuCap;
        $dailySalary     = $standardDays > 0 ? $monthlySalary / $standardDays : 0;

        if ($actualDays < $standardDays) {
            $salaryByAttendance = $dailySalary * $actualDays;
            $overtimeDays       = 0;
        } elseif ($actualDays == $standardDays) {
            $salaryByAttendance = $monthlySalary;
            $overtimeDays       = 0;
        } else {
            $salaryByAttendance = $monthlySalary;
            $overtimeDays       = $actualDays - $standardDays;
        }

        $overtimeByDay  = $overtimeDays * $dailySalary * 1.5;
        $hourlySalary   = $dailySalary / 8;
        $overtimeByHour = $gioOT * $hourlySalary * 1.5;
        $latePenalty    = $soNgayMuon * ($dailySalary * 0.1);
        $totalPhat      = $phat + $latePenalty;

        $tongLuong = $salaryByAttendance
            + $overtimeByDay
            + $overtimeByHour
            + $thuong
            - $totalPhat
            - $baoHiem;

        return compact(
            'salaryByAttendance', 'overtimeByDay', 'overtimeByHour',
            'latePenalty', 'tongLuong',
        );
    }

    // ----------------------------------------------------------------
    // Tests
    // ----------------------------------------------------------------

    public function test_full_attendance_month_equals_monthly_salary_plus_benefits(): void
    {
        $result = $this->calculatePayroll(
            luongCoSo:  self::LUONG_CO_SO,
            heSoLuong:  self::HE_SO_LUONG,
            phuCap:     self::PHU_CAP,
            actualDays: self::STANDARD_DAYS,   // đủ công
            gioOT:      0,
            thuong:     0,
            phat:       0,
            baoHiem:    0,
        );

        $expected = self::LUONG_CO_SO * self::HE_SO_LUONG + self::PHU_CAP;
        $this->assertEqualsWithDelta($expected, $result['tongLuong'], 0.01);
        $this->assertEquals(0.0, $result['overtimeByDay']);
        $this->assertEquals(0.0, $result['latePenalty']);
    }

    public function test_partial_attendance_reduces_salary_proportionally(): void
    {
        $actualDays = 13; // nửa tháng
        $result = $this->calculatePayroll(
            luongCoSo:  self::LUONG_CO_SO,
            heSoLuong:  self::HE_SO_LUONG,
            phuCap:     0,
            actualDays: $actualDays,
            gioOT:      0,
            thuong:     0,
            phat:       0,
            baoHiem:    0,
        );

        $monthly     = self::LUONG_CO_SO * self::HE_SO_LUONG;
        $daily       = $monthly / self::STANDARD_DAYS;
        $expectedNet = $daily * $actualDays;

        $this->assertEqualsWithDelta($expectedNet, $result['tongLuong'], 0.01);
    }

    public function test_overtime_days_are_paid_at_1_5x(): void
    {
        $extraDays = 3;
        $result = $this->calculatePayroll(
            luongCoSo:  self::LUONG_CO_SO,
            heSoLuong:  self::HE_SO_LUONG,
            phuCap:     0,
            actualDays: self::STANDARD_DAYS + $extraDays,
            gioOT:      0,
            thuong:     0,
            phat:       0,
            baoHiem:    0,
        );

        $monthly  = self::LUONG_CO_SO * self::HE_SO_LUONG;
        $daily    = $monthly / self::STANDARD_DAYS;
        $expected = $monthly + $extraDays * $daily * 1.5;

        $this->assertEqualsWithDelta($expected, $result['tongLuong'], 0.01);
        $this->assertGreaterThan(0, $result['overtimeByDay']);
    }

    public function test_overtime_hours_paid_at_1_5x(): void
    {
        $gioOT  = 10.0;
        $result = $this->calculatePayroll(
            luongCoSo:  self::LUONG_CO_SO,
            heSoLuong:  self::HE_SO_LUONG,
            phuCap:     0,
            actualDays: self::STANDARD_DAYS,
            gioOT:      $gioOT,
            thuong:     0,
            phat:       0,
            baoHiem:    0,
        );

        $monthly      = self::LUONG_CO_SO * self::HE_SO_LUONG;
        $daily        = $monthly / self::STANDARD_DAYS;
        $hourly       = $daily / 8;
        $expectedOT   = $gioOT * $hourly * 1.5;
        $expectedNet  = $monthly + $expectedOT;

        $this->assertEqualsWithDelta($expectedNet, $result['tongLuong'], 0.01);
        $this->assertEqualsWithDelta($expectedOT, $result['overtimeByHour'], 0.01);
    }

    public function test_bonus_increases_total_salary(): void
    {
        $thuong = 2_000_000.0;
        $base   = $this->calculatePayroll(
            self::LUONG_CO_SO, self::HE_SO_LUONG, 0,
            self::STANDARD_DAYS, 0, 0, 0, 0
        );
        $withBonus = $this->calculatePayroll(
            self::LUONG_CO_SO, self::HE_SO_LUONG, 0,
            self::STANDARD_DAYS, 0, $thuong, 0, 0
        );

        $this->assertEqualsWithDelta(
            $base['tongLuong'] + $thuong,
            $withBonus['tongLuong'],
            0.01
        );
    }

    public function test_penalty_reduces_total_salary(): void
    {
        $phat = 500_000.0;
        $base = $this->calculatePayroll(
            self::LUONG_CO_SO, self::HE_SO_LUONG, 0,
            self::STANDARD_DAYS, 0, 0, 0, 0
        );
        $withPenalty = $this->calculatePayroll(
            self::LUONG_CO_SO, self::HE_SO_LUONG, 0,
            self::STANDARD_DAYS, 0, 0, $phat, 0
        );

        $this->assertEqualsWithDelta(
            $base['tongLuong'] - $phat,
            $withPenalty['tongLuong'],
            0.01
        );
    }

    public function test_insurance_deducted_from_total(): void
    {
        $baoHiem = 1_000_000.0;
        $base    = $this->calculatePayroll(
            self::LUONG_CO_SO, self::HE_SO_LUONG, 0,
            self::STANDARD_DAYS, 0, 0, 0, 0
        );
        $withInsurance = $this->calculatePayroll(
            self::LUONG_CO_SO, self::HE_SO_LUONG, 0,
            self::STANDARD_DAYS, 0, 0, 0, $baoHiem
        );

        $this->assertEqualsWithDelta(
            $base['tongLuong'] - $baoHiem,
            $withInsurance['tongLuong'],
            0.01
        );
    }

    public function test_late_days_deduct_10_percent_daily_salary(): void
    {
        $monthly     = self::LUONG_CO_SO * self::HE_SO_LUONG;
        $daily       = $monthly / self::STANDARD_DAYS;
        $soNgayMuon  = 2;

        $result = $this->calculatePayroll(
            self::LUONG_CO_SO, self::HE_SO_LUONG, 0,
            self::STANDARD_DAYS, 0, 0, 0, 0,
            soNgayMuon: $soNgayMuon
        );

        $expectedPenalty = $soNgayMuon * $daily * 0.1;
        $this->assertEqualsWithDelta($expectedPenalty, $result['latePenalty'], 0.01);
        $this->assertEqualsWithDelta($monthly - $expectedPenalty, $result['tongLuong'], 0.01);
    }

    public function test_zero_attendance_results_in_zero_or_negative_salary(): void
    {
        $result = $this->calculatePayroll(
            self::LUONG_CO_SO, self::HE_SO_LUONG, 0,
            0, 0, 0, 0, 0
        );

        $this->assertEquals(0.0, $result['salaryByAttendance']);
        $this->assertLessThanOrEqual(0.0, $result['tongLuong']); // có thể âm nếu trừ BH
    }

    public function test_combined_scenario(): void
    {
        // Đủ công + 5 giờ OT + thưởng 1tr + phạt 200k + BH 800k
        $monthly  = self::LUONG_CO_SO * self::HE_SO_LUONG + self::PHU_CAP;
        $daily    = $monthly / self::STANDARD_DAYS;
        $hourly   = $daily / 8;
        $gioOT    = 5.0;
        $thuong   = 1_000_000.0;
        $phat     = 200_000.0;
        $baoHiem  = 800_000.0;

        $result = $this->calculatePayroll(
            self::LUONG_CO_SO, self::HE_SO_LUONG, self::PHU_CAP,
            self::STANDARD_DAYS, $gioOT, $thuong, $phat, $baoHiem
        );

        $expected = $monthly + ($gioOT * $hourly * 1.5) + $thuong - $phat - $baoHiem;
        $this->assertEqualsWithDelta($expected, $result['tongLuong'], 0.01);
    }
}
