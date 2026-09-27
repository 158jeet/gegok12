<?php

namespace App\Services\Tagore;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayrollService
{
    public function generate(int $institutionId, string $month, array $employees, int $userId): int
    {
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            throw ValidationException::withMessages(['month' => 'Payroll month must use YYYY-MM format.']);
        }

        return DB::transaction(function () use ($institutionId, $month, $employees, $userId) {
            $run = DB::table('tagore_payroll_runs')->where('institution_id',$institutionId)->where('month',$month)->lockForUpdate()->first();
            if ($run && in_array($run->status, ['approved','paid'], true)) {
                throw ValidationException::withMessages(['month' => 'This payroll run has already been approved or paid.']);
            }

            $runId = $run?->id ?: DB::table('tagore_payroll_runs')->insertGetId([
                'institution_id'=>$institutionId,'month'=>$month,'status'=>'draft','generated_by'=>$userId,
                'created_at'=>now(),'updated_at'=>now(),
            ]);

            DB::table('tagore_payroll_items')->where('payroll_run_id',$runId)->delete();

            $grossTotal = 0; $deductionTotal = 0; $netTotal = 0;
            $schoolId=DB::table('tagore_institutions')->where('id',$institutionId)->value('school_id');
            abort_unless($schoolId,422,'Institution is not linked to a school.');
            foreach ($employees as $employee) {
                $employeeId = (int)($employee['employee_id'] ?? 0);
                $basic = round((float)($employee['basic_salary'] ?? 0),2);
                $employeeUser=DB::table('users')->where('id',$employeeId)->where('school_id',$schoolId)->whereNull('deleted_at')->first(['id','usergroup_id']);
                if (!$employeeUser || $employeeUser->usergroup_id === 6) throw ValidationException::withMessages(['employees' => 'Each payroll employee must belong to the selected institution and not be a student.']);
                if ($employeeId <= 0 || $basic < 0) {
                    throw ValidationException::withMessages(['employees' => 'Each payroll row needs a valid employee and non-negative basic salary.']);
                }

                $allowances = (array)($employee['allowances'] ?? []);
                $gross = round($basic + collect($allowances)->sum(fn($v)=>(float)$v),2);
                $pfRate = max(0,min(100,(float)($employee['pf_rate'] ?? 12)));
                $esiRate = max(0,min(100,(float)($employee['esi_rate'] ?? 0)));
                $pf = round(min($basic,15000) * $pfRate / 100,2);
                $esi = round($gross * $esiRate / 100,2);
                $tds = round(max(0,(float)($employee['tds'] ?? 0)),2);
                $other = round(max(0,(float)($employee['other_deductions'] ?? 0)),2);
                $deductions = round($pf+$esi+$tds+$other,2);
                $net = round($gross-$deductions,2);
                if ($net < 0) throw ValidationException::withMessages(['employees' => 'Deductions cannot exceed gross salary.']);

                $grossTotal += $gross; $deductionTotal += $deductions; $netTotal += $net;
                DB::table('tagore_payroll_items')->insert([
                    'payroll_run_id'=>$runId,'employee_id'=>$employeeId,'basic_salary'=>$basic,'gross_amount'=>$gross,
                    'pf_amount'=>$pf,'esi_amount'=>$esi,'tds_amount'=>$tds,'other_deductions'=>$other,'net_amount'=>$net,
                    'earnings_json'=>json_encode($allowances),'deductions_json'=>json_encode(['pf'=>$pf,'esi'=>$esi,'tds'=>$tds,'other'=>$other]),
                    'payslip_no'=>'TAG-PS-'.$institutionId.'-'.$month.'-'.$employeeId,'status'=>'processed',
                    'created_at'=>now(),'updated_at'=>now(),
                ]);
            }

            DB::table('tagore_payroll_runs')->where('id',$runId)->update([
                'gross_total'=>round($grossTotal,2),'deduction_total'=>round($deductionTotal,2),'net_total'=>round($netTotal,2),
                'status'=>'processed','generated_by'=>$userId,'updated_at'=>now(),
            ]);

            return $runId;
        });
    }

    public function approve(int $runId, int $userId): void
    {
        DB::transaction(function () use ($runId,$userId) {
            $run=DB::table('tagore_payroll_runs')->where('id',$runId)->lockForUpdate()->first();
            if (!$run || $run->status !== 'processed') throw ValidationException::withMessages(['payroll'=>'Only processed payroll can be approved.']);
            DB::table('tagore_payroll_runs')->where('id',$runId)->update(['status'=>'approved','approved_by'=>$userId,'approved_at'=>now(),'updated_at'=>now()]);
        });
    }

    public function markPaid(int $runId): void
    {
        DB::transaction(function () use ($runId) {
            $run=DB::table('tagore_payroll_runs')->where('id',$runId)->lockForUpdate()->first();
            if (!$run || $run->status !== 'approved') throw ValidationException::withMessages(['payroll'=>'Only approved payroll can be marked paid.']);
            DB::table('tagore_payroll_runs')->where('id',$runId)->update(['status'=>'paid','paid_at'=>now(),'updated_at'=>now()]);
            DB::table('tagore_payroll_items')->where('payroll_run_id',$runId)->update(['status'=>'paid','paid_at'=>now(),'updated_at'=>now()]);
        });
    }
}
